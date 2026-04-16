<?php
// ============================================
// GLEAM API - Providers
// GET  /api/providers.php               → list all providers
// GET  /api/providers.php?id=X          → single provider
// POST /api/providers.php?action=create → create provider profile (auth required)
// PUT  /api/providers.php?action=update → update provider profile (auth required)
// ============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── GET all providers (public) ────────────────────────────
if ($method === 'GET' && !isset($_GET['id']) && !$action) {
    $job        = $_GET['job']    ?? '';   // doctor|nurse|teacher|coach
    $search     = $_GET['search'] ?? '';
    $governorate = $_GET['gov']   ?? '';

    $where = ["p.is_active = 1"];
    $params = [];
    if ($job) {
        $where[] = "EXISTS (SELECT 1 FROM provider_jobs pj WHERE pj.provider_id = p.id AND pj.job_type = ?)";
        $params[] = $job;
    }
    if ($search) {
        $where[] = "p.full_name LIKE ?";
        $params[] = "%$search%";
    }
    if ($governorate) {
        $where[] = "p.governorate = ?";
        $params[] = $governorate;
    }

    $sql = "SELECT p.id, p.full_name, p.profile_photo, p.rating, p.total_reviews,
                   p.governorate, p.city_area, p.years_experience,
                   GROUP_CONCAT(DISTINCT pj.job_type) AS jobs,
                   psd.session_price, psd.monthly_subscription_price
            FROM providers p
            LEFT JOIN provider_jobs pj ON pj.provider_id = p.id
            LEFT JOIN provider_service_details psd ON psd.provider_id = p.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY p.id
            ORDER BY p.rating DESC
            LIMIT 50";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $providers = $stmt->fetchAll();
    jsonResponse(['providers' => $providers, 'count' => count($providers)]);
}

// ── GET single provider ───────────────────────────────────
if ($method === 'GET' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare(
        "SELECT p.*, GROUP_CONCAT(DISTINCT pj.job_type) AS jobs,
                psd.session_price, psd.monthly_subscription_price,
                psd.working_hours_start, psd.working_hours_end, psd.coverage_area
         FROM providers p
         LEFT JOIN provider_jobs pj ON pj.provider_id = p.id
         LEFT JOIN provider_service_details psd ON psd.provider_id = p.id
         WHERE p.id = ? GROUP BY p.id"
    );
    $stmt->execute([$id]);
    $provider = $stmt->fetch();
    if (!$provider) jsonResponse(['error' => 'Provider not found'], 404);

    // Fetch availability days
    $stmt2 = $db->prepare("SELECT day FROM provider_availability WHERE provider_id = ?");
    $stmt2->execute([$id]);
    $provider['availability'] = array_column($stmt2->fetchAll(), 'day');

    // Fetch latest reviews
    $stmt3 = $db->prepare(
        "SELECT r.rating, r.review_text, r.created_at, par.full_name AS parent_name
         FROM reviews r JOIN parents par ON par.id = r.parent_id
         WHERE r.provider_id = ? ORDER BY r.created_at DESC LIMIT 10"
    );
    $stmt3->execute([$id]);
    $provider['reviews'] = $stmt3->fetchAll();

    jsonResponse(['provider' => $provider]);
}

// ── POST create provider profile (auth required) ──────────
if ($method === 'POST' && $action === 'create') {
    $auth = requireAuth();
    $body = getBody();

    $stmt = $db->prepare(
        "INSERT INTO providers (user_id, full_name, phone, gender, governorate, city_area, years_experience)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $auth['user_id'],
        $body['full_name']       ?? '',
        $body['phone']           ?? null,
        $body['gender']          ?? null,
        $body['governorate']     ?? null,
        $body['city_area']       ?? null,
        $body['years_experience'] ?? null,
    ]);
    $providerId = (int)$db->lastInsertId();

    // Insert job types
    if (!empty($body['jobs']) && is_array($body['jobs'])) {
        $jobStmt = $db->prepare("INSERT IGNORE INTO provider_jobs (provider_id, job_type) VALUES (?, ?)");
        foreach ($body['jobs'] as $job) $jobStmt->execute([$providerId, $job]);
    }

    // Insert availability
    if (!empty($body['availability']) && is_array($body['availability'])) {
        $avStmt = $db->prepare("INSERT IGNORE INTO provider_availability (provider_id, day) VALUES (?, ?)");
        foreach ($body['availability'] as $day) $avStmt->execute([$providerId, $day]);
    }

    // Insert service details
    if (!empty($body['session_price']) || !empty($body['monthly_price'])) {
        $sdStmt = $db->prepare(
            "INSERT INTO provider_service_details (provider_id, session_price, monthly_subscription_price, coverage_area)
             VALUES (?, ?, ?, ?)"
        );
        $sdStmt->execute([$providerId, $body['session_price'] ?? null, $body['monthly_price'] ?? null, $body['coverage_area'] ?? null]);
    }

    jsonResponse(['message' => 'Provider profile created', 'provider_id' => $providerId], 201);
}

// ── PUT update provider profile (auth required) ───────────
if ($method === 'PUT' && $action === 'update') {
    $auth = requireAuth();
    $body = getBody();

    // Find provider by user_id
    $stmt = $db->prepare("SELECT id FROM providers WHERE user_id = ?");
    $stmt->execute([$auth['user_id']]);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(['error' => 'Provider profile not found'], 404);
    $providerId = $row['id'];

    $fields = [];
    $params = [];
    foreach (['full_name','phone','gender','governorate','city_area','about_me','years_experience'] as $f) {
        if (isset($body[$f])) { $fields[] = "$f = ?"; $params[] = $body[$f]; }
    }
    if ($fields) {
        $params[] = $providerId;
        $db->prepare("UPDATE providers SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    }

    // Refresh availability
    if (isset($body['availability'])) {
        $db->prepare("DELETE FROM provider_availability WHERE provider_id = ?")->execute([$providerId]);
        $avStmt = $db->prepare("INSERT IGNORE INTO provider_availability (provider_id, day) VALUES (?, ?)");
        foreach ($body['availability'] as $day) $avStmt->execute([$providerId, $day]);
    }

    jsonResponse(['message' => 'Profile updated successfully']);
}

jsonResponse(['error' => 'Method not allowed'], 405);
