<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$attemptId = (int)($input['attempt_id'] ?? 0);
$reason = sanitize($input['reason'] ?? 'unknown');
$switchCount = (int)($input['switch_count'] ?? 0);
$studentId = $_SESSION['user_id'];

if (!$attemptId) jsonResponse(['error' => 'Missing attempt_id'], 400);

$conn->query("INSERT INTO exam_violations (attempt_id, student_id, reason, switch_count, recorded_at) VALUES ($attemptId, $studentId, '$reason', $switchCount, NOW())");
jsonResponse(['success' => true]);
