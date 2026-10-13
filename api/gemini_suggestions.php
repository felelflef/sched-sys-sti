<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
requireRole('admin');

header('Content-Type: application/json');
$db = getDB();

try {
    // Fetch last 7 days of session logs
    $stmtLogs = $db->prepare("
        SELECT c.room_name_number, u.first_name, u.last_name, sl.session_date, sl.actual_start_time, sl.actual_end_time 
        FROM session_logs sl 
        JOIN classrooms c ON sl.room_id = c.room_id 
        JOIN users u ON sl.faculty_id = u.user_id 
        WHERE sl.session_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY sl.session_date DESC
    ");
    $stmtLogs->execute();
    $logs = $stmtLogs->fetchAll();

    $logsText = "";
    foreach ($logs as $log) {
        $endTime = $log['actual_end_time'] ?? 'Ongoing';
        $logsText .= "Room {$log['room_name_number']} | Faculty: {$log['first_name']} {$log['last_name']} | Date: {$log['session_date']} | Start: {$log['actual_start_time']} | End: {$endTime}\n";
    }

    // Fetch all schedules
    $stmtSched = $db->prepare("
        SELECT c.room_name_number, s.day_of_week, s.start_time, s.end_time, s.subject_code, s.section_name, u.first_name, u.last_name 
        FROM schedules s 
        JOIN classrooms c ON s.room_id = c.room_id 
        JOIN users u ON s.faculty_id = u.user_id
        ORDER BY s.day_of_week, s.start_time
    ");
    $stmtSched->execute();
    $schedules = $stmtSched->fetchAll();

    $schedText = "";
    foreach ($schedules as $sched) {
        $schedText .= "Room {$sched['room_name_number']} | Day: {$sched['day_of_week']} | Time: {$sched['start_time']}-{$sched['end_time']} | Subject: {$sched['subject_code']} | Section: {$sched['section_name']} | Faculty: {$sched['first_name']} {$sched['last_name']}\n";
    }

    if (empty($logsText) && empty($schedText)) {
        jsonResponse(['success' => true, 'data' => 'No schedule or session data available yet. Add schedules and start logging sessions to receive AI-powered utilization insights.']);
    }

    $prompt = "You are an AI campus space utilization analyst for STI College Marikina. The campus has 22 rooms across 3 floors (Floor 1: 4 Labs, Kitchen, Bar; Floor 2: Rooms 201-205; Floor 3: Rooms 301-310, Tourism Lab). Analyze the following classroom usage data and provide 3-5 actionable recommendations.

SCHEDULED CLASSES:
{$schedText}
ACTUAL SESSION LOGS (Last 7 Days):
{$logsText}
Provide insights on:
1. Rooms that are consistently underutilized
2. Peak hours and congestion patterns
3. Discrepancies between scheduled and actual usage (phantom occupancy)
4. Recommendations for better space allocation

Format your response with clear numbered points and bold key findings using **bold** markdown.";

    $postData = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 1024
        ]
    ];

    $url = "https://generativelanguage.googleapis.com/v1beta/models/" . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        jsonResponse(['success' => false, 'error' => 'Connection error: ' . $curlError], 500);
    }

    if ($httpCode !== 200 || !$response) {
        jsonResponse(['success' => false, 'error' => 'Gemini API returned HTTP ' . $httpCode . '. Check your API key in config/db.php.'], 500);
    }

    $responseData = json_decode($response, true);
    $aiText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? 'No insights generated. The API returned an unexpected response format.';

    jsonResponse(['success' => true, 'data' => $aiText]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Error generating insights: ' . $e->getMessage()], 500);
}
