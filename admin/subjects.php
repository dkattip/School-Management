<?php
require_once __DIR__ . '/../config/database.php';
requireRole('admin');
$pageTitle = 'Manage Subjects';
include __DIR__ . '/../includes/header.php';

$success = $error = '';

$classes  = $conn->query("SELECT * FROM classes ORDER BY class_name, section")->fetch_all(MYSQLI_ASSOC);
$teachers = $conn->query("SELECT id, full_name FROM users WHERE role='teacher' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $subject_name = sanitize($_POST['subject_name'] ?? '');
        $subject_code = sanitize($_POST['subject_code'] ?? '');
        $board        = sanitize($_POST['board'] ?? 'BOTH');
        $class_id     = (int)($_POST['class_id'] ?? 0);
        $teacher_id   = (int)($_POST['teacher_id'] ?? 0);

        if (!$subject_name || !$class_id) {
            $error = 'Subject name and class are required.';
        } else {
            $tid = $teacher_id ?: 'NULL';
            $conn->query("INSERT INTO subjects (subject_name, subject_code, board, class_id, teacher_id) VALUES ('$subject_name','$subject_code','$board',$class_id,$tid)");
            $success = 'Subject added successfully.';
        }
    } elseif ($action === 'edit') {
        $id           = (int)($_POST['id'] ?? 0);
        $subject_name = sanitize($_POST['subject_name'] ?? '');
        $subject_code = sanitize($_POST['subject_code'] ?? '');
        $board        = sanitize($_POST['board'] ?? 'BOTH');
        $class_id     = (int)($_POST['class_id'] ?? 0);
        $teacher_id   = (int)($_POST['teacher_id'] ?? 0);

        if ($id && $subject_name) {
            $tid = $teacher_id ?: 'NULL';
            $conn->query("UPDATE subjects SET subject_name='$subject_name',subject_code='$subject_code',board='$board',class_id=$class_id,teacher_id=$tid WHERE id=$id");
            $success = 'Subject updated.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $conn->query("DELETE FROM subjects WHERE id=$id");
            $success = 'Subject deleted.';
        }
    }
}

$search      = sanitize($_GET['search'] ?? '');
$board_filter = sanitize($_GET['board'] ?? '');
$class_filter = (int)($_GET['class_id'] ?? 0);

$where = '1=1';
if ($search) $where .= " AND (s.subject_name LIKE '%$search%' OR s.subject_code LIKE '%$search%')";
if ($board_filter) $where .= " AND s.board='$board_filter'";
if ($class_filter) $where .= " AND s.class_id=$class_filter";

$subjects = $conn->query("SELECT s.*, CONCAT(c.class_name,' ',c.section) as class_full, u.full_name as teacher_name,
    (SELECT COUNT(*) FROM teacher_assignments WHERE subject_id=s.id) as assignment_count
    FROM subjects s
    LEFT JOIN classes c ON s.class_id=c.id
    LEFT JOIN users u ON s.teacher_id=u.id
    WHERE $where
    ORDER BY s.board, c.class_name, s.subject_name
    LIMIT 200")->fetch_all(MYSQLI_ASSOC);

$grouped = [];
foreach ($subjects as $s) {
    $grouped[$s['board']][] = $s;
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

<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-gray-800">All Subjects</h2>
        <span class="bg-gray-100 text-gray-600 text-xs font-medium px-2.5 py-1 rounded-full"><?= count($subjects) ?> found</span>
    </div>
    <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
        Add Subject
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6 p-4">
    <form method="GET" class="flex flex-col sm:flex-row gap-3">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search subjects..."
            class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
        <select name="class_id" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <option value="">All Classes</option>
            <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $class_filter == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['class_name'].' '.$c['section'].' ('.$c['board'].')') ?></option>
            <?php endforeach; ?>
        </select>
        <select name="board" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <option value="">All Boards</option>
            <?= renderBoardOptions($board_filter) ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">Filter</button>
    </form>
</div>

<?php if (empty($grouped)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">No subjects found</div>
<?php else: ?>
<?php foreach ($grouped as $board_name => $board_subjects): ?>
<div class="mb-6">
    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
        <span class="w-2 h-2 rounded-full <?= $board_name === 'CBSE' ? 'bg-blue-500' : ($board_name === 'STATE' ? 'bg-orange-500' : 'bg-purple-500') ?>"></span>
        <?= $board_name === 'BOTH' ? 'CBSE & State' : $board_name ?> Board
        <span class="text-xs font-normal normal-case tracking-normal text-gray-400">(<?= count($board_subjects) ?> subjects)</span>
    </h3>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 bg-gray-50">
                    <th class="px-6 py-3 font-medium">#</th>
                    <th class="px-6 py-3 font-medium">Subject</th>
                    <th class="px-6 py-3 font-medium">Code</th>
                    <th class="px-6 py-3 font-medium">Class</th>
                    <th class="px-6 py-3 font-medium">Teacher</th>
                    <th class="px-6 py-3 font-medium">Assignments</th>
                    <th class="px-6 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php $sn = 1; foreach ($board_subjects as $s): ?>
                <tr class="hover:bg-gray-50/50">
                    <td class="px-6 py-3 text-gray-400"><?= $sn++ ?></td>
                    <td class="px-6 py-3">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($s['subject_name']) ?></p>
                    </td>
                    <td class="px-6 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-medium bg-gray-100 text-gray-600"><?= htmlspecialchars($s['subject_code'] ?: '-') ?></span>
                    </td>
                    <td class="px-6 py-3 text-gray-600"><?= htmlspecialchars($s['class_full'] ?: '-') ?></td>
                    <td class="px-6 py-3 text-gray-600"><?= htmlspecialchars($s['teacher_name'] ?: 'Unassigned') ?></td>
                    <td class="px-6 py-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600"><?= $s['assignment_count'] ?> assignment<?= $s['assignment_count'] != 1 ? 's' : '' ?></span>
                    </td>
                    <td class="px-6 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button onclick='editSubject(<?= json_encode($s) ?>)' class="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors" title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </button>
                            <form method="POST" class="inline" onsubmit="return confirmDelete('Delete this subject?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; endif; ?>

<div id="addModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Add New Subject</h3>
            <button onclick="this.closest('.fixed').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject Name *</label>
                    <input type="text" name="subject_name" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject Code</label>
                    <input type="text" name="subject_code" placeholder="e.g. MATH10" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Board</label>
                    <select name="board" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <?= renderBoardOptions() ?>
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
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teacher</label>
                    <select name="teacher_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="0">-- None --</option>
                        <?php foreach ($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="this.closest('.fixed').classList.add('hidden')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">Add Subject</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Edit Subject</h3>
            <button onclick="this.closest('.fixed').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject Name</label>
                    <input type="text" name="subject_name" id="edit_subject_name" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject Code</label>
                    <input type="text" name="subject_code" id="edit_subject_code" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Board</label>
                    <select name="board" id="edit_board" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <?= renderBoardOptions() ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                    <select name="class_id" id="edit_class_id" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name'].' '.$c['section'].' ('.$c['board'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teacher</label>
                    <select name="teacher_id" id="edit_teacher_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="0">-- None --</option>
                        <?php foreach ($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="this.closest('.fixed').classList.add('hidden')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editSubject(s) {
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('edit_id').value = s.id;
    document.getElementById('edit_subject_name').value = s.subject_name;
    document.getElementById('edit_subject_code').value = s.subject_code || '';
    document.getElementById('edit_board').value = s.board;
    document.getElementById('edit_class_id').value = s.class_id;
    document.getElementById('edit_teacher_id').value = s.teacher_id || 0;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
