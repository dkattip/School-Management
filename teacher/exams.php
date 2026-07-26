<?php
require_once __DIR__ . '/../config/database.php';
requireRole('teacher');
$teacherId = $_SESSION['user_id'];
$pageTitle = 'My Exams';
include __DIR__ . '/../includes/header.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $check = $conn->query("SELECT id FROM exams WHERE id=$id AND created_by=$teacherId");
            if ($check->num_rows > 0) {
                $conn->query("DELETE FROM exams WHERE id=$id");
                $success = 'Exam deleted successfully.';
            } else {
                $error = 'You do not have permission to delete this exam.';
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $check = $conn->query("SELECT id FROM exams WHERE id=$id AND created_by=$teacherId");
            if ($check->num_rows > 0) {
                $conn->query("UPDATE exams SET is_active = 1 - is_active WHERE id=$id");
                $success = 'Exam status updated.';
            }
        }
    }
}

$assigned_subjects = $conn->query("SELECT s.id, s.subject_name FROM teacher_assignments ta JOIN subjects s ON ta.subject_id = s.id WHERE ta.teacher_id = $teacherId GROUP BY s.id ORDER BY s.subject_name")->fetch_all(MYSQLI_ASSOC);

$subject_filter = (int)($_GET['subject_id'] ?? 0);
$status_filter = sanitize($_GET['status'] ?? '');

$where = "e.created_by = $teacherId";
if ($subject_filter) $where .= " AND e.subject_id = $subject_filter";
if ($status_filter === 'active') $where .= " AND e.is_active = 1 AND e.end_time >= NOW()";
if ($status_filter === 'upcoming') $where .= " AND e.is_active = 1 AND e.start_time > NOW()";
if ($status_filter === 'ended') $where .= " AND (e.end_time < NOW() OR e.is_active = 0)";

$exams = $conn->query("SELECT e.*, s.subject_name, s.subject_code, CONCAT(c.class_name, ' ', c.section) as class_full,
    (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) as q_count,
    (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id AND status = 'completed') as attempt_count,
    (SELECT ROUND(AVG(percentage), 1) FROM exam_attempts WHERE exam_id = e.id AND status = 'completed') as avg_score,
    (SELECT COUNT(*) FROM exam_student_assignments WHERE exam_id = e.id) as assigned_count
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    JOIN classes c ON e.class_id = c.id
    WHERE $where
    ORDER BY e.created_at DESC
    LIMIT 100")->fetch_all(MYSQLI_ASSOC);
?>

<?php if ($success): ?>
<div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm flex items-center gap-2">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    <?= $success ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm flex items-center gap-2">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    <?= $error ?>
</div>
<?php endif; ?>

<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-gray-800">My Exams</h2>
        <span class="bg-gray-100 text-gray-600 text-xs font-medium px-2.5 py-1 rounded-full"><?= count($exams) ?> found</span>
    </div>
    <a href="create_exam.php" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        Create Exam
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6 p-4">
    <form method="GET" class="flex flex-col sm:flex-row gap-3">
        <select name="subject_id" class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <option value="">All My Subjects</option>
            <?php foreach ($assigned_subjects as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $subject_filter == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['subject_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <option value="">All Status</option>
            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="upcoming" <?= $status_filter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
            <option value="ended" <?= $status_filter === 'ended' ? 'selected' : '' ?>>Ended</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">Filter</button>
    </form>
</div>

<div class="space-y-4">
    <?php if (empty($exams)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">No exams found</div>
    <?php else: foreach ($exams as $e): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2 flex-wrap">
                        <h3 class="font-semibold text-gray-800 text-lg"><?= htmlspecialchars($e['exam_name']) ?></h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $e['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                            <?= $e['is_active'] ? 'Active' : 'Disabled' ?>
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                            <?= ucfirst($e['exam_type']) ?>
                        </span>
                        <?php if ($e['assign_mode'] === 'selected'): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                            <?= $e['assigned_count'] ?> Assigned
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-teal-100 text-teal-700">Class-wide</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap gap-4 text-sm text-gray-500">
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"></path></svg>
                            <?= htmlspecialchars($e['subject_name']) ?>
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            <?= htmlspecialchars($e['class_full']) ?>
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <?= $e['duration_minutes'] ?> min &middot; <?= $e['total_marks'] ?> marks
                        </span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-4 items-center">
                    <div class="text-center px-4 py-2 bg-gray-50 rounded-lg">
                        <p class="text-xl font-bold text-gray-800"><?= $e['q_count'] ?></p>
                        <p class="text-xs text-gray-500">Questions</p>
                    </div>
                    <div class="text-center px-4 py-2 bg-gray-50 rounded-lg">
                        <p class="text-xl font-bold text-gray-800"><?= $e['attempt_count'] ?></p>
                        <p class="text-xs text-gray-500">Attempts</p>
                    </div>
                    <div class="text-center px-4 py-2 bg-gray-50 rounded-lg">
                        <p class="text-xl font-bold <?= ($e['avg_score'] ?? 0) >= 80 ? 'text-green-600' : (($e['avg_score'] ?? 0) >= 50 ? 'text-amber-600' : 'text-red-500') ?>"><?= $e['avg_score'] ?? '-' ?>%</p>
                        <p class="text-xs text-gray-500">Avg Score</p>
                    </div>
                </div>
            </div>
            <div class="mt-3 text-xs text-gray-400">
                <?= $e['start_time'] ? date('d M Y, h:i A', strtotime($e['start_time'])) : 'No start time' ?> &mdash;
                <?= $e['end_time'] ? date('d M Y, h:i A', strtotime($e['end_time'])) : 'No end time' ?>
                &middot; Passing: <?= $e['passing_marks'] ?>/<?= $e['total_marks'] ?>
            </div>
        </div>
        <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <?php if ($e['seb_enabled']): ?>
                <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-700 rounded">SEB</span>
                <?php endif; ?>
                <?php if ($e['webcam_required']): ?>
                <span class="inline-flex items-center gap-1 px-2 py-1 bg-purple-100 text-purple-700 rounded">Webcam</span>
                <?php endif; ?>
                <?php if ($e['shuffle_questions']): ?>
                <span class="inline-flex items-center gap-1 px-2 py-1 bg-amber-100 text-amber-700 rounded">Shuffle</span>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-2">
                <a href="create_exam.php?edit=<?= $e['id'] ?>" class="px-3 py-1.5 text-xs font-medium text-primary-600 bg-primary-50 border border-primary-200 rounded-lg hover:bg-primary-100 transition-colors">Edit</a>
                <a href="assign_students.php?exam_id=<?= $e['id'] ?>" class="px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors">Assign Students</a>
                <a href="questions.php?exam_id=<?= $e['id'] ?>" class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">Questions (<?= $e['q_count'] ?>)</a>
                <a href="results.php?exam_id=<?= $e['id'] ?>" class="px-3 py-1.5 text-xs font-medium text-green-600 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors">Results</a>
                <a href="<?= $baseUrl ?>/api/export_exam_pdf.php?exam_id=<?= $e['id'] ?>" target="_blank" class="px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors">Export PDF</a>
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                    <button type="submit" class="px-3 py-1.5 text-xs font-medium <?= $e['is_active'] ? 'text-amber-600 bg-amber-50 border border-amber-200 hover:bg-amber-100' : 'text-green-600 bg-green-50 border border-green-200 hover:bg-green-100' ?> rounded-lg transition-colors">
                        <?= $e['is_active'] ? 'Disable' : 'Enable' ?>
                    </button>
                </form>
                <form method="POST" class="inline" onsubmit="return confirmDelete('Delete this exam and all its questions/attempts?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                    <button type="submit" class="px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors">Delete</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
