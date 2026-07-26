<?php
require_once __DIR__ . '/../config/database.php';
requireRole('admin');
$pageTitle = 'Teacher Assignments';
include __DIR__ . '/../includes/header.php';

$success = $error = '';

$teachers = $conn->query("SELECT id, full_name FROM users WHERE role='teacher' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$subjects = $conn->query("SELECT s.id, s.subject_name, s.subject_code, s.board, CONCAT(c.class_name,' ',c.section) as class_full FROM subjects s JOIN classes c ON s.class_id=c.id ORDER BY s.subject_name")->fetch_all(MYSQLI_ASSOC);
$classes  = $conn->query("SELECT id, class_name, section, board FROM classes ORDER BY class_name, section")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $class_id   = (int)($_POST['class_id'] ?? 0);

        if (!$teacher_id || !$subject_id || !$class_id) {
            $error = 'Please select teacher, subject, and class.';
        } else {
            $check = $conn->query("SELECT id FROM teacher_assignments WHERE teacher_id=$teacher_id AND subject_id=$subject_id AND class_id=$class_id");
            if ($check->num_rows > 0) {
                $error = 'This assignment already exists.';
            } else {
                $conn->query("INSERT INTO teacher_assignments (teacher_id, subject_id, class_id) VALUES ($teacher_id,$subject_id,$class_id)");
                $success = 'Assignment created.';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $conn->query("DELETE FROM teacher_assignments WHERE id=$id");
            $success = 'Assignment removed.';
        }
    } elseif ($action === 'bulk_assign') {
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        $class_id   = (int)($_POST['class_id'] ?? 0);
        $subject_ids = $_POST['subject_ids'] ?? [];

        if (!$teacher_id || !$class_id || empty($subject_ids)) {
            $error = 'Please select teacher, class and at least one subject.';
        } else {
            $added = 0;
            foreach ($subject_ids as $sid) {
                $sid = (int)$sid;
                $check = $conn->query("SELECT id FROM teacher_assignments WHERE teacher_id=$teacher_id AND subject_id=$sid AND class_id=$class_id");
                if ($check->num_rows === 0) {
                    $conn->query("INSERT INTO teacher_assignments (teacher_id, subject_id, class_id) VALUES ($teacher_id,$sid,$class_id)");
                    $added++;
                }
            }
            $success = "$added assignment(s) created.";
        }
    }
}

$teacher_filter = (int)($_GET['teacher_id'] ?? 0);
$class_filter   = (int)($_GET['class_id'] ?? 0);

$where = '1=1';
if ($teacher_filter) $where .= " AND ta.teacher_id=$teacher_filter";
if ($class_filter) $where .= " AND ta.class_id=$class_filter";

$assignments = $conn->query("SELECT ta.*, u.full_name as teacher_name,
    s.subject_name, s.subject_code, s.board,
    CONCAT(c.class_name,' ',c.section) as class_full
    FROM teacher_assignments ta
    JOIN users u ON ta.teacher_id=u.id
    JOIN subjects s ON ta.subject_id=s.id
    JOIN classes c ON ta.class_id=c.id
    WHERE $where
    ORDER BY u.full_name, c.class_name, s.subject_name
    LIMIT 200")->fetch_all(MYSQLI_ASSOC);

$grouped_by_teacher = [];
foreach ($assignments as $a) {
    $grouped_by_teacher[$a['teacher_name']][$a['id']] = $a;
}
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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-semibold text-gray-800">Current Assignments</h2>
                    <span class="bg-gray-100 text-gray-600 text-xs font-medium px-2.5 py-1 rounded-full"><?= count($assignments) ?></span>
                </div>
            </div>
            <div class="px-6 py-3 border-b border-gray-100">
                <form method="GET" class="flex gap-3">
                    <select name="teacher_id" class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="">All Teachers</option>
                        <?php foreach ($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $teacher_filter == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="class_id" class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $class_filter == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['class_name'].' '.$c['section']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">Filter</button>
                </form>
            </div>

            <?php if (empty($grouped_by_teacher)): ?>
            <div class="p-12 text-center text-gray-400">No assignments found</div>
            <?php else: foreach ($grouped_by_teacher as $teacher_name => $ta_assignments): ?>
            <div class="border-b border-gray-100 last:border-0">
                <div class="px-6 py-3 bg-gray-50 flex items-center gap-3">
                    <div class="w-8 h-8 bg-primary-100 text-primary-600 rounded-full flex items-center justify-center font-bold text-sm">
                        <?= strtoupper(substr($teacher_name, 0, 1)) ?>
                    </div>
                    <span class="font-medium text-gray-800"><?= htmlspecialchars($teacher_name) ?></span>
                    <span class="text-xs text-gray-400"><?= count($ta_assignments) ?> assignment<?= count($ta_assignments) != 1 ? 's' : '' ?></span>
                </div>
                <div class="px-6 py-2 space-y-1">
                    <?php foreach ($ta_assignments as $a): ?>
                    <div class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-gray-50 group">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $a['board'] === 'CBSE' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700' ?>"><?= $a['board'] ?></span>
                            <span class="text-sm text-gray-800"><?= htmlspecialchars($a['subject_name']) ?></span>
                            <span class="text-xs text-gray-400">(<?= htmlspecialchars($a['subject_code'] ?: '-') ?>)</span>
                            <svg class="w-3 h-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                            <span class="text-sm text-gray-600"><?= htmlspecialchars($a['class_full']) ?></span>
                        </div>
                        <form method="POST" class="opacity-0 group-hover:opacity-100 transition-opacity" onsubmit="return confirmDelete('Remove this assignment?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button type="submit" class="p-1 text-gray-400 hover:text-red-500 rounded transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Add Assignment</h3>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="add">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teacher *</label>
                    <select name="teacher_id" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="">-- Select --</option>
                        <?php foreach ($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Class *</label>
                    <select name="class_id" id="add_class_id" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="">-- Select --</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name'].' '.$c['section'].' ('.$c['board'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                    <select name="subject_id" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="">-- Select --</option>
                        <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['subject_name'].' ('.$s['class_full'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="w-full px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">Add Assignment</button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Bulk Assign</h3>
                <p class="text-xs text-gray-400 mt-1">Assign multiple subjects at once</p>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="bulk_assign">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teacher *</label>
                    <select name="teacher_id" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="">-- Select --</option>
                        <?php foreach ($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Class *</label>
                    <select name="class_id" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="">-- Select --</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name'].' '.$c['section'].' ('.$c['board'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subjects *</label>
                    <div class="space-y-2 max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-3">
                        <?php foreach ($subjects as $s): ?>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="subject_ids[]" value="<?= $s['id'] ?>" class="w-4 h-4 text-primary-600 rounded">
                            <span class="text-gray-700"><?= htmlspecialchars($s['subject_name']) ?></span>
                            <span class="text-xs text-gray-400">(<?= htmlspecialchars($s['class_full']) ?>)</span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="w-full px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors">Bulk Assign</button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
