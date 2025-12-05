<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Load config and DB
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

redirectIfLoggedIn();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (strlen($username) < 3) $errors[] = "Username too short (minimum 3 characters)";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address";
    if ($password !== $confirm) $errors[] = "Passwords don't match";
    if (strlen($password) < 6) $errors[] = "Password too weak (minimum 6 characters)";

    if (empty($errors)) {
        // Check username separately for a more specific error message
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = "Username is already taken. Please choose a different username.";
        }
        
        // Check email separately
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Email is already registered. Please use a different email or try logging in.";
            }
        }
        
        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            try {
                $ins = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([$username, $email, $hash, 'reader', 'active']);
                $user_id = $pdo->lastInsertId();

                // Log the user in
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['user_name'] = $username;
                $_SESSION['role'] = 'reader';

                header("Location: " . site_url());
                exit;
            } catch (Exception $e) {
                $errors[] = "Registration failed: " . $e->getMessage();
            }
        }
    }
}

$page_title = 'Register - Scroll Novels';
$page_head = '<script src="https://cdn.tailwindcss.com"></script>'
    . '<script>tailwind.config={darkMode:"class",theme:{extend:{colors:{primary:"#065f46",accent:"#10b981"}}}};</script>'
    . '<link rel="stylesheet" href="' . site_url('css/global.css') . '">'
    . '<link rel="stylesheet" href="' . site_url('css/theme.css') . '">'
    . '<script src="' . site_url('js/theme.js') . '" defer></script>'
    . '<style>:root{--color-primary:#10b981;--color-primary-dark:#059669;--bg-primary:#ffffff;--bg-secondary:#f9fafb;--bg-tertiary:#f3f4f6;--text-primary:#111827;--text-secondary:#6b7280;--border-color:#e5e7eb;--transition-base:200ms ease-in-out}[data-theme="dark"]{--bg-primary:#111827;--bg-secondary:#1f2937;--bg-tertiary:#374151;--text-primary:#f9fafb;--text-secondary:#d1d5db;--border-color:#374151}body{background-color:var(--bg-primary);color:var(--text-primary);transition:background-color var(--transition-base),color var(--transition-base)}</style>';

require_once __DIR__ . '/../includes/header.php';

?>

<!-- Main Content -->
<main class="flex-1 flex items-center justify-center py-12">
    <div class="w-full max-w-md px-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-emerald-200 dark:border-emerald-900 p-8">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-emerald-700 dark:text-emerald-400 mb-2">Join Scroll Novels</h2>
                <p class="text-gray-600 dark:text-gray-400">Create your account and start your story</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800">
                    <ul class="space-y-1">
                        <?php foreach ($errors as $e): ?>
                            <li class="text-sm text-red-700 dark:text-red-400">• <?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Username
                    </label>
                    <input type="text" name="username" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors" placeholder="Choose a username" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Email
                    </label>
                    <input type="email" name="email" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors" placeholder="Enter your email" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Password
                    </label>
                    <input type="password" name="password" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors" placeholder="Create a strong password" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Confirm Password
                    </label>
                    <input type="password" name="confirm" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors" placeholder="Confirm your password" required>
                </div>

                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold transition-colors">
                    Create Account
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-gray-600 dark:text-gray-400">
                    Already have an account? 
                    <a href="<?= site_url('/pages/login.php') ?>" class="font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                        Login here
                    </a>
                </p>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Nothing special needed for register page
});
</script>
</body>
</html>
