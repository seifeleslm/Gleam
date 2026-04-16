<?php
// ============================================
// GLEAM API - Reviews & Ratings
// GET  /api/reviews.php?provider_id=X  → public reviews for a provider
// POST /api/reviews.php?action=create  → submit a review (auth required)
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

// ── GET reviews for a provider ────────────────────────────
if ($method === 'GET') {
    $providerId = (int)($_GET['provider_id'] ?? 0);
    if (!$providerId) jsonResponse(['error' => 'provider_id required'], 400);

    $stmt = $db->prepare(
        "SELECT r.rating, r.review_text, r.created_at, par.full_name AS reviewer
         FROM reviews r JOIN parents par ON par.id = r.parent_id
         WHERE r.provider_id = ? ORDER BY r.created_at DESC LIMIT 20"
    );
    $stmt->execute([$providerId]);
    $reviews = $stmt->fetchAll();

    $avgStmt = $db->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM reviews WHERE provider_id = ?");
    $avgStmt->execute([$providerId]);
    $stats = $avgStmt->fetch();

    jsonResponse(['reviews' => $reviews, 'average' => round((float)$stats['avg_rating'], 1), 'total' => (int)$stats['total']]);
}

// ── POST create review ────────────────────────────────────
if ($method === 'POST' && $action === 'create') {
    $auth = requireAuth();
    $body = getBody();

    $providerId = (int)($body['provider_id'] ?? 0);
    $rating     = (int)($body['rating']      ?? 0);
    $text       = trim($body['review_text']  ?? '');

    if (!$providerId) jsonResponse(['error' => 'provider_id required'], 400);
    if ($rating < 1 || $rating > 5) jsonResponse(['error' => 'rating must be 1-5'], 400);

    // Get parent_id from user
    $pStmt = $db->prepare("SELECT id FROM parents WHERE user_id = ?");
    $pStmt->execute([$auth['user_id']]);
    $parent = $pStmt->fetch();
    if (!$parent) jsonResponse(['error' => 'Parent profile not found'], 404);
    $parentId = $parent['id'];

    // Upsert review
    $stmt = $db->prepare(
        "INSERT INTO reviews (provider_id, parent_id, rating, review_text)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE rating = VALUES(rating), review_text = VALUES(review_text)"
    );
    $stmt->execute([$providerId, $parentId, $rating, $text ?: null]);

    // Recalculate provider avg rating
    $avgStmt = $db->prepare("SELECT AVG(rating) AS avg, COUNT(*) AS cnt FROM reviews WHERE provider_id = ?");
    $avgStmt->execute([$providerId]);
    $avg = $avgStmt->fetch();
    $db->prepare("UPDATE providers SET rating = ?, total_reviews = ? WHERE id = ?")
       ->execute([round((float)$avg['avg'], 1), (int)$avg['cnt'], $providerId]);

    jsonResponse(['message' => 'Review submitted', 'new_average' => round((float)$avg['avg'], 1)]);
}

jsonResponse(['error' => 'Method not allowed'], 405);
