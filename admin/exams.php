<?php
require_once __DIR__ . '/../config/database.php';
requireRole('admin');
$pageTitle = 'Manage Exams';
include __DIR__ . '/../includes/header.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $conn->query("UPDATE exams SET is_active = 1 - is_active WHERE id=$id");
            $success = 'Exam status toggled.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $conn->query("DELETE FROM exams WHERE id=$id");
            $success = 'Exam deleted.';
        }
    } elseif ($action === 'update_settings') {
        $id              = (int)($_POST['id'] ?? 0);
        $seb_enabled     = isset($_POST['seb_enabled']) ? 1 : 0;
        $webcam_required = isset($_POST['webcam_required']) ? 1 : 0;
        $shuffle         = isset($_POST['shuffle_questions']) ? 1 : 0;
        $show_results    = isset($_POST['show_results']) ? 1 : 0;
        $max_attempts    = max(1, (int)($_POST['max_attempts'] ?? 1));

        if ($id) {
            $conn->query("UPDATE exams SET seb_enabled=$seb_enabled,webcam_required=$webcam_required,shuffle_questions=$shuffle,show_results=$show_results,max_attempts=$max_attempts WHERE id=$id");
            $success = 'Exam settings updated.';
        }
    }
}

$search       = sanitize($_GET['search'] ?? '');
$status_filter = sanitize($_GET['status'] ?? '');
$type_filter   = sanitize($_GET['type'] ?? '');

$where = '1=1';
if ($search) $where .= " AND (e.exam_name LIKE '%$search%' OR s.subject_name LIKE '%$search%')";
if ($status_filter === 'active') $where .= " AND e.is_active=1 AND e.end_time >= NOW()";
if ($status_filter === 'upcoming') $where .= " AND e.is_active=1 AND e.start_time > NOW()";
if ($status_filter === 'ended') $where .= " AND (e.end_time < NOW() OR e.is_active=0)";
if ($type_filter) $where .= " AND e.exam_type='$type_filter'";

$exams = $conn->query("SELECT e.*, s.subject_name, s.subject_code, CONCAT(c.class_name,' ',c.section) as class_full,
    u.full_name as teacher_name,
    (SELECT COUNT(*) FROM questions WHERE exam_id=e.id) as q_count,
    (SELECT COUNT(*) FROM exam_attempts WHERE exam_id=e.id AND status='completed') as attempt_count,
    (SELECT ROUND(AVG(percentage),1) FROM exam_attempts WHERE exam_id=e.id AND status='completed') as avg_score
    FROM exams e
    JOIN subjects s ON e.subject_id=s.id
    JOIN classes c ON e.class_id=c.id
    JOIN users u ON e.created_by=u.id
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
        <h2 class="text-lg font-semibold text-gray-800">All Exams</h2>
        <span class="bg-gray-100 text-gray-600 text-xs font-medium px-2.5 py-1 rounded-full"><?= count($exams) ?> found</span>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6 p-4">
    <form method="GET" class="flex flex-col sm:flex-row gap-3">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search exams..."
            class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
        <select name="status" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <option value="">All Status</option>
            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="upcoming" <?= $status_filter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
            <option value="ended" <?= $status_filter === 'ended' ? 'selected' : '' ?>>Ended/Disabled</option>
        </select>
        <select name="type" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <option value="">All Types</option>
            <option value="midterm" <?= $type_filter === 'midterm' ? 'selected' : '' ?>>Midterm</option>
            <option value="final" <?= $type_filter === 'final' ? 'selected' : '' ?>>Final</option>
            <option value="quiz" <?= $type_filter === 'quiz' ? 'selected' : '' ?>>Quiz</option>
            <option value="assignment" <?= $type_filter === 'assignment' ? 'selected' : '' ?>>Assignment</option>
            <option value="mock" <?= $type_filter === 'mock' ? 'selected' : '' ?>>Mock</option>
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
                    <div class="flex items-center gap-3 mb-2">
                        <h3 class="font-semibold text-gray-800 text-lg"><?= htmlspecialchars($e['exam_name']) ?></h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $e['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                            <?= $e['is_active'] ? 'Active' : 'Disabled' ?>
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                            <?= ucfirst($e['exam_type']) ?>
                        </span>
                    </div>
                    <?php if ($e['description']): ?>
                    <p class="text-sm text-gray-500 mb-2"><?= htmlspecialchars($e['description']) ?></p>
                    <?php endif; ?>
                    <div class="flex flex-wrap gap-4 text-sm text-gray-500">
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"></path></svg>
                            <?= htmlspecialchars($e['subject_name']) ?> (<?= htmlspecialchars($e['subject_code'] ?: $e['class_full']) ?>)
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            <?= htmlspecialchars($e['class_full']) ?>
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            <?= htmlspecialchars($e['teacher_name']) ?>
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
                        <p class="text-xl font-bold <?= $e['avg_score'] >= 80 ? 'text-green-600' : ($e['avg_score'] >= 50 ? 'text-amber-600' : 'text-red-500') ?>"><?= $e['avg_score'] ?? '-' ?>%</p>
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
                <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded">Attempts: <?= $e['max_attempts'] ?></span>
            </div>
            <div class="flex items-center gap-2">
                <a href="<?= $baseUrl ?>/api/export_exam_pdf.php?exam_id=<?= $e['id'] ?>" target="_blank" class="px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors">Export PDF</a>
                <button onclick='editExamSettings(<?= json_encode($e) ?>)' class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">Settings</button>
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

<div id="settingsModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Exam Settings</h3>
            <button onclick="this.closest('.fixed').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="update_settings">
            <input type="hidden" name="id" id="settings_id">
            <p class="text-sm text-gray-500 mb-2" id="settings_exam_name"></p>
            <div class="grid grid-cols-2 gap-4">
                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="checkbox" name="seb_enabled" value="1" id="settings_seb" class="w-4 h-4 text-primary-600 rounded">
                    <div>
                        <p class="text-sm font-medium text-gray-700">SEB Lockdown</p>
                        <p class="text-xs text-gray-400">Require Safe Exam Browser</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="checkbox" name="webcam_required" value="1" id="settings_webcam" class="w-4 h-4 text-primary-600 rounded">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Webcam Required</p>
                        <p class="text-xs text-gray-400">Monitor via webcam</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="checkbox" name="shuffle_questions" value="1" id="settings_shuffle" class="w-4 h-4 text-primary-600 rounded">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Shuffle Questions</p>
                        <p class="text-xs text-gray-400">Randomize question order</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="checkbox" name="show_results" value="1" id="settings_results" class="w-4 h-4 text-primary-600 rounded">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Show Results</p>
                        <p class="text-xs text-gray-400">Show marks after submit</p>
                    </div>
                </label>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Max Attempts</label>
                <input type="number" name="max_attempts" id="settings_attempts" min="1" max="10" value="1" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="this.closest('.fixed').classList.add('hidden')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<script>
function editExamSettings(e) {
    document.getElementById('settingsModal').classList.remove('hidden');
    document.getElementById('settings_id').value = e.id;
    document.getElementById('settings_exam_name').textContent = e.exam_name;
    document.getElementById('settings_seb').checked = e.seb_enabled == 1;
    document.getElementById('settings_webcam').checked = e.webcam_required == 1;
    document.getElementById('settings_shuffle').checked = e.shuffle_questions == 1;
    document.getElementById('settings_results').checked = e.show_results == 1;
    document.getElementById('settings_attempts').value = e.max_attempts;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
