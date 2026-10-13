<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
requireRole('admin');

header('Content-Type: application/json');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['action'])) {
    jsonResponse(['success' => false, 'message' => 'Missing action parameter'], 400);
}

// Validate CSRF
if (!isset($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

$action = $input['action'];

if ($action === 'add') {
    $firstName = trim($input['first_name'] ?? '');
    $lastName = trim($input['last_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $role = $input['role'] ?? 'faculty';

    // Validate required fields
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'All fields are required'], 400);
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Invalid email format'], 400);
    }

    // Validate role
    if (!in_array($role, ['admin', 'faculty'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid role'], 400);
    }

    // Validate password length
    if (strlen($password) < 8) {
        jsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters'], 400);
    }

    // Check if email already exists
    $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'A user with this email already exists'], 400);
    }

    // Hash password and insert
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    try {
        $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$firstName, $lastName, $email, $passwordHash, $role]);

        jsonResponse([
            'success' => true,
            'message' => 'User created successfully',
            'user_id' => $db->lastInsertId()
        ]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    }
} else {
    jsonResponse(['success' => false, 'message' => 'Unknown action: ' . $action], 400);
}
