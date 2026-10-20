<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        $isApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/') || 
                 (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            exit;
        }
        header('Location: /login.php');
        exit;
    }
}

function requireRole(string $role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        $isApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/') || 
                 (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
        if ($isApi) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to perform this action']);
            exit;
        }
        http_response_code(403);
        die('Forbidden: You do not have permission to access this page.');
    }
}

function currentUser(): ?array {
    if (isset($_SESSION['user_id'])) {
        return [
            'user_id' => $_SESSION['user_id'],
            'role' => $_SESSION['role'],
            'first_name' => $_SESSION['first_name'],
            'last_name' => $_SESSION['last_name'],
            'email' => $_SESSION['email']
        ];
    }
    return null;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
