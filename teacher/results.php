<?php
require_once __DIR__ . '/../config/database.php';
requireRole('teacher');
$teacherId = $_SESSION['user_id'];
$pageTitle = 'Exam Results';
include __DIR__ . '/../includes/header.php';

$exams_list = $conn->query("SELECT e.id, e.exam_name, e.total_marks, e.passing_marks, s.subject_name, CONCAT(c.class_name, ' ', c.section) as class_full
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    JOIN classes c ON e.class_id = c.id
    WHERE e.created_by = $teacherId
    ORDER BY e.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$exam_filter = (int)($_GET['exam_id'] ?? 0);
$print_mode = isset($_GET['print']);

$where = "e.created_by = $teacherId AND ea.status = 'completed'";
if ($exam_filter) $where .= " AND ea.exam_id = $exam_filter";

$results = $conn->query("SELECT ea.*, e.exam_name, e.total_marks as exam_total_marks, e.passing_marks, e.duration_minutes,
    s.subject_name, CONCAT(c.class_name, ' ', c.section) as class_full,
    u.full_name as student_name, u.username, u.email,
    sc.roll_number,
    TIMESTAMPDIFF(SECOND, ea.start_time, ea.end_time) as time_taken_secs
    FROM exam_attempts ea
    JOIN exams e ON ea.exam_id = e.id
    JOIN subjects s ON e.subject_id = s.id
    JOIN classes c ON e.class_id = c.id
    JOIN users u ON ea.student_id = u.id
    LEFT JOIN student_classes sc ON ea.student_id = sc.student_id AND sc.class_id = e.class_id
    WHERE $where
    ORDER BY e.exam_name, ea.percentage DESC
    LIMIT 200")->fetch_all(MYSQLI_ASSOC);

$grouped = [];
foreach ($results as $r) {
    $grouped[$r['exam_name']][] = $r;
}

if ($print_mode):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exam Results</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 11px; }
            table { page-break-inside: avoid; }
        }
    </style>
</head>
<body class="bg-white p-6">
    <div class="text-center mb-6 no-print">
        <h1 class="text-xl font-bold">Exam Results</h1>
        <p class="text-sm text-gray-500">Generated on <?= date('d M Y, h:i A') ?></p>
        <button onclick="window.print()" class="mt-2 px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg">Print</button>
        <a href="?<?= $exam_filter ? "exam_id=$exam_filter" : '' ?>" class="ml-2 px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg">Back</a>
    </div>
    <?php if (!empty($grouped)): foreach ($grouped as $exam_name => $rows): ?>
    <div class="mb-6">
        <h2 class="text-lg font-bold text-gray-800 mb-2"><?= htmlspecialchars($exam_name) ?></h2>
        <p class="text-xs text-gray-500 mb-3"><?= htmlspecialchars($rows[0]['subject_name']) ?> &middot; <?= htmlspecialchars($rows[0]['class_full']) ?> &middot; <?= count($rows) ?> students</p>
        <table class="w-full text-sm border border-gray-200">
            <thead>
                <tr class="bg-gray-100">
                    <th class="px-3 py-2 text-left border border-gray-200 font-medium">#</th>
                    <th class="px-3 py-2 text-left border border-gray-200 font-medium">Name</th>
                    <th class="px-3 py-2 text-left border border-gray-200 font-medium">Roll</th>
                    <th class="px-3 py-2 text-left border border-gray-200 font-medium">Score</th>
                    <th class="px-3 py-2 text-left border border-gray-200 font-medium">%</th>
                    <th class="px-3 py-2 text-left border border-gray-200 font-medium">Pass/Fail</th>
                    <th class="px-3 py-2 text-left border border-gray-200 font-medium">Time</th>
                </tr>
            </thead>
            <tbody>
                <?php $sn = 1; foreach ($rows as $r): ?>
                <tr>
                    <td class="px-3 py-2 border border-gray-200"><?= $sn++ ?></td>
                    <td class="px-3 py-2 border border-gray-200 font-medium"><?= htmlspecialchars($r['student_name']) ?></td>
                    <td class="px-3 py-2 border border-gray-200"><?= htmlspecialchars($r['roll_number'] ?: '-') ?></td>
                    <td class="px-3 py-2 border border-gray-200"><?= $r['total_score'] ?>/<?= $r['max_score'] ?></td>
                    <td class="px-3 py-2 border border-gray-200 font-semibold"><?= round($r['percentage'], 1) ?>%</td>
                    <td class="px-3 py-2 border border-gray-200">
                        <span class="<?= $r['percentage'] >= 33 ? 'text-green-600' : 'text-red-600' ?> font-semibold"><?= $r['percentage'] >= 33 ? 'Pass' : 'Fail' ?></span>
                    </td>
                    <td class="px-3 py-2 border border-gray-200"><?= $r['time_taken_secs'] ? floor($r['time_taken_secs'] / 60) . 'm ' . ($r['time_taken_secs'] % 60) . 's' : '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; else: ?>
    <p class="text-center text-gray-400 py-12">No results available</p>
    <?php endif; ?>
</body>
</html>
<?php exit(); endif; ?>

<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-gray-800">Exam Results</h2>
        <span class="bg-gray-100 text-gray-600 text-xs font-medium px-2.5 py-1 rounded-full"><?= count($results) ?> records</span>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Print Results
        </button>
        <a href="?print=1<?= $exam_filter ? "&exam_id=$exam_filter" : '' ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Export / Printable
        </a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6 p-4">
    <form method="GET" class="flex flex-col sm:flex-row gap-3">
        <select name="exam_id" class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <option value="">All Exams</option>
            <?php foreach ($exams_list as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $exam_filter == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['exam_name']) ?> (<?= htmlspecialchars($e['subject_name']) ?> - <?= htmlspecialchars($e['class_full']) ?>)</option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors">Filter</button>
    </form>
</div>

<?php if (empty($grouped)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">No results found</div>
<?php else: foreach ($grouped as $exam_name => $rows): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($exam_name) ?></h3>
            <p class="text-xs text-gray-400"><?= htmlspecialchars($rows[0]['subject_name']) ?> &middot; <?= htmlspecialchars($rows[0]['class_full']) ?> &middot; <?= count($rows) ?> student<?= count($rows) != 1 ? 's' : '' ?></p>
        </div>
        <?php
        $p_count = 0;
        $f_count = 0;
        $avg = 0;
        foreach ($rows as $r) {
            if ($r['percentage'] >= 33) $p_count++;
            else $f_count++;
            $avg += $r['percentage'];
        }
        $avg = count($rows) > 0 ? round($avg / count($rows), 1) : 0;
        ?>
        <div class="flex gap-3 text-xs">
            <span class="px-2.5 py-1 bg-green-100 text-green-700 rounded-full font-medium">Pass: <?= $p_count ?></span>
            <span class="px-2.5 py-1 bg-red-100 text-red-600 rounded-full font-medium">Fail: <?= $f_count ?></span>
            <span class="px-2.5 py-1 bg-gray-100 text-gray-600 rounded-full font-medium">Avg: <?= $avg ?>%</span>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 bg-gray-50">
                    <th class="px-6 py-3 font-medium">#</th>
                    <th class="px-6 py-3 font-medium">Student</th>
                    <th class="px-6 py-3 font-medium">Roll No.</th>
                    <th class="px-6 py-3 font-medium">Score</th>
                    <th class="px-6 py-3 font-medium">Percentage</th>
                    <th class="px-6 py-3 font-medium">Status</th>
                    <th class="px-6 py-3 font-medium">Time Taken</th>
                    <th class="px-6 py-3 font-medium">Submitted</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php $sn = 1; foreach ($rows as $r): ?>
                <tr class="hover:bg-gray-50/50">
                    <td class="px-6 py-3 text-gray-400"><?= $sn++ ?></td>
                    <td class="px-6 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-primary-100 text-primary-600 rounded-full flex items-center justify-center font-bold text-xs"><?= strtoupper(substr($r['student_name'], 0, 1)) ?></div>
                            <div>
                                <p class="font-medium text-gray-800"><?= htmlspecialchars($r['student_name']) ?></p>
                                <p class="text-xs text-gray-400">@<?= htmlspecialchars($r['username']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-3 text-gray-600"><?= htmlspecialchars($r['roll_number'] ?: '-') ?></td>
                    <td class="px-6 py-3">
                        <span class="font-semibold text-gray-800"><?= $r['total_score'] ?></span>
                        <span class="text-gray-400">/ <?= $r['max_score'] ?></span>
                    </td>
                    <td class="px-6 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-16 h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full <?= $r['percentage'] >= 80 ? 'bg-green-500' : ($r['percentage'] >= 50 ? 'bg-amber-500' : 'bg-red-500') ?>" style="width: <?= min(100, $r['percentage']) ?>%"></div>
                            </div>
                            <span class="text-sm font-semibold <?= $r['percentage'] >= 80 ? 'text-green-600' : ($r['percentage'] >= 50 ? 'text-amber-600' : 'text-red-500') ?>"><?= round($r['percentage'], 1) ?>%</span>
                        </div>
                    </td>
                    <td class="px-6 py-3">
                        <?php if ($r['percentage'] >= 33): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Pass</span>
                        <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-600">Fail</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-gray-600 text-xs">
                        <?= $r['time_taken_secs'] ? floor($r['time_taken_secs'] / 60) . 'm ' . ($r['time_taken_secs'] % 60) . 's' : '-' ?>
                    </td>
                    <td class="px-6 py-3 text-gray-500 text-xs">
                        <?= $r['submitted_at'] ? date('d M, h:i A', strtotime($r['submitted_at'])) : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
