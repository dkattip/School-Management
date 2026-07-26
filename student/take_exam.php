<?php
require_once __DIR__ . '/../config/database.php';
requireRole('student');
$studentId = $_SESSION['user_id'];

$examId = (int)($_GET['exam_id'] ?? 0);
if (!$examId) {
    header("Location: my_exams.php");
    exit();
}

$exam = $conn->query("SELECT e.*, s.subject_name, CONCAT(c.class_name, ' ', c.section) as class_full
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    JOIN classes c ON e.class_id = c.id
    WHERE e.id = $examId AND e.is_active = 1")->fetch_assoc();

if (!$exam) {
    header("Location: my_exams.php");
    exit();
}

$studentClass = $conn->query("SELECT class_id FROM student_classes WHERE student_id = $studentId AND class_id = {$exam['class_id']}")->fetch_assoc();
if (!$studentClass) {
    header("Location: my_exams.php");
    exit();
}

// Check assignment access for selected mode
if ($exam['assign_mode'] === 'selected') {
    $isAssigned = $conn->query("SELECT id FROM exam_student_assignments WHERE exam_id = $examId AND student_id = $studentId")->fetch_assoc();
    if (!$isAssigned) {
        header("Location: my_exams.php");
        exit();
    }
}

$existingAttempt = $conn->query("SELECT * FROM exam_attempts WHERE exam_id = $examId AND student_id = $studentId AND status = 'completed' ORDER BY id DESC LIMIT 1")->fetch_assoc();
if ($existingAttempt) {
    header("Location: results.php?view=$examId");
    exit();
}

$inProgressAttempt = $conn->query("SELECT * FROM exam_attempts WHERE exam_id = $examId AND student_id = $studentId AND status = 'in_progress' LIMIT 1")->fetch_assoc();

if (!$inProgressAttempt) {
    $conn->query("INSERT INTO exam_attempts (exam_id, student_id, start_time, status, max_score) VALUES ($examId, $studentId, NOW(), 'in_progress', {$exam['total_marks']})");
    $attemptId = $conn->insert_id;
    $startTime = date('Y-m-d H:i:s');
} else {
    $attemptId = $inProgressAttempt['id'];
    $startTime = $inProgressAttempt['start_time'];
}

$questions = $conn->query("SELECT id, question_text, question_type, option_a, option_b, option_c, option_d, option_e, marks FROM questions WHERE exam_id = $examId ORDER BY " . ($exam['shuffle_questions'] ? 'RAND()' : 'id'))->fetch_all(MYSQLI_ASSOC);

$savedAnswers = $conn->query("SELECT question_id, selected_option FROM student_answers WHERE attempt_id = $attemptId")->fetch_all(MYSQLI_ASSOC);
$answersMap = [];
foreach ($savedAnswers as $a) {
    $answersMap[$a['question_id']] = $a['selected_option'];
}

$markedForReview = $conn->query("SELECT question_id FROM student_answers WHERE attempt_id = $attemptId AND marked_for_review = 1")->fetch_all(MYSQLI_ASSOC);
$reviewMap = [];
foreach ($markedForReview as $r) {
    $reviewMap[$r['question_id']] = true;
}

$totalMarks = 0;
foreach ($questions as $q) {
    $totalMarks += (int)$q['marks'];
}
if ($totalMarks == 0) $totalMarks = $exam['total_marks'];

$questionsJson = json_encode($questions);
$answersJson = json_encode($answersMap);
$reviewJson = json_encode(array_keys($reviewMap));

$conn->query("UPDATE exam_attempts SET last_activity = NOW() WHERE id = $attemptId");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?= htmlspecialchars($exam['exam_name']) ?> - Exam</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50:'#eef2ff',100:'#e0e7ff',200:'#c7d2fe',300:'#a5b4fc',400:'#818cf8',500:'#6366f1',600:'#4f46e5',700:'#4338ca',800:'#3730a3',900:'#312e81' },
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { margin: 0; padding: 0; overflow: hidden; background: #f8fafc; }
        .webcam-feed { position: fixed; bottom: 16px; right: 16px; z-index: 9999; cursor: move; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.25); border: 2px solid #6366f1; }
        .webcam-feed video { display: block; width: 200px; height: 150px; object-fit: cover; background: #1e1b4b; }
        .question-nav-btn { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.15s; border: 2px solid transparent; }
        .question-nav-btn.answered { background: #22c55e; color: white; }
        .question-nav-btn.unanswered { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }
        .question-nav-btn.current { background: #6366f1; color: white; border-color: #4f46e5; transform: scale(1.1); }
        .question-nav-btn.review { background: #facc15; color: #78350f; }
        .question-nav-btn.review::after { content: ''; position: absolute; top: -2px; right: -2px; width: 8px; height: 8px; background: #dc2626; border-radius: 50%; }
        .option-label { cursor: pointer; transition: all 0.15s; }
        .option-label:hover { border-color: #818cf8; background: #eef2ff; }
        .option-label.selected { border-color: #6366f1; background: #eef2ff; }
        .option-label.selected .option-letter { background: #6366f1; color: white; }
        .timer-critical { animation: timerPulse 1s infinite; }
        @keyframes timerPulse { 0%,100%{ opacity:1; } 50%{ opacity:0.5; } }
        .warning-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 100000; display: flex; align-items: center; justify-content: center; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #c7d2fe; border-radius: 3px; }
        input[type="radio"] { accent-color: #6366f1; width: 16px; height: 16px; }
    </style>
</head>
<body class="h-screen flex flex-col">
    <div id="warningOverlay" class="warning-overlay hidden">
        <div class="text-center text-white p-8 max-w-lg">
            <svg class="w-20 h-20 mx-auto mb-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <h2 class="text-3xl font-bold mb-3">WARNING!</h2>
            <p id="warningMessage" class="text-xl text-red-300 mb-2"></p>
            <p class="text-lg text-gray-300 mb-2">Tab switch detected</p>
            <p class="text-yellow-400 font-bold text-2xl mb-6">Warning <span id="warningCountDisplay">1</span> of 3</p>
            <p class="text-red-400 font-semibold text-lg">3 warnings will auto-submit your exam!</p>
            <button onclick="dismissWarning()" class="mt-6 px-8 py-3 bg-primary-600 text-white font-semibold rounded-xl hover:bg-primary-700 transition-colors">I Understand - Return to Exam</button>
        </div>
    </div>

    <div id="submitOverlay" class="warning-overlay hidden">
        <div class="text-center text-white p-8 max-w-lg">
            <div id="submitSpinner" class="mb-6">
                <svg class="w-16 h-16 mx-auto animate-spin text-primary-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </div>
            <h2 id="submitTitle" class="text-2xl font-bold mb-3">Submitting Exam...</h2>
            <p id="submitMessage" class="text-gray-400">Please do not close this page</p>
        </div>
    </div>

    <div id="resultOverlay" class="warning-overlay hidden" style="background: rgba(15,23,42,0.95);">
        <div class="text-center text-white p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div id="resultContent"></div>
        </div>
    </div>

    <header class="bg-white border-b border-gray-200 px-4 py-2.5 flex items-center justify-between shrink-0 z-50">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
            </div>
            <div>
                <h1 class="font-bold text-gray-800 text-sm leading-tight"><?= htmlspecialchars($exam['exam_name']) ?></h1>
                <p class="text-xs text-gray-500"><?= htmlspecialchars($exam['subject_name']) ?> &middot; <?= htmlspecialchars($exam['class_full']) ?></p>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div id="tabSwitchDisplay" class="hidden items-center gap-1.5 px-3 py-1 bg-red-50 border border-red-200 rounded-lg">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <span class="text-xs font-medium text-red-600">Warnings: <span id="warningCount">0</span>/3</span>
            </div>
            <div class="flex items-center gap-2 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg">
                <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span id="timer" class="font-mono font-bold text-sm text-gray-800">00:00:00</span>
            </div>
            <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 bg-primary-50 border border-primary-200 rounded-lg">
                <span class="text-xs font-medium text-primary-700" id="answeredCount">0</span>
                <span class="text-xs text-primary-400">/</span>
                <span class="text-xs font-medium text-primary-700"><?= count($questions) ?></span>
                <span class="text-xs text-primary-500">answered</span>
            </div>
        </div>
    </header>

    <div class="flex-1 flex overflow-hidden">
        <div class="flex-1 flex flex-col overflow-hidden" id="questionArea">
            <div id="questionContent" class="flex-1 overflow-y-auto p-6"></div>
            <div class="bg-white border-t border-gray-200 px-6 py-3 flex items-center justify-between shrink-0">
                <button id="prevBtn" onclick="prevQuestion()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    Previous
                </button>
                <div class="flex items-center gap-2">
                    <button onclick="toggleMarkReview()" id="reviewBtn" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg transition-colors border">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path></svg>
                        <span id="reviewBtnText">Mark for Review</span>
                    </button>
                </div>
                <button id="nextBtn" onclick="nextQuestion()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                    Next
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>
        </div>

        <div id="navPanel" class="w-72 bg-white border-l border-gray-200 flex flex-col shrink-0 overflow-hidden transition-all" style="width: 288px;">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 text-sm">Questions</h3>
                <button onclick="toggleNavPanel()" class="lg:hidden text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="px-4 py-2 border-b border-gray-100 space-y-1.5">
                <div class="flex items-center gap-2 text-xs">
                    <span class="w-3 h-3 rounded bg-green-500"></span>
                    <span class="text-gray-500">Answered</span>
                    <span class="ml-auto font-semibold text-gray-700" id="answeredLegend">0</span>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="w-3 h-3 rounded bg-red-100 border border-red-300"></span>
                    <span class="text-gray-500">Not Answered</span>
                    <span class="ml-auto font-semibold text-gray-700" id="notAnsweredLegend">0</span>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="w-3 h-3 rounded bg-yellow-400 relative"><span class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 bg-red-500 rounded-full"></span></span>
                    <span class="text-gray-500">Marked</span>
                    <span class="ml-auto font-semibold text-gray-700" id="markedLegend">0</span>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto p-3">
                <div id="questionNavGrid" class="grid grid-cols-5 gap-2"></div>
            </div>
            <div class="p-3 border-t border-gray-100">
                <button onclick="confirmSubmit()" class="w-full py-2.5 bg-emerald-600 text-white font-semibold text-sm rounded-xl hover:bg-emerald-700 transition-colors">
                    Submit Exam
                </button>
            </div>
        </div>

        <button onclick="toggleNavPanel()" id="navToggle" class="hidden fixed right-4 bottom-24 z-50 w-10 h-10 bg-primary-600 text-white rounded-full shadow-lg items-center justify-center hover:bg-primary-700 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
        </button>
    </div>

    <div class="webcam-feed" id="webcamContainer" style="display:none;">
        <video id="webcamVideo" autoplay muted playsinline></video>
        <canvas id="webcamCanvas" style="display:none;"></canvas>
        <div class="absolute top-1 left-1 flex items-center gap-1 px-1.5 py-0.5 bg-black/50 rounded text-white text-[10px] font-medium">
            <span class="w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
            REC
        </div>
    </div>

<script>
(function() {
    const EXAM_ID = <?= $examId ?>;
    const ATTEMPT_ID = <?= $attemptId ?>;
    const BASE_URL = '<?= $baseUrl ?>';
    const DURATION_MINUTES = <?= $exam['duration_minutes'] ?>;
    const TOTAL_QUESTIONS = <?= count($questions) ?>;
    const WEBCAM_REQUIRED = <?= $exam['webcam_required'] ? 'true' : 'false' ?>;
    const SEB_ENABLED = <?= $exam['seb_enabled'] ? 'true' : 'false' ?>;
    const START_TIME = '<?= $startTime ?>';
    const QUESTIONS = <?= $questionsJson ?>;
    const SAVED_ANSWERS = <?= $answersJson ?>;
    const SAVED_REVIEW = <?= $reviewJson ?>;

    let currentQuestion = 0;
    let answers = {};
    let markedForReview = new Set();
    let tabSwitchCount = 0;
    let maxWarnings = 3;
    let isSubmitted = false;
    let navPanelOpen = window.innerWidth > 1024;
    let webcamStream = null;
    let webcamInterval = null;
    let autoSaveInterval = null;
    let activityInterval = null;
    let startTimestamp = new Date(START_TIME.replace(' ', 'T')).getTime();
    let endTimestamp = startTimestamp + (DURATION_MINUTES * 60 * 1000);

    Object.keys(SAVED_ANSWERS).forEach(qid => { answers[qid] = SAVED_ANSWERS[qid]; });
    SAVED_REVIEW.forEach(qid => { markedForReview.add(String(qid)); });

    function init() {
        requestFullScreen();
        renderQuestionNav();
        showQuestion(0);
        startTimer();
        startAutoSave();
        startActivityPing();
        if (WEBCAM_REQUIRED) initWebcam();
        setupSecurity();
        if (!navPanelOpen) document.getElementById('navToggle').classList.remove('hidden');
        document.getElementById('navToggle').classList.add('flex');
    }

    function requestFullScreen() {
        const el = document.documentElement;
        if (el.requestFullscreen) el.requestFullscreen().catch(()=>{});
        else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen().catch(()=>{});
        else if (el.msRequestFullscreen) el.msRequestFullscreen().catch(()=>{});
    }

    function setupSecurity() {
        document.addEventListener('contextmenu', e => { e.preventDefault(); e.stopPropagation(); return false; }, true);
        document.addEventListener('copy', e => { e.preventDefault(); return false; }, true);
        document.addEventListener('paste', e => { e.preventDefault(); return false; }, true);
        document.addEventListener('cut', e => { e.preventDefault(); return false; }, true);
        document.addEventListener('selectstart', e => { if (e.target.tagName !== 'INPUT') e.preventDefault(); }, true);
        document.addEventListener('keydown', function(e) {
            if (isSubmitted) return;
            if (e.key === 'F12') { e.preventDefault(); return false; }
            if (e.key === 'F11') { e.preventDefault(); return false; }
            if (e.ctrlKey && (e.key === 'c' || e.key === 'v' || e.key === 'x' || e.key === 'a' || e.key === 'p' || e.key === 's' || e.key === 'u' || e.key === 'i' || e.key === 'j')) { e.preventDefault(); return false; }
            if (e.altKey && (e.key === 'Tab' || e.key === 'F4' || e.key === 'Enter')) { e.preventDefault(); return false; }
            if (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J' || e.key === 'C' || e.key === 'K')) { e.preventDefault(); return false; }
            if (e.metaKey) { e.preventDefault(); return false; }
        }, true);
        document.addEventListener('keyup', function(e) {
            if (e.key === 'F12' || e.key === 'F11') e.preventDefault();
        }, true);
        window.addEventListener('blur', function() {
            if (!isSubmitted) recordTabSwitch('Window lost focus');
        });
        document.addEventListener('visibilitychange', function() {
            if (document.hidden && !isSubmitted) recordTabSwitch('Tab switched');
        });
        window.addEventListener('beforeunload', function(e) {
            if (!isSubmitted) {
                e.preventDefault();
                e.returnValue = 'Are you sure you want to leave the exam? Your exam will be auto-submitted.';
                return e.returnValue;
            }
        });
        let touchStartY = 0;
        document.addEventListener('touchstart', function(e) {
            touchStartY = e.touches[0].clientY;
        }, { passive: true });
        document.addEventListener('touchmove', function(e) {
            if (e.touches[0].clientY < touchStartY - 50 && window.scrollY === 0) {
                // pull down
            }
        }, { passive: true });
        let mouseLeaveCount = 0;
        document.addEventListener('mouseleave', function(e) {
            if (e.clientY <= 0 && !isSubmitted) {
                recordTabSwitch('Mouse left window');
            }
        });
        let popStateCount = 0;
        window.addEventListener('popstate', function() {
            if (!isSubmitted) {
                history.pushState(null, '', location.href);
                recordTabSwitch('Back button pressed');
            }
        });
        history.pushState(null, '', location.href);
        setInterval(function() {
            if (!isSubmitted && location.hash !== '') {
                history.pushState(null, '', location.href);
            }
        }, 1000);
        document.addEventListener('fullscreenchange', function() {
            if (!document.fullscreenElement && !isSubmitted) {
                setTimeout(() => {
                    if (!document.fullscreenElement) recordTabSwitch('Exited fullscreen');
                }, 2000);
            }
        });
        document.addEventListener('webkitfullscreenchange', function() {
            if (!document.webkitFullscreenElement && !isSubmitted) {
                setTimeout(() => {
                    if (!document.webkitFullscreenElement) recordTabSwitch('Exited fullscreen');
                }, 2000);
            }
        });
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey && e.key === 'Tab') || e.key === 'Meta') {
                e.preventDefault();
                recordTabSwitch('Task switch attempted');
                return false;
            }
        }, true);
        setInterval(function() {
            if (!isSubmitted && !document.hidden) {
                const activeEl = document.activeElement;
                if (activeEl && activeEl.tagName === 'IFRAME') {
                    recordTabSwitch('Iframe focus detected');
                }
            }
        }, 2000);
    }

    function recordTabSwitch(reason) {
        if (isSubmitted) return;
        tabSwitchCount++;
        document.getElementById('warningCount').textContent = tabSwitchCount;
        document.getElementById('warningCountDisplay').textContent = tabSwitchCount;
        document.getElementById('warningMessage').textContent = reason;
        document.getElementById('tabSwitchDisplay').classList.remove('hidden');
        document.getElementById('tabSwitchDisplay').classList.add('flex');
        const overlay = document.getElementById('warningOverlay');
        overlay.classList.remove('hidden');
        fetch(BASE_URL + '/api/record_violation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ attempt_id: ATTEMPT_ID, reason: reason, switch_count: tabSwitchCount })
        }).catch(()=>{});
        if (tabSwitchCount >= maxWarnings) {
            document.getElementById('warningMessage').textContent = 'Maximum warnings reached. Auto-submitting exam...';
            document.querySelector('#warningOverlay button').classList.add('hidden');
            setTimeout(() => {
                submitExam('auto_submit_max_warnings');
            }, 3000);
        }
        requestFullScreen();
    }

    function dismissWarning() {
        document.getElementById('warningOverlay').classList.add('hidden');
        document.querySelector('#warningOverlay button').classList.remove('hidden');
        requestFullScreen();
    }

    function renderQuestionNav() {
        const grid = document.getElementById('questionNavGrid');
        grid.innerHTML = '';
        let answered = 0, notAnswered = 0, marked = 0;
        QUESTIONS.forEach((q, i) => {
            const btn = document.createElement('button');
            btn.className = 'question-nav-btn relative';
            btn.textContent = i + 1;
            if (i === currentQuestion) btn.classList.add('current');
            else if (markedForReview.has(String(q.id))) { btn.classList.add('review'); marked++; }
            else if (answers[q.id]) { btn.classList.add('answered'); answered++; }
            else { btn.classList.add('unanswered'); notAnswered++; }
            btn.onclick = () => showQuestion(i);
            grid.appendChild(btn);
        });
        document.getElementById('answeredLegend').textContent = answered;
        document.getElementById('notAnsweredLegend').textContent = notAnswered;
        document.getElementById('markedLegend').textContent = marked;
        document.getElementById('answeredCount').textContent = answered;
        document.getElementById('prevBtn').disabled = currentQuestion === 0;
        document.getElementById('nextBtn').disabled = currentQuestion === TOTAL_QUESTIONS - 1;
    }

    function showQuestion(index) {
        currentQuestion = index;
        const q = QUESTIONS[index];
        const qId = q.id;
        const selected = answers[qId] || '';
        const isMarked = markedForReview.has(String(qId));

        document.getElementById('reviewBtnText').textContent = isMarked ? 'Unmark Review' : 'Mark for Review';
        const reviewBtn = document.getElementById('reviewBtn');
        if (isMarked) {
            reviewBtn.className = 'inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg transition-colors border border-yellow-400 bg-yellow-50 text-yellow-700';
        } else {
            reviewBtn.className = 'inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg transition-colors border border-gray-200 text-gray-600 hover:bg-gray-50';
        }

        let optionsHtml = '';
        const opts = ['A','B','C','D','E'];
        const optKeys = ['option_a','option_b','option_c','option_d','option_e'];
        optKeys.forEach((key, oi) => {
            if (!q[key]) return;
            const letter = opts[oi];
            const isSelected = selected === letter;
            optionsHtml += `
                <label class="option-label flex items-start gap-3 p-4 rounded-xl border-2 ${isSelected ? 'border-primary-500 bg-primary-50' : 'border-gray-200 bg-white'} cursor-pointer hover:border-primary-300 transition-all mb-3">
                    <input type="radio" name="answer" value="${letter}" ${isSelected ? 'checked' : ''} onchange="setAnswer(${qId}, '${letter}')" class="mt-0.5">
                    <span class="option-letter w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold shrink-0 ${isSelected ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-500'}">${letter}</span>
                    <span class="text-sm text-gray-700 leading-relaxed">${renderLatex(q[key])}</span>
                </label>`;
        });

        document.getElementById('questionContent').innerHTML = `
            <div class="max-w-3xl mx-auto">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 bg-primary-100 text-primary-700 rounded-lg text-xs font-semibold">Q${index + 1} of ${TOTAL_QUESTIONS}</span>
                        <span class="px-2.5 py-1 bg-gray-100 text-gray-600 rounded-lg text-xs font-medium">${q.question_type === 'mcq' ? 'Multiple Choice' : 'Objective'}</span>
                        ${q.marks ? `<span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-lg text-xs font-medium">${q.marks} mark${q.marks > 1 ? 's' : ''}</span>` : ''}
                    </div>
                    ${isMarked ? '<span class="flex items-center gap-1 text-xs font-medium text-yellow-600"><svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path></svg>Marked for Review</span>' : ''}
                </div>
                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-4">
                    <p class="text-gray-800 text-base leading-relaxed"><?= htmlspecialchars($exam['exam_name']) ?> - Question ${index + 1}</p>
                    <p class="text-gray-800 text-base leading-relaxed mt-3 font-medium">${renderLatex(q.question_text)}</p>
                </div>
                <div class="space-y-1">${optionsHtml}</div>
            </div>`;

        renderQuestionNav();
    }

    window.setAnswer = function(qId, answer) {
        answers[qId] = answer;
        renderQuestionNav();
    };

    window.toggleMarkReview = function() {
        const qId = QUESTIONS[currentQuestion].id;
        if (markedForReview.has(String(qId))) {
            markedForReview.delete(String(qId));
        } else {
            markedForReview.add(String(qId));
        }
        showQuestion(currentQuestion);
    };

    window.nextQuestion = function() {
        if (currentQuestion < TOTAL_QUESTIONS - 1) showQuestion(currentQuestion + 1);
    };

    window.prevQuestion = function() {
        if (currentQuestion > 0) showQuestion(currentQuestion - 1);
    };

    window.toggleNavPanel = function() {
        const panel = document.getElementById('navPanel');
        navPanelOpen = !navPanelOpen;
        if (navPanelOpen) {
            panel.style.width = '288px';
            panel.style.minWidth = '288px';
            document.getElementById('navToggle').classList.add('hidden');
            document.getElementById('navToggle').classList.remove('flex');
        } else {
            panel.style.width = '0';
            panel.style.minWidth = '0';
            panel.style.padding = '0';
            panel.style.overflow = 'hidden';
            document.getElementById('navToggle').classList.remove('hidden');
            document.getElementById('navToggle').classList.add('flex');
        }
        setTimeout(() => {
            panel.style.overflow = '';
            panel.style.padding = '';
        }, 300);
    };

    function startTimer() {
        updateTimer();
        setInterval(updateTimer, 1000);
    }

    function updateTimer() {
        if (isSubmitted) return;
        const now = Date.now();
        let remaining = endTimestamp - now;
        if (remaining <= 0) {
            remaining = 0;
            document.getElementById('timer').textContent = '00:00:00';
            document.getElementById('timer').classList.add('text-red-600', 'timer-critical');
            submitExam('timeout');
            return;
        }
        const hours = Math.floor(remaining / 3600000);
        const mins = Math.floor((remaining % 3600000) / 60000);
        const secs = Math.floor((remaining % 60000) / 1000);
        const display = String(hours).padStart(2,'0') + ':' + String(mins).padStart(2,'0') + ':' + String(secs).padStart(2,'0');
        document.getElementById('timer').textContent = display;
        const timerEl = document.getElementById('timer');
        if (remaining < 300000) {
            timerEl.classList.add('text-red-600', 'timer-critical');
            timerEl.parentElement.classList.remove('bg-gray-50', 'border-gray-200');
            timerEl.parentElement.classList.add('bg-red-50', 'border-red-200');
        } else if (remaining < 600000) {
            timerEl.classList.add('text-amber-600');
            timerEl.parentElement.classList.remove('bg-gray-50', 'border-gray-200');
            timerEl.parentElement.classList.add('bg-amber-50', 'border-amber-200');
        }
    }

    function startAutoSave() {
        autoSaveInterval = setInterval(() => {
            if (isSubmitted) return;
            saveAnswers();
        }, 10000);
    }

    function saveAnswers() {
        const data = {
            attempt_id: ATTEMPT_ID,
            exam_id: EXAM_ID,
            answers: answers,
            marked_for_review: Array.from(markedForReview).map(Number)
        };
        fetch(BASE_URL + '/api/save_answer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).catch(()=>{});
    }

    function startActivityPing() {
        activityInterval = setInterval(() => {
            if (isSubmitted) return;
            fetch(BASE_URL + '/api/activity_ping.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ attempt_id: ATTEMPT_ID })
            }).catch(()=>{});
        }, 60000);
    }

    async function initWebcam() {
        try {
            webcamStream = await navigator.mediaDevices.getUserMedia({ video: { width: 320, height: 240, facingMode: 'user' }, audio: false });
            const video = document.getElementById('webcamVideo');
            video.srcObject = webcamStream;
            document.getElementById('webcamContainer').style.display = 'block';
            webcamInterval = setInterval(captureWebcam, 30000);
            captureWebcam();
        } catch(e) {
            console.warn('Webcam not available:', e);
        }
    }

    function captureWebcam() {
        if (!webcamStream || isSubmitted) return;
        const video = document.getElementById('webcamVideo');
        const canvas = document.getElementById('webcamCanvas');
        canvas.width = video.videoWidth || 320;
        canvas.height = video.videoHeight || 240;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function(blob) {
            if (!blob) return;
            const formData = new FormData();
            formData.append('image', blob, 'webcam.jpg');
            formData.append('attempt_id', ATTEMPT_ID);
            formData.append('exam_id', EXAM_ID);
            fetch(BASE_URL + '/api/webcam_capture.php', { method: 'POST', body: formData }).catch(()=>{});
        }, 'image/jpeg', 0.6);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderLatex(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        let s = div.innerHTML;
        const blocks = [];

        s = s.replace(/\$\$([\s\S]+?)\$\$/g, (m) => {
            blocks.push(m); return '\x00BLK' + (blocks.length - 1) + '\x00';
        });
        s = s.replace(/\$([^$\n]+?)\$/g, (m) => {
            blocks.push(m); return '\x00BLK' + (blocks.length - 1) + '\x00';
        });

        s = s.replace(/(\\begin\{[^}]+\}[\s\S]*?\\end\{[^}]+\})/g, (m) => {
            blocks.push('$$' + m + '$$'); return '\x00BLK' + (blocks.length - 1) + '\x00';
        });

        s = s.replace(/(\\(?:frac|sqrt|dfrac|tfrac|text|mathrm|mathbf|operatorname|binom|vec|hat|widehat|widetilde|overline|underline|overbrace|underbrace|stackrel)(?:\s*\{[^}]*\}){1,2})/g, (m) => '$' + m + '$');

        s = s.replace(/(\\(?:sin|cos|tan|cot|sec|csc|arcsin|arccos|arctan|sinh|cosh|tanh|log|ln|exp|lim|sup|inf|max|min|det|dim|gcd|deg|ker|hom|arg|hom|mod|bmod|pmod)(?:\s*)?)/g, (m) => '$' + m + '$');

        s = s.replace(/(\\(?:times|pm|mp|cdot|div|leq|geq|le|ge|neq|ne|approx|equiv|sim|simeq|cong|propto|ll|gg|partial|nabla|infty|emptyset|varnothing|forall|exists|nexists|neg|land|lor|oplus|otimes|perp|parallel|angle|triangle|triangleq|circ|bullet|star|dagger|ddagger|prime|angle|measuredangle|sphericalangle|ell|Re|Im|aleph|wp|complement|oslash|circledS|textregistered|copyright|texttrademark|varnothing)\b)/g, (m) => '$' + m + '$');

        s = s.replace(/(\\(?:alpha|beta|gamma|delta|epsilon|varepsilon|zeta|eta|theta|vartheta|iota|kappa|lambda|mu|nu|xi|pi|varpi|rho|varrho|sigma|varsigma|tau|upsilon|phi|varphi|chi|psi|omega|Gamma|Delta|Theta|Lambda|Xi|Pi|Sigma|Upsilon|Phi|Psi|Omega|varkappa|digamma)\b)/g, (m) => '$' + m + '$');

        s = s.replace(/(\\(?:Rightarrow|Leftarrow|Leftrightarrow|rightarrow|leftarrow|leftrightarrow|mapsto|hookrightarrow|hookleftarrow|nearrow|searrow|nwarrow|swarrow|uparrow|downarrow|updownarrow|Uparrow|Downarrow|Updownarrow|Longrightarrow|Longleftarrow|Longleftrightarrow|iff|implies|impliedby)\b)/g, (m) => '$' + m + '$');

        s = s.replace(/(\\(?:quad|qquad|,|;|:|!|enspace|hphantom|vphantom|phantom|mathord|mathop|mathopen|mathclose|mathpunct|mathrel|mathbin|mathinner|mathletter|mathrm|mathit|mathbf|mathsf|mathtt|mathcal|mathbb|mathfrak|mathscr)\b)/g, (m) => '$' + m + '$');

        s = s.replace(/(\\(?:ce|pu|xsym|cancel|enclose|overset|underset|stackrel|xleftarrow|xrightarrow|dbinom|tbinom|binom|overset|underset)\b(?:\s*\{[^}]*\})*)/g, (m) => '$' + m + '$');

        s = s.replace(/(\\[a-zA-Z]+)/g, (m) => '$' + m + '$');

        s = s.replace(/([\w\)\]])\^(\{[^}]+\}|[0-9a-zA-Z])/g, (m, pre, post) => '$' + pre + '^' + post + '$');
        s = s.replace(/([\w\)\]])_(\{[^}]+\}|[0-9a-zA-Z])/g, (m, pre, post) => '$' + pre + '_' + post + '$');

        for (let i = 0; i < blocks.length; i++) {
            s = s.replace('\x00BLK' + i + '\x00', blocks[i]);
        }

        s = s.replace(/\$\$\s*\$\$/g, '');

        if (typeof katex !== 'undefined') {
            s = s.replace(/\$\$([\s\S]+?)\$\$/g, (m, tex) => {
                try { return katex.renderToString(tex.trim(), { displayMode: true, throwOnError: false }); }
                catch(e) { return m; }
            });
            s = s.replace(/\$([^\$]+?)\$/g, (m, tex) => {
                try { return katex.renderToString(tex.trim(), { displayMode: false, throwOnError: false }); }
                catch(e) { return m; }
            });
        }

        return s;
    }

    window.confirmSubmit = function() {
        const answeredCount = Object.keys(answers).length;
        const unansweredCount = TOTAL_QUESTIONS - answeredCount;
        let msg = 'Are you sure you want to submit the exam?';
        if (unansweredCount > 0) {
            msg = `You have ${unansweredCount} unanswered question${unansweredCount > 1 ? 's' : ''}. Are you sure you want to submit?`;
        }
        if (confirm(msg)) {
            submitExam('manual');
        }
    };

    function submitExam(reason) {
        if (isSubmitted) return;
        isSubmitted = true;
        clearInterval(autoSaveInterval);
        clearInterval(activityInterval);
        clearInterval(webcamInterval);
        if (webcamStream) {
            webcamStream.getTracks().forEach(t => t.stop());
        }
        document.getElementById('submitOverlay').classList.remove('hidden');
        document.getElementById('warningOverlay').classList.add('hidden');
        document.getElementById('resultOverlay').classList.add('hidden');
        const data = {
            attempt_id: ATTEMPT_ID,
            exam_id: EXAM_ID,
            answers: answers,
            marked_for_review: Array.from(markedForReview).map(Number),
            reason: reason,
            tab_switches: tabSwitchCount
        };
        fetch(BASE_URL + '/api/submit_exam.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showResults(data);
            } else {
                document.getElementById('submitTitle').textContent = 'Submission Failed';
                document.getElementById('submitMessage').textContent = data.error || 'An error occurred. Please try again.';
                setTimeout(() => { isSubmitted = false; document.getElementById('submitOverlay').classList.add('hidden'); }, 3000);
            }
        })
        .catch(err => {
            fetch(BASE_URL + '/api/submit_exam.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            }).then(r => r.json()).then(d => {
                if (d.success) showResults(d);
                else {
                    document.getElementById('submitTitle').textContent = 'Submission Error';
                    document.getElementById('submitMessage').textContent = 'Network error. Your answers have been saved locally.';
                }
            }).catch(() => {
                document.getElementById('submitTitle').textContent = 'Submission Error';
                document.getElementById('submitMessage').textContent = 'Please contact your administrator.';
            });
        });
    }

    function showResults(data) {
        document.getElementById('submitOverlay').classList.add('hidden');
        const overlay = document.getElementById('resultOverlay');
        overlay.classList.remove('hidden');
        const passed = data.percentage >= <?= $exam['passing_marks'] / $exam['total_marks'] * 100 ?>;
        document.getElementById('resultContent').innerHTML = `
            <div class="mb-8">
                <div class="w-20 h-20 mx-auto mb-4 rounded-full flex items-center justify-center ${passed ? 'bg-emerald-500' : 'bg-red-500'}">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${passed ? 'M5 13l4 4L19 7' : 'M6 18L18 6M6 6l12 12'}"></path></svg>
                </div>
                <h2 class="text-3xl font-bold text-white mb-2">${passed ? 'Congratulations!' : 'Keep Trying!'}</h2>
                <p class="text-gray-400">Your exam has been submitted successfully</p>
            </div>
            <div class="grid grid-cols-3 gap-4 mb-8">
                <div class="bg-white/10 rounded-xl p-4 backdrop-blur">
                    <p class="text-3xl font-bold text-white">${data.score}/${data.max_score}</p>
                    <p class="text-gray-400 text-sm mt-1">Score</p>
                </div>
                <div class="bg-white/10 rounded-xl p-4 backdrop-blur">
                    <p class="text-3xl font-bold text-white">${data.percentage}%</p>
                    <p class="text-gray-400 text-sm mt-1">Percentage</p>
                </div>
                <div class="bg-white/10 rounded-xl p-4 backdrop-blur">
                    <p class="text-3xl font-bold ${passed ? 'text-emerald-400' : 'text-red-400'}">${passed ? 'PASS' : 'FAIL'}</p>
                    <p class="text-gray-400 text-sm mt-1">Status</p>
                </div>
            </div>
            ${data.tab_switches > 0 ? `<p class="text-amber-400 text-sm mb-4">Tab switches detected: ${data.tab_switches}</p>` : ''}
            <a href="results.php?view=${EXAM_ID}" class="inline-flex items-center gap-2 px-6 py-3 bg-primary-600 text-white font-semibold rounded-xl hover:bg-primary-700 transition-colors">
                View Detailed Results
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </a>
            <div class="mt-4">
                <a href="my_exams.php" class="text-gray-400 hover:text-white text-sm transition-colors">Back to My Exams</a>
            </div>`;
    }

    document.addEventListener('DOMContentLoaded', init);
})();
</script>
</body>
</html>
