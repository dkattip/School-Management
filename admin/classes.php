<?php
require_once __DIR__ . '/../config/database.php';
requireRole('admin');
$pageTitle = 'Manage Classes';
include __DIR__ . '/../includes/header.php';

$success = $error = '';

$teachers = $conn->query("SELECT id, full_name FROM users WHERE role='teacher' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $class_name      = sanitize($_POST['class_name'] ?? '');
        $section          = sanitize($_POST['section'] ?? '');
        $board            = sanitize($_POST['board'] ?? 'CBSE');
        $class_teacher_id = (int)($_POST['class_teacher_id'] ?? 0);

        if (!$class_name) {
            $error = 'Class name is required.';
        } else {
            $conn->query("INSERT INTO classes (class_name, section, board, class_teacher_id) VALUES ('$class_name','$section','$board'," . ($class_teacher_id ?: 'NULL') . ")");
            $success = 'Class added successfully.';
        }
    } elseif ($action === 'edit') {
        $id               = (int)($_POST['id'] ?? 0);
        $class_name       = sanitize($_POST['class_name'] ?? '');
        $section          = sanitize($_POST['section'] ?? '');
        $board            = sanitize($_POST['board'] ?? 'CBSE');
        $class_teacher_id = (int)($_POST['class_teacher_id'] ?? 0);

        if ($id && $class_name) {
            $teacher_val = $class_teacher_id ?: 'NULL';
            $conn->query("UPDATE classes SET class_name='$class_name',section='$section',board='$board',class_teacher_id=$teacher_val WHERE id=$id");
            $success = 'Class updated.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $conn->query("DELETE FROM classes WHERE id=$id");
            $success = 'Class deleted.';
        }
    }
}

$search = sanitize($_GET['search'] ?? '');
$board_filter = sanitize($_GET['board'] ?? '');

$where = '1=1';
if ($search) $where .= " AND (c.class_name LIKE '%$search%' OR c.section LIKE '%$search%')";
if ($board_filter) $where .= " AND c.board='$board_filter'";

$classes = $conn->query("SELECT c.*,
    CONCAT(u.full_name) as teacher_name,
    (SELECT COUNT(*) FROM student_classes WHERE class_id=c.id) as student_count,
    (SELECT COUNT(*) FROM subjects WHERE class_id=c.id) as subject_count
    FROM classes c
    LEFT JOIN users u ON c.class_teacher_id=u.id
    WHERE $where
    ORDER BY c.class_name, c.section
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

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <h2 class="text-lg font-semibold text-gray-800">All Classes</h2>
            <span class="bg-gray-100 text-gray-600 text-xs font-medium px-2.5 py-1 rounded-full"><?= count($classes) ?> found</span>
        </div>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Add Class
        </button>
    </div>

    <div class="px-6 py-3 border-b border-gray-100">
        <form method="GET" class="flex gap-3">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search classes..."
                class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            <select name="board" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="">All Boards</option>
                <?= renderBoardOptions($board_filter) ?>
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">Filter</button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-6">
        <?php if (empty($classes)): ?>
        <div class="col-span-full text-center py-12 text-gray-400">No classes found</div>
        <?php else: foreach ($classes as $c): ?>
        <div class="border border-gray-200 rounded-xl p-5 hover:shadow-md transition-shadow relative group">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="font-semibold text-gray-800 text-lg"><?= htmlspecialchars($c['class_name']) ?></h3>
                    <p class="text-sm text-gray-500">Section: <?= htmlspecialchars($c['section'] ?: 'N/A') ?></p>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $c['board'] === 'CBSE' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700' ?>">
                    <?= $c['board'] ?>
                </span>
            </div>
            <div class="space-y-2 text-sm text-gray-600 mb-4">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    <?= htmlspecialchars($c['teacher_name'] ?: 'No teacher assigned') ?>
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    <?= $c['student_count'] ?> student<?= $c['student_count'] != 1 ? 's' : '' ?>
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    <?= $c['subject_count'] ?> subject<?= $c['subject_count'] != 1 ? 's' : '' ?>
                </div>
            </div>
            <div class="flex gap-2">
                <button onclick='editClass(<?= json_encode($c) ?>)' class="flex-1 px-3 py-1.5 text-xs font-medium text-primary-600 bg-primary-50 rounded-lg hover:bg-primary-100 transition-colors text-center">Edit</button>
                <form method="POST" class="flex-1" onsubmit="return confirmDelete('Delete this class and all related data?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <button type="submit" class="w-full px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">Delete</button>
                </form>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<div id="addModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Add New Class</h3>
            <button onclick="this.closest('.fixed').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Class Name *</label>
                    <input type="text" name="class_name" placeholder="e.g. Class 10" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                    <input type="text" name="section" placeholder="e.g. A" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Board</label>
                    <select name="board" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <?= renderBoardOptions() ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Class Teacher</label>
                    <select name="class_teacher_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="0">-- None --</option>
                        <?php foreach ($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="this.closest('.fixed').classList.add('hidden')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">Add Class</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Edit Class</h3>
            <button onclick="this.closest('.fixed').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Class Name</label>
                    <input type="text" name="class_name" id="edit_class_name" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                    <input type="text" name="section" id="edit_section" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Board</label>
                    <select name="board" id="edit_board" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <?= renderBoardOptions() ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Class Teacher</label>
                    <select name="class_teacher_id" id="edit_class_teacher_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
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
function editClass(c) {
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('edit_id').value = c.id;
    document.getElementById('edit_class_name').value = c.class_name;
    document.getElementById('edit_section').value = c.section || '';
    document.getElementById('edit_board').value = c.board;
    document.getElementById('edit_class_teacher_id').value = c.class_teacher_id || 0;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
