<?php
require_once __DIR__ . '/../config/database.php';
requireRole('student');
$studentId = $_SESSION['user_id'];
$pageTitle = 'My Exams';
include __DIR__ . '/../includes/header.php';

$studentInfo = $conn->query("SELECT class_id FROM student_classes WHERE student_id = $studentId LIMIT 1")->fetch_assoc();
$classId = $studentInfo['class_id'] ?? 0;

$statusFilter = sanitize($_GET['status'] ?? '');

$where = "e.class_id = $classId AND e.is_active = 1 AND (
    e.assign_mode = 'class'
    OR (e.assign_mode = 'selected' AND EXISTS (SELECT 1 FROM exam_student_assignments WHERE exam_id = e.id AND student_id = $studentId))
)";
if ($statusFilter === 'upcoming') $where .= " AND e.start_time > NOW()";
elseif ($statusFilter === 'active') $where .= " AND e.start_time <= NOW() AND e.end_time >= NOW()";
elseif ($statusFilter === 'completed') $where .= " AND (e.end_time < NOW() OR EXISTS (SELECT 1 FROM exam_attempts WHERE exam_id = e.id AND student_id = $studentId AND status = 'completed'))";

$exams = $conn->query("SELECT e.*, s.subject_name, s.subject_code, CONCAT(c.class_name, ' ', c.section) as class_full,
    (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) as q_count,
    (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id AND student_id = $studentId AND status = 'completed') as my_attempts,
    (SELECT total_score FROM exam_attempts WHERE exam_id = e.id AND student_id = $studentId AND status = 'completed' ORDER BY id DESC LIMIT 1) as my_score,
    (SELECT max_score FROM exam_attempts WHERE exam_id = e.id AND student_id = $studentId AND status = 'completed' ORDER BY id DESC LIMIT 1) as my_max,
    (SELECT ROUND(percentage, 1) FROM exam_attempts WHERE exam_id = e.id AND student_id = $studentId AND status = 'completed' ORDER BY id DESC LIMIT 1) as my_pct
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    JOIN classes c ON e.class_id = c.id
    WHERE $where
    ORDER BY e.start_time DESC
    LIMIT 100")->fetch_all(MYSQLI_ASSOC);

function getExamStatus($exam) {
    $now = date('Y-m-d H:i:s');
    if ($exam['my_attempts'] > 0) return 'completed';
    if ($exam['start_time'] > $now) return 'upcoming';
    if ($exam['end_time'] >= $now && $exam['start_time'] <= $now) return 'active';
    return 'expired';
}
?>

<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-gray-800">My Exams</h2>
        <span class="bg-gray-100 text-gray-600 text-xs font-medium px-2.5 py-1 rounded-full"><?= count($exams) ?> found</span>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6 p-4">
    <form method="GET" class="flex flex-col sm:flex-row gap-3">
        <div class="flex gap-2 flex-wrap flex-1">
            <a href="?" class="px-4 py-2 text-sm font-medium rounded-lg transition-colors <?= !$statusFilter ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">All</a>
            <a href="?status=upcoming" class="px-4 py-2 text-sm font-medium rounded-lg transition-colors <?= $statusFilter === 'upcoming' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">Upcoming</a>
            <a href="?status=active" class="px-4 py-2 text-sm font-medium rounded-lg transition-colors <?= $statusFilter === 'active' ? 'bg-green-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">Active</a>
            <a href="?status=completed" class="px-4 py-2 text-sm font-medium rounded-lg transition-colors <?= $statusFilter === 'completed' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">Completed</a>
        </div>
    </form>
</div>

<div class="space-y-4">
    <?php if (empty($exams)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">No exams found</div>
    <?php else: foreach ($exams as $e):
        $status = getExamStatus($e);
    ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2 flex-wrap">
                        <h3 class="font-semibold text-gray-800 text-lg"><?= htmlspecialchars($e['exam_name']) ?></h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            <?= $status === 'active' ? 'bg-green-100 text-green-700' : ($status === 'upcoming' ? 'bg-amber-100 text-amber-700' : ($status === 'completed' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600')) ?>">
                            <?= ucfirst($status === 'expired' ? 'Expired' : $status) ?>
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                            <?= ucfirst($e['exam_type']) ?>
                        </span>
                        <?php if ($e['seb_enabled']): ?>
                        <span class="inline-flex items-center px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-xs font-medium">SEB</span>
                        <?php endif; ?>
                        <?php if ($e['webcam_required']): ?>
                        <span class="inline-flex items-center px-2 py-0.5 bg-purple-50 text-purple-600 rounded text-xs font-medium">Webcam</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap gap-4 text-sm text-gray-500">
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                            <?= htmlspecialchars($e['subject_name']) ?>
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <?= $e['duration_minutes'] ?> min &middot; <?= $e['total_marks'] ?> marks
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <?= $e['q_count'] ?> Questions
                        </span>
                    </div>
                    <div class="mt-2 text-xs text-gray-400">
                        <?php if ($status === 'upcoming'): ?>
                        Starts: <?= date('d M Y, h:i A', strtotime($e['start_time'])) ?>
                        <?php elseif ($status === 'active'): ?>
                        Ends: <?= date('d M Y, h:i A', strtotime($e['end_time'])) ?>
                        <?php elseif ($status === 'completed' && $e['my_score'] !== null): ?>
                        Score: <?= $e['my_score'] ?>/<?= $e['my_max'] ?> (<?= $e['my_pct'] ?>%)
                        <?php else: ?>
                        <?= date('d M Y, h:i A', strtotime($e['start_time'])) ?> &mdash; <?= date('d M Y, h:i A', strtotime($e['end_time'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 items-center">
                    <?php if ($status === 'active'): ?>
                    <a href="take_exam.php?exam_id=<?= $e['id'] ?>" onclick="return confirm('You are about to start this exam. The timer will begin immediately. Are you ready?')" class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Start Exam
                    </a>
                    <?php elseif ($status === 'completed'): ?>
                    <a href="results.php?view=<?= $e['id'] ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-green-50 text-green-700 text-sm font-medium rounded-lg hover:bg-green-100 border border-green-200 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        View Result
                    </a>
                    <?php elseif ($status === 'upcoming'): ?>
                    <span class="text-sm text-gray-400 italic">Not yet available</span>
                    <?php else: ?>
                    <span class="text-sm text-gray-400 italic">Expired</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
