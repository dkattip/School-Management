<?php
require_once __DIR__ . '/../config/database.php';
requireRole('admin');
$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';

$stats = [];
$stats['students'] = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='student'")->fetch_assoc()['c'];
$stats['teachers'] = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='teacher'")->fetch_assoc()['c'];
$stats['classes']  = $conn->query("SELECT COUNT(*) as c FROM classes")->fetch_assoc()['c'];
$stats['exams']    = $conn->query("SELECT COUNT(*) as c FROM exams")->fetch_assoc()['c'];
$stats['active_exams'] = $conn->query("SELECT COUNT(*) as c FROM exams WHERE is_active=1 AND end_time >= NOW()")->fetch_assoc()['c'];
$stats['total_attempts'] = $conn->query("SELECT COUNT(*) as c FROM exam_attempts WHERE status='completed'")->fetch_assoc()['c'];

$recent_exams = $conn->query("SELECT e.*, s.subject_name, CONCAT(c.class_name,' ',c.section) as class_full,
    u.full_name as teacher_name,
    (SELECT COUNT(*) FROM questions WHERE exam_id=e.id) as q_count,
    (SELECT COUNT(*) FROM exam_attempts WHERE exam_id=e.id AND status='completed') as attempt_count
    FROM exams e
    JOIN subjects s ON e.subject_id=s.id
    JOIN classes c ON e.class_id=c.id
    JOIN users u ON e.created_by=u.id
    ORDER BY e.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

$top_performers = $conn->query("SELECT u.full_name, sc.class_name, sc.section, ea.total_score, ea.max_score,
    ROUND(ea.percentage,1) as pct, e.exam_name
    FROM exam_attempts ea
    JOIN users u ON ea.student_id=u.id
    JOIN exams e ON ea.exam_id=e.id
    JOIN classes sc ON e.class_id=sc.id
    WHERE ea.status='completed'
    ORDER BY ea.percentage DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
?>

<div class="space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-blue-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?= $stats['students'] ?></p>
                    <p class="text-xs text-gray-500">Students</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-green-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?= $stats['teachers'] ?></p>
                    <p class="text-xs text-gray-500">Teachers</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-purple-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?= $stats['classes'] ?></p>
                    <p class="text-xs text-gray-500">Classes</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-amber-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?= $stats['exams'] ?></p>
                    <p class="text-xs text-gray-500">Total Exams</p>
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
                <div class="w-11 h-11 bg-rose-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800"><?= $stats['total_attempts'] ?></p>
                    <p class="text-xs text-gray-500">Completed</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Recent Exams</h3>
                <a href="exams.php" class="text-sm text-primary-600 hover:text-primary-700 font-medium">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 bg-gray-50">
                            <th class="px-6 py-3 font-medium">Exam</th>
                            <th class="px-6 py-3 font-medium">Class</th>
                            <th class="px-6 py-3 font-medium">Teacher</th>
                            <th class="px-6 py-3 font-medium">Questions</th>
                            <th class="px-6 py-3 font-medium">Attempts</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (empty($recent_exams)): ?>
                        <tr><td colspan="6" class="px-6 py-8 text-center text-gray-400">No exams created yet</td></tr>
                        <?php else: foreach ($recent_exams as $exam): ?>
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-6 py-3">
                                <p class="font-medium text-gray-800"><?= htmlspecialchars($exam['exam_name']) ?></p>
                                <p class="text-xs text-gray-400"><?= ucfirst($exam['exam_type']) ?></p>
                            </td>
                            <td class="px-6 py-3 text-gray-600"><?= htmlspecialchars($exam['class_full']) ?></td>
                            <td class="px-6 py-3 text-gray-600"><?= htmlspecialchars($exam['teacher_name']) ?></td>
                            <td class="px-6 py-3 text-gray-600"><?= $exam['q_count'] ?></td>
                            <td class="px-6 py-3 text-gray-600"><?= $exam['attempt_count'] ?></td>
                            <td class="px-6 py-3">
                                <?php if ($exam['is_active']): ?>
                                    <?php if ($exam['end_time'] >= date('Y-m-d H:i:s')): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Ended</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-600">Disabled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Top Performers</h3>
            </div>
            <div class="divide-y divide-gray-50">
                <?php if (empty($top_performers)): ?>
                <p class="px-6 py-8 text-center text-gray-400 text-sm">No results yet</p>
                <?php else: foreach ($top_performers as $idx => $p): ?>
                <div class="px-6 py-3 flex items-center gap-3 hover:bg-gray-50/50">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                        <?= $idx === 0 ? 'bg-yellow-100 text-yellow-700' : ($idx === 1 ? 'bg-gray-200 text-gray-600' : ($idx === 2 ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-500')) ?>">
                        <?= $idx + 1 ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate"><?= htmlspecialchars($p['full_name']) ?></p>
                        <p class="text-xs text-gray-400"><?= htmlspecialchars($p['class_name'].' '.$p['section']) ?> &middot; <?= htmlspecialchars($p['exam_name']) ?></p>
                    </div>
                    <span class="text-sm font-semibold <?= $p['pct'] >= 80 ? 'text-green-600' : ($p['pct'] >= 50 ? 'text-amber-600' : 'text-red-500') ?>">
                        <?= $p['pct'] ?>%
                    </span>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-800 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <a href="students.php?action=add" class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 hover:border-primary-300 hover:bg-primary-50 transition-all group">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                </div>
                <span class="text-sm font-medium text-gray-700 group-hover:text-primary-600">Add Student</span>
            </a>
            <a href="teachers.php?action=add" class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 hover:border-primary-300 hover:bg-primary-50 transition-all group">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center group-hover:bg-green-200 transition-colors">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <span class="text-sm font-medium text-gray-700 group-hover:text-primary-600">Add Teacher</span>
            </a>
            <a href="classes.php?action=add" class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 hover:border-primary-300 hover:bg-primary-50 transition-all group">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center group-hover:bg-purple-200 transition-colors">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
                <span class="text-sm font-medium text-gray-700 group-hover:text-primary-600">Add Class</span>
            </a>
            <a href="settings.php" class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 hover:border-primary-300 hover:bg-primary-50 transition-all group">
                <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center group-hover:bg-amber-200 transition-colors">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </div>
                <span class="text-sm font-medium text-gray-700 group-hover:text-primary-600">Settings</span>
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
