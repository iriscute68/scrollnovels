<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Load config and DB
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

redirectIfLoggedIn();

$userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username/email and password";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_name'] = $user['username'];
            // Set role from roles JSON (default to 'user' if not set)
            $roles = json_decode($user['roles'], true);
            $r = is_array($roles) && !empty($roles) ? $roles[0] : 'user';
            $_SESSION['role'] = $r;
            // also set the standardized role key
            $_SESSION['user_role'] = $r;
            header("Location: " . site_url());
            exit;
        } else {
            $error = "Invalid username or password";
        }
    }
}
?>
    <?php
        $page_title = 'Login - Scroll Novels';
        $page_head = '<style> :root { --color-primary: #10b981; --color-primary-dark: #059669; --bg-primary: #ffffff; --bg-secondary: #f9fafb; --bg-tertiary: #f3f4f6; --text-primary: #111827; --text-secondary: #6b7280; --border-color: #e5e7eb; --transition-base: 200ms ease-in-out; } [data-theme="dark"] { --bg-primary: #111827; --bg-secondary: #1f2937; --bg-tertiary: #374151; --text-primary: #f9fafb; --text-secondary: #d1d5db; --border-color: #374151; } body { background-color: var(--bg-primary); color: var(--text-primary); transition: background-color var(--transition-base), color var(--transition-base); } </style>';
        require_once __DIR__ . '/../includes/header.php';
    ?>

    <!-- Main Content -->
    <main class="flex-1 flex items-center justify-center py-12">
    <div class="w-full max-w-md px-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-emerald-200 dark:border-emerald-900 p-8">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-emerald-700 dark:text-emerald-400 mb-2">Welcome Back</h2>
                <p class="text-gray-600 dark:text-gray-400">Sign in to your Scroll Novels account</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Username or Email
                    </label>
                    <input type="text" name="username" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors" placeholder="Enter your username or email" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Password
                    </label>
                    <input type="password" name="password" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold transition-colors">
                    Login
                </button>
            </form>

            <!-- Google OAuth Button -->
            <?php
            $googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id' => '14679695374-2ouitfeqp4mso0h2vnu17avhhnqqe5ei.apps.googleusercontent.com',
                'redirect_uri' => 'http://localhost/pages/google-callback.php',
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'access_type' => 'online'
            ]);
            ?>
            <div class="mt-6">
                <div class="relative mb-4">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300 dark:border-gray-600"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400">Or</span>
                    </div>
                </div>
                
                <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="w-full flex items-center justify-center gap-3 px-4 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-600 font-semibold transition-colors">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Continue with Google
                </a>
            </div>
                <p class="text-gray-600 dark:text-gray-400">
                    Don't have an account? 
                    <a href="<?= site_url('/pages/register.php') ?>" class="font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                        Create one now
                    </a>
                </p>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Nothing special needed for login page
});
</script>
</body>
</html>
