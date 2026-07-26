<?php
require_once __DIR__ . '/../config/database.php';
requireRole('teacher');
$teacherId = $_SESSION['user_id'];
$pageTitle = 'My Students';
include __DIR__ . '/../includes/header.php';

$assigned_classes = $conn->query("SELECT DISTINCT ta.class_id, CONCAT(c.class_name, ' ', c.section) as class_full
    FROM teacher_assignments ta
    JOIN classes c ON ta.class_id = c.id
    WHERE ta.teacher_id = $teacherId
    ORDER BY c.class_name, c.section")->fetch_all(MYSQLI_ASSOC);

$assigned_class_ids = array_column($assigned_classes, 'class_id');
$class_ids_sql = !empty($assigned_class_ids) ? implode(',', array_map('intval', $assigned_class_ids)) : '0';

$filter_class = (int)($_GET['class_id'] ?? 0);
$search = sanitize($_GET['search'] ?? '');
$student_history_id = (int)($_GET['history'] ?? 0);

$where = "sc.class_id IN ($class_ids_sql)";
if ($filter_class) $where .= " AND sc.class_id = $filter_class";
if ($search) $where .= " AND (u.full_name LIKE '%$search%' OR u.username LIKE '%$search%' OR u.email LIKE '%$search%' OR sc.roll_number LIKE '%$search%')";

$students = $conn->query("SELECT u.id, u.full_name, u.username, u.email, u.phone, u.is_active,
    sc.class_id, sc.roll_number, sc.admission_number,
    CONCAT(c.class_name, ' ', c.section) as class_name
    FROM users u
    JOIN student_classes sc ON u.id = sc.student_id
    JOIN classes c ON sc.class_id = c.id
    WHERE u.role = 'student' AND $where
    ORDER BY c.class_name, sc.roll_number, u.full_name
    LIMIT 200")->fetch_all(MYSQLI_ASSOC);

$student_history = null;
if ($student_history_id) {
    $student_history = $conn->query("SELECT u.full_name FROM users u WHERE u.id = $student_history_id AND u.role = 'student'")->fetch_assoc();
}

$exam_history = [];
if ($student_history_id) {
    $exam_history = $conn->query("SELECT ea.*, e.exam_name, e.total_marks as exam_total_marks, e.passing_marks,
        s.subject_name, CONCAT(c.class_name, ' ', c.section) as class_full
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        JOIN subjects s ON e.subject_id = s.id
        JOIN classes c ON e.class_id = c.id
        WHERE ea.student_id = $student_history_id AND e.created_by = $teacherId AND ea.status = 'completed'
        ORDER BY ea.created_at DESC
        LIMIT 20")->fetch_all(MYSQLI_ASSOC);
}
?>

<?php if ($student_history): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <a href="students.php" class="text-sm text-primary-600 hover:text-primary-700 mb-1 inline-flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                Back to Students
            </a>
            <h3 class="text-lg font-semibold text-gray-800">Exam History: <?= htmlspecialchars($student_history['full_name']) ?></h3>
        </div>
    </div>
    <?php if (empty($exam_history)): ?>
    <p class="text-gray-400 text-sm text-center py-6">No exam attempts found for your exams.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 bg-gray-50">
                    <th class="px-4 py-3 font-medium">Exam</th>
                    <th class="px-4 py-3 font-medium">Subject</th>
                    <th class="px-4 py-3 font-medium">Score</th>
                    <th class="px-4 py-3 font-medium">Percentage</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($exam_history as $h): ?>
                <tr class="hover:bg-gray-50/50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= htmlspecialchars($h['exam_name']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($h['subject_name']) ?></td>
                    <td class="px-4 py-3">
                        <span class="font-semibold"><?= $h['total_score'] ?>/<?= $h['max_score'] ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="font-semibold <?= $h['percentage'] >= 80 ? 'text-green-600' : ($h['percentage'] >= 50 ? 'text-amber-600' : 'text-red-500') ?>"><?= round($h['percentage'], 1) ?>%</span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $h['percentage'] >= 33 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                            <?= $h['percentage'] >= 33 ? 'Pass' : 'Fail' ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs"><?= date('d M Y', strtotime($h['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!$student_history): ?>
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-gray-800">My Students</h2>
        <span class="bg-gray-100 text-gray-600 text-xs font-medium px-2.5 py-1 rounded-full"><?= count($students) ?> found</span>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6 p-4">
    <form method="GET" class="flex flex-col sm:flex-row gap-3">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, username, email or roll number..."
            class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
        <select name="class_id" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <option value="">All My Classes</option>
            <?php foreach ($assigned_classes as $c): ?>
            <option value="<?= $c['class_id'] ?>" <?= $filter_class == $c['class_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['class_full']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">Filter</button>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
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
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700"><?= htmlspecialchars($s['class_name']) ?></span>
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
                        <a href="?history=<?= $s['id'] ?>" class="px-3 py-1.5 text-xs font-medium text-primary-600 bg-primary-50 border border-primary-200 rounded-lg hover:bg-primary-100 transition-colors inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            History
                        </a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
