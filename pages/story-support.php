<?php
/**
 * pages/story-support.php - Support author page with points system
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$page_title = 'Support Authors - Scroll Novels';
require_once dirname(__DIR__) . '/includes/header.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
$storyId = (int)($_GET['story_id'] ?? $_POST['story_id'] ?? 0);

// Get story and author info if story_id provided
$story = null;
$userPoints = 0;
$supportStats = [];

if ($storyId && $isLoggedIn) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.title, s.author_id, u.username as author_name, u.profile_image
        FROM stories s
        JOIN users u ON s.author_id = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$storyId]);
    $story = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get user's points
    $pointsStmt = $pdo->prepare("
        SELECT COALESCE(SUM(points), 0) as points
        FROM user_points
        WHERE user_id = ?
    ");
    $pointsStmt->execute([$userId]);
    $userPoints = (int)($pointsStmt->fetch()['points'] ?? 0);

    // Get support stats
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT supporter_id) as supporter_count,
            SUM(points_amount) as total_points
        FROM story_support
        WHERE story_id = ?
    ");
    $statsStmt->execute([$storyId]);
    $supportStats = $statsStmt->fetch(PDO::FETCH_ASSOC);
}

// Get top supporters if story_id provided
$topSupporters = [];
if ($storyId) {
    $stmt = $pdo->prepare("
        SELECT 
            u.id, u.username, u.profile_image,
            SUM(ss.points_amount) as total_points,
            COUNT(*) as support_count
        FROM story_support ss
        JOIN users u ON ss.supporter_id = u.id
        WHERE ss.story_id = ?
        GROUP BY ss.supporter_id
        ORDER BY total_points DESC
        LIMIT 10
    ");
    $stmt->execute([$storyId]);
    $topSupporters = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<main class="max-w-6xl mx-auto px-4 py-12">
    <?php if ($story && $isLoggedIn): ?>
        <!-- Support Modal -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Support Form -->
            <div class="md:col-span-2">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
                    <h1 class="text-3xl font-bold text-emerald-600 dark:text-emerald-400 mb-6">
                        Support {{ $story['author_name'] }}
                    </h1>

                    <div class="mb-8 p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg">
                        <p class="text-gray-700 dark:text-gray-300">
                            <strong>Story:</strong> {{ $story['title'] }}<br>
                            <strong>Author:</strong> {{ $story['author_name'] }}<br>
                            <strong>Your Points:</strong> <span class="text-emerald-600 font-bold">{{ $userPoints }}</span>
                        </p>
                    </div>

                    <!-- Support with Points -->
                    <form id="supportForm" class="mb-8">
                        <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-4">Support with Points</h2>

                        <div class="mb-4">
                            <label class="block text-gray-700 dark:text-gray-300 font-semibold mb-2">
                                How many points would you like to give?
                            </label>
                            <div class="flex gap-2 mb-4">
                                <button type="button" class="points-btn px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded hover:bg-gray-300" data-points="10">10 pts</button>
                                <button type="button" class="points-btn px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded hover:bg-gray-300" data-points="25">25 pts</button>
                                <button type="button" class="points-btn px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded hover:bg-gray-300" data-points="50">50 pts</button>
                                <button type="button" class="points-btn px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded hover:bg-gray-300" data-points="100">100 pts</button>
                            </div>

                            <input type="number" id="pointsAmount" name="points" min="1" max="{{ $userPoints }}" placeholder="Or enter custom amount" 
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700 dark:text-white">
                        </div>

                        <input type="hidden" name="story_id" value="{{ $storyId }}">

                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-4 rounded-lg transition">
                            💝 Support with Points
                        </button>
                    </form>

                    <!-- Or Patreon -->
                    <div class="border-t pt-8">
                        <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-4">Other Ways to Support</h2>
                        <a href="#" class="block mb-3 bg-red-500 hover:bg-red-600 text-white font-bold py-3 px-4 rounded-lg text-center transition">
                            🎉 Join on Patreon
                        </a>
                        <a href="/pages/points-dashboard.php" class="block bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-4 rounded-lg text-center transition">
                            ⭐ Earn More Points
                        </a>
                    </div>

                    <!-- Message -->
                    <div id="message" class="mt-4"></div>
                </div>
            </div>

            <!-- Sidebar: Top Supporters -->
            <div class="md:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                    <h2 class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mb-4">🏆 Top Supporters</h2>

                    <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded text-sm">
                        <p><strong>Supporters:</strong> {{ $supportStats['supporter_count'] ?? 0 }}</p>
                        <p><strong>Total Points:</strong> {{ $supportStats['total_points'] ?? 0 }}</p>
                    </div>

                    <?php if (empty($topSupporters)): ?>
                        <p class="text-gray-500 dark:text-gray-400 text-center py-8">No supporters yet. Be the first!</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($topSupporters as $index => $supporter): ?>
                                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg font-bold text-emerald-600">{{ $index + 1 }}</span>
                                        <?php if ($supporter['profile_image']): ?>
                                            <img src="{{ $supporter['profile_image'] }}" class="w-8 h-8 rounded-full">
                                        <?php else: ?>
                                            <div class="w-8 h-8 rounded-full bg-gray-300 dark:bg-gray-500 flex items-center justify-center">👤</div>
                                        <?php endif; ?>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-semibold text-gray-800 dark:text-white truncate">{{ $supporter['username'] }}</p>
                                            <p class="text-sm text-emerald-600">{{ $supporter['total_points'] }} pts</p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        // Quick select buttons
        document.querySelectorAll('.points-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('pointsAmount').value = this.dataset.points;
            });
        });

        // Support form
        document.getElementById('supportForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const points = parseInt(document.getElementById('pointsAmount').value);
            const msgDiv = document.getElementById('message');

            if (!points || points <= 0) {
                msgDiv.innerHTML = '<p class="text-red-600">Please enter a valid amount</p>';
                return;
            }

            try {
                const response = await fetch('/api/support-with-points.php?action=support_points', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        story_id: {{ $storyId }},
                        points: points
                    })
                });

                const data = await response.json();

                if (data.success) {
                    msgDiv.innerHTML = '<p class="text-emerald-600 font-semibold">✅ ' + data.message + '</p>';
                    document.getElementById('pointsAmount').value = '';
                    setTimeout(() => location.reload(), 2000);
                } else {
                    msgDiv.innerHTML = '<p class="text-red-600">❌ ' + data.error + '</p>';
                }
            } catch (error) {
                msgDiv.innerHTML = '<p class="text-red-600">Error: ' + error.message + '</p>';
            }
        });
        </script>

    <?php else: ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-12 text-center">
            <?php if (!$isLoggedIn): ?>
                <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-4">Support Your Favorite Authors</h1>
                <p class="text-gray-600 dark:text-gray-400 mb-6">Please log in to support this story.</p>
                <a href="/pages/login.php" class="inline-block bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-8 rounded-lg">
                    Login to Support
                </a>
            <?php else: ?>
                <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-4">Support Authors</h1>
                <p class="text-gray-600 dark:text-gray-400">Click the support button on any story to get started!</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
