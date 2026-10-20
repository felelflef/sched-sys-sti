<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $db->query("SELECT user_id, first_name, last_name, email, role, subject_role, created_at FROM users ORDER BY user_id ASC");
        $users = $stmt->fetchAll();
        jsonResponse(['success' => true, 'data' => $users]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

requireLogin();
requireRole('admin');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['action'])) {
    jsonResponse(['success' => false, 'message' => 'Missing action parameter'], 400);
}

// Validate CSRF if session token exists
if (isset($_SESSION['csrf_token']) && isset($input['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

$action = $input['action'];

if ($action === 'add') {
    $firstName = trim($input['first_name'] ?? '');
    $lastName = trim($input['last_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = !empty($input['password']) ? trim($input['password']) : 'password';
    $role = $input['role'] ?? 'faculty';
    $subjectRole = trim($input['subject_role'] ?? 'IT');

    // Validate required fields
    if (empty($firstName) || empty($lastName) || empty($email)) {
        jsonResponse(['success' => false, 'message' => 'First name, last name, and email are required'], 400);
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Invalid email format'], 400);
    }

    // Validate role
    if (!in_array($role, ['admin', 'academic-head', 'program-head', 'faculty'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid role'], 400);
    }

    // Set appropriate subject role per role category
    if ($role === 'admin') {
        $subjectRole = null;
    } elseif ($role === 'academic-head') {
        $subjectRole = !empty($subjectRole) ? $subjectRole : 'Academic Leadership';
    } elseif ($role === 'program-head') {
        $subjectRole = !empty($subjectRole) ? $subjectRole : 'Program Leadership';
    } elseif (empty($subjectRole)) {
        $subjectRole = 'IT';
    }

    // Validate password length
    if (strlen($password) < 6) {
        jsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
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
        $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, password_hash, role, subject_role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$firstName, $lastName, $email, $passwordHash, $role, $subjectRole]);

        jsonResponse([
            'success' => true,
            'message' => 'User created successfully',
            'user_id' => $db->lastInsertId()
        ]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    }
} elseif ($action === 'edit_subject_role') {
    $userId = (int)($input['user_id'] ?? 0);
    $subjectRole = trim($input['subject_role'] ?? '');

    if ($userId <= 0 || empty($subjectRole)) {
        jsonResponse(['success' => false, 'message' => 'User ID and Subject Role are required'], 400);
    }

    try {
        $stmt = $db->prepare("UPDATE users SET subject_role = ? WHERE user_id = ? AND role = 'faculty'");
        $stmt->execute([$subjectRole, $userId]);

        if ($stmt->rowCount() === 0) {
            // Check if user exists or is not faculty
            $check = $db->prepare("SELECT user_id, role FROM users WHERE user_id = ?");
            $check->execute([$userId]);
            $user = $check->fetch();
            if (!$user) {
                jsonResponse(['success' => false, 'message' => 'User not found'], 404);
            }
        }

        jsonResponse(['success' => true, 'message' => 'Subject role updated successfully']);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    }
} elseif ($action === 'delete' || $action === 'delete_by_email') {
    $userId = (int)($input['user_id'] ?? 0);
    $email = trim($input['email'] ?? '');
    $current = currentUser();

    if ($email === 'admin@sti.edu' || ($current && $email === $current['email'])) {
        jsonResponse(['success' => false, 'message' => 'Cannot delete the primary administrator account'], 400);
    }

    if ($userId > 0 && $current && $userId === (int)$current['user_id']) {
        jsonResponse(['success' => false, 'message' => 'Cannot delete your own account'], 400);
    }

    try {
        if (!empty($email)) {
            $stmt = $db->prepare("DELETE FROM users WHERE email = ?");
            $stmt->execute([$email]);
        } elseif ($userId > 0) {
            $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
        } else {
            jsonResponse(['success' => false, 'message' => 'User ID or Email is required'], 400);
        }

        jsonResponse(['success' => true, 'message' => 'Account removed successfully']);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    }
} else {
    jsonResponse(['success' => false, 'message' => 'Unknown action: ' . $action], 400);
}
