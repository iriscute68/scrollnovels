<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Require login
requireLogin();

$page_title = 'Blocked Users - Scroll Novels';
$page_head = '';
$user_id = $_SESSION['user_id'];

// Handle unblock request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['unblock_id'])) {
    $unblock_id = (int)$_POST['unblock_id'];
    
    try {
        // Create user_blocks table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_blocks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            blocker_id INT NOT NULL,
            blocked_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_block (blocker_id, blocked_id),
            FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $stmt = $pdo->prepare("DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
        $stmt->execute([$user_id, $unblock_id]);
        $_SESSION['message'] = 'User unblocked successfully';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Failed to unblock user';
    }
    header("Location: " . site_url('/pages/blocked-users.php'));
    exit;
}

// Fetch blocked users
try {
    // Create user_blocks table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_blocks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        blocker_id INT NOT NULL,
        blocked_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_block (blocker_id, blocked_id),
        FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.profile_image, u.bio, u.created_at
        FROM user_blocks ub
        LEFT JOIN users u ON ub.blocked_id = u.id
        WHERE ub.blocker_id = ?
        ORDER BY ub.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $blockedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $blockedUsers = [];
    $_SESSION['error'] = 'Failed to fetch blocked users';
}

require_once __DIR__ . '/../includes/header.php';
?>
?>
<main class="flex-1 max-w-4xl mx-auto px-4 py-12 w-full">
    <!-- Page Title -->
    <div class="mb-8">
        <h1 class="text-4xl font-bold text-emerald-700 dark:text-emerald-400 mb-2">🚫 Blocked Users</h1>
        <p class="text-gray-600 dark:text-gray-400">Manage the list of users you've blocked</p>
    </div>

    <!-- Messages -->
    <?php if (!empty($_SESSION['message'])): ?>
        <div class="mb-6 p-4 bg-emerald-100 dark:bg-emerald-900/30 border border-emerald-300 dark:border-emerald-700 rounded-lg text-emerald-800 dark:text-emerald-300">
            ✅ <?= htmlspecialchars($_SESSION['message']) ?>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 rounded-lg text-red-800 dark:text-red-300">
            ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Blocked Users List -->
    <?php if (!empty($blockedUsers)): ?>
        <div class="space-y-4">
            <?php foreach ($blockedUsers as $blockedUser): ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm border border-emerald-200 dark:border-emerald-900 hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between">
                        <!-- User Info -->
                        <div class="flex items-start gap-4 flex-1">
                            <!-- Avatar -->
                            <div class="flex-shrink-0">
                                <?php if (!empty($blockedUser['avatar'])): ?>
                                    <img src="<?= htmlspecialchars($blockedUser['avatar']) ?>" alt="<?= htmlspecialchars($blockedUser['username']) ?>" class="w-14 h-14 rounded-full object-cover border-2 border-emerald-200">
                                <?php else: ?>
                                    <div class="w-14 h-14 rounded-full bg-emerald-200 dark:bg-emerald-900 flex items-center justify-center text-2xl">
                                        👤
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- User Details -->
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-emerald-700 dark:text-emerald-400">
                                    <a href="<?= site_url('/pages/profile.php?user_id=' . $blockedUser['id']) ?>" class="hover:underline">
                                        @<?= htmlspecialchars($blockedUser['username']) ?>
                                    </a>
                                </h3>
                                
                                <?php if (!empty($blockedUser['bio'])): ?>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 line-clamp-2">
                                        <?= htmlspecialchars($blockedUser['bio']) ?>
                                    </p>
                                <?php endif; ?>

                                <p class="text-xs text-gray-500 dark:text-gray-500 mt-2">
                                    Blocked since: <?= date('M d, Y', strtotime($blockedUser['created_at'] ?? 'now')) ?>
                                </p>
                            </div>
                        </div>

                        <!-- Unblock Button -->
                        <form method="POST" class="flex-shrink-0 ml-4">
                            <input type="hidden" name="unblock_id" value="<?= $blockedUser['id'] ?>">
                            <button type="submit" onclick="return confirm('Unblock @<?= htmlspecialchars($blockedUser['username']) ?>?')" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-medium transition-colors text-sm">
                                🔓 Unblock
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Stats -->
        <div class="mt-8 p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-900 rounded-lg">
            <p class="text-sm text-emerald-700 dark:text-emerald-300">
                📊 You have blocked <strong><?= count($blockedUsers) ?></strong> user<?= count($blockedUsers) !== 1 ? 's' : '' ?>
            </p>
        </div>
    <?php else: ?>
        <!-- Empty State -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-12 shadow-sm border border-emerald-200 dark:border-emerald-900 text-center">
            <div class="text-6xl mb-4">🟢</div>
            <h2 class="text-xl font-bold text-gray-700 dark:text-gray-300 mb-2">No Blocked Users</h2>
            <p class="text-gray-600 dark:text-gray-400">You haven't blocked anyone yet. Your community interactions are open!</p>
        </div>
    <?php endif; ?>

    <!-- Help Section -->
    <div class="mt-12 p-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-900 rounded-lg">
        <h3 class="font-bold text-blue-900 dark:text-blue-300 mb-2">ℹ️ About Blocking</h3>
        <ul class="text-sm text-blue-800 dark:text-blue-300 space-y-1">
            <li>• When you block someone, they cannot see your profile</li>
            <li>• Blocked users cannot send you messages or comments</li>
            <li>• You can unblock users at any time from this page</li>
            <li>• Your block list is private and not shared with others</li>
        </ul>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>

