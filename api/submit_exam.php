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
$reason = $input['reason'] ?? 'manual';
$tabSwitches = (int)($input['tab_switches'] ?? 0);
$studentId = $_SESSION['user_id'];

if (!$attemptId || !$examId) jsonResponse(['error' => 'Missing parameters'], 400);

$attempt = $conn->query("SELECT * FROM exam_attempts WHERE id = $attemptId AND student_id = $studentId AND exam_id = $examId AND status = 'in_progress'")->fetch_assoc();
if (!$attempt) jsonResponse(['error' => 'Invalid attempt or already submitted'], 400);

$exam = $conn->query("SELECT * FROM exams WHERE id = $examId")->fetch_assoc();
if (!$exam) jsonResponse(['error' => 'Exam not found'], 400);

$conn->begin_transaction();
try {
    $totalScore = 0;
    $maxScore = 0;

    $questions = $conn->query("SELECT id, correct_answer, marks FROM questions WHERE exam_id = $examId")->fetch_all(MYSQLI_ASSOC);
    foreach ($questions as $q) {
        $maxScore += (int)$q['marks'];
    }

    foreach ($answers as $questionId => $selectedOption) {
        $questionId = (int)$questionId;
        $selectedOption = strtoupper(sanitize($selectedOption));

        $question = null;
        foreach ($questions as $q) {
            if ((int)$q['id'] === $questionId) { $question = $q; break; }
        }
        if (!$question) continue;

        $isCorrect = strtoupper($question['correct_answer']) === $selectedOption ? 1 : 0;
        $marksObtained = $isCorrect ? (int)$question['marks'] : 0;
        $totalScore += $marksObtained;
        $isMarked = in_array($questionId, $markedReview) ? 1 : 0;

        $existing = $conn->query("SELECT id FROM student_answers WHERE attempt_id = $attemptId AND question_id = $questionId")->fetch_assoc();
        if ($existing) {
            $conn->query("UPDATE student_answers SET selected_option = '$selectedOption', is_correct = $isCorrect, marks_obtained = $marksObtained, marked_for_review = $isMarked WHERE attempt_id = $attemptId AND question_id = $questionId");
        } else {
            $conn->query("INSERT INTO student_answers (attempt_id, question_id, selected_option, is_correct, marks_obtained, marked_for_review) VALUES ($attemptId, $questionId, '$selectedOption', $isCorrect, $marksObtained, $isMarked)");
        }
    }

    $percentage = $maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0;
    $percentage = round($percentage, 2);

    $conn->query("UPDATE exam_attempts SET
        total_score = $totalScore,
        max_score = $maxScore,
        percentage = $percentage,
        status = 'completed',
        end_time = NOW(),
        submitted_at = NOW(),
        tab_switches = $tabSwitches,
        submission_reason = '$reason'
        WHERE id = $attemptId");

    $conn->commit();

    jsonResponse([
        'success' => true,
        'score' => $totalScore,
        'max_score' => $maxScore,
        'percentage' => $percentage,
        'tab_switches' => $tabSwitches,
        'reason' => $reason
    ]);
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(['error' => 'Failed to submit exam: ' . $e->getMessage()], 500);
}
