<?php
require_once __DIR__ . '/../config/database.php';
requireRole('teacher');
$teacherId = $_SESSION['user_id'];
$pageTitle = 'My Subjects';
include __DIR__ . '/../includes/header.php';

$assignments = $conn->query("SELECT ta.id, ta.subject_id, ta.class_id,
    s.subject_name, s.subject_code, s.board,
    c.class_name, c.section, CONCAT(c.class_name, ' ', c.section) as class_full,
    (SELECT COUNT(DISTINCT sc2.student_id) FROM student_classes sc2 WHERE sc2.class_id = ta.class_id) as student_count
    FROM teacher_assignments ta
    JOIN subjects s ON ta.subject_id = s.id
    JOIN classes c ON ta.class_id = c.id
    WHERE ta.teacher_id = $teacherId
    ORDER BY c.class_name, c.section, s.subject_name")->fetch_all(MYSQLI_ASSOC);

$grouped = [];
foreach ($assignments as $a) {
    $key = $a['class_full'];
    $grouped[$key][] = $a;
}
?>

<?php if (empty($grouped)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
    <h3 class="text-lg font-semibold text-gray-600 mb-1">No Subjects Assigned</h3>
    <p class="text-sm text-gray-400">Contact your administrator to get subjects assigned.</p>
</div>
<?php else: ?>

<div class="flex items-center gap-3 mb-6">
    <h2 class="text-lg font-semibold text-gray-800">My Subjects</h2>
    <span class="bg-gray-100 text-gray-600 text-xs font-medium px-2.5 py-1 rounded-full"><?= count($assignments) ?> assignment<?= count($assignments) != 1 ? 's' : '' ?></span>
</div>

<div class="space-y-6">
    <?php foreach ($grouped as $class_name => $subjects): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-primary-100 text-primary-600 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($class_name) ?></h3>
                    <p class="text-xs text-gray-400"><?= count($subjects) ?> subject<?= count($subjects) != 1 ? 's' : '' ?></p>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 bg-gray-50/50">
                        <th class="px-6 py-3 font-medium">#</th>
                        <th class="px-6 py-3 font-medium">Subject</th>
                        <th class="px-6 py-3 font-medium">Code</th>
                        <th class="px-6 py-3 font-medium">Board</th>
                        <th class="px-6 py-3 font-medium text-right">Students</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php $sn = 1; foreach ($subjects as $s): ?>
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-6 py-3 text-gray-400"><?= $sn++ ?></td>
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                </div>
                                <span class="font-medium text-gray-800"><?= htmlspecialchars($s['subject_name']) ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-medium bg-gray-100 text-gray-600"><?= htmlspecialchars($s['subject_code'] ?: '-') ?></span>
                        </td>
                        <td class="px-6 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $s['board'] === 'CBSE' ? 'bg-blue-100 text-blue-700' : ($s['board'] === 'STATE' ? 'bg-orange-100 text-orange-700' : 'bg-purple-100 text-purple-700') ?>"><?= $s['board'] ?></span>
                        </td>
                        <td class="px-6 py-3 text-right">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-700"><?= $s['student_count'] ?> students</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
