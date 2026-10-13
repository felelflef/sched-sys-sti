<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
$db = getDB();

if (!isset($_GET['day'])) {
    jsonResponse(['success' => false, 'error' => 'Missing required parameter: day'], 400);
}

$day = $_GET['day'];
$floor = isset($_GET['floor']) ? (int)$_GET['floor'] : null;
$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : null;

$query = "SELECT s.*, c.room_name_number, c.floor_level, u.first_name, u.last_name 
          FROM schedules s 
          JOIN classrooms c ON s.room_id = c.room_id 
          JOIN users u ON s.faculty_id = u.user_id 
          WHERE s.day_of_week = ?";
$params = [$day];

if ($floor) {
    $query .= " AND c.floor_level = ?";
    $params[] = $floor;
}

if ($room_id) {
    $query .= " AND s.room_id = ?";
    $params[] = $room_id;
}

$query .= " ORDER BY c.floor_level, c.room_name_number, s.start_time";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll();
    
    jsonResponse(['success' => true, 'data' => $schedules]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
