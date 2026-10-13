<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');
$user = currentUser();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Invalid request method'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['action'])) {
    jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
}

if (!isset($input['csrf_token']) || !validateCSRF($input['csrf_token'])) {
    jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
}

$action = $input['action'];

try {
    if ($action === 'submit') {
        if ($user['role'] !== 'faculty' && $user['role'] !== 'admin') {
            jsonResponse(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        
        $room_id = (int)($input['room_id'] ?? 0);
        $request_date = $input['request_date'] ?? '';
        $start_time = $input['start_time'] ?? '';
        $end_time = $input['end_time'] ?? '';
        $purpose = $input['purpose'] ?? '';
        
        if (!$room_id || !$request_date || !$start_time || !$end_time || !$purpose) {
            jsonResponse(['success' => false, 'error' => 'All fields are required'], 400);
        }
        
        $stmt = $db->prepare("INSERT INTO room_requests (room_id, faculty_id, request_date, start_time, end_time, purpose, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$room_id, $user['user_id'], $request_date, $start_time, $end_time, $purpose]);
        jsonResponse(['success' => true, 'message' => 'Request submitted successfully']);
    } elseif ($action === 'approve' || $action === 'decline') {
        if ($user['role'] !== 'admin') {
            jsonResponse(['success' => false, 'error' => 'Admin privileges required'], 403);
        }
        
        $request_id = (int)($input['request_id'] ?? 0);
        if (!$request_id) {
            jsonResponse(['success' => false, 'error' => 'Missing request ID'], 400);
        }
        
        $status = $action === 'approve' ? 'Approved' : 'Declined';
        $stmt = $db->prepare("UPDATE room_requests SET status = ? WHERE request_id = ?");
        $stmt->execute([$status, $request_id]);
        jsonResponse(['success' => true, 'message' => "Request $status"]);
    } else {
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
