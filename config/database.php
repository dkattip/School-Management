<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_management');

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");
    $conn->query("SET GLOBAL time_zone = '+05:30'");
    $conn->query("SET time_zone = '+05:30'");
} catch (mysqli_sql_exception $e) {
    $setupUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/School-management/setup.php';
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'setup.php') {
        header("Location: $setupUrl");
        exit();
    }
    die('<h2>Database not found.</h2><p>Please <a href="/School-management/setup.php">run the setup</a> first.</p>');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Kolkata');

$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
if (basename($scriptDir) === 'config' || basename($scriptDir) === 'includes' || basename($scriptDir) === 'api') {
    $baseUrl = '/School-management';
} else {
    $baseUrl = '/School-management';
}

$GLOBALS['INDIAN_BOARDS'] = [
    'National' => ['CBSE', 'ICSE', 'NIOS'],
    'States' => [
        'Andhra Pradesh (AP)',
        'Arunachal Pradesh (AR)',
        'Assam (AS)',
        'Bihar (BR)',
        'Chhattisgarh (CG)',
        'Goa (GA)',
        'Gujarat (GJ)',
        'Haryana (HR)',
        'Himachal Pradesh (HP)',
        'Jharkhand (JH)',
        'Karnataka (KA)',
        'Kerala (KL)',
        'Madhya Pradesh (MP)',
        'Maharashtra (MH)',
        'Manipur (MN)',
        'Meghalaya (ML)',
        'Mizoram (MZ)',
        'Nagaland (NL)',
        'Odisha (OD)',
        'Punjab (PB)',
        'Rajasthan (RJ)',
        'Sikkim (SK)',
        'Tamil Nadu (TN)',
        'Telangana (TS)',
        'Tripura (TR)',
        'Uttar Pradesh (UP)',
        'Uttarakhand (UK)',
        'West Bengal (WB)',
    ],
    'Union Territories' => [
        'Andaman & Nicobar Islands',
        'Chandigarh',
        'Delhi (NCT)',
        'Jammu & Kashmir',
        'Ladakh',
        'Lakshadweep',
        'Puducherry',
        'Dadra & Nagar Haveli and Daman & Diu',
    ],
];

function renderBoardOptions($selected = '') {
    $boards = $GLOBALS['INDIAN_BOARDS'];
    $allBoards = [];
    foreach ($boards as $group => $list) {
        foreach ($list as $b) $allBoards[] = $b;
    }
    $allBoards[] = 'Other';
    $html = '';
    foreach ($boards as $group => $list) {
        $html .= '<optgroup label="' . htmlspecialchars($group) . '">';
        foreach ($list as $b) {
            $sel = ($selected === $b) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($b) . '"' . $sel . '>' . htmlspecialchars($b) . '</option>';
        }
        $html .= '</optgroup>';
    }
    $sel = ($selected === 'Other') ? ' selected' : '';
    $html .= '<option value="Other"' . $sel . '>Other</option>';
    return $html;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function sanitize($data) {
    global $conn;
    return $conn->real_escape_string(htmlspecialchars(strip_tags(trim($data))));
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function requireLogin() {
    if (!isLoggedIn()) {
        global $baseUrl;
        header("Location: $baseUrl/login.php");
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        global $baseUrl;
        header("Location: $baseUrl/login.php");
        exit();
    }
}

function renderLatexText($text) {
    if (!$text) return '';
    $blocks = [];
    $s = $text;

    $s = preg_replace_callback('/\$\$(?!\$)(.*?)\$\$/s', function($m) use (&$blocks) {
        $blocks[] = $m[0];
        return "\x00" . (count($blocks) - 1) . "\x00";
    }, $s);

    $s = preg_replace_callback('/\$([^$\n\x00]+?)\$/', function($m) use (&$blocks) {
        $blocks[] = $m[0];
        return "\x00" . (count($blocks) - 1) . "\x00";
    }, $s);

    $s = preg_replace_callback('/(\\\\begin\{[^}]+\}.*?\\\\end\{[^}]+\})/s', function($m) use (&$blocks) {
        $blocks[] = '$$' . $m[1] . '$$';
        return "\x00" . (count($blocks) - 1) . "\x00";
    }, $s);

    $len = strlen($s);
    $regions = [];
    $i = 0;

    while ($i < $len) {
        if ($s[$i] === "\x00") {
            $end = strpos($s, "\x00", $i + 1);
            $i = ($end !== false) ? $end + 1 : $len;
            continue;
        }

        $start = -1;

        if ($s[$i] === '\\' && $i + 1 < $len && ctype_alpha($s[$i + 1])) {
            $start = $i;
            $i = _latex_scan_cmd($s, $i, $len);
            $i = _latex_extend($s, $i, $len);
        } elseif (($s[$i] === '^' || $s[$i] === '_') && $i > 0 && isset($s[$i - 1]) && preg_match('/[\w\)\}\]]/', $s[$i - 1]) && $s[$i - 1] !== "\x00") {
            $start = $i - 1;
            while ($start > 0 && preg_match('/[\[\(]/', $s[$start - 1]) && $s[$start - 1] !== "\x00") $start--;
            $i++;
            $i = _latex_scan_arg($s, $i, $len);
            $i = _latex_extend($s, $i, $len);
        }

        if ($start >= 0) {
            $regions[] = [$start, $i];
        } else {
            $i++;
        }
    }

    $regions = _latex_merge($regions);

    $result = '';
    $lastEnd = 0;
    foreach ($regions as [$rs, $re]) {
        if ($rs > $lastEnd) $result .= htmlspecialchars(substr($s, $lastEnd, $rs - $lastEnd));
        $result .= '$' . substr($s, $rs, $re - $rs) . '$';
        $lastEnd = $re;
    }
    if ($lastEnd < $len) $result .= htmlspecialchars(substr($s, $lastEnd));

    foreach ($blocks as $idx => $block) {
        $result = str_replace("\x00" . $idx . "\x00", $block, $result);
    }

    return $result;
}

function _latex_skip_balanced($s, $i, $len) {
    if ($i >= $len || $s[$i] !== '{') return $i;
    $depth = 0;
    while ($i < $len) {
        if ($s[$i] === '{') $depth++;
        elseif ($s[$i] === '}') { $depth--; if ($depth === 0) return $i + 1; }
        $i++;
    }
    return $i;
}

function _latex_scan_arg($s, $i, $len) {
    if ($i >= $len) return $i;
    if ($s[$i] === '{') return _latex_skip_balanced($s, $i, $len);
    if ($s[$i] === '\\' && $i + 1 < $len && ctype_alpha($s[$i + 1])) return _latex_scan_cmd($s, $i, $len);
    if ($s[$i] !== ' ' && $s[$i] !== "\x00") return $i + 1;
    return $i;
}

function _latex_scan_cmd($s, $i, $len) {
    $cmdStart = $i + 1;
    $i++;
    while ($i < $len && ctype_alpha($s[$i])) $i++;
    $cmdName = substr($s, $cmdStart, $i - $cmdStart);

    if (($cmdName === 'left' || $cmdName === 'right') && $i < $len && !ctype_space($s[$i]) && $s[$i] !== "\x00") {
        if ($s[$i] === '\\' && $i + 1 < $len && ($s[$i + 1] === '{' || $s[$i + 1] === '}')) {
            $i += 2;
        } else {
            $i++;
        }
    }

    while ($i < $len) {
        while ($i < $len && ctype_space($s[$i])) $i++;
        if ($i >= $len) break;

        if ($s[$i] === '{') { $i = _latex_skip_balanced($s, $i, $len); continue; }
        if ($s[$i] === '^' || $s[$i] === '_') { $i++; $i = _latex_scan_arg($s, $i, $len); continue; }

        if ($s[$i] === '\\' && $i + 1 < $len && ctype_alpha($s[$i + 1])) {
            $peek = $i + 1;
            while ($peek < $len && ctype_alpha($s[$peek])) $peek++;
            $adjCmd = substr($s, $i + 1, $peek - $i - 1);
            if ($adjCmd === 'left' || $adjCmd === 'right') break;
            $i = $peek;
            continue;
        }

        break;
    }

    return $i;
}

function _latex_extend($s, $i, $len) {
    while ($i < $len) {
        while ($i < $len && ctype_space($s[$i])) $i++;
        if ($i >= $len) break;
        if ($s[$i] === "\x00") break;

        if ($s[$i] === '\\' && $i + 1 < $len && ctype_alpha($s[$i + 1])) {
            $i = _latex_scan_cmd($s, $i, $len);
            continue;
        }

        if (($s[$i] === '^' || $s[$i] === '_') && $i > 0 && preg_match('/[\w\)\}\]]/', $s[$i - 1]) && $s[$i - 1] !== "\x00") {
            $i++;
            $i = _latex_scan_arg($s, $i, $len);
            continue;
        }

        if (preg_match('/[+\-*=<>]/', $s[$i])) { $i++; continue; }

        if (preg_match('/[0-9]/', $s[$i])) {
            while ($i < $len && preg_match('/[0-9.]/', $s[$i])) $i++;
            continue;
        }

        if ($s[$i] === '(' || $s[$i] === '[') {
            $open = $s[$i];
            $close = ($open === '(') ? ')' : ']';
            $depth = 0;
            while ($i < $len) {
                if ($s[$i] === $open) $depth++;
                elseif ($s[$i] === $close) { $depth--; if ($depth === 0) { $i++; break; } }
                $i++;
            }
            continue;
        }

        if ($s[$i] === ')' || $s[$i] === ']') { $i++; continue; }

        if ($s[$i] === ',') {
            $j = $i + 1;
            while ($j < $len && ctype_space($s[$j])) $j++;
            if ($j < $len && $s[$j] === '\\' && $j + 1 < $len && ctype_alpha($s[$j + 1])) {
                $i = $j;
                continue;
            }
            break;
        }

        break;
    }
    return $i;
}

function _latex_merge($regions) {
    if (empty($regions)) return [];
    usort($regions, function($a, $b) { return $a[0] - $b[0]; });
    $merged = [$regions[0]];
    for ($i = 1; $i < count($regions); $i++) {
        $last = &$merged[count($merged) - 1];
        if ($regions[$i][0] <= $last[1] + 1) {
            $last[1] = max($last[1], $regions[$i][1]);
        } else {
            $merged[] = $regions[$i];
        }
    }
    return $merged;
}
?>
