<?php
// ============================================
// GLEAM API - Provider Registration Details
// POST /api/profile.php?action=doctor
// POST /api/profile.php?action=nurse
// POST /api/profile.php?action=teacher
// POST /api/profile.php?action=coach
// GET  /api/profile.php               → own full profile
// ============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$auth   = requireAuth();

function getProviderId(PDO $db, int $userId): ?int {
    $s = $db->prepare("SELECT id FROM providers WHERE user_id = ?");
    $s->execute([$userId]);
    $r = $s->fetch();
    return $r ? (int)$r['id'] : null;
}

// ── GET full profile ──────────────────────────────────────
if ($method === 'GET') {
    $pid = getProviderId($db, $auth['user_id']);
    if (!$pid) jsonResponse(['error' => 'Provider profile not found'], 404);

    $p = $db->prepare("SELECT * FROM providers WHERE id = ?");
    $p->execute([$pid]); $profile = $p->fetch();

    $profile['jobs'] = array_column(
        $db->query("SELECT job_type FROM provider_jobs WHERE provider_id = $pid")->fetchAll(), 'job_type'
    );
    $profile['availability'] = array_column(
        $db->query("SELECT day FROM provider_availability WHERE provider_id = $pid")->fetchAll(), 'day'
    );

    // Load job-specific details
    foreach (['doctor','nurse','teacher','coach'] as $job) {
        $tbl = "{$job}_details";
        $s = $db->prepare("SELECT * FROM $tbl WHERE provider_id = ?");
        $s->execute([$pid]);
        $row = $s->fetch();
        if ($row) $profile["{$job}_details"] = $row;
    }

    // Service details
    $sd = $db->prepare("SELECT * FROM provider_service_details WHERE provider_id = ?");
    $sd->execute([$pid]);
    $profile['service_details'] = $sd->fetch() ?: null;

    // Certificates
    $c = $db->prepare("SELECT * FROM provider_certificates WHERE provider_id = ?");
    $c->execute([$pid]);
    $profile['certificates'] = $c->fetchAll();

    jsonResponse(['profile' => $profile]);
}

// ── POST doctor details ───────────────────────────────────
if ($method === 'POST' && $action === 'doctor') {
    $pid  = getProviderId($db, $auth['user_id']);
    if (!$pid) jsonResponse(['error' => 'Provider profile not found'], 404);
    $body = getBody();

    $stmt = $db->prepare(
        "INSERT INTO doctor_details (provider_id, license_number, license_document, workplace_type, workplace_address,
         clinic_price, home_visit_price, online_session_price, working_hours_start, working_hours_end)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
         license_number=VALUES(license_number), workplace_type=VALUES(workplace_type),
         workplace_address=VALUES(workplace_address), clinic_price=VALUES(clinic_price),
         home_visit_price=VALUES(home_visit_price), online_session_price=VALUES(online_session_price),
         working_hours_start=VALUES(working_hours_start), working_hours_end=VALUES(working_hours_end)"
    );
    $stmt->execute([
        $pid,
        $body['license_number']        ?? null,
        $body['license_document']      ?? null,
        $body['workplace_type']        ?? null,
        $body['workplace_address']     ?? null,
        $body['clinic_price']          ?? null,
        $body['home_visit_price']      ?? null,
        $body['online_session_price']  ?? null,
        $body['working_hours_start']   ?? null,
        $body['working_hours_end']     ?? null,
    ]);

    // Specializations
    if (!empty($body['specializations'])) {
        $db->prepare("DELETE FROM doctor_specializations WHERE provider_id = ?")->execute([$pid]);
        $s = $db->prepare("INSERT IGNORE INTO doctor_specializations (provider_id, specialization) VALUES (?, ?)");
        foreach ($body['specializations'] as $spec) $s->execute([$pid, $spec]);
    }

    // Ensure job tag
    $db->prepare("INSERT IGNORE INTO provider_jobs (provider_id, job_type) VALUES (?, 'doctor')")->execute([$pid]);

    jsonResponse(['message' => 'Doctor details saved']);
}

// ── POST nurse details ────────────────────────────────────
if ($method === 'POST' && $action === 'nurse') {
    $pid  = getProviderId($db, $auth['user_id']);
    if (!$pid) jsonResponse(['error' => 'Provider profile not found'], 404);
    $body = getBody();

    $stmt = $db->prepare(
        "INSERT INTO nurse_details (provider_id, nurse_type, certification_training, ministry_certified, certificate_file)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
         nurse_type=VALUES(nurse_type), certification_training=VALUES(certification_training),
         ministry_certified=VALUES(ministry_certified)"
    );
    $stmt->execute([
        $pid,
        $body['nurse_type']              ?? 'vaccination_nurse',
        $body['certification_training']  ?? null,
        !empty($body['ministry_certified']) ? 1 : 0,
        $body['certificate_file']        ?? null,
    ]);

    // Services
    if (!empty($body['services'])) {
        $db->prepare("DELETE FROM nurse_services WHERE provider_id = ?")->execute([$pid]);
        $s = $db->prepare("INSERT IGNORE INTO nurse_services (provider_id, service) VALUES (?, ?)");
        foreach ($body['services'] as $svc) $s->execute([$pid, $svc]);
    }

    $db->prepare("INSERT IGNORE INTO provider_jobs (provider_id, job_type) VALUES (?, 'nurse')")->execute([$pid]);
    jsonResponse(['message' => 'Nurse details saved']);
}

// ── POST teacher details ──────────────────────────────────
if ($method === 'POST' && $action === 'teacher') {
    $pid  = getProviderId($db, $auth['user_id']);
    if (!$pid) jsonResponse(['error' => 'Provider profile not found'], 404);
    $body = getBody();

    $stmt = $db->prepare(
        "INSERT INTO teacher_details (provider_id, teacher_type, education_degree)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE teacher_type=VALUES(teacher_type), education_degree=VALUES(education_degree)"
    );
    $stmt->execute([$pid, $body['teacher_type'] ?? null, $body['education_degree'] ?? null]);

    // Subjects
    if (!empty($body['subjects'])) {
        $db->prepare("DELETE FROM teacher_subjects WHERE provider_id = ?")->execute([$pid]);
        $s = $db->prepare("INSERT IGNORE INTO teacher_subjects (provider_id, subject) VALUES (?, ?)");
        foreach ($body['subjects'] as $sub) $s->execute([$pid, $sub]);
    }

    // Special needs experience
    if (!empty($body['special_needs'])) {
        $db->prepare("DELETE FROM teacher_special_needs_experience WHERE provider_id = ?")->execute([$pid]);
        $s = $db->prepare("INSERT IGNORE INTO teacher_special_needs_experience (provider_id, condition) VALUES (?, ?)");
        foreach ($body['special_needs'] as $cond) $s->execute([$pid, $cond]);
    }

    $db->prepare("INSERT IGNORE INTO provider_jobs (provider_id, job_type) VALUES (?, 'teacher')")->execute([$pid]);
    jsonResponse(['message' => 'Teacher details saved']);
}

// ── POST coach details ────────────────────────────────────
if ($method === 'POST' && $action === 'coach') {
    $pid  = getProviderId($db, $auth['user_id']);
    if (!$pid) jsonResponse(['error' => 'Provider profile not found'], 404);
    $body = getBody();

    $stmt = $db->prepare(
        "INSERT INTO coach_details (provider_id, training_location, experience_special_needs,
         session_duration_30, session_duration_45, session_duration_60, price_per_session, price_per_month)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
         training_location=VALUES(training_location),
         experience_special_needs=VALUES(experience_special_needs),
         session_duration_30=VALUES(session_duration_30),
         session_duration_45=VALUES(session_duration_45),
         session_duration_60=VALUES(session_duration_60),
         price_per_session=VALUES(price_per_session),
         price_per_month=VALUES(price_per_month)"
    );
    $durations = $body['session_durations'] ?? [];
    $stmt->execute([
        $pid,
        $body['training_location']        ?? null,
        !empty($body['experience_special_needs']) ? 1 : 0,
        in_array('30', $durations) ? 1 : 0,
        in_array('45', $durations) ? 1 : 0,
        in_array('60', $durations) ? 1 : 0,
        $body['price_per_session'] ?? null,
        $body['price_per_month']   ?? null,
    ]);

    // Sports
    if (!empty($body['sports'])) {
        $db->prepare("DELETE FROM coach_sports WHERE provider_id = ?")->execute([$pid]);
        $s = $db->prepare("INSERT IGNORE INTO coach_sports (provider_id, sport) VALUES (?, ?)");
        foreach ($body['sports'] as $sport) $s->execute([$pid, $sport]);
    }

    $db->prepare("INSERT IGNORE INTO provider_jobs (provider_id, job_type) VALUES (?, 'coach')")->execute([$pid]);
    jsonResponse(['message' => 'Coach details saved']);
}

jsonResponse(['error' => 'Unknown action'], 400);
