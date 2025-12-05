<?php
// pages/book-support.php - Support a book with points and view rankings

session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$bookId = (int)($_GET['id'] ?? 0);
if (!$bookId) {
    header('Location: ' . site_url('/pages/browse.php'));
    exit;
}

// Get book info
$stmt = $pdo->prepare("
    SELECT s.*, u.username as author, u.id as author_id
    FROM stories s
    LEFT JOIN users u ON s.author_id = u.id
    WHERE s.id = ? AND s.status = 'published'
");
$stmt->execute([$bookId]);
$book = $stmt->fetch();

if (!$book) {
    header('Location: ' . site_url('/pages/browse.php'));
    exit;
}

// Get current user's points if logged in
$userPoints = null;
$userId = $_SESSION['user_id'] ?? null;
if ($userId) {
    $stmt = $pdo->prepare("SELECT * FROM user_points WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userPoints = $stmt->fetch();
}

// Get book ranking info
$stmt = $pdo->prepare("
    SELECT * FROM book_rankings 
    WHERE book_id = ? 
    ORDER BY FIELD(rank_type, 'daily', 'weekly', 'monthly', 'all_time')
");
$stmt->execute([$bookId]);
$rankings = $stmt->fetchAll();

// Get top supporters of this book
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.username,
        u.profile_image,
        SUM(bs.effective_points) as total_support,
        COUNT(*) as support_count
    FROM book_support bs
    JOIN users u ON bs.user_id = u.id
    WHERE bs.book_id = ?
    GROUP BY bs.user_id
    ORDER BY total_support DESC
    LIMIT 10
");
$stmt->execute([$bookId]);
$topSupporters = $stmt->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-b from-emerald-50 to-green-100 dark:from-gray-900 dark:to-gray-800">
    <main class="max-w-6xl mx-auto px-4 py-8">
        <!-- Back Button -->
        <div class="mb-6">
            <a href="<?= site_url('/pages/book.php?id=' . $bookId) ?>" class="text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 font-medium">
                ← Back to <?= htmlspecialchars($book['title']) ?>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Book Info + Support Widget -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-emerald-200 dark:border-emerald-900 mb-6">
                    <div class="flex gap-4">
                        <img src="<?= htmlspecialchars($book['cover_url'] ?? '') ?>" alt="<?= htmlspecialchars($book['title']) ?>" 
                             class="w-24 h-32 rounded-lg shadow-md object-cover">
                        <div class="flex-1">
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2"><?= htmlspecialchars($book['title']) ?></h1>
                            <p class="text-gray-600 dark:text-gray-400 mb-4">By <strong><?= htmlspecialchars($book['author'] ?? 'Unknown') ?></strong></p>
                            
                            <!-- Rankings Display -->
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <?php foreach ($rankings as $rank): ?>
                                    <div class="bg-gradient-to-br from-emerald-50 to-green-50 dark:from-emerald-900/20 dark:to-green-900/20 p-3 rounded-lg border border-emerald-200 dark:border-emerald-700">
                                        <div class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold uppercase"><?= ucfirst($rank['rank_type']) ?></div>
                                        <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-300"><?= number_format($rank['total_support_points'] ?? 0) ?></div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400"><?= ($rank['supporter_count'] ?? 0) ?> supporters</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Support Options -->
                <?php if ($userId): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-emerald-200 dark:border-emerald-900">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">💝 Support This Book</h2>
                        
                        <!-- Point Type Selector -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Support Type:</label>
                            <div class="grid grid-cols-3 gap-3">
                                <label class="flex items-center p-3 border-2 border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:border-emerald-500 transition-colors">
                                    <input type="radio" name="pointType" value="free" checked class="support-type-input">
                                    <span class="ml-2">
                                        <span class="block font-medium text-gray-900 dark:text-white">Free</span>
                                        <span class="text-xs text-gray-500">1x Points</span>
                                    </span>
                                </label>
                                <label class="flex items-center p-3 border-2 border-blue-300 dark:border-blue-700 rounded-lg cursor-pointer hover:border-blue-500 transition-colors bg-blue-50 dark:bg-blue-900/10">
                                    <input type="radio" name="pointType" value="premium" class="support-type-input">
                                    <span class="ml-2">
                                        <span class="block font-medium text-blue-900 dark:text-blue-300">Premium</span>
                                        <span class="text-xs text-blue-700 dark:text-blue-400">2x Points</span>
                                    </span>
                                </label>
                                <label class="flex items-center p-3 border-2 border-purple-300 dark:border-purple-700 rounded-lg cursor-pointer hover:border-purple-500 transition-colors bg-purple-50 dark:bg-purple-900/10">
                                    <input type="radio" name="pointType" value="patreon" class="support-type-input">
                                    <span class="ml-2">
                                        <span class="block font-medium text-purple-900 dark:text-purple-300">Patreon</span>
                                        <span class="text-xs text-purple-700 dark:text-purple-400">3x Points</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <!-- Points Options -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Amount:</label>
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                                <?php foreach ([10, 50, 100, 500, 1000] as $pts): ?>
                                    <button class="support-btn p-4 rounded-lg border-2 border-gray-300 dark:border-gray-600 hover:border-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-all text-center" data-points="<?= $pts ?>">
                                        <div class="text-xl font-bold text-gray-900 dark:text-white"><?= $pts ?></div>
                                        <div class="text-xs text-gray-500">Points</div>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Current Points Display -->
                        <div class="mb-6 p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 rounded-lg">
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">Free Points</div>
                                    <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($userPoints['free_points'] ?? 0) ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">Premium Points</div>
                                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400"><?= number_format($userPoints['premium_points'] ?? 0) ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">Patreon Points</div>
                                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-400"><?= number_format($userPoints['patreon_points'] ?? 0) ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Support Button -->
                        <button id="supportBtn" class="w-full px-6 py-4 bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700 text-white rounded-lg font-bold text-lg transition-all shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                            💝 Support Now
                        </button>

                        <!-- Message -->
                        <div id="supportMessage" class="mt-4 hidden p-3 rounded-lg text-sm font-medium"></div>
                    </div>
                <?php else: ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-emerald-200 dark:border-emerald-900 text-center">
                        <p class="text-gray-600 dark:text-gray-400 mb-4">Please log in to support this book</p>
                        <a href="<?= site_url('/pages/login.php') ?>" class="inline-block px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium">
                            Log In Now
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Sidebar: Top Supporters -->
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-emerald-200 dark:border-emerald-900 sticky top-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">🏆 Top Supporters (<?= count($topSupporters) ?>)</h3>
                    
                    <?php if (!empty($topSupporters)): ?>
                        <div class="space-y-3">
                            <?php foreach ($topSupporters as $idx => $supporter): ?>
                                <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="flex-shrink-0">
                                        <?php if ($supporter['profile_image']): ?>
                                            <img src="<?= htmlspecialchars($supporter['profile_image']) ?>" alt="<?= htmlspecialchars($supporter['username']) ?>" 
                                                 class="w-10 h-10 rounded-full object-cover">
                                        <?php else: ?>
                                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-400 to-green-500 flex items-center justify-center text-white font-bold text-sm">
                                                <?= substr($supporter['username'] ?? 'U', 0, 1) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white truncate"><?= htmlspecialchars($supporter['username']) ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400"><?= number_format($supporter['total_support']) ?> points</p>
                                    </div>
                                    <div class="flex-shrink-0 text-xl font-bold text-emerald-600 dark:text-emerald-400">
                                        #<?= $idx + 1 ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <p class="text-gray-500 dark:text-gray-400">No supporters yet</p>
                            <p class="text-sm text-gray-400 dark:text-gray-500 mt-2">Be the first to support!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
let selectedPoints = null;
let selectedType = 'free';

// Select points
document.querySelectorAll('.support-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.support-btn').forEach(b => b.classList.remove('border-emerald-500', 'bg-emerald-50'));
        this.classList.add('border-emerald-500', 'bg-emerald-50');
        selectedPoints = parseInt(this.dataset.points);
    });
});

// Select type
document.querySelectorAll('.support-type-input').forEach(input => {
    input.addEventListener('change', function() {
        selectedType = this.value;
    });
});

// Support button
document.getElementById('supportBtn').addEventListener('click', async function() {
    if (!selectedPoints) {
        showMessage('Please select an amount', 'error');
        return;
    }

    this.disabled = true;

    try {
        const response = await fetch('<?= site_url('/api/support-book.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                book_id: <?= $bookId ?>,
                points: selectedPoints,
                point_type: selectedType
            })
        });

        const data = await response.json();

        if (data.success) {
            showMessage('✓ ' + data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showMessage('✗ ' + (data.error || 'Failed to support'), 'error');
        }
    } catch (e) {
        showMessage('✗ Network error: ' + e.message, 'error');
    } finally {
        this.disabled = false;
    }
});

function showMessage(msg, type) {
    const msgDiv = document.getElementById('supportMessage');
    msgDiv.textContent = msg;
    msgDiv.className = type === 'success' 
        ? 'mt-4 p-3 rounded-lg text-sm font-medium bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300'
        : 'mt-4 p-3 rounded-lg text-sm font-medium bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300';
    msgDiv.classList.remove('hidden');
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
