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
if (!$input || !isset($input['room_id']) || !isset($input['action'])) {
    jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
}

$room_id = (int)$input['room_id'];
$action = $input['action']; // 'start' or 'stop'

// Validate if user has access to this room today (if faculty)
if ($user['role'] === 'faculty') {
    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $today = $days[date('w')];
    $stmt = $db->prepare("SELECT 1 FROM schedules WHERE room_id = ? AND faculty_id = ? AND day_of_week = ?");
    $stmt->execute([$room_id, $user['user_id'], $today]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'error' => 'You do not have a schedule for this room today.'], 403);
    }
}

try {
    $db->beginTransaction();

    if ($action === 'start') {
        // Check for existing open session
        $stmt = $db->prepare("SELECT log_id FROM session_logs WHERE room_id = ? AND actual_end_time IS NULL");
        $stmt->execute([$room_id]);
        if ($stmt->fetch()) {
            $db->rollBack();
            jsonResponse(['success' => false, 'error' => 'An active session already exists for this room.'], 400);
        }

        // Insert session log
        $stmt = $db->prepare("INSERT INTO session_logs (room_id, faculty_id, session_date, actual_start_time) VALUES (?, ?, CURDATE(), NOW())");
        $stmt->execute([$room_id, $user['user_id']]);

        // Update classroom status
        $stmt = $db->prepare("UPDATE classrooms SET current_status = 'Occupied' WHERE room_id = ?");
        $stmt->execute([$room_id]);
        
        $new_status = 'Occupied';
    } elseif ($action === 'stop') {
        // Update session log
        $stmt = $db->prepare("UPDATE session_logs SET actual_end_time = NOW() WHERE room_id = ? AND actual_end_time IS NULL");
        $stmt->execute([$room_id]);
        
        if ($stmt->rowCount() === 0) {
            $db->rollBack();
            jsonResponse(['success' => false, 'error' => 'No active session found for this room.'], 400);
        }

        // Update classroom status
        $stmt = $db->prepare("UPDATE classrooms SET current_status = 'Vacant' WHERE room_id = ?");
        $stmt->execute([$room_id]);
        
        $new_status = 'Vacant';
    } else {
        $db->rollBack();
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
    }

    $db->commit();
    jsonResponse(['success' => true, 'new_status' => $new_status]);
} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
