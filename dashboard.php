<?php
// ============================================
// GLEAM API - Dashboard (Provider Summary)
// GET /api/dashboard.php  → returns all dashboard stats (auth required)
// ============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$auth = requireAuth();
$db   = getDB();

// Get provider record
$stmt = $db->prepare("SELECT * FROM providers WHERE user_id = ?");
$stmt->execute([$auth['user_id']]);
$provider = $stmt->fetch();
if (!$provider) jsonResponse(['error' => 'Provider profile not found'], 404);
$pid = $provider['id'];

// Total clients (distinct children with any subscription)
$s1 = $db->prepare("SELECT COUNT(DISTINCT child_id) AS total FROM subscriptions WHERE provider_id = ?");
$s1->execute([$pid]); $totalClients = (int)$s1->fetchColumn();

// Monthly earnings (sum of active subscriptions)
$s2 = $db->prepare("SELECT COALESCE(SUM(price_egp),0) AS total FROM subscriptions WHERE provider_id = ? AND status = 'active'");
$s2->execute([$pid]); $monthlyEarnings = (float)$s2->fetchColumn();

// Reports sent
$s3 = $db->prepare("SELECT COUNT(*) FROM reports WHERE provider_id = ? AND sent_at IS NOT NULL");
$s3->execute([$pid]); $reportsSent = (int)$s3->fetchColumn();

// Active subscriptions count
$s4 = $db->prepare("SELECT COUNT(*) FROM subscriptions WHERE provider_id = ? AND status = 'active'");
$s4->execute([$pid]); $activeSubs = (int)$s4->fetchColumn();

// Expiring in 7 days
$s5 = $db->prepare(
    "SELECT COUNT(*) FROM subscriptions
     WHERE provider_id = ? AND status = 'active'
     AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
);
$s5->execute([$pid]); $expiringSoon = (int)$s5->fetchColumn();

// Latest reviews (4)
$s6 = $db->prepare(
    "SELECT r.rating, r.review_text, r.created_at, par.full_name AS reviewer
     FROM reviews r JOIN parents par ON par.id = r.parent_id
     WHERE r.provider_id = ? ORDER BY r.created_at DESC LIMIT 4"
);
$s6->execute([$pid]);
$latestReviews = $s6->fetchAll();

// Jobs list
$s7 = $db->prepare("SELECT job_type FROM provider_jobs WHERE provider_id = ?");
$s7->execute([$pid]);
$jobs = array_column($s7->fetchAll(), 'job_type');

jsonResponse([
    'provider' => [
        'id'            => $pid,
        'full_name'     => $provider['full_name'],
        'profile_photo' => $provider['profile_photo'],
        'rating'        => (float)$provider['rating'],
        'total_reviews' => (int)$provider['total_reviews'],
        'jobs'          => $jobs,
        'governorate'   => $provider['governorate'],
    ],
    'stats' => [
        'total_clients'      => $totalClients,
        'monthly_earnings'   => $monthlyEarnings,
        'reports_sent'       => $reportsSent,
        'active_subscriptions' => $activeSubs,
        'expiring_soon'      => $expiringSoon,
    ],
    'latest_reviews' => $latestReviews,
]);
