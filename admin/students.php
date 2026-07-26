<?php
require_once __DIR__ . '/../config/database.php';
requireRole('admin');
$pageTitle = 'Manage Students';
include __DIR__ . '/../includes/header.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username  = sanitize($_POST['username'] ?? '');
        $email     = sanitize($_POST['email'] ?? '');
        $full_name = sanitize($_POST['full_name'] ?? '');
        $phone     = sanitize($_POST['phone'] ?? '');
        $password  = password_hash($_POST['password'] ?? 'password123', PASSWORD_DEFAULT);
        $class_id  = (int)($_POST['class_id'] ?? 0);
        $roll      = sanitize($_POST['roll_number'] ?? '');
        $adm_no   = sanitize($_POST['admission_number'] ?? '');

        if (!$username || !$email || !$full_name) {
            $error = 'Username, email and name are required.';
        } else {
            $check = $conn->query("SELECT id FROM users WHERE username='$username' OR email='$email'");
            if ($check->num_rows > 0) {
                $error = 'Username or email already exists.';
            } else {
                $conn->query("INSERT INTO users (username,email,password,full_name,role,phone) VALUES ('$username','$email','$password','$full_name','student','$phone')");
                $user_id = $conn->insert_id;
                if ($class_id > 0) {
                    $conn->query("INSERT INTO student_classes (student_id,class_id,roll_number,admission_number) VALUES ($user_id,$class_id,'$roll','$adm_no')");
                }
                $success = 'Student added successfully.';
            }
        }
    } elseif ($action === 'edit') {
        $id        = (int)($_POST['id'] ?? 0);
        $full_name = sanitize($_POST['full_name'] ?? '');
        $email     = sanitize($_POST['email'] ?? '');
        $phone     = sanitize($_POST['phone'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $class_id  = (int)($_POST['class_id'] ?? 0);
        $roll      = sanitize($_POST['roll_number'] ?? '');
        $adm_no   = sanitize($_POST['admission_number'] ?? '');

        if ($id && $full_name && $email) {
            $conn->query("UPDATE users SET full_name='$full_name',email='$email',phone='$phone',is_active=$is_active WHERE id=$id AND role='student'");
            $conn->query("DELETE FROM student_classes WHERE student_id=$id");
            if ($class_id > 0) {
                $conn->query("INSERT INTO student_classes (student_id,class_id,roll_number,admission_number) VALUES ($id,$class_id,'$roll','$adm_no')");
            }
            $success = 'Student updated successfully.';
        } else {
            $error = 'Invalid data.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $conn->query("DELETE FROM users WHERE id=$id AND role='student'");
            $success = 'Student deleted.';
        }
    } elseif ($action === 'reset_password') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $hash = password_hash('password123', PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password='$hash' WHERE id=$id");
            $success = 'Password reset to default.';
        }
    }
}

$filter_class = (int)($_GET['class_id'] ?? 0);
$search = sanitize($_GET['search'] ?? '');

$classes = $conn->query("SELECT * FROM classes ORDER BY class_name, section")->fetch_all(MYSQLI_ASSOC);

$where = "u.role='student'";
if ($filter_class) $where .= " AND sc.class_id=$filter_class";
if ($search) $where .= " AND (u.full_name LIKE '%$search%' OR u.username LIKE '%$search%' OR u.email LIKE '%$search%')";

$students = $conn->query("SELECT u.*, sc.class_id, sc.roll_number, sc.admission_number,
    CONCAT(c.class_name,' ',c.section) as class_name
    FROM users u
    LEFT JOIN student_classes sc ON u.id=sc.student_id
    LEFT JOIN classes c ON sc.class_id=c.id
    WHERE $where
    ORDER BY u.full_name ASC
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
            <h2 class="text-lg font-semibold text-gray-800">All Students</h2>
            <span class="bg-gray-100 text-gray-600 text-xs font-medium px-2.5 py-1 rounded-full"><?= count($students) ?> found</span>
        </div>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Add Student
        </button>
    </div>

    <div class="px-6 py-3 border-b border-gray-100 flex flex-col sm:flex-row gap-3">
        <form method="GET" class="flex-1 flex gap-3">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, username or email..."
                class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            <select name="class_id" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="">All Classes</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filter_class == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['class_name'].' '.$c['section'].' ('.$c['board'].')') ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">Filter</button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 bg-gray-50">
                    <th class="px-6 py-3 font-medium">#</th>
                    <th class="px-6 py-3 font-medium">Student</th>
                    <th class="px-6 py-3 font-medium">Contact</th>
                    <th class="px-6 py-3 font-medium">Class</th>
                    <th class="px-6 py-3 font-medium">Roll No.</th>
                    <th class="px-6 py-3 font-medium">Status</th>
                    <th class="px-6 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($students)): ?>
                <tr><td colspan="7" class="px-6 py-12 text-center text-gray-400">No students found</td></tr>
                <?php else: $sn = 1; foreach ($students as $s): ?>
                <tr class="hover:bg-gray-50/50">
                    <td class="px-6 py-3 text-gray-400"><?= $sn++ ?></td>
                    <td class="px-6 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 bg-primary-100 text-primary-600 rounded-full flex items-center justify-center font-bold text-sm">
                                <?= strtoupper(substr($s['full_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800"><?= htmlspecialchars($s['full_name']) ?></p>
                                <p class="text-xs text-gray-400">@<?= htmlspecialchars($s['username']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-3">
                        <p class="text-gray-600"><?= htmlspecialchars($s['email']) ?></p>
                        <p class="text-xs text-gray-400"><?= htmlspecialchars($s['phone'] ?: '-') ?></p>
                    </td>
                    <td class="px-6 py-3">
                        <?php if ($s['class_name']): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700"><?= htmlspecialchars($s['class_name']) ?></span>
                        <?php else: ?>
                        <span class="text-gray-400 text-xs">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-gray-600"><?= htmlspecialchars($s['roll_number'] ?: '-') ?></td>
                    <td class="px-6 py-3">
                        <?php if ($s['is_active']): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                        <?php else: ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-600">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button onclick='editStudent(<?= json_encode($s) ?>)' class="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors" title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </button>
                            <form method="POST" class="inline" onsubmit="return confirmDelete()">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Reset Password">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                                </button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirmDelete('Delete this student?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="addModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Add New Student</h3>
            <button onclick="this.closest('.fixed').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <input type="text" name="full_name" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                    <input type="text" name="username" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="text" name="password" value="password123" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                    <select name="class_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="0">-- Select --</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name'].' '.$c['section'].' ('.$c['board'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Roll Number</label>
                    <input type="text" name="roll_number" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Admission No.</label>
                    <input type="text" name="admission_number" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="this.closest('.fixed').classList.add('hidden')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">Add Student</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Edit Student</h3>
            <button onclick="this.closest('.fixed').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" name="full_name" id="edit_full_name" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" id="edit_email" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" id="edit_phone" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
                    <select name="class_id" id="edit_class_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="0">-- Select --</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name'].' '.$c['section'].' ('.$c['board'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Roll Number</label>
                    <input type="text" name="roll_number" id="edit_roll_number" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Admission No.</label>
                    <input type="text" name="admission_number" id="edit_admission_number" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="is_active" id="edit_is_active" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
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
function editStudent(s) {
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('edit_id').value = s.id;
    document.getElementById('edit_full_name').value = s.full_name;
    document.getElementById('edit_email').value = s.email;
    document.getElementById('edit_phone').value = s.phone || '';
    document.getElementById('edit_class_id').value = s.class_id || 0;
    document.getElementById('edit_roll_number').value = s.roll_number || '';
    document.getElementById('edit_admission_number').value = s.admission_number || '';
    document.getElementById('edit_is_active').value = s.is_active;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
