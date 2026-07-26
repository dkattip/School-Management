<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isLoggedIn() || !in_array($_SESSION['role'] ?? '', ['admin', 'teacher'])) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$exam_id = (int)($_GET['exam_id'] ?? 0);
if (!$exam_id) {
    echo 'No exam specified.';
    exit;
}

$exam = $conn->query("SELECT e.*, s.subject_name, s.subject_code, CONCAT(c.class_name, ' ', c.section) as class_full, c.board
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    JOIN classes c ON e.class_id = c.id
    WHERE e.id = $exam_id")->fetch_assoc();

if (!$exam) {
    echo 'Exam not found.';
    exit;
}

if ($_SESSION['role'] === 'teacher' && $exam['created_by'] != $_SESSION['user_id']) {
    echo 'Access denied.';
    exit;
}

$settings = $conn->query("SELECT * FROM school_settings LIMIT 1")->fetch_assoc();

$questions = $conn->query("SELECT * FROM questions WHERE exam_id = $exam_id ORDER BY question_order ASC, id ASC")->fetch_all(MYSQLI_ASSOC);

$stripLatex = function($text) {
    $text = preg_replace('/\$\$(.*?)\$\$/s', '$1', $text);
    $text = preg_replace('/\$(.*?)\$/', '$1', $text);
    $text = preg_replace('/\\frac\{([^}]*)\}\{([^}]*)\}/', '($1/$2)', $text);
    $text = preg_replace('/\\sqrt\{([^}]*)\}/', '√($1)', $text);
    $text = preg_replace('/\\[a-zA-Z]+/', '', $text);
    $text = preg_replace('/\{([^}]*)\}/', '$1', $text);
    $text = str_replace(['\\', '~', '^'], '', $text);
    return trim($text);
};

$schoolName = htmlspecialchars($settings['school_name'] ?? 'School');
$schoolAddress = htmlspecialchars($settings['school_address'] ?? '');
$schoolPhone = htmlspecialchars($settings['school_phone'] ?? '');
$schoolEmail = htmlspecialchars($settings['school_email'] ?? '');
$schoolMotto = htmlspecialchars($settings['school_motto'] ?? '');

$examName = htmlspecialchars($exam['exam_name']);
$subjectName = htmlspecialchars($exam['subject_name']);
$subjectCode = htmlspecialchars($exam['subject_code'] ?? '');
$classFull = htmlspecialchars($exam['class_full']);
$board = htmlspecialchars($exam['board'] ?? '');
$examType = ucfirst(htmlspecialchars($exam['exam_type']));
$totalMarks = (int)$exam['total_marks'];
$passingMarks = (int)$exam['passing_marks'];
$duration = (int)$exam['duration_minutes'];
$startDate = $exam['start_time'] ? date('d M Y', strtotime($exam['start_time'])) : 'Not scheduled';
$endDate = $exam['end_time'] ? date('d M Y, h:i A', strtotime($exam['end_time'])) : 'Not scheduled';
$examCode = 'EXAM-' . str_pad($exam_id, 5, '0', STR_PAD_LEFT);
$examDescription = htmlspecialchars($exam['description'] ?? '');

$logoHtml = '';
if (!empty($settings['school_logo'])) {
    $logoPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($settings['school_logo'], '/');
    if (file_exists($logoPath)) {
        $logoData = base64_encode(file_get_contents($logoPath));
        $logoMime = mime_content_type($logoPath);
        $logoHtml = '<img src="data:' . $logoMime . ';base64,' . $logoData . '" style="width:70px;height:70px;border-radius:10px;object-fit:cover;margin:0 auto 10px auto;display:block;border:1px solid #e5e7eb;">';
    }
}

$questionsHtml = '';
foreach ($questions as $idx => $q) {
    $qNum = $idx + 1;
    $qText = nl2br(htmlspecialchars($stripLatex($q['question_text'])));
    $marks = $q['marks'];
    $type = $q['question_type'];

    $optionsHtml = '';
    if ($type === 'mcq' || $type === 'true_false') {
        $opts = [
            'A' => $q['option_a'] ?? '',
            'B' => $q['option_b'] ?? '',
            'C' => $q['option_c'] ?? '',
            'D' => $q['option_d'] ?? '',
            'E' => $q['option_e'] ?? '',
        ];
        foreach ($opts as $letter => $opt) {
            if (!empty($opt)) {
                $optionsHtml .= '<div style="padding:4px 0 4px 20px;font-size:12px;color:#374151;">' . $letter . ')&nbsp;&nbsp;' . htmlspecialchars($stripLatex($opt)) . '</div>';
            }
        }
    }

    $questionsHtml .= '
    <div style="page-break-inside:avoid;margin-bottom:16px;">
        <table cellpadding="0" cellspacing="0" style="width:100%;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
            <tr>
                <td style="background:#f8fafc;padding:10px 14px;border-bottom:1px solid #e5e7eb;">
                    <table cellpadding="0" cellspacing="0" style="width:100%;">
                        <tr>
                            <td style="font-size:13px;font-weight:bold;color:#1e293b;">Q. ' . $qNum . '</td>
                            <td style="text-align:right;font-size:11px;color:#64748b;font-weight:600;">' . $marks . ' Mark' . ($marks != 1 ? 's' : '') . '</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td style="padding:12px 14px;">
                    <div style="font-size:12.5px;color:#1f2937;line-height:1.6;margin-bottom:6px;">' . $qText . '</div>
                    ' . $optionsHtml . '
                </td>
            </tr>
        </table>
    </div>';
}

$instructions = [
    'Read all questions carefully before answering.',
    'This exam consists of ' . count($questions) . ' question(s) carrying a total of ' . $totalMarks . ' marks.',
    'Time duration: ' . $duration . ' minutes.',
    'All questions are compulsory unless stated otherwise.',
    'For MCQ questions, select the most appropriate answer from the given options.',
    'Write your answers clearly and legibly.',
    'No electronic devices are allowed unless specified by the institution.',
    'Malpractice of any kind will result in disqualification.',
    'Contact the invigilator for any clarifications during the exam.',
];

$instructionsHtml = '';
foreach ($instructions as $idx => $inst) {
    $instructionsHtml .= '<div style="padding:6px 0;font-size:11.5px;color:#374151;border-bottom:1px solid #f1f5f9;">
        <span style="display:inline-block;width:20px;font-weight:bold;color:#6366f1;">' . ($idx + 1) . '.</span>' . htmlspecialchars($inst) . '</div>';
}

$html = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;color:#1f2937;">

<!-- COVER PAGE -->
<div style="padding:40px 50px 30px 50px;">
    <!-- Header Bar -->
    <table cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:20px;">
        <tr>
            <td style="width:80px;vertical-align:top;">
                ' . $logoHtml . '
            </td>
            <td style="vertical-align:top;padding-left:10px;">
                <div style="font-size:20px;font-weight:800;color:#1e293b;letter-spacing:-0.5px;">' . $schoolName . '</div>
                ' . ($schoolAddress ? '<div style="font-size:10px;color:#64748b;margin-top:2px;">' . $schoolAddress . '</div>' : '') . '
                ' . ($schoolPhone || $schoolEmail ? '<div style="font-size:10px;color:#64748b;margin-top:1px;">' . $schoolPhone . ($schoolPhone && $schoolEmail ? ' &middot; ' : '') . $schoolEmail . '</div>' : '') . '
                ' . ($schoolMotto ? '<div style="font-size:9.5px;color:#94a3b8;margin-top:4px;font-style:italic;">&ldquo;' . $schoolMotto . '&rdquo;</div>' : '') . '
            </td>
        </tr>
    </table>

    <!-- Divider -->
    <div style="border-top:3px solid #6366f1;margin:10px 0 20px 0;"></div>

    <!-- Title -->
    <div style="text-align:center;margin-bottom:25px;">
        <div style="font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#6366f1;font-weight:700;margin-bottom:6px;">Examination Paper</div>
        <div style="font-size:24px;font-weight:800;color:#0f172a;letter-spacing:-0.5px;">' . $examName . '</div>
        ' . ($examDescription ? '<div style="font-size:11.5px;color:#64748b;margin-top:6px;">' . $examDescription . '</div>' : '') . '
    </div>

    <!-- Exam Details Card -->
    <table cellpadding="0" cellspacing="0" style="width:100%;border:1.5px solid #e2e8f0;border-radius:10px;margin-bottom:25px;">
        <tr>
            <td style="padding:0;">
                <table cellpadding="0" cellspacing="0" style="width:100%;">
                    <tr>
                        <td style="background:#f8fafc;padding:10px 16px;width:50%;border-bottom:1px solid #e2e8f0;font-size:12px;">
                            <span style="color:#64748b;font-weight:600;">Subject:</span>
                            <span style="color:#1e293b;font-weight:700;margin-left:4px;">' . $subjectName . ($subjectCode ? ' (' . $subjectCode . ')' : '') . '</span>
                        </td>
                        <td style="background:#f8fafc;padding:10px 16px;width:50%;border-bottom:1px solid #e2e8f0;border-left:1px solid #e2e8f0;font-size:12px;">
                            <span style="color:#64748b;font-weight:600;">Class:</span>
                            <span style="color:#1e293b;font-weight:700;margin-left:4px;">' . $classFull . '</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px 16px;width:50%;border-bottom:1px solid #e2e8f0;font-size:12px;">
                            <span style="color:#64748b;font-weight:600;">Type:</span>
                            <span style="color:#1e293b;font-weight:700;margin-left:4px;">' . $examType . '</span>
                        </td>
                        <td style="padding:10px 16px;width:50%;border-bottom:1px solid #e2e8f0;border-left:1px solid #e2e8f0;font-size:12px;">
                            <span style="color:#64748b;font-weight:600;">Board:</span>
                            <span style="color:#1e293b;font-weight:700;margin-left:4px;">' . $board . '</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px 16px;width:50%;border-bottom:1px solid #e2e8f0;font-size:12px;">
                            <span style="color:#64748b;font-weight:600;">Date:</span>
                            <span style="color:#1e293b;font-weight:700;margin-left:4px;">' . $startDate . '</span>
                        </td>
                        <td style="padding:10px 16px;width:50%;border-bottom:1px solid #e2e8f0;border-left:1px solid #e2e8f0;font-size:12px;">
                            <span style="color:#64748b;font-weight:600;">Ends:</span>
                            <span style="color:#1e293b;font-weight:700;margin-left:4px;">' . $endDate . '</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px 16px;width:50%;border-bottom:1px solid #e2e8f0;font-size:12px;">
                            <span style="color:#64748b;font-weight:600;">Duration:</span>
                            <span style="color:#1e293b;font-weight:700;margin-left:4px;">' . $duration . ' minutes</span>
                        </td>
                        <td style="padding:10px 16px;width:50%;border-bottom:1px solid #e2e8f0;border-left:1px solid #e2e8f0;font-size:12px;">
                            <span style="color:#64748b;font-weight:600;">Exam Code:</span>
                            <span style="color:#6366f1;font-weight:800;margin-left:4px;font-size:13px;">' . $examCode . '</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px 16px;width:50%;font-size:12px;">
                            <span style="color:#64748b;font-weight:600;">Total Marks:</span>
                            <span style="color:#1e293b;font-weight:700;margin-left:4px;">' . $totalMarks . '</span>
                        </td>
                        <td style="padding:10px 16px;width:50%;border-left:1px solid #e2e8f0;font-size:12px;">
                            <span style="color:#64748b;font-weight:600;">Passing Marks:</span>
                            <span style="color:#1e293b;font-weight:700;margin-left:4px;">' . $passingMarks . '</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Instructions -->
    <div style="margin-bottom:20px;">
        <div style="font-size:13px;font-weight:800;color:#0f172a;margin-bottom:10px;padding-bottom:6px;border-bottom:2px solid #6366f1;display:inline-block;">
            INSTRUCTIONS TO STUDENTS
        </div>
        ' . $instructionsHtml . '
    </div>

    <!-- Footer -->
    <div style="border-top:1px solid #e2e8f0;padding-top:12px;margin-top:15px;text-align:center;">
        <div style="font-size:9.5px;color:#94a3b8;">This is a computer-generated examination paper. No signature is required.</div>
        <div style="font-size:9.5px;color:#94a3b8;margin-top:2px;">Generated on ' . date('d M Y, h:i A') . '</div>
    </div>
</div>

<!-- QUESTIONS PAGES -->
<div style="padding:30px 50px;">
    <div style="font-size:13px;font-weight:800;color:#0f172a;margin-bottom:4px;padding-bottom:6px;border-bottom:2px solid #6366f1;display:inline-block;">
        QUESTIONS
    </div>
    <div style="font-size:10.5px;color:#64748b;margin-bottom:20px;">
        Total Questions: ' . count($questions) . ' &nbsp;&middot;&nbsp; Total Marks: ' . $totalMarks . ' &nbsp;&middot;&nbsp; Duration: ' . $duration . ' min
    </div>
    ' . $questionsHtml . '
</div>

</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultMediaType', 'print');
$options->set('isFontSubsettingEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = $examCode . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $exam['exam_name']) . '.pdf';

$dompdf->stream($filename, ['Attachment' => false]);
