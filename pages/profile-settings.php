<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/config/db.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $patreon = trim($_POST['patreon'] ?? '');
    $kofi = trim($_POST['kofi'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
    $favorite_categories = isset($_POST['favorite_categories']) ? array_filter($_POST['favorite_categories']) : [];
    // Validate
    if (empty($username)) {
        $error = 'Username is required';
    } elseif (strlen($bio) > 500) {
        $error = 'Bio must be 500 characters or less';
    } else {
        try {
            // Update profile - directly update all fields
            $stmt = $pdo->prepare("
                UPDATE users SET 
                    username = ?,
                    email = ?,
                    bio = ?,
                    country = ?,
                    patreon = ?,
                    kofi = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $username,
                $email,
                $bio,
                $country ?: null,
                $patreon ?: null,
                $kofi ?: null,
                $user_id
            ]);

            // Update age and favorite_categories if they exist in DB
            try {
                $stmt2 = $pdo->prepare("UPDATE users SET age = ?, favorite_categories = ? WHERE id = ?");
                $stmt2->execute([$age, json_encode($favorite_categories), $user_id]);
            } catch (Exception $e) {
                // Columns might not exist yet, silently fail
            }

            // Handle avatar upload if provided
            if (isset($_FILES['avatar']) && ($_FILES['avatar']['size'] ?? 0) > 0) {
                $allowed = ['image/jpeg','image/png','image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowed)) {
                    $error = 'Invalid image type. Allowed: jpg, png, webp.';
                } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                    $error = 'Image too large (max 2MB).';
                } else {
                    $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                    $ext = strtolower($ext ?: (strpos($mime, 'png') !== false ? 'png' : (strpos($mime,'jpeg')!==false?'jpg':'webp')));
                    $destDir = __DIR__ . '/../uploads/avatars';
                    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                    $filename = "{$user_id}." . $ext;
                    $avatarPath = "uploads/avatars/{$filename}";
                    $fullPath = __DIR__ . '/../' . $avatarPath;
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $fullPath)) {
                        // Save avatar path in DB (use profile_image column, not avatar) - use full URL
                        $displayPath = site_url('/') . $avatarPath;
                        $s2 = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                        $s2->execute([$displayPath, $user_id]);
                        // Update local user var and session if needed
                        $user['profile_image'] = $displayPath;
                        $_SESSION['profile_image'] = $displayPath;
                    } else {
                        $error = 'Failed to move uploaded file.';
                    }
                }
            }

            // Update password if provided
            if (!empty($new_password)) {
                if (empty($current_password)) {
                    $error = 'Current password is required to change password';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'Passwords do not match';
                } elseif (strlen($new_password) < 6) {
                    $error = 'New password must be at least 6 characters';
                } elseif (!password_verify($current_password, $user['password'])) {
                    $error = 'Current password is incorrect';
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([password_hash($new_password, PASSWORD_DEFAULT), $user_id]);
                }
            }

            if (empty($error)) {
                $_SESSION['user_name'] = $username;
                $_SESSION['username'] = $username;
                $message = 'Profile updated successfully!';
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            }
        } catch (Exception $e) {
            $error = 'Error updating profile: ' . $e->getMessage();
        }
    }
}

$userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);
?>

<?php
    $page_title = 'Profile Settings - Scroll Novels';
    $page_head = '<script src="https://cdn.tailwindcss.com"></script>'
        . '<script>tailwind.config={darkMode:"class"};</script>'
        . '<link rel="stylesheet" href="' . asset_url('css/global.css') . '">'
        . '<link rel="stylesheet" href="' . asset_url('css/theme.css') . '">'
        . '<script src="' . asset_url('js/theme.js') . '" defer></script>'
        . '<style>:root{--transition-base:200ms ease-in-out}body{transition:background-color var(--transition-base),color var(--transition-base)}</style>';

    require_once __DIR__ . '/../includes/header.php';
?>

<!-- Main Content -->
<main class="flex-1">
    <div class="max-w-2xl mx-auto px-4 py-12">
        <h1 class="text-3xl font-bold text-emerald-700 dark:text-emerald-400 mb-8">Profile Settings</h1>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/20 border border-green-300 dark:border-green-700 rounded-lg text-green-700 dark:text-green-400">
                ✓ <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/20 border border-red-300 dark:border-red-700 rounded-lg text-red-700 dark:text-red-400">
                ✕ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Settings Form -->
    <form method="POST" enctype="multipart/form-data" class="bg-white dark:bg-gray-800 rounded-lg p-4 sm:p-8 shadow border border-emerald-200 dark:border-emerald-900 space-y-6">
            <!-- Username -->
            <div>
                <label for="username" class="block text-xs sm:text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Username</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 text-emerald-900 dark:text-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600">
            </div>

            <!-- Email -->
            <div>
                <label for="email" class="block text-xs sm:text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 text-emerald-900 dark:text-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600">
            </div>

            <!-- Bio -->
            <div>
                <label for="bio" class="block text-xs sm:text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Bio <span class="text-gray-500 text-xs">(max 500)</span></label>
                <textarea id="bio" name="bio" rows="4" maxlength="500" class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 text-emerald-900 dark:text-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
            </div>

            <!-- Country -->
            <div>
    <label for="country" class="block text-xs sm:text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Country <span class="text-gray-500 text-xs">(optional)</span></label>
    <select id="country" name="country" class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 text-emerald-900 dark:text-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600">
        <option value="">-- Select Country --</option>
        <?php
        $currentCountry = $user['country'] ?? '';
        $countries = [
            'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua and Barbuda',
            'Argentina', 'Armenia', 'Australia', 'Austria', 'Azerbaijan', 'Bahamas', 'Bahrain',
            'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan', 'Bolivia',
            'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso',
            'Burundi', 'Cambodia', 'Cameroon', 'Canada', 'Cape Verde', 'Central African Republic',
            'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Congo', 'Costa Rica', 'Croatia',
            'Cuba', 'Cyprus', 'Czech Republic', 'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic',
            'East Timor', 'Ecuador', 'Egypt', 'El Salvador', 'Equatorial Guinea', 'Eritrea',
            'Estonia', 'Ethiopia', 'Fiji', 'Finland', 'France', 'Gabon', 'Gambia', 'Georgia',
            'Germany', 'Ghana', 'Greece', 'Grenada', 'Guatemala', 'Guinea', 'Guinea-Bissau',
            'Guyana', 'Haiti', 'Honduras', 'Hong Kong', 'Hungary', 'Iceland', 'India', 'Indonesia',
            'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy', 'Ivory Coast', 'Jamaica', 'Japan',
            'Jordan', 'Kazakhstan', 'Kenya', 'Kiribati', 'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia',
            'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg',
            'Macao', 'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta',
            'Marshall Islands', 'Mauritania', 'Mauritius', 'Mexico', 'Micronesia', 'Moldova',
            'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique', 'Myanmar', 'Namibia',
            'Nauru', 'Nepal', 'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria',
            'North Korea', 'North Macedonia', 'Norway', 'Oman', 'Pakistan', 'Palau', 'Palestine',
            'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal',
            'Qatar', 'Romania', 'Russia', 'Rwanda', 'Saint Kitts and Nevis', 'Saint Lucia',
            'Saint Vincent and the Grenadines', 'Samoa', 'San Marino', 'Sao Tome and Principe',
            'Saudi Arabia', 'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore',
            'Slovakia', 'Slovenia', 'Solomon Islands', 'Somalia', 'South Africa', 'South Korea',
            'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Sweden', 'Switzerland',
            'Syria', 'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand', 'Togo', 'Tonga',
            'Trinidad and Tobago', 'Tunisia', 'Turkey', 'Turkmenistan', 'Tuvalu', 'Uganda',
            'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay',
            'Uzbekistan', 'Vanuatu', 'Vatican City', 'Venezuela', 'Vietnam', 'Yemen', 'Zambia',
            'Zimbabwe'
        ];
        
        foreach ($countries as $country) {
            $selected = ($currentCountry === $country) ? 'selected' : '';
            echo "<option value=\"" . htmlspecialchars($country) . "\" $selected>" . htmlspecialchars($country) . "</option>\n";
        }
        ?>
    </select>
</div>

            <hr class="border-emerald-200 dark:border-emerald-900">

            <!-- Support Links Section -->
            <div>
                <h2 class="text-base sm:text-lg font-bold text-emerald-700 dark:text-emerald-400 mb-4">💰 Support Links</h2>
                <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 mb-4">Add your Patreon and Ko-fi links so readers can support you:</p>
                
                <!-- Patreon Link -->
                <div class="mb-4">
                    <label for="patreon" class="block text-xs sm:text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Patreon Link <span class="text-gray-500 text-xs">(optional)</span></label>
                    <input type="url" id="patreon" name="patreon" placeholder="https://patreon.com/yourname" value="<?= htmlspecialchars($user['patreon'] ?? '') ?>" class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 text-emerald-900 dark:text-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                </div>

                <!-- Ko-fi Link -->
                <div class="mb-4">
                    <label for="kofi" class="block text-xs sm:text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Ko-fi Link <span class="text-gray-500 text-xs">(optional)</span></label>
                    <input type="url" id="kofi" name="kofi" placeholder="https://ko-fi.com/yourname" value="<?= htmlspecialchars($user['kofi'] ?? '') ?>" class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 text-emerald-900 dark:text-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                </div>
            </div>

            <hr class="border-emerald-200 dark:border-emerald-900">
            <!-- Avatar Upload -->
            <div>
                <label for="avatar" class="block text-xs sm:text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Profile Picture</label>
                <input type="file" id="avatar" name="avatar" accept="image/*" class="w-full text-xs sm:text-sm">
                <p class="text-xs text-gray-500 mt-1">Allowed: jpg, jpeg, png, webp. Max 2MB.</p>
            </div>

            <hr class="border-emerald-200 dark:border-emerald-900">

            <!-- Change Password Section -->
            <div>
                <h2 class="text-base sm:text-lg font-bold text-emerald-700 dark:text-emerald-400 mb-4">Change Password <span class="text-xs sm:text-sm font-normal text-gray-500">(leave blank to keep current)</span></h2>
                
                <!-- Current Password -->
                <div class="mb-4">
                    <label for="current_password" class="block text-xs sm:text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 text-emerald-900 dark:text-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                </div>

                <!-- New Password -->
                <div class="mb-4">
                    <label for="new_password" class="block text-xs sm:text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 text-emerald-900 dark:text-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                </div>

                <!-- Confirm Password -->
                <div class="mb-4">
                    <label for="confirm_password" class="block text-xs sm:text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 sm:px-4 py-2 sm:py-3 text-sm border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 text-emerald-900 dark:text-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                </div>
            </div>

            <!-- Submit -->
            <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 pt-4">
                <button type="submit" class="flex-1 px-4 sm:px-6 py-3 text-sm sm:text-base bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors">💾 Save</button>
                <a href="<?= site_url('/pages/profile.php?user=' . urlencode($user['username'])) ?>" class="flex-1 text-center px-4 sm:px-6 py-3 text-sm sm:text-base border-2 border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20 font-medium transition-colors">← Back</a>
            </div>
        </form>
    </div>
</main>

<!-- Footer -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const closeSidebarBtn = document.getElementById('closeSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('translate-x-full');
        sidebar.classList.add('translate-x-0');
        if (overlay) {
            overlay.classList.remove('hidden');
            overlay.classList.add('block');
        }
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('translate-x-full');
        sidebar.classList.remove('translate-x-0');
        if (overlay) {
            overlay.classList.add('hidden');
            overlay.classList.remove('block');
        }
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e){
            e.preventDefault();
            openSidebar();
        });
    }

    if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);
});
</script>

</body>
</html>

