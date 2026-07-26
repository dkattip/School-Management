<?php
require_once __DIR__ . '/../config/database.php';
requireRole('student');
$studentId = $_SESSION['user_id'];
$pageTitle = 'My Profile';
include __DIR__ . '/../includes/header.php';

$success = $error = '';

$userInfo = $conn->query("SELECT * FROM users WHERE id = $studentId")->fetch_assoc();
$studentInfo = $conn->query("SELECT sc.*, CONCAT(c.class_name, ' ', c.section) as class_full, c.class_name, c.section
    FROM student_classes sc
    JOIN classes c ON sc.class_id = c.id
    WHERE sc.student_id = $studentId LIMIT 1")->fetch_assoc();

$stats = [];
$stats['exams_taken'] = $conn->query("SELECT COUNT(*) as c FROM exam_attempts WHERE student_id = $studentId AND status = 'completed'")->fetch_assoc()['c'];
$stats['avg_score'] = $conn->query("SELECT ROUND(AVG(percentage), 1) as avg FROM exam_attempts WHERE student_id = $studentId AND status = 'completed'")->fetch_assoc()['avg'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $newName = sanitize($_POST['full_name'] ?? '');
        $newEmail = sanitize($_POST['email'] ?? '');
        $newPhone = sanitize($_POST['phone'] ?? '');

        if (empty($newName)) {
            $error = 'Name cannot be empty.';
        } else {
            $checkEmail = $conn->query("SELECT id FROM users WHERE email = '$newEmail' AND id != $studentId");
            if ($checkEmail->num_rows > 0) {
                $error = 'This email is already in use by another account.';
            } else {
                $conn->query("UPDATE users SET full_name = '$newName', email = '$newEmail', phone = '$newPhone' WHERE id = $studentId");
                $_SESSION['full_name'] = $newName;
                $success = 'Profile updated successfully.';
                $userInfo = $conn->query("SELECT * FROM users WHERE id = $studentId")->fetch_assoc();
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, $userInfo['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } else {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password = '$hashed' WHERE id = $studentId");
            $success = 'Password changed successfully.';
        }
    }
}
?>

<div class="max-w-4xl mx-auto space-y-6">

    <?php if ($success): ?>
    <div class="p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm flex items-center gap-2">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <?= $success ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm flex items-center gap-2">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <?= $error ?>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-gradient-to-r from-primary-600 to-purple-600 px-8 py-8 text-white">
            <div class="flex items-center gap-5">
                <div class="w-20 h-20 bg-white/20 rounded-2xl flex items-center justify-center text-3xl font-bold backdrop-blur-sm">
                    <?= strtoupper(substr($_SESSION['full_name'], 0, 2)) ?>
                </div>
                <div>
                    <h2 class="text-2xl font-bold"><?= htmlspecialchars($_SESSION['full_name']) ?></h2>
                    <p class="text-white/70 mt-1">@<?= htmlspecialchars($userInfo['username']) ?></p>
                    <p class="text-white/50 text-sm mt-0.5"><?= htmlspecialchars($userInfo['email']) ?></p>
                </div>
            </div>
        </div>
        <div class="px-8 py-5 grid grid-cols-2 sm:grid-cols-4 gap-4 border-b border-gray-100">
            <div class="text-center">
                <p class="text-xl font-bold text-gray-800"><?= htmlspecialchars($studentInfo['class_full'] ?? 'N/A') ?></p>
                <p class="text-xs text-gray-500">Class</p>
            </div>
            <div class="text-center">
                <p class="text-xl font-bold text-gray-800"><?= htmlspecialchars($studentInfo['roll_number'] ?? '-') ?></p>
                <p class="text-xs text-gray-500">Roll Number</p>
            </div>
            <div class="text-center">
                <p class="text-xl font-bold text-gray-800"><?= $stats['exams_taken'] ?></p>
                <p class="text-xs text-gray-500">Exams Taken</p>
            </div>
            <div class="text-center">
                <p class="text-xl font-bold text-primary-600"><?= $stats['avg_score'] ?? 0 ?>%</p>
                <p class="text-xs text-gray-500">Avg Score</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Edit Profile</h3>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="update_profile">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($userInfo['full_name']) ?>" required
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($userInfo['email']) ?>" required
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($userInfo['phone'] ?? '') ?>"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="Phone number">
                </div>
                <button type="submit" class="px-5 py-2.5 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors">
                    Save Changes
                </button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Change Password</h3>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="change_password">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                    <input type="password" name="current_password" required
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <input type="password" name="new_password" required minlength="6"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="Min 6 characters">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="6"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                <button type="submit" class="px-5 py-2.5 bg-gray-800 text-white text-sm font-medium rounded-lg hover:bg-gray-900 transition-colors">
                    Change Password
                </button>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Academic Information</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl">
                    <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Class</p>
                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($studentInfo['class_full'] ?? 'Not Assigned') ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Roll Number</p>
                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($studentInfo['roll_number'] ?? '-') ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl">
                    <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Admission Number</p>
                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($studentInfo['admission_number'] ?? '-') ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl">
                    <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Username</p>
                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($userInfo['username']) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
