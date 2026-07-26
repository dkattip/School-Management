<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$attemptId = (int)($input['attempt_id'] ?? 0);
$studentId = $_SESSION['user_id'];

if (!$attemptId) jsonResponse(['error' => 'Missing attempt_id'], 400);

$conn->query("UPDATE exam_attempts SET last_activity = NOW() WHERE id = $attemptId AND student_id = $studentId");
jsonResponse(['success' => true]);
