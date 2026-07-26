<?php
require_once __DIR__ . '/../config/database.php';
requireRole('teacher');
$teacherId = $_SESSION['user_id'];

$edit_id = (int)($_GET['edit'] ?? 0);
$success = $error = '';

$assigned = $conn->query("SELECT ta.subject_id, ta.class_id,
    s.subject_name, s.subject_code,
    CONCAT(c.class_name, ' ', c.section) as class_full
    FROM teacher_assignments ta
    JOIN subjects s ON ta.subject_id = s.id
    JOIN classes c ON ta.class_id = c.id
    WHERE ta.teacher_id = $teacherId
    ORDER BY s.subject_name")->fetch_all(MYSQLI_ASSOC);

$subjects = [];
$classes = [];
foreach ($assigned as $a) {
    $subjects[$a['subject_id']] = $a['subject_name'] . ' (' . $a['subject_code'] . ')';
    $classes[$a['class_id']] = $a['class_full'];
}
$subjects = array_unique($subjects);
$classes = array_unique($classes);

$exam = null;
$editMode = false;

if ($edit_id) {
    $check = $conn->query("SELECT * FROM exams WHERE id = $edit_id AND created_by = $teacherId");
    if ($check->num_rows > 0) {
        $exam = $check->fetch_assoc();
        $editMode = true;
    } else {
        $error = 'Exam not found or no permission.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_name = sanitize($_POST['exam_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $class_id = (int)($_POST['class_id'] ?? 0);
    $exam_type = sanitize($_POST['exam_type'] ?? 'quiz');
    $total_marks = max(1, (int)($_POST['total_marks'] ?? 100));
    $passing_marks = max(0, (int)($_POST['passing_marks'] ?? 33));
    $duration_minutes = max(1, (int)($_POST['duration_minutes'] ?? 60));
    $start_time = sanitize($_POST['start_time'] ?? '');
    $end_time = sanitize($_POST['end_time'] ?? '');
    $shuffle_questions = isset($_POST['shuffle_questions']) ? 1 : 0;
    $show_results = isset($_POST['show_results']) ? 1 : 0;
    $seb_enabled = isset($_POST['seb_enabled']) ? 1 : 0;
    $webcam_required = isset($_POST['webcam_required']) ? 1 : 0;
    $max_attempts = max(1, (int)($_POST['max_attempts'] ?? 1));
    $assign_mode = sanitize($_POST['assign_mode'] ?? 'class');
    if (!in_array($assign_mode, ['class', 'selected'])) $assign_mode = 'class';

    if (!$exam_name || !$subject_id || !$class_id) {
        $error = 'Exam name, subject, and class are required.';
    } else {
        $valid = false;
        foreach ($assigned as $a) {
            if ($a['subject_id'] == $subject_id && $a['class_id'] == $class_id) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            $error = 'Not assigned to this subject/class.';
        } else {
            if ($editMode) {
                $stmt = $conn->prepare("UPDATE exams SET exam_name=?, description=?, subject_id=?, class_id=?, exam_type=?, assign_mode=?, total_marks=?, passing_marks=?, duration_minutes=?, start_time=?, end_time=?, shuffle_questions=?, show_results=?, seb_enabled=?, webcam_required=?, max_attempts=? WHERE id=? AND created_by=?");
                $stmt->bind_param("ssiiisiiisssiiiiii", $exam_name, $description, $subject_id, $class_id, $exam_type, $assign_mode, $total_marks, $passing_marks, $duration_minutes, $start_time, $end_time, $shuffle_questions, $show_results, $seb_enabled, $webcam_required, $max_attempts, $edit_id, $teacherId);
                $stmt->execute();
                $stmt->close();
                $success = 'Exam updated!';
                $check = $conn->query("SELECT * FROM exams WHERE id = $edit_id");
                $exam = $check->fetch_assoc();
            } else {
                $stmt = $conn->prepare("INSERT INTO exams (exam_name, description, subject_id, class_id, created_by, exam_type, assign_mode, total_marks, passing_marks, duration_minutes, start_time, end_time, shuffle_questions, show_results, seb_enabled, webcam_required, max_attempts) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssiiissiiissiiiii", $exam_name, $description, $subject_id, $class_id, $teacherId, $exam_type, $assign_mode, $total_marks, $passing_marks, $duration_minutes, $start_time, $end_time, $shuffle_questions, $show_results, $seb_enabled, $webcam_required, $max_attempts);
                $stmt->execute();
                $new_id = $conn->insert_id;
                $stmt->close();
                global $baseUrl;
                header("Location: $baseUrl/teacher/questions.php?exam_id=$new_id&created=1");
                exit();
            }
        }
    }
}

$pageTitle = $editMode ? 'Edit Exam' : 'Create Exam';
include __DIR__ . '/../includes/header.php';
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

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800"><?= $editMode ? 'Edit Exam Details' : 'New Exam' ?></h3>
    </div>
    <form method="POST" class="p-6 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Exam Name *</label>
                <input type="text" name="exam_name" required value="<?= htmlspecialchars($exam['exam_name'] ?? $_POST['exam_name'] ?? '') ?>"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                    placeholder="e.g. Mid-Term Mathematics">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Exam Type</label>
                <select name="exam_type" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <?php foreach (['quiz'=>'Quiz','midterm'=>'Mid-Term','final'=>'Final','assignment'=>'Assignment','mock'=>'Mock'] as $val => $label): ?>
                    <option value="<?= $val ?>" <?= ($exam['exam_type'] ?? $_POST['exam_type'] ?? 'quiz') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                <select name="subject_id" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <option value="">-- Select Subject --</option>
                    <?php foreach ($subjects as $sid => $sname): ?>
                    <option value="<?= $sid ?>" <?= ($exam['subject_id'] ?? $_POST['subject_id'] ?? '') == $sid ? 'selected' : '' ?>><?= htmlspecialchars($sname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Class *</label>
                <select name="class_id" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <option value="">-- Select Class --</option>
                    <?php foreach ($classes as $cid => $cname): ?>
                    <option value="<?= $cid ?>" <?= ($exam['class_id'] ?? $_POST['class_id'] ?? '') == $cid ? 'selected' : '' ?>><?= htmlspecialchars($cname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Total Marks</label>
                <input type="number" name="total_marks" min="1" value="<?= $exam['total_marks'] ?? $_POST['total_marks'] ?? 100 ?>"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Passing Marks</label>
                <input type="number" name="passing_marks" min="0" value="<?= $exam['passing_marks'] ?? $_POST['passing_marks'] ?? 33 ?>"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes)</label>
                <input type="number" name="duration_minutes" min="1" value="<?= $exam['duration_minutes'] ?? $_POST['duration_minutes'] ?? 60 ?>"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Max Attempts</label>
                <input type="number" name="max_attempts" min="1" max="10" value="<?= $exam['max_attempts'] ?? $_POST['max_attempts'] ?? 1 ?>"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                <input type="datetime-local" name="start_time" value="<?= isset($exam['start_time']) ? date('Y-m-d\TH:i', strtotime($exam['start_time'])) : ($_POST['start_time'] ?? '') ?>"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                <input type="datetime-local" name="end_time" value="<?= isset($exam['end_time']) ? date('Y-m-d\TH:i', strtotime($exam['end_time'])) : ($_POST['end_time'] ?? '') ?>"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                placeholder="Optional description"><?= htmlspecialchars($exam['description'] ?? $_POST['description'] ?? '') ?></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Assign Mode</label>
            <div class="grid grid-cols-2 gap-3">
                <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 <?= ($exam['assign_mode'] ?? 'class') === 'class' ? 'border-primary-400 bg-primary-50' : 'border-gray-200' ?>">
                    <input type="radio" name="assign_mode" value="class" <?= ($exam['assign_mode'] ?? 'class') === 'class' ? 'checked' : '' ?> class="text-primary-600 focus:ring-primary-500">
                    <div>
                        <span class="text-sm font-medium text-gray-700">Entire Class</span>
                        <p class="text-xs text-gray-400">All students in the class can see this exam</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 <?= ($exam['assign_mode'] ?? 'class') === 'selected' ? 'border-primary-400 bg-primary-50' : 'border-gray-200' ?>">
                    <input type="radio" name="assign_mode" value="selected" <?= ($exam['assign_mode'] ?? 'class') === 'selected' ? 'checked' : '' ?> class="text-primary-600 focus:ring-primary-500">
                    <div>
                        <span class="text-sm font-medium text-gray-700">Selected Students</span>
                        <p class="text-xs text-gray-400">Pick specific students after creating</p>
                    </div>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                <input type="checkbox" name="shuffle_questions" value="1" <?= ($exam['shuffle_questions'] ?? 1) ? 'checked' : '' ?> class="rounded text-primary-600 focus:ring-primary-500">
                <span class="text-sm text-gray-700">Shuffle Questions</span>
            </label>
            <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                <input type="checkbox" name="show_results" value="1" <?= ($exam['show_results'] ?? 1) ? 'checked' : '' ?> class="rounded text-primary-600 focus:ring-primary-500">
                <span class="text-sm text-gray-700">Show Results</span>
            </label>
            <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                <input type="checkbox" name="seb_enabled" value="1" <?= ($exam['seb_enabled'] ?? 1) ? 'checked' : '' ?> class="rounded text-primary-600 focus:ring-primary-500">
                <span class="text-sm text-gray-700">Enable SEB</span>
            </label>
            <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                <input type="checkbox" name="webcam_required" value="1" <?= ($exam['webcam_required'] ?? 1) ? 'checked' : '' ?> class="rounded text-primary-600 focus:ring-primary-500">
                <span class="text-sm text-gray-700">Webcam Required</span>
            </label>
        </div>

        <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
            <button type="submit" class="px-6 py-2.5 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors shadow-sm">
                <?= $editMode ? 'Update Exam' : 'Create Exam' ?>
            </button>
            <a href="exams.php" class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
