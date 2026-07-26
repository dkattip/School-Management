<?php
require_once __DIR__ . '/../config/database.php';
requireRole('teacher');
$teacherId = $_SESSION['user_id'];
$exam_id = (int)($_GET['exam_id'] ?? 0);

if (!$exam_id) {
    global $baseUrl;
    redirect("$baseUrl/teacher/exams.php");
}

$exam = $conn->query("SELECT e.*, s.subject_name, CONCAT(c.class_name, ' ', c.section) as class_full
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    JOIN classes c ON e.class_id = c.id
    WHERE e.id = $exam_id AND e.created_by = $teacherId")->fetch_assoc();

if (!$exam) {
    global $baseUrl;
    redirect("$baseUrl/teacher/exams.php");
}

$pageTitle = 'Manage Questions: ' . $exam['exam_name'];
include __DIR__ . '/../includes/header.php';

$success = $error = '';

if (isset($_GET['created'])) {
    $success = 'Exam created! Now add questions below.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $question_text = sanitize($_POST['question_text'] ?? '');
        $question_type = sanitize($_POST['question_type'] ?? 'mcq');
        $option_a = sanitize($_POST['option_a'] ?? '');
        $option_b = sanitize($_POST['option_b'] ?? '');
        $option_c = sanitize($_POST['option_c'] ?? '');
        $option_d = sanitize($_POST['option_d'] ?? '');
        $correct_answer = sanitize($_POST['correct_answer'] ?? '');
        $marks = max(0.5, (float)($_POST['marks'] ?? 1));
        $max_order = $conn->query("SELECT COALESCE(MAX(question_order), 0) + 1 as next_order FROM questions WHERE exam_id = $exam_id")->fetch_assoc()['next_order'];

        if (!$question_text || !$correct_answer) {
            $error = 'Question text and correct answer are required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO questions (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, marks, question_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssssdi", $exam_id, $question_text, $question_type, $option_a, $option_b, $option_c, $option_d, $correct_answer, $marks, $max_order);
            $stmt->execute();
            $stmt->close();
            $success = 'Question added successfully.';
        }
    } elseif ($action === 'edit') {
        $qid = (int)($_POST['question_id'] ?? 0);
        $question_text = sanitize($_POST['question_text'] ?? '');
        $question_type = sanitize($_POST['question_type'] ?? 'mcq');
        $option_a = sanitize($_POST['option_a'] ?? '');
        $option_b = sanitize($_POST['option_b'] ?? '');
        $option_c = sanitize($_POST['option_c'] ?? '');
        $option_d = sanitize($_POST['option_d'] ?? '');
        $correct_answer = sanitize($_POST['correct_answer'] ?? '');
        $marks = max(0.5, (float)($_POST['marks'] ?? 1));

        if ($qid && $question_text && $correct_answer) {
            $stmt = $conn->prepare("UPDATE questions SET question_text=?, question_type=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=?, marks=? WHERE id=? AND exam_id=?");
            $stmt->bind_param("sssssssdii", $question_text, $question_type, $option_a, $option_b, $option_c, $option_d, $correct_answer, $marks, $qid, $exam_id);
            $stmt->execute();
            $stmt->close();
            $success = 'Question updated.';
        }
    } elseif ($action === 'delete') {
        $qid = (int)($_POST['question_id'] ?? 0);
        if ($qid) {
            $conn->query("DELETE FROM questions WHERE id = $qid AND exam_id = $exam_id");
            $success = 'Question deleted.';
        }
    } elseif ($action === 'reorder') {
        $order = $_POST['order'] ?? [];
        foreach ($order as $idx => $qid) {
            $qid = (int)$qid;
            $conn->query("UPDATE questions SET question_order = $idx WHERE id = $qid AND exam_id = $exam_id");
        }
        $success = 'Question order updated.';
    }
}

$questions = $conn->query("SELECT * FROM questions WHERE exam_id = $exam_id ORDER BY question_order ASC, id ASC")->fetch_all(MYSQLI_ASSOC);
$total_marks_db = 0;
foreach ($questions as $q) {
    $total_marks_db += $q['marks'];
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
    <div>
        <a href="exams.php" class="text-sm text-primary-600 hover:text-primary-700 mb-1 inline-flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            Back to Exams
        </a>
        <h2 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($exam['exam_name']) ?></h2>
        <p class="text-sm text-gray-500"><?= htmlspecialchars($exam['subject_name']) ?> &middot; <?= htmlspecialchars($exam['class_full']) ?></p>
    </div>
    <div class="flex gap-3">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-4 py-2 text-center">
            <p class="text-xl font-bold text-gray-800"><?= count($questions) ?></p>
            <p class="text-xs text-gray-500">Questions</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-4 py-2 text-center">
            <p class="text-xl font-bold text-primary-600"><?= $total_marks_db ?></p>
            <p class="text-xs text-gray-500">Total Marks</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-4 py-2 text-center">
            <p class="text-xl font-bold text-gray-800"><?= $exam['total_marks'] ?></p>
            <p class="text-xs text-gray-500">Target Marks</p>
        </div>
        <a href="<?= $baseUrl ?>/api/export_exam_pdf.php?exam_id=<?= $exam_id ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors h-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Export PDF
        </a>
    </div>
</div>

<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden" id="addQuestionSection">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <h3 class="font-semibold text-gray-800">Add New Question</h3>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Question Text *</label>
                    <textarea name="question_text" rows="3" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="Enter your question..."></textarea>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Question Type</label>
                        <select name="question_type" id="add_qtype" onchange="toggleOptions()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="mcq">Multiple Choice</option>
                            <option value="true_false">True / False</option>
                            <option value="short_answer">Short Answer</option>
                            <option value="long_answer">Long Answer</option>
                            <option value="numerical">Numerical</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Marks</label>
                        <input type="number" name="marks" value="1" min="0.5" step="0.5" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Correct Answer *</label>
                        <input type="text" name="correct_answer" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="e.g. B">
                    </div>
                </div>
            </div>
            <div id="mcqOptions" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Option A</label>
                    <input type="text" name="option_a" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Option B</label>
                    <input type="text" name="option_b" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Option C</label>
                    <input type="text" name="option_c" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Option D</label>
                    <input type="text" name="option_d" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors shadow-sm">Add Question</button>
            </div>
        </form>
    </div>

    <?php if (!empty($questions)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Questions (<?= count($questions) ?>)</h3>
            <a href="bulk_import.php?exam_id=<?= $exam_id ?>" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-100 rounded-lg hover:bg-amber-200 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                Bulk Import
            </a>
        </div>
        <div class="divide-y divide-gray-50">
            <?php foreach ($questions as $idx => $q): ?>
            <div class="p-5 hover:bg-gray-50/50 transition-colors" id="q-<?= $q['id'] ?>">
                <div class="flex items-start gap-4">
                    <div class="flex flex-col items-center gap-1 pt-1">
                        <span class="text-xs font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded">#<?= $idx + 1 ?></span>
                        <span class="text-xs text-gray-400"><?= $q['marks'] ?>m</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-gray-800"><?= nl2br(renderLatexText($q['question_text'])) ?></p>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium mt-1 <?= $q['question_type'] === 'mcq' ? 'bg-blue-100 text-blue-700' : ($q['question_type'] === 'true_false' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600') ?>">
                                    <?= ucfirst(str_replace('_', ' ', $q['question_type'])) ?>
                                </span>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <button onclick='editQuestion(<?= json_encode($q) ?>)' class="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirmDelete('Delete this question?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php if ($q['question_type'] === 'mcq' || $q['question_type'] === 'true_false'): ?>
                        <div class="mt-2 grid grid-cols-2 gap-1 text-xs">
                            <?php foreach (['a' => $q['option_a'], 'b' => $q['option_b'], 'c' => $q['option_c'], 'd' => $q['option_d']] as $letter => $opt): ?>
                            <?php if ($opt): ?>
                            <span class="px-2 py-1 rounded <?= strtoupper($q['correct_answer']) === strtoupper($letter) ? 'bg-green-100 text-green-700 font-medium' : 'bg-gray-100 text-gray-600' ?>">
                                <?= strtoupper($letter) ?>) <?= renderLatexText($opt) ?>
                            </span>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="mt-1 text-xs text-green-600">Answer: <?= htmlspecialchars($q['correct_answer']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <h3 class="text-lg font-semibold text-gray-600 mb-1">No Questions Yet</h3>
        <p class="text-sm text-gray-400">Add questions using the form above or try bulk import.</p>
    </div>
    <?php endif; ?>
</div>

<div id="editModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Edit Question</h3>
            <button onclick="this.closest('.fixed').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="question_id" id="edit_qid">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Question Text</label>
                    <textarea name="question_text" id="edit_qtext" rows="3" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"></textarea>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="question_type" id="edit_qtype" onchange="toggleEditOptions()" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="mcq">Multiple Choice</option>
                            <option value="true_false">True / False</option>
                            <option value="short_answer">Short Answer</option>
                            <option value="long_answer">Long Answer</option>
                            <option value="numerical">Numerical</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Marks</label>
                        <input type="number" name="marks" id="edit_marks" min="0.5" step="0.5" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Correct Answer</label>
                        <input type="text" name="correct_answer" id="edit_answer" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                    </div>
                </div>
            </div>
            <div id="editMcqOptions" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Option A</label>
                    <input type="text" name="option_a" id="edit_optA" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Option B</label>
                    <input type="text" name="option_b" id="edit_optB" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Option C</label>
                    <input type="text" name="option_c" id="edit_optC" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Option D</label>
                    <input type="text" name="option_d" id="edit_optD" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
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
function toggleOptions() {
    const t = document.getElementById('add_qtype').value;
    document.getElementById('mcqOptions').style.display = (t === 'mcq' || t === 'true_false') ? 'grid' : 'none';
}
function toggleEditOptions() {
    const t = document.getElementById('edit_qtype').value;
    document.getElementById('editMcqOptions').style.display = (t === 'mcq' || t === 'true_false') ? 'grid' : 'none';
}
function editQuestion(q) {
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('edit_qid').value = q.id;
    document.getElementById('edit_qtext').value = q.question_text;
    document.getElementById('edit_qtype').value = q.question_type;
    document.getElementById('edit_marks').value = q.marks;
    document.getElementById('edit_answer').value = q.correct_answer;
    document.getElementById('edit_optA').value = q.option_a || '';
    document.getElementById('edit_optB').value = q.option_b || '';
    document.getElementById('edit_optC').value = q.option_c || '';
    document.getElementById('edit_optD').value = q.option_d || '';
    toggleEditOptions();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
