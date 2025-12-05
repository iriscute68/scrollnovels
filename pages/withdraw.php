<?php
/**
 * pages/withdraw.php - Author Donation Management & Withdrawal System
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();

$page_title = 'Donation Management';
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Fetch user details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    header("Location: " . site_url());
    exit;
}

// Get donations received
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as donor_count, COALESCE(SUM(amount), 0) as total_amount
        FROM donations 
        WHERE recipient_id = ? AND type = 'author' AND status = 'success'
    ");
    $stmt->execute([$user_id]);
    $donationStats = $stmt->fetch();
    $total_donations = (float)($donationStats['total_amount'] ?? 0);
    $donor_count = (int)($donationStats['donor_count'] ?? 0);
} catch (Exception $e) {
    $total_donations = 0;
    $donor_count = 0;
}

// Get withdrawn total
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_withdrawn
        FROM withdrawals 
        WHERE user_id = ? AND status IN ('completed', 'paid')
    ");
    $stmt->execute([$user_id]);
    $withdrawalStats = $stmt->fetch();
    $total_withdrawn = (float)($withdrawalStats['total_withdrawn'] ?? 0);
} catch (Exception $e) {
    $total_withdrawn = 0;
}

// Available balance = total donations - total withdrawn - pending withdrawals
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as pending_amount
        FROM withdrawals 
        WHERE user_id = ? AND status IN ('pending', 'processing')
    ");
    $stmt->execute([$user_id]);
    $pendingStats = $stmt->fetch();
    $pending_amount = (float)($pendingStats['pending_amount'] ?? 0);
} catch (Exception $e) {
    $pending_amount = 0;
}

$available_balance = max(0, $total_donations - $total_withdrawn - $pending_amount);

// Get withdrawal history
$withdrawals = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM withdrawals 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $withdrawals = [];
}

// Get recent donations
$recent_donations = [];
try {
    $stmt = $pdo->prepare("
        SELECT d.*, u.username as donor_name
        FROM donations d
        LEFT JOIN users u ON d.donor_id = u.id
        WHERE d.recipient_id = ? AND d.type = 'author' AND d.status = 'success'
        ORDER BY d.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_donations = [];
}

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'withdraw') {
    $amount = (float)($_POST['amount'] ?? 0);
    $method = trim($_POST['method'] ?? '');

    // Validation
    if ($amount <= 0) {
        $message = 'Please enter a valid amount';
        $message_type = 'error';
    } elseif ($amount > $available_balance) {
        $message = 'Insufficient available balance';
        $message_type = 'error';
    } elseif ($amount < 5) {
        $message = 'Minimum withdrawal is $5';
        $message_type = 'error';
    } elseif (empty($method) || !in_array($method, ['bank', 'paypal', 'paystack'])) {
        $message = 'Please select a payment method';
        $message_type = 'error';
    } else {
        try {
            // Ensure withdrawals table exists
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS withdrawals (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    amount DECIMAL(10, 2) NOT NULL,
                    method VARCHAR(50) NOT NULL,
                    status ENUM('pending', 'processing', 'completed', 'paid', 'rejected') DEFAULT 'pending',
                    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    completed_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_user (user_id),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Create withdrawal record
            $stmt = $pdo->prepare("
                INSERT INTO withdrawals (user_id, amount, method, status, requested_at)
                VALUES (?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$user_id, $amount, $method]);
            
            $message = "Withdrawal request of \$$amount submitted successfully! We'll process it within 24-48 hours.";
            $message_type = 'success';
            
            // Refresh page to show new data
            header("Refresh: 2");
        } catch (Exception $e) {
            $message = 'Error processing withdrawal: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>

<?php
    if (empty($page_title)) $page_title = 'Donation Management';
    $page_head = '<script src="https://cdn.tailwindcss.com"></script>'
        . '<link rel="stylesheet" href="' . asset_url('css/global.css') . '">'
        . '<link rel="stylesheet" href="' . asset_url('css/theme.css') . '">';

    require_once __DIR__ . '/../includes/header.php';
?>

<main class="flex-1 max-w-6xl mx-auto px-4 py-12 w-full">
    <!-- Back Button -->
    <a href="<?= site_url('/pages/dashboard.php') ?>" class="text-emerald-600 dark:text-emerald-400 hover:underline mb-6 inline-block">← Back to Dashboard</a>

    <!-- Page Title -->
    <h1 class="text-4xl font-bold text-emerald-700 dark:text-emerald-400 mb-2">💰 Donation Management</h1>
    <p class="text-gray-600 dark:text-gray-400 mb-8">Track your donations and manage withdrawals</p>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg border <?= $message_type === 'success' ? 'bg-emerald-100 dark:bg-emerald-900/30 border-emerald-300 dark:border-emerald-700 text-emerald-800 dark:text-emerald-300' : 'bg-red-100 dark:bg-red-900/30 border-red-300 dark:border-red-700 text-red-800 dark:text-red-300' ?>">
            <?= $message_type === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
        <!-- Total Donations -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm border border-emerald-200 dark:border-emerald-900">
            <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2">Total Donations Received</h3>
            <div class="text-3xl font-bold text-emerald-600 dark:text-emerald-400 mb-2">$<?= number_format($total_donations, 2) ?></div>
            <p class="text-sm text-gray-500 dark:text-gray-500">From <?= $donor_count ?> donor<?= $donor_count !== 1 ? 's' : '' ?></p>
        </div>

        <!-- Available Balance -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm border border-blue-200 dark:border-blue-900">
            <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2">Available Balance</h3>
            <div class="text-3xl font-bold text-blue-600 dark:text-blue-400 mb-2">$<?= number_format($available_balance, 2) ?></div>
            <p class="text-sm text-gray-500 dark:text-gray-500">Ready to withdraw</p>
        </div>

        <!-- Withdrawn Total -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm border border-purple-200 dark:border-purple-900">
            <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2">Withdrawn Total</h3>
            <div class="text-3xl font-bold text-purple-600 dark:text-purple-400 mb-2">$<?= number_format($total_withdrawn, 2) ?></div>
            <p class="text-sm text-gray-500 dark:text-gray-500"><?= count(array_filter($withdrawals, fn($w) => $w['status'] === 'completed')) ?> successful</p>
        </div>
    </div>

    <!-- Withdrawal Form -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-8 shadow-sm border border-emerald-200 dark:border-emerald-900 mb-12">
        <h2 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400 mb-6">Request Withdrawal</h2>
        
        <form method="POST" class="space-y-6">
            <input type="hidden" name="action" value="withdraw">
            
            <!-- Amount -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Withdrawal Amount ($)</label>
                <div class="flex gap-2">
                    <input 
                        type="number" 
                        name="amount" 
                        step="0.01" 
                        min="5" 
                        max="<?= $available_balance ?>" 
                        placeholder="Enter amount" 
                        required
                        class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    >
                    <span class="flex items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm font-semibold text-gray-600 dark:text-gray-400">Max: $<?= number_format($available_balance, 2) ?></span>
                </div>
            </div>

            <!-- Payment Method -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Payment Method</label>
                <div class="space-y-2">
                    <label class="flex items-center p-3 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <input type="radio" name="method" value="bank" required class="mr-3">
                        <span class="font-medium text-gray-700 dark:text-gray-300">🏦 Bank Transfer</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <input type="radio" name="method" value="paypal" class="mr-3">
                        <span class="font-medium text-gray-700 dark:text-gray-300">💳 PayPal</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <input type="radio" name="method" value="paystack" class="mr-3">
                        <span class="font-medium text-gray-700 dark:text-gray-300">🏦 Paystack</span>
                    </label>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="w-full px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold transition-colors">
                Request Withdrawal
            </button>
        </form>
    </div>

    <!-- Withdrawal History -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-emerald-200 dark:border-emerald-900 mb-12">
        <div class="p-8 border-b border-emerald-200 dark:border-emerald-900">
            <h2 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">Withdrawal History</h2>
        </div>
        
        <?php if (!empty($withdrawals)): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Amount</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Status</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Request Date</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Completed Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($withdrawals as $w): ?>
                            <tr class="border-b border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">$<?= number_format($w['amount'], 2) ?></td>
                                <td class="px-6 py-4">
                                    <?php
                                    $status = $w['status'] ?? '';
                                    $status_color = 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300';
                                    if ($status === 'pending') {
                                        $status_color = 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300';
                                    } elseif ($status === 'processing') {
                                        $status_color = 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300';
                                    } elseif ($status === 'completed' || $status === 'paid') {
                                        $status_color = 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300';
                                    } elseif ($status === 'rejected') {
                                        $status_color = 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300';
                                    }

                                    $status_icon = '•';
                                    if ($status === 'pending') {
                                        $status_icon = '⏳';
                                    } elseif ($status === 'processing') {
                                        $status_icon = '⚙️';
                                    } elseif ($status === 'completed' || $status === 'paid') {
                                        $status_icon = '✅';
                                    } elseif ($status === 'rejected') {
                                        $status_icon = '❌';
                                    }
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-sm font-semibold <?= $status_color ?>">
                                        <?= $status_icon ?> <?= ucfirst($w['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400"><?= date('M d, Y', strtotime($w['requested_at'] ?? $w['created_at'])) ?></td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400"><?= !empty($w['completed_at']) ? date('M d, Y', strtotime($w['completed_at'])) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-12 text-center">
                <p class="text-gray-600 dark:text-gray-400">No withdrawal requests yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Donations -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-emerald-200 dark:border-emerald-900">
        <div class="p-8 border-b border-emerald-200 dark:border-emerald-900">
            <h2 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">Recent Donations</h2>
        </div>
        
        <?php if (!empty($recent_donations)): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Donor</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Amount</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Book</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_donations as $d): ?>
                            <tr class="border-b border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">@<?= htmlspecialchars($d['donor_name'] ?? 'Anonymous') ?></td>
                                <td class="px-6 py-4 font-bold text-emerald-600 dark:text-emerald-400">$<?= number_format($d['amount'], 2) ?></td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400"><?= htmlspecialchars($d['story_id'] ? 'Book #' . $d['story_id'] : 'General Support') ?></td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400"><?= date('M d, Y', strtotime($d['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-12 text-center">
                <p class="text-gray-600 dark:text-gray-400">No donations received yet. Share your stories to get donations!</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>

