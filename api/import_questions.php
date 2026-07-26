<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'teacher') {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$teacherId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$exam_id = (int)($input['exam_id'] ?? $_POST['exam_id'] ?? 0);
$questions = $input['questions'] ?? [];

if (!$exam_id) {
    jsonResponse(['error' => 'exam_id required'], 400);
}

$check = $conn->query("SELECT id FROM exams WHERE id = $exam_id AND created_by = $teacherId");
if ($check->num_rows === 0) {
    jsonResponse(['error' => 'Exam not found or not authorized'], 403);
}

if (empty($questions)) {
    jsonResponse(['error' => 'No questions provided'], 400);
}

$max_order = $conn->query("SELECT COALESCE(MAX(question_order), 0) as mo FROM questions WHERE exam_id = $exam_id")->fetch_assoc()['mo'];
$imported = 0;
$errors = [];

foreach ($questions as $idx => $q) {
    $question_text = trim($q['question_text'] ?? $q['text'] ?? '');
    $question_type = trim($q['question_type'] ?? $q['type'] ?? 'mcq');
    $option_a = trim($q['option_a'] ?? $q['a'] ?? '');
    $option_b = trim($q['option_b'] ?? $q['b'] ?? '');
    $option_c = trim($q['option_c'] ?? $q['c'] ?? '');
    $option_d = trim($q['option_d'] ?? $q['d'] ?? '');
    $correct_answer = trim($q['correct_answer'] ?? $q['answer'] ?? '');
    $marks = max(0.5, (float)($q['marks'] ?? 1));

    if (empty($question_text) || empty($correct_answer)) {
        $errors[] = "Question " . ($idx + 1) . ": missing text or answer";
        continue;
    }

    $valid_types = ['mcq', 'short_answer', 'long_answer', 'true_false', 'numerical'];
    if (!in_array($question_type, $valid_types)) {
        $question_type = 'mcq';
    }

    $max_order++;
    $stmt = $conn->prepare("INSERT INTO questions (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, marks, question_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssssdi", $exam_id, $question_text, $question_type, $option_a, $option_b, $option_c, $option_d, $correct_answer, $marks, $max_order);
    if ($stmt->execute()) {
        $imported++;
    } else {
        $errors[] = "Question " . ($idx + 1) . ": insert failed";
    }
    $stmt->close();
}

if ($imported > 0) {
    $conn->query("INSERT INTO import_logs (exam_id, imported_by, import_type, questions_count, status) VALUES ($exam_id, $teacherId, 'paste', $imported, " . (empty($errors) ? "'success'" : "'partial'") . ")");
}

jsonResponse([
    'success' => true,
    'imported' => $imported,
    'errors' => $errors,
    'total_requested' => count($questions)
]);
