<?php
// ============================================
// GLEAM API - Reports
// GET  /api/reports.php              → provider's reports (auth)
// POST /api/reports.php?action=create
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

// ── GET reports ───────────────────────────────────────────
if ($method === 'GET') {
    $providerId = getProviderId($db, $auth['user_id']);
    if (!$providerId) jsonResponse(['error' => 'Provider not found'], 404);

    $stmt = $db->prepare(
        "SELECT r.id, r.report_type, r.symptoms, r.behavior, r.notes,
                r.recommendations, r.sent_at, r.created_at,
                c.full_name AS child_name,
                GROUP_CONCAT(DISTINCT rr.recipient) AS recipients,
                COUNT(DISTINCT ra.id) AS attachment_count
         FROM reports r
         JOIN children c ON c.id = r.child_id
         LEFT JOIN report_recipients rr ON rr.report_id = r.id
         LEFT JOIN report_attachments ra ON ra.report_id = r.id
         WHERE r.provider_id = ?
         GROUP BY r.id
         ORDER BY r.created_at DESC
         LIMIT 100"
    );
    $stmt->execute([$providerId]);
    jsonResponse(['reports' => $stmt->fetchAll()]);
}

// ── POST create report ────────────────────────────────────
if ($method === 'POST' && $action === 'create') {
    $body = getBody();
    $providerId = getProviderId($db, $auth['user_id']);
    if (!$providerId) jsonResponse(['error' => 'Provider not found'], 404);

    if (empty($body['child_id']))    jsonResponse(['error' => 'child_id required'], 400);
    if (empty($body['report_type'])) jsonResponse(['error' => 'report_type required'], 400);

    $validTypes = ['health', 'educational', 'behavioral'];
    if (!in_array($body['report_type'], $validTypes))
        jsonResponse(['error' => 'report_type must be: ' . implode(', ', $validTypes)], 400);

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            "INSERT INTO reports (provider_id, child_id, report_type, symptoms, behavior, notes, recommendations, scheduled_send, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $isScheduled = !empty($body['scheduled_send']);
        $stmt->execute([
            $providerId,
            (int)$body['child_id'],
            $body['report_type'],
            $body['symptoms']        ?? null,
            $body['behavior']        ?? null,
            $body['notes']           ?? null,
            $body['recommendations'] ?? null,
            $isScheduled ? $body['scheduled_send'] : null,
            $isScheduled ? null : date('Y-m-d H:i:s'),
        ]);
        $reportId = (int)$db->lastInsertId();

        // Insert recipients
        $validRecipients = ['parent','doctor','psychologist','nurse','trainer'];
        if (!empty($body['recipients']) && is_array($body['recipients'])) {
            $rStmt = $db->prepare("INSERT IGNORE INTO report_recipients (report_id, recipient) VALUES (?, ?)");
            foreach ($body['recipients'] as $rec) {
                if (in_array($rec, $validRecipients)) $rStmt->execute([$reportId, $rec]);
            }
        }

        $db->commit();
        jsonResponse(['message' => 'Report created', 'report_id' => $reportId], 201);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => 'Failed to create report: ' . $e->getMessage()], 500);
    }
}

jsonResponse(['error' => 'Method not allowed'], 405);
