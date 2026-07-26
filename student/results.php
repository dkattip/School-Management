<?php
require_once __DIR__ . '/../config/database.php';
requireRole('student');
$studentId = $_SESSION['user_id'];
$pageTitle = 'My Results';
include __DIR__ . '/../includes/header.php';

$viewExamId = (int)($_GET['view'] ?? 0);

if ($viewExamId) {
    $attempt = $conn->query("SELECT ea.*, e.exam_name, e.total_marks as exam_total_marks, e.passing_marks, e.duration_minutes,
        s.subject_name, CONCAT(c.class_name, ' ', c.section) as class_full,
        TIMESTAMPDIFF(SECOND, ea.start_time, ea.end_time) as time_taken_secs
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        JOIN subjects s ON e.subject_id = s.id
        JOIN classes c ON e.class_id = c.id
        WHERE ea.exam_id = $viewExamId AND ea.student_id = $studentId AND ea.status = 'completed'
        ORDER BY ea.id DESC LIMIT 1")->fetch_assoc();

    if (!$attempt) {
        echo '<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">Result not found. <a href="results.php" class="text-primary-600 hover:underline">Go back</a></div>';
        include __DIR__ . '/../includes/footer.php';
        exit();
    }

    $attemptId = $attempt['id'];
    $studentRank = $conn->query("SELECT COUNT(*) + 1 as rank_num FROM exam_attempts ea2
        WHERE ea2.exam_id = $viewExamId AND ea2.status = 'completed' AND ea2.percentage > {$attempt['percentage']}")->fetch_assoc()['rank_num'];
    $totalStudents = $conn->query("SELECT COUNT(*) as c FROM exam_attempts WHERE exam_id = $viewExamId AND status = 'completed'")->fetch_assoc()['c'];

    $questions = $conn->query("SELECT q.*, sa.selected_option, sa.marks_obtained, sa.marked_for_review
        FROM questions q
        LEFT JOIN student_answers sa ON q.id = sa.question_id AND sa.attempt_id = $attemptId
        WHERE q.exam_id = $viewExamId
        ORDER BY q.id")->fetch_all(MYSQLI_ASSOC);

    $correctCount = 0;
    $incorrectCount = 0;
    $unattemptedCount = 0;
    foreach ($questions as $q) {
        if (empty($q['selected_option'])) $unattemptedCount++;
        elseif (strtoupper($q['selected_option']) === strtoupper($q['correct_answer'])) $correctCount++;
        else $incorrectCount++;
    }

    $passThreshold = ($attempt['passing_marks'] / $attempt['exam_total_marks'] * 100);
    $passed = $attempt['percentage'] >= $passThreshold;
?>

<div class="space-y-6">
    <div class="flex items-center gap-3 mb-2">
        <a href="results.php" class="text-primary-600 hover:text-primary-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <h2 class="text-lg font-semibold text-gray-800">Exam Result</h2>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-gradient-to-r from-primary-600 to-purple-600 px-8 py-8 text-white">
            <div class="flex flex-col lg:flex-row items-center gap-6">
                <div class="w-24 h-24 rounded-full flex items-center justify-center text-3xl font-bold <?= $passed ? 'bg-white/20' : 'bg-white/10' ?>">
                    <?= round($attempt['percentage']) ?>%
                </div>
                <div class="flex-1 text-center lg:text-left">
                    <h3 class="text-2xl font-bold"><?= htmlspecialchars($attempt['exam_name']) ?></h3>
                    <p class="text-white/70 mt-1"><?= htmlspecialchars($attempt['subject_name']) ?> &middot; <?= htmlspecialchars($attempt['class_full']) ?></p>
                    <p class="text-white/50 text-sm mt-1">Submitted: <?= date('d M Y, h:i A', strtotime($attempt['submitted_at'] ?? $attempt['created_at'])) ?></p>
                </div>
                <div class="flex gap-6">
                    <div class="text-center">
                        <p class="text-3xl font-bold"><?= $attempt['total_score'] ?>/<?= $attempt['max_score'] ?></p>
                        <p class="text-white/60 text-xs mt-1">Score</p>
                    </div>
                    <div class="text-center">
                        <p class="text-3xl font-bold">#<?= $studentRank ?></p>
                        <p class="text-white/60 text-xs mt-1">Rank</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="px-8 py-5 grid grid-cols-2 sm:grid-cols-5 gap-4 border-b border-gray-100">
            <div class="text-center">
                <p class="text-lg font-bold <?= $passed ? 'text-emerald-600' : 'text-red-500' ?>"><?= $passed ? 'PASS' : 'FAIL' ?></p>
                <p class="text-xs text-gray-500">Status</p>
            </div>
            <div class="text-center">
                <p class="text-lg font-bold text-gray-800"><?= $attempt['time_taken_secs'] ? floor($attempt['time_taken_secs'] / 60) . 'm ' . ($attempt['time_taken_secs'] % 60) . 's' : '-' ?></p>
                <p class="text-xs text-gray-500">Time Taken</p>
            </div>
            <div class="text-center">
                <p class="text-lg font-bold text-emerald-600"><?= $correctCount ?></p>
                <p class="text-xs text-gray-500">Correct</p>
            </div>
            <div class="text-center">
                <p class="text-lg font-bold text-red-500"><?= $incorrectCount ?></p>
                <p class="text-xs text-gray-500">Incorrect</p>
            </div>
            <div class="text-center">
                <p class="text-lg font-bold text-gray-400"><?= $unattemptedCount ?></p>
                <p class="text-xs text-gray-500">Unattempted</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Question-wise Breakdown</h3>
        </div>
        <div class="divide-y divide-gray-50">
            <?php foreach ($questions as $qi => $q):
                $isCorrect = strtoupper($q['selected_option'] ?? '') === strtoupper($q['correct_answer']);
                $isUnattempted = empty($q['selected_option']);
                $correctLetter = strtoupper($q['correct_answer']);
            ?>
            <div class="p-5">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-bold shrink-0
                        <?= $isUnattempted ? 'bg-gray-100 text-gray-400' : ($isCorrect ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600') ?>">
                        <?= $qi + 1 ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-800 font-medium mb-3"><?= renderLatexText($q['question_text']) ?></p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">
                            <?php
                            $opts = ['A' => $q['option_a'], 'B' => $q['option_b'], 'C' => $q['option_c'], 'D' => $q['option_d'], 'E' => $q['option_e']];
                            foreach ($opts as $letter => $opt):
                                if (!$opt) continue;
                                $isSelected = strtoupper($q['selected_option'] ?? '') === $letter;
                                $isCorrectOpt = $letter === $correctLetter;
                            ?>
                            <div class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm
                                <?= $isCorrectOpt ? 'bg-emerald-50 border border-emerald-200' : ($isSelected && !$isCorrectOpt ? 'bg-red-50 border border-red-200' : 'bg-gray-50 border border-gray-100') ?>">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold
                                    <?= $isCorrectOpt ? 'bg-emerald-500 text-white' : ($isSelected && !$isCorrectOpt ? 'bg-red-500 text-white' : 'bg-gray-200 text-gray-500') ?>">
                                    <?= $letter ?>
                                </span>
                                <span class="flex-1 text-gray-700"><?= renderLatexText($opt) ?></span>
                                <?php if ($isCorrectOpt && $isSelected): ?>
                                <span class="text-emerald-500 font-bold text-xs">Your Answer</span>
                                <?php elseif ($isCorrectOpt): ?>
                                <span class="text-emerald-600 text-xs">Correct</span>
                                <?php elseif ($isSelected): ?>
                                <span class="text-red-500 text-xs">Your Answer</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            <?php if ($isUnattempted): ?>
                            <span class="text-gray-400">Not attempted</span>
                            <?php elseif ($isCorrect): ?>
                            <span class="text-emerald-600 font-medium">Correct (+<?= $q['marks_obtained'] ?? $q['marks'] ?> marks)</span>
                            <?php else: ?>
                            <span class="text-red-500 font-medium">Incorrect (0 marks)</span>
                            <?php endif; ?>
                            <span class="text-gray-400">|</span>
                            <span class="text-gray-500">Correct answer: <?= $correctLetter ?></span>
                            <?php if ($q['marked_for_review']): ?>
                            <span class="text-yellow-600 font-medium">Marked for review</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
    include __DIR__ . '/../includes/footer.php';
    exit();
}

$examResults = $conn->query("SELECT ea.*, e.exam_name, e.total_marks as exam_total_marks, e.passing_marks,
    s.subject_name,
    TIMESTAMPDIFF(SECOND, ea.start_time, ea.end_time) as time_taken_secs,
    (SELECT COUNT(*) + 1 FROM exam_attempts ea2 WHERE ea2.exam_id = ea.exam_id AND ea2.status = 'completed' AND ea2.percentage > ea.percentage) as rank_num,
    (SELECT COUNT(*) FROM exam_attempts ea2 WHERE ea2.exam_id = ea.exam_id AND ea2.status = 'completed') as total_students
    FROM exam_attempts ea
    JOIN exams e ON ea.exam_id = e.id
    JOIN subjects s ON e.subject_id = s.id
    WHERE ea.student_id = $studentId AND ea.status = 'completed'
    ORDER BY ea.created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-gray-800">My Results</h2>
        <span class="bg-gray-100 text-gray-600 text-xs font-medium px-2.5 py-1 rounded-full"><?= count($examResults) ?> result<?= count($examResults) != 1 ? 's' : '' ?></span>
    </div>
</div>

<?php if (empty($examResults)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
    <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
    <p class="text-gray-400 text-lg">No results yet</p>
    <p class="text-gray-300 text-sm mt-1">Your exam results will appear here after completion</p>
    <a href="my_exams.php" class="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors">Browse Exams</a>
</div>
<?php else: ?>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <p class="text-2xl font-bold text-gray-800"><?= count($examResults) ?></p>
        <p class="text-xs text-gray-500">Exams Completed</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <?php
        $totalPct = 0;
        foreach ($examResults as $r) $totalPct += $r['percentage'];
        $avgPct = count($examResults) > 0 ? round($totalPct / count($examResults), 1) : 0;
        ?>
        <p class="text-2xl font-bold <?= $avgPct >= 80 ? 'text-green-600' : ($avgPct >= 50 ? 'text-amber-600' : 'text-red-500') ?>"><?= $avgPct ?>%</p>
        <p class="text-xs text-gray-500">Average Score</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <?php
        $passCount = 0;
        foreach ($examResults as $r) {
            if ($r['percentage'] >= ($r['passing_marks'] / $r['exam_total_marks'] * 100)) $passCount++;
        }
        ?>
        <p class="text-2xl font-bold text-emerald-600"><?= $passCount ?>/<?= count($examResults) ?></p>
        <p class="text-xs text-gray-500">Exams Passed</p>
    </div>
</div>

<div class="space-y-4">
    <?php foreach ($examResults as $r):
        $pct = round($r['percentage'], 1);
        $passThreshold = ($r['passing_marks'] / $r['exam_total_marks'] * 100);
        $passed = $r['percentage'] >= $passThreshold;
    ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">
        <div class="p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($r['exam_name']) ?></h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $passed ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600' ?>">
                            <?= $passed ? 'Pass' : 'Fail' ?>
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                        <span><?= htmlspecialchars($r['subject_name']) ?></span>
                        <span>&middot;</span>
                        <span><?= date('d M Y, h:i A', strtotime($r['submitted_at'] ?? $r['created_at'])) ?></span>
                        <span>&middot;</span>
                        <span><?= $r['time_taken_secs'] ? floor($r['time_taken_secs'] / 60) . 'm ' . ($r['time_taken_secs'] % 60) . 's' : '-' ?></span>
                        <span>&middot;</span>
                        <span>Rank #<?= $r['rank_num'] ?>/<?= $r['total_students'] ?></span>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <div class="flex items-center gap-2">
                            <div class="w-20 h-2.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full <?= $pct >= 80 ? 'bg-emerald-500' : ($pct >= 50 ? 'bg-amber-500' : 'bg-red-500') ?>" style="width: <?= min(100, $pct) ?>%"></div>
                            </div>
                            <span class="text-lg font-bold <?= $pct >= 80 ? 'text-emerald-600' : ($pct >= 50 ? 'text-amber-600' : 'text-red-500') ?>"><?= $pct ?>%</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5"><?= $r['total_score'] ?>/<?= $r['max_score'] ?> marks</p>
                    </div>
                    <a href="?view=<?= $r['exam_id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-primary-600 bg-primary-50 border border-primary-200 rounded-lg hover:bg-primary-100 transition-colors">
                        Details
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
