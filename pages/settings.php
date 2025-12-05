<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$user = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    header("Location: " . site_url());
    exit;
}

if (!$user) {
    header("Location: " . site_url('/pages/logout.php'));
    exit;
}

$message = '';
$message_type = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bio = trim($_POST['bio'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $privacy = $_POST['privacy'] ?? 'public';

    if (empty($email)) {
        $message = 'Email is required';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email format';
        $message_type = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET bio = ?, email = ? WHERE id = ?");
            $stmt->execute([$bio, $email, $user_id]);
            
            $_SESSION['user_name'] = $user['username'];
            $message = 'Settings updated successfully!';
            $message_type = 'success';
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            $message = 'Error updating settings: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

$userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);
?>

<?php
    $page_title = 'Settings - Scroll Novels';
    $page_head = '<script src="https://cdn.tailwindcss.com"></script>'
        . '<script>tailwind.config={darkMode:"class"};</script>'
        . '<link rel="stylesheet" href="' . asset_url('css/global.css') . '">'
        . '<link rel="stylesheet" href="' . asset_url('css/theme.css') . '">'
        . '<script src="' . asset_url('js/theme.js') . '" defer></script>';

    require_once __DIR__ . '/../includes/header.php';
?>

<!-- Main Content -->
<main class="flex-1">
    <div class="max-w-2xl mx-auto px-4 py-12">
        <!-- Page Title -->
        <div class="mb-8">
            <h2 class="text-4xl font-bold text-emerald-700 dark:text-emerald-400 mb-2">⚙️ Settings</h2>
            <p class="text-gray-600 dark:text-gray-400">Manage your account preferences</p>
        </div>

        <!-- Message -->
        <?php if ($message): ?>
            <div class="mb-8 p-4 rounded-lg border <?= $message_type === 'success' ? 'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-200' : 'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-200' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Settings Form -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-8 shadow border border-emerald-200 dark:border-emerald-900 mb-8">
            <h3 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400 mb-6">Account Information</h3>

            <form method="POST" class="space-y-6">
                <!-- Username (readonly) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Username</label>
                    <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled 
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 cursor-not-allowed">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Username cannot be changed</p>
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Address *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                </div>

                <!-- Bio -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Bio</label>
                    <textarea name="bio" rows="4" placeholder="Tell readers about yourself..." 
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Maximum 500 characters</p>
                </div>

                <!-- Verification Status -->
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg p-4">
                    <h4 class="font-medium text-emerald-900 dark:text-emerald-200 mb-3">Verification Status</h4>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">
                                <?= ($user['is_verified_artist'] ?? 0) ? '✅' : '⭕' ?>
                            </span>
                            <span class="text-sm text-emerald-700 dark:text-emerald-300">
                                <?= ($user['is_verified_artist'] ?? 0) ? 'Verified Artist' : 'Not a verified artist' ?>
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-lg">
                                <?= ($user['is_verified_editor'] ?? 0) ? '✅' : '⭕' ?>
                            </span>
                            <span class="text-sm text-emerald-700 dark:text-emerald-300">
                                <?= ($user['is_verified_editor'] ?? 0) ? 'Verified Editor' : 'Not a verified editor' ?>
                            </span>
                        </div>
                    </div>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-3">
                        Contact support to apply for artist or editor verification
                    </p>
                </div>

                <!-- Account Stats -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 text-center">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Member Since</p>
                        <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">
                            <?= date('M d, Y', strtotime($user['created_at'] ?? 'now')) ?>
                        </p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 text-center">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Account ID</p>
                        <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">#<?= $user['id'] ?></p>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="submit" class="flex-1 px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors">
                        💾 Save Changes
                    </button>
                    <a href="<?= site_url('/pages/dashboard.php') ?>" class="flex-1 px-6 py-3 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-medium transition-colors text-center hover:bg-gray-50 dark:hover:bg-gray-700">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Danger Zone -->
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-8 shadow">
            <h3 class="text-2xl font-bold text-red-700 dark:text-red-400 mb-4">⚠️ Danger Zone</h3>
            <p class="text-sm text-red-600 dark:text-red-300 mb-4">These actions cannot be undone</p>
            
            <div class="space-y-3">
                <button type="button" onclick="alert('Password change feature coming soon!')" class="w-full px-6 py-3 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-300 rounded-lg font-medium hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors">
                    🔐 Change Password
                </button>
                <button type="button" onclick="alert('Account deletion feature coming soon!')" class="w-full px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors">
                    🗑️ Delete Account
                </button>
            </div>
        </div>
    </div>
</main>

<!-- Footer -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>

