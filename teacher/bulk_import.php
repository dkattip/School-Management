<?php
require_once __DIR__ . '/../config/database.php';
requireRole('teacher');
$teacherId = $_SESSION['user_id'];
$exam_id = (int)($_GET['exam_id'] ?? 0);
$pageTitle = 'Bulk Import Questions';
include __DIR__ . '/../includes/header.php';

$success = $error = '';

$exams = $conn->query("SELECT e.id, e.exam_name, s.subject_name, CONCAT(c.class_name, ' ', c.section) as class_full
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    JOIN classes c ON e.class_id = c.id
    WHERE e.created_by = $teacherId
    ORDER BY e.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$exam = null;
if ($exam_id) {
    foreach ($exams as $e) {
        if ($e['id'] == $exam_id) { $exam = $e; break; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $exam_id) {
    $raw_text = $_POST['questions_text'] ?? '';

    if (empty(trim($raw_text))) {
        $error = 'Please paste some questions first.';
    } else {
        $raw_text = str_replace('**', '', $raw_text);
        $raw_text = preg_replace('/^---\s*$/m', '', $raw_text);

        $pre_split = preg_replace('/(?=Question\s+\d+[\.\)\:\s])/i', "\n", $raw_text);
        $pre_split = preg_replace('/(?=\bQ\.?\s*\d+[\.\)\:\/])/i', "\n", $pre_split);
        $pre_split = preg_replace('/(?<!Q)(\d{1,2})\.(?=\s+[A-Z])/', "\n$1. ", $pre_split);
        $pre_split = preg_replace('/(\d{1,2})\.(?=\s*$)/m', "\n$1. ", $pre_split);
        $pre_split = preg_replace('/(\d{1,2})\)(?=\s+[A-Z])/', "\n$1) ", $pre_split);

        $lines = explode("\n", $pre_split);
        $questions = [];
        $current = null;

        $qStartRe = '/^(?:Question\s+\d+|Q\.?\s*\d+[\.\)\:\/]|\d{1,2}[\.\)]\s+[A-Z]|\d{1,2}\s+[A-Z]|\(\d{1,2}\)\s*\S|\d{1,2}[\.\)]\s*$)/i';

        foreach ($lines as $line) {
            $line = rtrim($line);
            $trimmed = ltrim($line);

            if ($trimmed === '') {
                continue;
            }

            if ($current && preg_match('/^(?:\*?\*?(?:Correct\s+)?Answer[\.:]\s*\*?\*?\s*)\(?([a-dA-D])\)?/i', $trimmed, $am)) {
                $current['answer'] = strtoupper($am[1]);
                continue;
            }

            if ($current && preg_match('/^\(([A-Da-d])\)\s*(.+)/', $trimmed, $om)) {
                $current[strtolower($om[1])] = trim($om[2]);
                continue;
            }

            if (preg_match('/^(?:Explanation|Solution|Note|Remark)[\.:]/i', $trimmed)) {
                continue;
            }

            $isNewQ = preg_match($qStartRe, $trimmed);

            if ($isNewQ) {
                if ($current) $questions[] = $current;
                $current = ['text'=>'','a'=>'','b'=>'','c'=>'','d'=>'','answer'=>'','marks'=>1,'type'=>'mcq'];

                $qText = $trimmed;
                $qText = preg_replace('/^Question\s+\d+[\.\)\:\s]*/i', '', $qText);
                $qText = preg_replace('/^Q\.?\s*\d+[\.\)\:\s\/]*/i', '', $qText);
                $qText = preg_replace('/^\(\d{1,2}\)\s*/', '', $qText);
                $qText = preg_replace('/^\d{1,2}[\.\)]\s*/', '', $qText);
                $qText = preg_replace('/^\d{1,2}\s+(?=[A-Z])/', '', $qText);
                $qText = trim($qText);

                if (preg_match('/(?:Correct\s+)?Answer[\.:]\s*\(?([a-dA-D])\)?/i', $qText, $am)) {
                    $current['answer'] = strtoupper($am[1]);
                    $qText = preg_replace('/Answer[\s\S]*$/i', '', $qText);
                }
                $qText = preg_replace('/(?:Explanation|Solution|Note|Remark)[\.:].*$/i', '', $qText);

                if (preg_match_all('/\(([A-Da-d])\)\s*(.+?)(?=\s*\([A-Da-d]\)\s|\s*$)/', $qText, $inlineOpts, PREG_SET_ORDER)) {
                    foreach ($inlineOpts as $om) {
                        $current[strtolower($om[1])] = trim($om[2]);
                    }
                    $qText = preg_replace('/\(([A-Da-d])\)\s*.+?$/s', '', $qText);
                    $qText = trim($qText);
                }

                $current['text'] = $qText;
                continue;
            }

            if ($current === null) {
                if (preg_match('/^\d{1,2}[\.\)]\s*(.+)/', $trimmed, $m)) {
                    $current = ['text'=>trim($m[1]),'a'=>'','b'=>'','c'=>'','d'=>'','answer'=>'','marks'=>1,'type'=>'mcq'];
                } elseif (preg_match('/^Q\.?\s*\d+[\.\)\:\s\/]*(.+)/i', $trimmed, $m)) {
                    $current = ['text'=>trim($m[1]),'a'=>'','b'=>'','c'=>'','d'=>'','answer'=>'','marks'=>1,'type'=>'mcq'];
                } elseif (preg_match('/^Question\s+\d+[\.\)\:\s]*(.+)/i', $trimmed, $m)) {
                    $current = ['text'=>trim($m[1]),'a'=>'','b'=>'','c'=>'','d'=>'','answer'=>'','marks'=>1,'type'=>'mcq'];
                } elseif (preg_match('/^\d{1,2}\s+(.+)/', $trimmed, $m)) {
                    $current = ['text'=>trim($m[1]),'a'=>'','b'=>'','c'=>'','d'=>'','answer'=>'','marks'=>1,'type'=>'mcq'];
                }

                if ($current) {
                    $t = $current['text'];
                    if (preg_match('/(?:Correct\s+)?Answer[\.:]\s*\(?([a-dA-D])\)?/i', $t, $am)) {
                        $current['answer'] = strtoupper($am[1]);
                        $t = preg_replace('/Answer[\s\S]*$/i', '', $t);
                    }
                    $t = preg_replace('/(?:Explanation|Solution|Note|Remark)[\.:].*$/i', '', $t);
                    $current['text'] = $t;
                }
                continue;
            }

            if (preg_match('/^Marks[\.:]\s*(\d+)/i', $trimmed, $m)) {
                $current['marks'] = (int)$m[1];
            } elseif (preg_match('/^([Aa])\)\s*(.+)/', $trimmed, $m)) {
                $current['a'] = trim($m[2]);
            } elseif (preg_match('/^([Bb])\)\s*(.+)/', $trimmed, $m)) {
                $current['b'] = trim($m[2]);
            } elseif (preg_match('/^([Cc])\)\s*(.+)/', $trimmed, $m)) {
                $current['c'] = trim($m[2]);
            } elseif (preg_match('/^([Dd])\)\s*(.+)/', $trimmed, $m)) {
                $current['d'] = trim($m[2]);
            } elseif (preg_match('/^\(?([AaBbCcDd])\)?\.\s*(.+)/', $trimmed, $m)) {
                $letter = strtolower($m[1]);
                $current[$letter] = trim($m[2]);
            } elseif ($current !== null) {
                $current['text'] .= ($current['text'] ? ' ' : '') . $trimmed;
            }
        }
        if ($current) $questions[] = $current;

        foreach ($questions as &$q) {
            $ans = trim($q['answer']);
            if (preg_match('/^\(?([A-Da-d])\)?/i', $ans, $am)) $q['answer'] = strtoupper($am[1]);
            foreach (['text','a','b','c','d'] as $k) $q[$k] = trim($q[$k]);
        }
        unset($q);

        if (empty($questions)) {
            $error = 'Could not parse any questions.';
        } else {
            $max_order = $conn->query("SELECT COALESCE(MAX(question_order),0) as mo FROM questions WHERE exam_id = $exam_id")->fetch_assoc()['mo'];
            $imported = 0;
            foreach ($questions as $q) {
                $stmt = $conn->prepare("INSERT INTO questions (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, marks, question_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $question_text = $q['text'];
                $question_type = $q['type'];
                $option_a = $q['a'];
                $option_b = $q['b'];
                $option_c = $q['c'];
                $option_d = $q['d'];
                $correct_answer = $q['answer'];
                $marks = $q['marks'];
                $max_order++;
                $question_order = $max_order;
                $stmt->bind_param("isssssssdi", $exam_id, $question_text, $question_type, $option_a, $option_b, $option_c, $option_d, $correct_answer, $marks, $question_order);
                $stmt->execute();
                $stmt->close();
                $imported++;
            }

            $truncated = substr($raw_text, 0, 5000);
            $stmtLog = $conn->prepare("INSERT INTO import_logs (exam_id, imported_by, import_type, questions_count, import_data, status) VALUES (?, ?, 'paste', ?, ?, 'success')");
            $stmtLog->bind_param("iiss", $exam_id, $teacherId, $imported, $truncated);
            $stmtLog->execute();
            $stmtLog->close();

            $success = "Successfully imported $imported question(s)!";
        }
    }
}

$import_logs = [];
if ($exam_id) {
    $import_logs = $conn->query("SELECT il.*, u.full_name as importer_name
        FROM import_logs il JOIN users u ON il.imported_by = u.id
        WHERE il.exam_id = $exam_id ORDER BY il.created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
}
?>

<?php if ($success): ?>
<div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm flex items-center gap-2">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    <?= $success ?>
    <?php if ($exam_id): ?><a href="questions.php?exam_id=<?= $exam_id ?>" class="ml-2 text-green-800 underline font-medium">View Questions</a><?php endif; ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm flex items-center gap-2">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    <?= $error ?>
</div>
<?php endif; ?>

<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-semibold text-gray-800">Select Exam</h3></div>
        <div class="p-6">
            <?php if (empty($exams)): ?>
            <p class="text-gray-400 text-sm">No exams found. <a href="create_exam.php" class="text-primary-600 underline">Create one first</a>.</p>
            <?php else: ?>
            <form method="GET" class="flex gap-3">
                <select name="exam_id" required class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <option value="">-- Select Exam --</option>
                    <?php foreach ($exams as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $exam_id == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['exam_name']) ?> (<?= htmlspecialchars($e['subject_name']) ?> - <?= htmlspecialchars($e['class_full']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors">Load</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($exam): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-800">Import Questions</h3>
                    <p class="text-xs text-gray-400 mt-1">Into: <?= htmlspecialchars($exam['exam_name']) ?> (<?= htmlspecialchars($exam['subject_name']) ?>)</p>
                </div>
                <?php if ($exam_id): ?>
                <a href="questions.php?exam_id=<?= $exam_id ?>" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View Existing</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab Headers -->
        <div class="flex border-b border-gray-200">
            <button onclick="switchTab('paste')" id="tabPaste" class="flex-1 px-4 py-3 text-sm font-medium text-center border-b-2 transition-colors border-primary-600 text-primary-600 bg-primary-50">
                <svg class="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Paste Text
            </button>
            <button onclick="switchTab('script')" id="tabScript" class="flex-1 px-4 py-3 text-sm font-medium text-center border-b-2 transition-colors border-transparent text-gray-500 hover:text-gray-700">
                <svg class="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                JavaScript
            </button>
            <button onclick="switchTab('word')" id="tabWord" class="flex-1 px-4 py-3 text-sm font-medium text-center border-b-2 transition-colors border-transparent text-gray-500 hover:text-gray-700">
                <svg class="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Word File
            </button>
            <button onclick="switchTab('pdf')" id="tabPdf" class="flex-1 px-4 py-3 text-sm font-medium text-center border-b-2 transition-colors border-transparent text-gray-500 hover:text-gray-700">
                <svg class="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                PDF File
            </button>
        </div>

        <!-- Tab 1: Paste Text -->
        <div id="panelPaste" class="p-6">
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                <h4 class="text-sm font-semibold text-blue-800 mb-2">Supported Formats</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs text-blue-700">
                    <div>
                        <p class="font-medium mb-1">Format 1 (Multi-line):</p>
                        <pre class="bg-white p-2 rounded border border-blue-200 text-gray-700 whitespace-pre-wrap">Q: What is 2+2?
A) 3
B) 4
C) 5
D) 6
Answer: B
Marks: 2</pre>
                    </div>
                    <div>
                        <p class="font-medium mb-1">Format 2 (Pipe-separated):</p>
                        <pre class="bg-white p-2 rounded border border-blue-200 text-gray-700 whitespace-pre-wrap">1. What is x? | A) 1 | B) 2 | C) 3 | D) 4 | Answer: B | Marks: 2
2. What is y? | A) 5 | B) 6 | C) 7 | D) 8 | Answer: C | Marks: 3</pre>
                    </div>
                </div>
            </div>
            <form method="POST" id="importForm" onsubmit="return confirmImport()">
                <textarea name="questions_text" id="questionsText" rows="16" required
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                    placeholder="Paste your questions here..."></textarea>
                <div class="flex items-center justify-between mt-4">
                    <div class="text-sm text-gray-500">
                        <span id="lineCount">0</span> lines &middot; <span id="questionEstimate">0</span> questions detected
                    </div>
                    <div class="flex gap-3">
                        <button type="button" onclick="document.getElementById('questionsText').value=''; updateCounts()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Clear</button>
                        <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 shadow-sm">Import Questions</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tab 2: JavaScript Import -->
        <div id="panelScript" class="p-6 hidden">
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6">
                <h4 class="text-sm font-semibold text-amber-800 mb-2">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    JavaScript Import — Standard JS or Google Apps Script
                </h4>
                <p class="text-xs text-amber-700"><b>Standard JS:</b> Write a <code class="bg-amber-100 px-1 rounded">getQuestions()</code> function using <code class="bg-amber-100 px-1 rounded">addQuestion(text, a, b, c, d, answer, marks)</code>.<br>
                <b>Google Apps Script:</b> Paste your FormApp script directly — the <code class="bg-amber-100 px-1 rounded">questions</code> array (with <code>q</code>, <code>options</code>, <code>answer</code> fields) is auto-detected.</p>
            </div>
            <div id="scriptEditor" class="border border-gray-200 rounded-xl overflow-hidden">
                <div class="bg-gray-900 px-4 py-2 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                        <span class="w-3 h-3 bg-yellow-500 rounded-full"></span>
                        <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                        <span class="text-gray-400 text-xs ml-2">questions.js</span>
                    </div>
                    <button type="button" onclick="runScript()" class="px-3 py-1 bg-green-600 text-white text-xs font-medium rounded hover:bg-green-700 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path></svg>
                        Run & Preview
                    </button>
                </div>
                <textarea id="scriptCode" rows="20" class="w-full px-4 py-3 bg-gray-900 text-green-400 text-sm font-mono focus:outline-none border-0 resize-none" spellcheck="false">function getQuestions() {
    var questions = [];

    addQuestion("What is the value of x if 2x + 5 = 15?", "5", "10", "15", "20", "B", 2);
    addQuestion("The square root of 144 is:", "11", "12", "13", "14", "B", 2);
    addQuestion("If a triangle has angles 60 and 80, the third angle is:", "30", "40", "50", "60", "B", 3);

    return questions;
}

function addQuestion(text, a, b, c, d, answer, marks) {
    questions.push({
        text: text, a: a, b: b, c: c, d: d,
        answer: answer, marks: marks || 1, type: "mcq"
    });
}</textarea>
            </div>

            <div id="scriptPreview" class="mt-4 hidden">
                <div class="bg-gray-50 border border-gray-200 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200 bg-gray-100 flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-gray-700">Preview (<span id="scriptCount">0</span> questions)</h4>
                        <button type="button" onclick="submitScript()" class="px-4 py-1.5 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700">Import These Questions</button>
                    </div>
                    <div id="scriptPreviewList" class="divide-y divide-gray-100 max-h-96 overflow-y-auto"></div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Word File Import -->
        <div id="panelWord" class="p-6 hidden">
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                <h4 class="text-sm font-semibold text-blue-800 mb-2">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Word Document Import (.docx)
                </h4>
                <p class="text-xs text-blue-700">Upload a Word document. Text will be extracted and placed in the editor below. LaTeX expressions (e.g. <code class="bg-blue-100 px-1 rounded">$\\frac{a}{b}$</code>) are preserved as-is. Review and adjust before importing.</p>
            </div>

            <div id="wordDropZone" class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-primary-400 hover:bg-primary-50/30 transition-colors cursor-pointer" onclick="document.getElementById('wordFileInput').click()">
                <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                <p class="text-sm text-gray-500 font-medium">Click to upload or drag & drop</p>
                <p class="text-xs text-gray-400 mt-1">.docx files only, max 20MB</p>
                <input type="file" id="wordFileInput" accept=".docx" class="hidden" onchange="handleFileUpload(this, 'word')">
            </div>

            <div id="wordProgress" class="mt-4 hidden">
                <div class="flex items-center gap-3 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                    <div class="w-5 h-5 border-2 border-blue-400 border-t-blue-600 rounded-full animate-spin"></div>
                    <span class="text-sm text-blue-700" id="wordProgressText">Extracting text from document...</span>
                </div>
            </div>

            <div id="wordPreview" class="mt-4 hidden">
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-semibold text-gray-700">Extracted Text — review before importing</h4>
                        <button type="button" onclick="document.getElementById('questionsText').value = document.getElementById('wordExtractedText').value; switchTab('paste'); updateCounts();" class="px-4 py-1.5 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700">Edit in Paste Tab</button>
                    </div>
                    <textarea id="wordExtractedText" rows="16" class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-primary-500 bg-white" readonly></textarea>
                    <div class="flex justify-end mt-3">
                        <button type="button" onclick="submitExtractedText('wordExtractedText')" class="px-6 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 shadow-sm">Import Questions</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 4: PDF File Import -->
        <div id="panelPdf" class="p-6 hidden">
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                <h4 class="text-sm font-semibold text-red-800 mb-2">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    PDF Document Import
                </h4>
                <p class="text-xs text-red-700">Upload a PDF file. Text will be extracted and placed in the editor below. LaTeX expressions (e.g. <code class="bg-red-100 px-1 rounded">$\\frac{a}{b}$</code>) are preserved as-is. Review and adjust before importing.</p>
            </div>

            <div id="pdfDropZone" class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-primary-400 hover:bg-primary-50/30 transition-colors cursor-pointer" onclick="document.getElementById('pdfFileInput').click()">
                <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                <p class="text-sm text-gray-500 font-medium">Click to upload or drag & drop</p>
                <p class="text-xs text-gray-400 mt-1">.pdf files only, max 20MB</p>
                <input type="file" id="pdfFileInput" accept=".pdf" class="hidden" onchange="handleFileUpload(this, 'pdf')">
            </div>

            <div id="pdfProgress" class="mt-4 hidden">
                <div class="flex items-center gap-3 p-4 bg-red-50 border border-red-200 rounded-xl">
                    <div class="w-5 h-5 border-2 border-red-400 border-t-red-600 rounded-full animate-spin"></div>
                    <span class="text-sm text-red-700" id="pdfProgressText">Extracting text from PDF...</span>
                </div>
            </div>

            <div id="pdfPreview" class="mt-4 hidden">
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-semibold text-gray-700">Extracted Text — review before importing</h4>
                        <button type="button" onclick="document.getElementById('questionsText').value = document.getElementById('pdfExtractedText').value; switchTab('paste'); updateCounts();" class="px-4 py-1.5 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700">Edit in Paste Tab</button>
                    </div>
                    <textarea id="pdfExtractedText" rows="16" class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-primary-500 bg-white" readonly></textarea>
                    <div class="flex justify-end mt-3">
                        <button type="button" onclick="submitExtractedText('pdfExtractedText')" class="px-6 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 shadow-sm">Import Questions</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($import_logs)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100"><h3 class="font-semibold text-gray-800">Import History</h3></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500 bg-gray-50">
                    <th class="px-6 py-3 font-medium">#</th>
                    <th class="px-6 py-3 font-medium">Date</th>
                    <th class="px-6 py-3 font-medium">By</th>
                    <th class="px-6 py-3 font-medium">Type</th>
                    <th class="px-6 py-3 font-medium">Questions</th>
                    <th class="px-6 py-3 font-medium">Status</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($import_logs as $idx => $log): ?>
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-6 py-3 text-gray-400"><?= $idx+1 ?></td>
                        <td class="px-6 py-3 text-gray-600"><?= date('d M Y, h:i A', strtotime($log['created_at'])) ?></td>
                        <td class="px-6 py-3 text-gray-600"><?= htmlspecialchars($log['importer_name']) ?></td>
                        <td class="px-6 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700"><?= ucfirst($log['import_type']) ?></span></td>
                        <td class="px-6 py-3 font-medium text-gray-800"><?= $log['questions_count'] ?></td>
                        <td class="px-6 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $log['status']==='success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>"><?= ucfirst($log['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
const EXAM_ID = <?= $exam_id ?: '0' ?>;
const API_URL = '<?= $baseUrl ?>/api/import_questions.php';
const EXTRACT_URL = '<?= $baseUrl ?>/api/extract_file.php';

function switchTab(tab) {
    document.querySelectorAll('[id^="panel"]').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('[id^="tab"]').forEach(t => {
        t.classList.remove('border-primary-600','text-primary-600','bg-primary-50');
        t.classList.add('border-transparent','text-gray-500');
    });
    document.getElementById('panel' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.remove('hidden');
    const activeTab = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
    activeTab.classList.remove('border-transparent','text-gray-500');
    activeTab.classList.add('border-primary-600','text-primary-600','bg-primary-50');
}

document.getElementById('questionsText')?.addEventListener('input', updateCounts);
function updateCounts() {
    const text = document.getElementById('questionsText').value;
    const lines = text.split('\n').filter(l => l.trim()).length;
    let qCount = 0;
    qCount = Math.max(qCount, (text.match(/Question\s+\d/gi) || []).length);
    qCount = Math.max(qCount, (text.match(/Q\.?\s*\d/gi) || []).length);
    qCount = Math.max(qCount, (text.match(/\d{1,2}\.\s*[A-Z\\]/g) || []).length);
    qCount = Math.max(qCount, (text.match(/\d{1,2}\.\s/g) || []).length);
    qCount = Math.max(qCount, (text.match(/\d{1,2}\)\s*[A-Z\\]/g) || []).length);
    qCount = Math.max(qCount, (text.match(/\(\d{1,2}\)\s*\S/g) || []).length);
    if (qCount === 0 && lines > 0) qCount = Math.ceil(lines / 3);
    document.getElementById('lineCount').textContent = lines;
    document.getElementById('questionEstimate').textContent = qCount;
}
function confirmImport() {
    const text = document.getElementById('questionsText').value;
    if (!text.trim()) { alert('Please paste some questions first.'); return false; }
    let qCount = 0;
    qCount = Math.max(qCount, (text.match(/Question\s+\d/gi) || []).length);
    qCount = Math.max(qCount, (text.match(/Q\.?\s*\d/gi) || []).length);
    qCount = Math.max(qCount, (text.match(/\d{1,2}\.\s*[A-Z\\]/g) || []).length);
    qCount = Math.max(qCount, (text.match(/\d{1,2}\.\s/g) || []).length);
    qCount = Math.max(qCount, (text.match(/\d{1,2}\)\s*[A-Z\\]/g) || []).length);
    qCount = Math.max(qCount, (text.match(/\(\d{1,2}\)\s*\S/g) || []).length);
    if (qCount === 0) { alert('No questions detected. Please use format like:\n1. Question text\nA) option\nB) option\nC) option\nD) option'); return false; }
    return confirm('Import ' + qCount + ' question(s)?');
}

// File upload handler for Word and PDF
function handleFileUpload(input, type) {
    const file = input.files[0];
    if (!file) return;

    const progress = document.getElementById(type + 'Progress');
    const progressText = document.getElementById(type + 'ProgressText');
    const preview = document.getElementById(type + 'Preview');
    const dropZone = document.getElementById(type + 'DropZone');

    progress.classList.remove('hidden');
    preview.classList.add('hidden');
    dropZone.classList.add('hidden');
    progressText.textContent = 'Extracting text from ' + (type === 'word' ? 'document' : 'PDF') + '...';

    const formData = new FormData();
    formData.append('file', file);

    fetch(EXTRACT_URL, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        progress.classList.add('hidden');
        dropZone.classList.remove('hidden');
        if (data.error) {
            alert(data.error);
            return;
        }
        document.getElementById(type + 'ExtractedText').value = data.text;
        preview.classList.remove('hidden');
    })
    .catch(e => {
        progress.classList.add('hidden');
        dropZone.classList.remove('hidden');
        alert('Error: ' + e.message);
    });
}

// Submit extracted text via the paste-text form parser
function submitExtractedText(textareaId) {
    const text = document.getElementById(textareaId).value;
    if (!text.trim()) { alert('No text to import.'); return; }
    document.getElementById('questionsText').value = text;
    document.getElementById('importForm').submit();
}

// Drag and drop support
['word', 'pdf'].forEach(type => {
    const zone = document.getElementById(type + 'DropZone');
    if (!zone) return;
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('border-primary-400', 'bg-primary-50/30'); });
    zone.addEventListener('dragleave', e => { zone.classList.remove('border-primary-400', 'bg-primary-50/30'); });
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('border-primary-400', 'bg-primary-50/30');
        const fileInput = document.getElementById(type + 'FileInput');
        fileInput.files = e.dataTransfer.files;
        handleFileUpload(fileInput, type);
    });
});

// JavaScript Import Tab
function runScript() {
    const code = document.getElementById('scriptCode').value;
    let questions = [];
    var isGAS = /FormApp|addMultipleChoiceItem|Logger\.log/.test(code);

    if (isGAS) {
        try {
            var _questions = [];
            var _currentItem = null;
            var _Choice = function(value, correct) { this._v = value; this._c = correct; };
            _Choice.prototype.getValue = function() { return this._v; };
            _Choice.prototype.isCorrectChoice = function() { return this._c; };
            var _feedback = { setText: function() { return this; }, build: function() { return {}; } };
            var _item = {
                setTitle: function(t) { _currentItem = { text: t, choices: [], marks: 1 }; return this; },
                setChoices: function(choices) { if (_currentItem) { _currentItem.choices = choices; _questions.push(_currentItem); _currentItem = null; } return this; },
                setPoints: function(p) { if (_currentItem) _currentItem.marks = p; return this; },
                setRequired: function() { return this; },
                createChoice: function(value, isCorrect) { return new _Choice(value, isCorrect); },
                setFeedbackForCorrect: function() { return this; },
                setFeedbackForIncorrect: function() { return this; },
                setFeedbackForNeutral: function() { return this; },
                setHelpText: function() { return this; },
                setTitleContentAlignment: function() { return this; }
            };
            var _form = {
                addMultipleChoiceItem: function() { return _item; },
                addCheckboxItem: function() { return _item; },
                addTextItem: function() { return _item; },
                addParagraphTextItem: function() { return _item; },
                addListItem: function() { return _item; },
                addScaleItem: function() { return _item; },
                addGridItem: function() { return _item; },
                setDescription: function() { return this; },
                setTitle: function() { return this; },
                setIsQuiz: function() { return this; }
            };
            var _stub = function() { return _form; };
            _stub.create = function() { return _form; };
            _stub.createFeedback = function() { return _feedback; };
            _stub.getActiveForm = function() { return _form; };
            var _logger = { log: function(){} };
            var _funcNames = code.match(/function\s+([a-zA-Z_$][0-9a-zA-Z_$]*)\s*\(/g) || [];
            _funcNames = _funcNames.map(function(m) { return m.replace(/function\s+/, '').replace(/\s*\($/, ''); });
            var _calls = _funcNames.map(function(fn) { return 'try{' + fn + '();}catch(e){}'; }).join('\n');
            var _fn = new Function('FormApp', 'Logger', code + '\n' + _calls);
            _fn(_stub, _logger);
            questions = _questions.map(function(q) {
                var opts = q.choices.map(function(c) { return c._v; });
                var correctIdx = q.choices.findIndex(function(c) { return c._c; });
                return {
                    text: q.text, a: opts[0] || '', b: opts[1] || '', c: opts[2] || '', d: opts[3] || '',
                    answer: correctIdx >= 0 ? String.fromCharCode(65 + correctIdx) : '',
                    marks: q.marks || 1, type: 'mcq'
                };
            });
        } catch(e) { alert('Script Error: ' + e.message); return; }
    } else {
        try {
            var _scope2 = {};
            var _fn2 = new Function('questions', 'addQuestion', code + '\nreturn getQuestions();');
            var _addQ2 = function(text, a, b, c, d, answer, marks) {
                _scope2.questions.push({ text: text, a: a, b: b, c: c, d: d, answer: answer, marks: marks || 1, type: "mcq" });
            };
            _scope2.questions = [];
            questions = _fn2(_scope2.questions, _addQ2);
        } catch(e) { alert('Script Error: ' + e.message); return; }
    }

    if (!questions || questions.length === 0) { alert('No questions found.'); return; }
    window._parsedQuestions = questions;
    renderPreview(questions, 'script');
}

function renderPreview(questions, prefix) {
    const list = document.getElementById(prefix + 'PreviewList');
    const count = document.getElementById(prefix + 'Count');
    count.textContent = questions.length;
    list.innerHTML = '';
    questions.forEach((q, i) => {
        list.innerHTML += '<div class="px-4 py-3 flex items-start gap-3 hover:bg-gray-50">' +
            '<span class="w-7 h-7 bg-primary-100 text-primary-700 rounded-lg flex items-center justify-center text-xs font-bold shrink-0">' + (i+1) + '</span>' +
            '<div class="flex-1 min-w-0">' +
                '<p class="text-sm text-gray-800 font-medium">' + escHtml(q.text) + '</p>' +
                '<div class="flex flex-wrap gap-1.5 mt-1.5">' +
                    '<span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded">A) ' + escHtml(q.a) + '</span>' +
                    '<span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded">B) ' + escHtml(q.b) + '</span>' +
                    '<span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded">C) ' + escHtml(q.c || '') + '</span>' +
                    '<span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded">D) ' + escHtml(q.d || '') + '</span>' +
                '</div>' +
                '<p class="text-xs text-gray-400 mt-1">Answer: <span class="text-green-600 font-semibold">' + escHtml(q.answer) + '</span> &middot; ' + (q.marks||1) + ' mark(s)</p>' +
            '</div></div>';
    });
    document.getElementById(prefix + 'Preview').classList.remove('hidden');
}

function submitScript() {
    if (!window._parsedQuestions || window._parsedQuestions.length === 0) return;
    importViaApi(window._parsedQuestions);
}

function importViaApi(questions) {
    if (!EXAM_ID) { alert('No exam selected.'); return; }
    if (!confirm('Import ' + questions.length + ' question(s) into this exam?')) return;
    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ exam_id: EXAM_ID, questions: questions })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Successfully imported ' + data.imported + ' question(s)!' + (data.errors && data.errors.length ? '\nErrors: ' + data.errors.join('; ') : ''));
            if (data.imported > 0) window.location.href = 'questions.php?exam_id=' + EXAM_ID;
        } else {
            alert('Import failed: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(e => alert('Network error: ' + e.message));
}

function escHtml(t) {
    const d = document.createElement('div');
    d.textContent = t || '';
    return d.innerHTML;
}

updateCounts();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
