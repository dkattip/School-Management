<?php
require_once __DIR__ . '/../config/database.php';
requireRole('student');
$studentId = $_SESSION['user_id'];
$pageTitle = 'Student Dashboard';
include __DIR__ . '/../includes/header.php';

$studentInfo = $conn->query("SELECT sc.*, CONCAT(c.class_name, ' ', c.section) as class_full, c.class_name, c.section
    FROM student_classes sc
    JOIN classes c ON sc.class_id = c.id
    WHERE sc.student_id = $studentId LIMIT 1")->fetch_assoc();

$classId = $studentInfo['class_id'] ?? 0;
$rollNumber = $studentInfo['roll_number'] ?? '-';
$admissionNumber = $studentInfo['admission_number'] ?? '-';

$stats = [];
$stats['total_exams'] = $conn->query("SELECT COUNT(*) as c FROM exams e
    WHERE e.class_id = $classId AND e.is_active = 1 AND e.start_time <= NOW() AND e.end_time >= NOW()
    AND (e.assign_mode = 'class' OR (e.assign_mode = 'selected' AND EXISTS (SELECT 1 FROM exam_student_assignments WHERE exam_id = e.id AND student_id = $studentId)))")->fetch_assoc()['c'];

$stats['exams_taken'] = $conn->query("SELECT COUNT(*) as c FROM exam_attempts
    WHERE student_id = $studentId AND status = 'completed'")->fetch_assoc()['c'];

$avgRow = $conn->query("SELECT ROUND(AVG(percentage), 1) as avg_score FROM exam_attempts
    WHERE student_id = $studentId AND status = 'completed'")->fetch_assoc();
$stats['avg_score'] = $avgRow['avg_score'] ?? 0;

$rankRow = $conn->query("SELECT COUNT(*) + 1 as rank_num FROM exam_attempts ea
    JOIN exams e ON ea.exam_id = e.id
    WHERE e.class_id = $classId AND ea.status = 'completed'
    AND ea.percentage > (SELECT COALESCE(AVG(percentage), 0) FROM exam_attempts WHERE student_id = $studentId AND status = 'completed')
    GROUP BY ea.student_id")->fetch_assoc();
$stats['rank'] = $rankRow['rank_num'] ?? '-';

$recent_results = $conn->query("SELECT ea.*, e.exam_name, s.subject_name,
    ROUND(ea.percentage, 1) as pct
    FROM exam_attempts ea
    JOIN exams e ON ea.exam_id = e.id
    JOIN subjects s ON e.subject_id = s.id
    WHERE ea.student_id = $studentId AND ea.status = 'completed'
    ORDER BY ea.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

$upcoming_exams = $conn->query("SELECT e.*, s.subject_name, CONCAT(c.class_name, ' ', c.section) as class_full,
    (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) as q_count,
    (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id AND student_id = $studentId) as my_attempts
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    JOIN classes c ON e.class_id = c.id
    WHERE e.class_id = $classId AND e.is_active = 1
    AND (e.assign_mode = 'class' OR (e.assign_mode = 'selected' AND EXISTS (SELECT 1 FROM exam_student_assignments WHERE exam_id = e.id AND student_id = $studentId)))
    AND ((e.start_time <= NOW() AND e.end_time >= NOW()) OR e.start_time > NOW())
    AND (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id AND student_id = $studentId AND status = 'completed') = 0
    ORDER BY e.start_time ASC LIMIT 6")->fetch_all(MYSQLI_ASSOC);
?>

<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
            <div class="w-16 h-16 bg-primary-100 text-primary-600 rounded-2xl flex items-center justify-center text-2xl font-bold">
                <?= strtoupper(substr($_SESSION['full_name'], 0, 2)) ?>
            </div>
            <div class="flex-1">
                <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($_SESSION['full_name']) ?></h2>
                <div class="flex flex-wrap gap-4 mt-1 text-sm text-gray-500">
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        <?= $studentInfo ? htmlspecialchars($studentInfo['class_full']) : 'Not Assigned' ?>
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path></svg>
                        Roll: <?= htmlspecialchars($rollNumber) ?>
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        Admission: <?= htmlspecialchars($admissionNumber) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-indigo-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?= $stats['total_exams'] ?></p>
                    <p class="text-xs text-gray-500">Available Exams</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-purple-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?= $stats['exams_taken'] ?></p>
                    <p class="text-xs text-gray-500">Exams Taken</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-emerald-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?= $stats['avg_score'] ?>%</p>
                    <p class="text-xs text-gray-500">Average Score</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-amber-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">#<?= $stats['rank'] ?></p>
                    <p class="text-xs text-gray-500">Class Rank</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Recent Results</h3>
                <a href="results.php" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 bg-gray-50">
                            <th class="px-6 py-3 font-medium">Exam</th>
                            <th class="px-6 py-3 font-medium">Score</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (empty($recent_results)): ?>
                        <tr><td colspan="3" class="px-6 py-8 text-center text-gray-400">No results yet</td></tr>
                        <?php else: foreach ($recent_results as $r): ?>
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-6 py-3">
                                <p class="font-medium text-gray-800"><?= htmlspecialchars($r['exam_name']) ?></p>
                                <p class="text-xs text-gray-400"><?= htmlspecialchars($r['subject_name']) ?></p>
                            </td>
                            <td class="px-6 py-3">
                                <span class="font-semibold <?= $r['pct'] >= 80 ? 'text-green-600' : ($r['pct'] >= 50 ? 'text-amber-600' : 'text-red-500') ?>"><?= $r['total_score'] ?>/<?= $r['max_score'] ?></span>
                                <span class="text-gray-400 text-xs">(<?= $r['pct'] ?>%)</span>
                            </td>
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $r['percentage'] >= 33 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                                    <?= $r['percentage'] >= 33 ? 'Pass' : 'Fail' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Upcoming / Available Exams</h3>
                <a href="my_exams.php" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View All</a>
            </div>
            <div class="divide-y divide-gray-50">
                <?php if (empty($upcoming_exams)): ?>
                <p class="px-6 py-8 text-center text-gray-400 text-sm">No upcoming exams</p>
                <?php else: foreach ($upcoming_exams as $exam): ?>
                <div class="px-6 py-4 hover:bg-gray-50/50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-800 truncate"><?= htmlspecialchars($exam['exam_name']) ?></p>
                            <div class="flex items-center gap-2 text-xs text-gray-400 mt-1">
                                <span><?= htmlspecialchars($exam['subject_name']) ?></span>
                                <span>&middot;</span>
                                <span><?= $exam['duration_minutes'] ?> min</span>
                                <span>&middot;</span>
                                <span><?= $exam['total_marks'] ?> marks</span>
                            </div>
                        </div>
                        <div class="text-right ml-4">
                            <?php if ($exam['start_time'] > date('Y-m-d H:i:s')): ?>
                            <p class="text-xs font-medium text-amber-600">Starts</p>
                            <p class="text-xs text-gray-400"><?= date('d M, h:i A', strtotime($exam['start_time'])) ?></p>
                            <?php else: ?>
                            <a href="take_exam.php?exam_id=<?= $exam['id'] ?>" class="inline-flex items-center gap-1 px-3 py-1.5 bg-primary-600 text-white text-xs font-medium rounded-lg hover:bg-primary-700 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Start
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
