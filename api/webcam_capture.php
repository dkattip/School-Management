<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

header('Content-Type: application/json');

$attemptId = (int)($_POST['attempt_id'] ?? 0);
$studentId = $_SESSION['user_id'];

if (!$attemptId) jsonResponse(['error' => 'Missing attempt_id'], 400);

$attempt = $conn->query("SELECT id FROM exam_attempts WHERE id = $attemptId AND student_id = $studentId AND status = 'in_progress'")->fetch_assoc();
if (!$attempt) jsonResponse(['error' => 'Invalid attempt'], 400);

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['error' => 'No image received'], 400);
}

$uploadDir = __DIR__ . '/../uploads/webcam/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = "webcam_{$attemptId}_" . time() . ".jpg";
$filepath = $uploadDir . $filename;

if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
    $conn->query("INSERT INTO webcam_logs (attempt_id, student_id, image_path, captured_at) VALUES ($attemptId, $studentId, '$filename', NOW())");
    jsonResponse(['success' => true, 'filename' => $filename]);
} else {
    jsonResponse(['error' => 'Failed to save image'], 500);
}
