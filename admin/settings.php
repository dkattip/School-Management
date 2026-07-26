<?php
require_once __DIR__ . '/../config/database.php';
requireRole('admin');
$pageTitle = 'School Settings';
include __DIR__ . '/../includes/header.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_name    = sanitize($_POST['school_name'] ?? '');
    $school_address = sanitize($_POST['school_address'] ?? '');
    $school_phone   = sanitize($_POST['school_phone'] ?? '');
    $school_email   = sanitize($_POST['school_email'] ?? '');
    $school_board   = sanitize($_POST['school_board'] ?? 'BOTH');
    $academic_year  = sanitize($_POST['academic_year'] ?? '2025-26');
    $school_motto   = sanitize($_POST['school_motto'] ?? '');
    $max_duration   = max(10, min(300, (int)($_POST['max_exam_duration'] ?? 120)));
    $seb_enabled    = isset($_POST['seb_enabled']) ? 1 : 0;
    $webcam_required = isset($_POST['webcam_required']) ? 1 : 0;
    $theme_color    = sanitize($_POST['theme_color'] ?? '#4F46E5');

    if (!$school_name) {
        $error = 'School name is required.';
    } else {
        $conn->query("UPDATE school_settings SET
            school_name='$school_name',
            school_address='$school_address',
            school_phone='$school_phone',
            school_email='$school_email',
            school_board='$school_board',
            academic_year='$academic_year',
            school_motto='$school_motto',
            max_exam_duration=$max_duration,
            seb_enabled=$seb_enabled,
            webcam_required=$webcam_required,
            theme_color='$theme_color'
            WHERE id=1");

        if (isset($_FILES['school_image']) && $_FILES['school_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['school_image'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (in_array($ext, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
                $uploadDir = __DIR__ . '/../uploads/school/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $filename = 'school_image.' . $ext;
                move_uploaded_file($file['tmp_name'], $uploadDir . $filename);
                $conn->query("UPDATE school_settings SET school_logo='uploads/school/$filename' WHERE id=1");
                $settings = $conn->query("SELECT * FROM school_settings LIMIT 1")->fetch_assoc();
            } else {
                $error = 'Image must be JPG/PNG/WebP/GIF, max 5MB.';
            }
        }

        if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
            $old = $settings['school_logo'] ?? '';
            if ($old && file_exists(__DIR__ . '/../' . $old)) unlink(__DIR__ . '/../' . $old);
            $conn->query("UPDATE school_settings SET school_logo=NULL WHERE id=1");
        }

        $success = 'Settings saved successfully.';
        $settings = $conn->query("SELECT * FROM school_settings LIMIT 1")->fetch_assoc();
    }
}

$settings = $conn->query("SELECT * FROM school_settings LIMIT 1")->fetch_assoc();
?>

<?php if ($success): ?>
<div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm flex items-center gap-2">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    <?= $success ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm flex items-center gap-2">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    <?= $error ?>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="space-y-6 max-w-3xl">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">School Information</h3>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">School Name *</label>
                <input type="text" name="school_name" value="<?= htmlspecialchars($settings['school_name'] ?? '') ?>" required
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                <textarea name="school_address" rows="2"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"><?= htmlspecialchars($settings['school_address'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                <input type="text" name="school_phone" value="<?= htmlspecialchars($settings['school_phone'] ?? '') ?>"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="school_email" value="<?= htmlspecialchars($settings['school_email'] ?? '') ?>"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Board</label>
                <select name="school_board" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <?= renderBoardOptions($settings['school_board'] ?? '') ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                <input type="text" name="academic_year" value="<?= htmlspecialchars($settings['academic_year'] ?? '2025-26') ?>"
                    placeholder="e.g. 2025-26"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">School Motto</label>
                <input type="text" name="school_motto" value="<?= htmlspecialchars($settings['school_motto'] ?? '') ?>"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Exam Settings</h3>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Max Exam Duration (minutes)</label>
                <input type="number" name="max_exam_duration" value="<?= $settings['max_exam_duration'] ?? 120 ?>" min="10" max="300"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-3">Security Settings</label>
                <div class="space-y-3">
                    <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors">
                        <input type="checkbox" name="seb_enabled" value="1" <?= ($settings['seb_enabled'] ?? 1) ? 'checked' : '' ?>
                            class="w-4 h-4 text-primary-600 rounded focus:ring-primary-500">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-700">Enable Safe Exam Browser (SEB)</p>
                            <p class="text-xs text-gray-400">Require SEB for exam lockdown. Prevents switching tabs, copying, and unauthorized access.</p>
                        </div>
                        <div class="w-10 h-6 rounded-full relative <?= ($settings['seb_enabled'] ?? 1) ? 'bg-primary-500' : 'bg-gray-300' ?> transition-colors" id="sebToggle">
                            <div class="absolute top-0.5 <?= ($settings['seb_enabled'] ?? 1) ? 'left-5' : 'left-0.5' ?> w-5 h-5 bg-white rounded-full shadow transition-all" id="sebDot"></div>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors">
                        <input type="checkbox" name="webcam_required" value="1" <?= ($settings['webcam_required'] ?? 1) ? 'checked' : '' ?>
                            class="w-4 h-4 text-primary-600 rounded focus:ring-primary-500">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-700">Require Webcam</p>
                            <p class="text-xs text-gray-400">Enable webcam monitoring during exams for identity verification.</p>
                        </div>
                        <div class="w-10 h-6 rounded-full relative <?= ($settings['webcam_required'] ?? 1) ? 'bg-primary-500' : 'bg-gray-300' ?> transition-colors">
                            <div class="absolute top-0.5 <?= ($settings['webcam_required'] ?? 1) ? 'left-5' : 'left-0.5' ?> w-5 h-5 bg-white rounded-full shadow transition-all"></div>
                        </div>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Appearance</h3>
        </div>
        <div class="p-6">
            <label class="block text-sm font-medium text-gray-700 mb-3">Theme Color</label>
            <div class="flex items-center gap-4">
                <input type="color" name="theme_color" value="<?= htmlspecialchars($settings['theme_color'] ?? '#4F46E5') ?>"
                    class="w-12 h-12 rounded-lg border border-gray-200 cursor-pointer">
                <div class="flex gap-2">
                    <?php
                    $colors = ['#4F46E5','#7C3AED','#2563EB','#0891B2','#059669','#D97706','#DC2626','#DB2777'];
                    foreach ($colors as $c): ?>
                    <button type="button" onclick="document.querySelector('input[name=theme_color]').value='<?= $c ?>' "
                        class="w-8 h-8 rounded-full border-2 border-transparent hover:border-gray-400 transition-colors"
                        style="background-color: <?= $c ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mt-4">
                <div class="w-full h-16 rounded-xl" style="background-color: <?= htmlspecialchars($settings['theme_color'] ?? '#4F46E5') ?>; opacity: 0.1"></div>
                <p class="text-xs text-gray-400 mt-1">Preview of selected color at 10% opacity</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">School Image</h3>
            <p class="text-xs text-gray-400 mt-0.5">Displayed as background on login page</p>
        </div>
        <div class="p-6">
            <?php if (!empty($settings['school_logo'])): ?>
            <div class="relative inline-block">
                <img src="<?= htmlspecialchars('/School-management/' . $settings['school_logo']) ?>?t=<?= time() ?>" class="w-48 h-28 object-cover rounded-xl border border-gray-200">
                <label class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center cursor-pointer hover:bg-red-600 transition shadow">
                    <input type="checkbox" name="remove_image" value="1" class="hidden" onchange="this.closest('.relative').remove()">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </label>
            </div>
            <?php endif; ?>
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= !empty($settings['school_logo']) ? 'Replace Image' : 'Upload Image' ?></label>
                <input type="file" name="school_image" accept="image/*"
                    class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 file:cursor-pointer">
                <p class="text-xs text-gray-400 mt-1">JPG, PNG, WebP or GIF. Max 5MB. Recommended: 1920x1080</p>
            </div>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors shadow-sm">
            Save All Settings
        </button>
    </div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
