<?php
// ============================================
// GLEAM API - Subscriptions
// GET  /api/subscriptions.php            → provider's subscriptions (auth)
// POST /api/subscriptions.php?action=create
// PUT  /api/subscriptions.php?action=update&id=X  (pause/cancel)
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
$auth   = requireAuth();

// Helper – get provider_id from user_id
function getProviderId(PDO $db, int $userId): ?int {
    $s = $db->prepare("SELECT id FROM providers WHERE user_id = ?");
    $s->execute([$userId]);
    $r = $s->fetch();
    return $r ? (int)$r['id'] : null;
}

// ── GET provider's subscriptions ──────────────────────────
if ($method === 'GET') {
    $providerId = getProviderId($db, $auth['user_id']);
    if (!$providerId) jsonResponse(['error' => 'Provider profile not found'], 404);

    $filter = $_GET['status'] ?? 'active'; // active|paused|expired|cancelled

    $stmt = $db->prepare(
        "SELECT s.id, s.start_date, s.end_date, s.price_egp, s.status,
                DATEDIFF(s.end_date, CURDATE()) AS days_remaining,
                c.full_name AS child_name, c.photo AS child_photo,
                par.full_name AS parent_name
         FROM subscriptions s
         JOIN children c   ON c.id = s.child_id
         JOIN parents par  ON par.id = s.parent_id
         WHERE s.provider_id = ? AND s.status = ?
         ORDER BY s.end_date ASC"
    );
    $stmt->execute([$providerId, $filter]);
    $subs = $stmt->fetchAll();

    // Summary totals
    $sumStmt = $db->prepare(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN status='active' THEN price_egp ELSE 0 END) AS monthly_earnings,
                SUM(CASE WHEN status='active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS expiring_soon
         FROM subscriptions WHERE provider_id = ?"
    );
    $sumStmt->execute([$providerId]);
    $summary = $sumStmt->fetch();

    jsonResponse(['subscriptions' => $subs, 'summary' => $summary]);
}

// ── POST create subscription ──────────────────────────────
if ($method === 'POST' && $action === 'create') {
    $body = getBody();
    $providerId = getProviderId($db, $auth['user_id']);
    if (!$providerId) jsonResponse(['error' => 'Provider profile not found'], 404);

    $required = ['child_id', 'parent_id', 'start_date', 'end_date', 'price_egp'];
    foreach ($required as $f) {
        if (empty($body[$f])) jsonResponse(['error' => "Field '$f' is required"], 400);
    }

    $stmt = $db->prepare(
        "INSERT INTO subscriptions (provider_id, child_id, parent_id, start_date, end_date, price_egp, status)
         VALUES (?, ?, ?, ?, ?, ?, 'active')"
    );
    $stmt->execute([
        $providerId,
        (int)$body['child_id'],
        (int)$body['parent_id'],
        $body['start_date'],
        $body['end_date'],
        (float)$body['price_egp'],
    ]);
    jsonResponse(['message' => 'Subscription created', 'id' => (int)$db->lastInsertId()], 201);
}

// ── PUT update subscription status ───────────────────────
if ($method === 'PUT' && $action === 'update') {
    $id   = (int)($_GET['id'] ?? 0);
    $body = getBody();
    if (!$id) jsonResponse(['error' => 'Subscription id required'], 400);

    $allowed = ['active', 'paused', 'expired', 'cancelled'];
    $status  = $body['status'] ?? '';
    if (!in_array($status, $allowed)) jsonResponse(['error' => 'Invalid status'], 400);

    $providerId = getProviderId($db, $auth['user_id']);
    $stmt = $db->prepare(
        "UPDATE subscriptions SET status = ? WHERE id = ? AND provider_id = ?"
    );
    $stmt->execute([$status, $id, $providerId]);
    if ($stmt->rowCount() === 0) jsonResponse(['error' => 'Subscription not found or unauthorized'], 404);

    jsonResponse(['message' => "Subscription $status"]);
}

jsonResponse(['error' => 'Method not allowed'], 405);
