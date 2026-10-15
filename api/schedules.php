<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['day'])) {
        jsonResponse(['success' => false, 'error' => 'Missing required parameter: day'], 400);
    }

    $day = $_GET['day'];
    $floor = (isset($_GET['floor']) && $_GET['floor'] !== 'all') ? (int)$_GET['floor'] : null;
    $room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : null;

    // Build room query
    $roomQuery = "SELECT room_id, room_name_number, floor_level FROM classrooms";
    $roomParams = [];
    $conditions = [];

    if ($floor) {
        $conditions[] = "floor_level = ?";
        $roomParams[] = $floor;
    }
    if ($room_id) {
        $conditions[] = "room_id = ?";
        $roomParams[] = $room_id;
    }
    if ($conditions) {
        $roomQuery .= " WHERE " . implode(" AND ", $conditions);
    }
    $roomQuery .= " ORDER BY floor_level, room_name_number";

    $stmt = $db->prepare($roomQuery);
    $stmt->execute($roomParams);
    $rooms = $stmt->fetchAll();

    // Get schedules for the day
    $schedQuery = "SELECT s.schedule_id, s.room_id, s.faculty_id, s.subject_code, s.section_name,
                          s.day_of_week, s.start_time, s.end_time,
                          CONCAT(u.first_name, ' ', u.last_name) AS faculty_name
                   FROM schedules s
                   JOIN users u ON s.faculty_id = u.user_id
                   WHERE s.day_of_week = ?";
    $schedParams = [$day];

    if ($room_id) {
        $schedQuery .= " AND s.room_id = ?";
        $schedParams[] = $room_id;
    }

    $stmt = $db->prepare($schedQuery);
    $stmt->execute($schedParams);
    $schedules = $stmt->fetchAll();

    // Group schedules by room_id
    $schedByRoom = [];
    foreach ($schedules as $s) {
        $schedByRoom[$s['room_id']][] = $s;
    }

    // Build grouped response
    $result = [];
    foreach ($rooms as $room) {
        $result[] = [
            'room_id' => $room['room_id'],
            'room_name_number' => $room['room_name_number'],
            'floor_level' => $room['floor_level'],
            'schedules' => $schedByRoom[$room['room_id']] ?? []
        ];
    }

    jsonResponse(['success' => true, 'data' => $result]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    requireRole('admin');
    validateCSRF();

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $room_id = (int)($input['room_id'] ?? 0);
        $faculty_id = (int)($input['faculty_id'] ?? 0);
        $subject_code = trim($input['subject_code'] ?? '');
        $section_name = trim($input['section_name'] ?? '');
        $day_of_week = $input['day_of_week'] ?? '';
        $start_time = $input['start_time'] ?? '';
        $end_time = $input['end_time'] ?? '';

        if (!$room_id || !$faculty_id || !$subject_code || !$section_name || !$day_of_week || !$start_time || !$end_time) {
            jsonResponse(['success' => false, 'message' => 'All fields are required'], 400);
        }

        $validDays = ['Mon','Tue','Wed','Thu','Fri','Sat'];
        if (!in_array($day_of_week, $validDays)) {
            jsonResponse(['success' => false, 'message' => 'Invalid day of week'], 400);
        }

        // Check time conflict (same room, same day, overlapping time)
        $conflictQuery = "SELECT schedule_id FROM schedules
                          WHERE room_id = ? AND day_of_week = ?
                          AND start_time < ? AND end_time > ?";
        $conflictParams = [$room_id, $day_of_week, $end_time, $start_time];

        if ($action === 'edit') {
            $schedule_id = (int)($input['schedule_id'] ?? 0);
            if (!$schedule_id) {
                jsonResponse(['success' => false, 'message' => 'schedule_id required for edit'], 400);
            }
            $conflictQuery .= " AND schedule_id != ?";
            $conflictParams[] = $schedule_id;
        }

        $stmt = $db->prepare($conflictQuery);
        $stmt->execute($conflictParams);
        if ($stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Time conflict: another class is scheduled in this room at the same time'], 409);
        }

        if ($action === 'add') {
            $stmt = $db->prepare("INSERT INTO schedules (room_id, faculty_id, subject_code, section_name, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$room_id, $faculty_id, $subject_code, $section_name, $day_of_week, $start_time, $end_time]);
            jsonResponse(['success' => true, 'message' => 'Schedule added', 'schedule_id' => $db->lastInsertId()]);
        } else {
            $stmt = $db->prepare("UPDATE schedules SET room_id=?, faculty_id=?, subject_code=?, section_name=?, day_of_week=?, start_time=?, end_time=? WHERE schedule_id=?");
            $stmt->execute([$room_id, $faculty_id, $subject_code, $section_name, $day_of_week, $start_time, $end_time, $schedule_id]);
            jsonResponse(['success' => true, 'message' => 'Schedule updated']);
        }

    } elseif ($action === 'delete') {
        $schedule_id = (int)($input['schedule_id'] ?? 0);
        if (!$schedule_id) {
            jsonResponse(['success' => false, 'message' => 'schedule_id required'], 400);
        }
        $stmt = $db->prepare("DELETE FROM schedules WHERE schedule_id = ?");
        $stmt->execute([$schedule_id]);
        jsonResponse(['success' => true, 'message' => 'Schedule deleted']);

    } else {
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
} else {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}
