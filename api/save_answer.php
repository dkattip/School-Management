<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonResponse(['error' => 'Invalid request'], 400);

$attemptId = (int)($input['attempt_id'] ?? 0);
$examId = (int)($input['exam_id'] ?? 0);
$answers = $input['answers'] ?? [];
$markedReview = $input['marked_for_review'] ?? [];
$studentId = $_SESSION['user_id'];

if (!$attemptId || !$examId) jsonResponse(['error' => 'Missing parameters'], 400);

$attempt = $conn->query("SELECT * FROM exam_attempts WHERE id = $attemptId AND student_id = $studentId AND exam_id = $examId AND status = 'in_progress'")->fetch_assoc();
if (!$attempt) jsonResponse(['error' => 'Invalid attempt'], 400);

$conn->begin_transaction();
try {
    foreach ($answers as $questionId => $selectedOption) {
        $questionId = (int)$questionId;
        $selectedOption = strtoupper(sanitize($selectedOption));

        $question = $conn->query("SELECT correct_answer, marks FROM questions WHERE id = $questionId AND exam_id = $examId")->fetch_assoc();
        if (!$question) continue;

        $isCorrect = strtoupper($question['correct_answer']) === $selectedOption ? 1 : 0;
        $marksObtained = $isCorrect ? $question['marks'] : 0;
        $isMarked = in_array($questionId, $markedReview) ? 1 : 0;

        $existing = $conn->query("SELECT id FROM student_answers WHERE attempt_id = $attemptId AND question_id = $questionId")->fetch_assoc();
        if ($existing) {
            $conn->query("UPDATE student_answers SET selected_option = '$selectedOption', is_correct = $isCorrect, marks_obtained = $marksObtained, marked_for_review = $isMarked WHERE attempt_id = $attemptId AND question_id = $questionId");
        } else {
            $conn->query("INSERT INTO student_answers (attempt_id, question_id, selected_option, is_correct, marks_obtained, marked_for_review) VALUES ($attemptId, $questionId, '$selectedOption', $isCorrect, $marksObtained, $isMarked)");
        }
    }

    $conn->query("UPDATE exam_attempts SET last_activity = NOW() WHERE id = $attemptId");
    $conn->commit();
    jsonResponse(['success' => true, 'message' => 'Answers saved']);
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(['error' => 'Failed to save answers'], 500);
}
