<?php
// ============================================
// GLEAM API - Authentication (Register / Login)
// POST /api/auth.php?action=register
// POST /api/auth.php?action=login
// ============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$action = $_GET['action'] ?? '';
$body   = getBody();
$db     = getDB();

// ── REGISTER ──────────────────────────────────────────────
if ($action === 'register') {
    $email    = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';
    $role     = $body['role'] ?? 'provider'; // provider | parent

    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        jsonResponse(['error' => 'Invalid email address'], 400);
    if (strlen($password) < 6)
        jsonResponse(['error' => 'Password must be at least 6 characters'], 400);
    if (!in_array($role, ['provider', 'parent']))
        jsonResponse(['error' => 'Role must be provider or parent'], 400);

    // Check duplicate
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) jsonResponse(['error' => 'Email already registered'], 409);

    // Insert user
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)");
    $stmt->execute([$email, $hash, $role]);
    $userId = (int)$db->lastInsertId();

    $token = generateJWT(['user_id' => $userId, 'role' => $role, 'email' => $email]);
    jsonResponse(['message' => 'Account created successfully', 'token' => $token, 'user_id' => $userId, 'role' => $role], 201);
}

// ── LOGIN ─────────────────────────────────────────────────
if ($action === 'login') {
    $email    = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';

    if (!$email || !$password) jsonResponse(['error' => 'Email and password required'], 400);

    $stmt = $db->prepare("SELECT id, password_hash, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash']))
        jsonResponse(['error' => 'Invalid email or password'], 401);

    $token = generateJWT(['user_id' => $user['id'], 'role' => $user['role'], 'email' => $email]);
    jsonResponse(['message' => 'Login successful', 'token' => $token, 'user_id' => $user['id'], 'role' => $user['role']]);
}

jsonResponse(['error' => 'Unknown action. Use ?action=register or ?action=login'], 400);
