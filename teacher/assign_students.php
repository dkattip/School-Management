<?php
require_once __DIR__ . '/../config/database.php';
requireRole('teacher');
$teacherId = $_SESSION['user_id'];
$success = $error = '';

$examId = (int)($_GET['exam_id'] ?? 0);
if (!$examId) { header("Location: exams.php"); exit(); }

$exam = $conn->query("SELECT e.*, CONCAT(c.class_name, ' ', c.section) as class_full, s.subject_name
    FROM exams e JOIN classes c ON e.class_id = c.id JOIN subjects s ON e.subject_id = s.id
    WHERE e.id = $examId AND e.created_by = $teacherId")->fetch_assoc();
if (!$exam) { header("Location: exams.php"); exit(); }

$students = $conn->query("SELECT u.id, u.full_name, u.email, sc.roll_number, sc.admission_number
    FROM student_classes sc
    JOIN users u ON sc.student_id = u.id
    WHERE sc.class_id = {$exam['class_id']} AND u.role = 'student' AND u.is_active = 1
    ORDER BY sc.roll_number, u.full_name")->fetch_all(MYSQLI_ASSOC);

$assignedRows = $conn->query("SELECT student_id FROM exam_student_assignments WHERE exam_id = $examId")->fetch_all(MYSQLI_ASSOC);
$assignedIds = array_column($assignedRows, 'student_id');
$assignedIds = array_map('intval', $assignedIds);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedStudents = $_POST['students'] ?? [];
    $selectedStudents = array_map('intval', $selectedStudents);

    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM exam_student_assignments WHERE exam_id = $examId");
        if (!empty($selectedStudents)) {
            $stmt = $conn->prepare("INSERT INTO exam_student_assignments (exam_id, student_id) VALUES (?, ?)");
            foreach ($selectedStudents as $sid) {
                if ($sid > 0) {
                    $stmt->bind_param("ii", $examId, $sid);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }
        $conn->commit();
        $success = count($selectedStudents) . ' student(s) assigned to this exam.';
        $assignedIds = $selectedStudents;
    } catch (Exception $e) {
        $conn->rollback();
        $error = 'Failed to save assignments.';
    }
}

$pageTitle = 'Assign Students - ' . htmlspecialchars($exam['exam_name']);
include __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center gap-3 mb-6">
    <a href="exams.php" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
    </a>
    <div>
        <h2 class="text-lg font-semibold text-gray-800">Assign Students</h2>
        <p class="text-sm text-gray-500"><?= htmlspecialchars($exam['exam_name']) ?> &middot; <?= htmlspecialchars($exam['subject_name']) ?> &middot; <?= htmlspecialchars($exam['class_full']) ?></p>
    </div>
</div>

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

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h3 class="font-semibold text-gray-800">Students in <?= htmlspecialchars($exam['class_full']) ?></h3>
            <p class="text-xs text-gray-400 mt-0.5"><?= count($students) ?> student(s) enrolled</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" onclick="selectAll()" class="px-3 py-1.5 text-xs font-medium text-primary-600 bg-primary-50 border border-primary-200 rounded-lg hover:bg-primary-100 transition-colors">Select All</button>
            <button type="button" onclick="selectNone()" class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 border border-gray-200 rounded-lg hover:bg-gray-200 transition-colors">Deselect All</button>
        </div>
    </div>

    <?php if (empty($students)): ?>
    <div class="p-12 text-center text-gray-400 text-sm">No students enrolled in this class yet.</div>
    <?php else: ?>
    <form method="POST">
        <div class="divide-y divide-gray-50">
            <?php foreach ($students as $s): ?>
            <label class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50/50 cursor-pointer transition-colors">
                <input type="checkbox" name="students[]" value="<?= $s['id'] ?>" <?= in_array($s['id'], $assignedIds) ? 'checked' : '' ?>
                    class="student-cb rounded text-primary-600 focus:ring-primary-500 w-4 h-4">
                <div class="w-9 h-9 bg-primary-100 text-primary-600 rounded-lg flex items-center justify-center text-xs font-bold shrink-0">
                    <?= strtoupper(substr($s['full_name'], 0, 2)) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($s['full_name']) ?></p>
                    <p class="text-xs text-gray-400"><?= htmlspecialchars($s['email']) ?></p>
                </div>
                <div class="text-right text-xs text-gray-400 shrink-0">
                    <p>Roll: <?= htmlspecialchars($s['roll_number'] ?? '-') ?></p>
                    <p>Adm: <?= htmlspecialchars($s['admission_number'] ?? '-') ?></p>
                </div>
                <div class="assigned-badge hidden shrink-0">
                    <span class="inline-flex items-center px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">Assigned</span>
                </div>
            </label>
            <?php endforeach; ?>
        </div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <p class="text-sm text-gray-500"><span id="selectedCount"><?= count($assignedIds) ?></span> of <?= count($students) ?> selected</p>
            <div class="flex items-center gap-3">
                <a href="exams.php" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">Cancel</a>
                <button type="submit" class="px-5 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors shadow-sm">Save Assignments</button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
function selectAll() {
    document.querySelectorAll('.student-cb').forEach(cb => { cb.checked = true; });
    updateCount();
}
function selectNone() {
    document.querySelectorAll('.student-cb').forEach(cb => { cb.checked = false; });
    updateCount();
}
function updateCount() {
    document.getElementById('selectedCount').textContent = document.querySelectorAll('.student-cb:checked').length;
}
document.querySelectorAll('.student-cb').forEach(cb => cb.addEventListener('change', updateCount));
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
