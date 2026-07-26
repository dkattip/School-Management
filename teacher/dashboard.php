<?php
require_once __DIR__ . '/../config/database.php';
requireRole('teacher');
$teacherId = $_SESSION['user_id'];
$pageTitle = 'Teacher Dashboard';
include __DIR__ . '/../includes/header.php';

$stats = [];
$stats['my_subjects'] = $conn->query("SELECT COUNT(*) as c FROM teacher_assignments WHERE teacher_id=$teacherId")->fetch_assoc()['c'];
$stats['my_exams'] = $conn->query("SELECT COUNT(*) as c FROM exams WHERE created_by=$teacherId")->fetch_assoc()['c'];
$stats['active_exams'] = $conn->query("SELECT COUNT(*) as c FROM exams WHERE created_by=$teacherId AND is_active=1 AND end_time >= NOW()")->fetch_assoc()['c'];

$assigned_class_ids = $conn->query("SELECT DISTINCT class_id FROM teacher_assignments WHERE teacher_id=$teacherId")->fetch_all(MYSQLI_ASSOC);
$class_ids = array_column($assigned_class_ids, 'class_id');
$class_ids_sql = !empty($class_ids) ? implode(',', array_map('intval', $class_ids)) : '0';
$stats['total_students'] = $conn->query("SELECT COUNT(DISTINCT sc.student_id) as c FROM student_classes sc WHERE sc.class_id IN ($class_ids_sql)")->fetch_assoc()['c'];

$recent_results = $conn->query("SELECT ea.*, e.exam_name, s.subject_name, u.full_name as student_name,
    ROUND(ea.percentage, 1) as pct
    FROM exam_attempts ea
    JOIN exams e ON ea.exam_id = e.id
    JOIN subjects s ON e.subject_id = s.id
    JOIN users u ON ea.student_id = u.id
    WHERE e.created_by = $teacherId AND ea.status = 'completed'
    ORDER BY ea.created_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

$upcoming_exams = $conn->query("SELECT e.*, s.subject_name, CONCAT(c.class_name, ' ', c.section) as class_full,
    (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) as q_count
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    JOIN classes c ON e.class_id = c.id
    WHERE e.created_by = $teacherId AND e.is_active = 1 AND e.start_time > NOW()
    ORDER BY e.start_time ASC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
?>

<div class="space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-indigo-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?= $stats['my_subjects'] ?></p>
                    <p class="text-xs text-gray-500">My Subjects</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-purple-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?= $stats['my_exams'] ?></p>
                    <p class="text-xs text-gray-500">My Exams</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-emerald-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?= $stats['active_exams'] ?></p>
                    <p class="text-xs text-gray-500">Active Exams</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-amber-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?= $stats['total_students'] ?></p>
                    <p class="text-xs text-gray-500">Students I Teach</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <a href="create_exam.php" class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 hover:border-primary-300 hover:bg-primary-50 transition-all group">
                <div class="w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center group-hover:bg-primary-200 transition-colors">
                    <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                </div>
                <span class="text-sm font-medium text-gray-700 group-hover:text-primary-600">Create Exam</span>
            </a>
            <a href="results.php" class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 hover:border-primary-300 hover:bg-primary-50 transition-all group">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center group-hover:bg-green-200 transition-colors">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </div>
                <span class="text-sm font-medium text-gray-700 group-hover:text-primary-600">View Results</span>
            </a>
            <a href="bulk_import.php" class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 hover:border-primary-300 hover:bg-primary-50 transition-all group">
                <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center group-hover:bg-amber-200 transition-colors">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                </div>
                <span class="text-sm font-medium text-gray-700 group-hover:text-primary-600">Bulk Import</span>
            </a>
            <a href="students.php" class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 hover:border-primary-300 hover:bg-primary-50 transition-all group">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center group-hover:bg-purple-200 transition-colors">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </div>
                <span class="text-sm font-medium text-gray-700 group-hover:text-primary-600">My Students</span>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Recent Exam Results</h3>
                <a href="results.php" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 bg-gray-50">
                            <th class="px-6 py-3 font-medium">Student</th>
                            <th class="px-6 py-3 font-medium">Exam</th>
                            <th class="px-6 py-3 font-medium">Score</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (empty($recent_results)): ?>
                        <tr><td colspan="4" class="px-6 py-8 text-center text-gray-400">No results yet</td></tr>
                        <?php else: foreach ($recent_results as $r): ?>
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 bg-primary-100 text-primary-600 rounded-full flex items-center justify-center font-bold text-xs"><?= strtoupper(substr($r['student_name'], 0, 1)) ?></div>
                                    <span class="font-medium text-gray-800"><?= htmlspecialchars($r['student_name']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-3 text-gray-600 text-xs"><?= htmlspecialchars($r['exam_name']) ?></td>
                            <td class="px-6 py-3">
                                <span class="font-semibold <?= $r['pct'] >= 80 ? 'text-green-600' : ($r['pct'] >= 50 ? 'text-amber-600' : 'text-red-500') ?>"><?= $r['total_score'] ?>/<?= $r['max_score'] ?></span>
                                <span class="text-gray-400 text-xs">(<?= $r['pct'] ?>%)</span>
                            </td>
                            <td class="px-6 py-3">
                                <?php if ($r['pct'] >= 33): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $r['pct'] >= 33 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                                    Pass
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-600">
                                    Fail
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Upcoming Exams</h3>
                <a href="exams.php" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View All</a>
            </div>
            <div class="divide-y divide-gray-50">
                <?php if (empty($upcoming_exams)): ?>
                <p class="px-6 py-8 text-center text-gray-400 text-sm">No upcoming exams</p>
                <?php else: foreach ($upcoming_exams as $exam): ?>
                <div class="px-6 py-3 hover:bg-gray-50/50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-800 truncate"><?= htmlspecialchars($exam['exam_name']) ?></p>
                            <div class="flex items-center gap-2 text-xs text-gray-400 mt-1">
                                <span><?= htmlspecialchars($exam['subject_name']) ?></span>
                                <span>&middot;</span>
                                <span><?= htmlspecialchars($exam['class_full']) ?></span>
                                <span>&middot;</span>
                                <span><?= $exam['q_count'] ?> Q</span>
                            </div>
                        </div>
                        <div class="text-right ml-4">
                            <p class="text-xs font-medium text-primary-600"><?= date('d M', strtotime($exam['start_time'])) ?></p>
                            <p class="text-xs text-gray-400"><?= date('h:i A', strtotime($exam['start_time'])) ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
