<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
$db = getDB();

$floor = isset($_GET['floor']) ? (int)$_GET['floor'] : null;

$query = "SELECT room_id, room_name_number, room_type, capacity, floor_level, current_status FROM classrooms";
$params = [];
if ($floor) {
    $query .= " WHERE floor_level = ?";
    $params[] = $floor;
}
$query .= " ORDER BY floor_level, room_name_number";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $rooms = $stmt->fetchAll();
    
    $grouped = [];
    foreach ($rooms as $room) {
        $f = $room['floor_level'];
        if (!isset($grouped[$f])) {
            $grouped[$f] = [];
        }
        $grouped[$f][] = $room;
    }
    
    jsonResponse(['success' => true, 'data' => $grouped]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
