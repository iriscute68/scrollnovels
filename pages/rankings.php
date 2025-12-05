<?php
/**
 * pages/rankings.php - Story and writer rankings
 * Uses weighted scoring algorithm for accurate rankings
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/RankingService.php';

$page_title = 'Rankings - Scroll Novels';
require_once dirname(__DIR__) . '/includes/header.php';

// Get selected period (default: daily)
$period = $_GET['period'] ?? 'daily';
if (!in_array($period, ['daily', 'weekly', 'monthly'])) {
    $period = 'daily';
}

// Initialize ranking service
$rankingService = new RankingService($pdo);

// Fetch rankings
$rankings = $rankingService->getStoryRankings($period, 50);
// Fallback: if ranking service has no data (e.g., story_stats empty), show popular stories by views
if (empty($rankings)) {
    try {
        // Get all story stats including favorites/likes and comments counts
        $stmt = $pdo->query("
            SELECT s.id as story_id, 
                   s.title as story_title, 
                   u.username as author_name, 
                   COALESCE(s.views, 0) as views,
                   COALESCE(s.likes, 0) as favorites,
                   (SELECT COUNT(*) FROM story_likes WHERE story_id = s.id) as support_count,
                   (SELECT COUNT(*) FROM reading_list WHERE story_id = s.id) as readers_count,
                   (SELECT COUNT(*) FROM comments WHERE story_id = s.id) as comment_count
            FROM stories s 
            LEFT JOIN users u ON s.author_id = u.id 
            WHERE s.status = 'published' 
            ORDER BY s.views DESC, s.created_at DESC 
            LIMIT 50
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rankings = array_map(function($r){
            $views = (int)($r['views'] ?? 0);
            $readers = (int)($r['readers_count'] ?? 0);
            $support = (int)($r['support_count'] ?? $r['favorites'] ?? 0);
            $comments = (int)($r['comment_count'] ?? 0);
            // Calculate score based on weights
            $score = ($views * 0.3) + ($readers * 0.2) + ($support * 0.2) + ($comments * 0.15);
            return [
                'story_id' => (int)$r['story_id'],
                'story_title' => $r['story_title'],
                'author' => ['name' => $r['author_name']],
                'metrics' => [
                    'views' => $views,
                    'unique_views' => $readers,
                    'favorites' => $support,
                    'comments' => $comments,
                    'reading_seconds' => 0,
                    'boosts' => 0,
                ],
                'score' => round($score, 2),
            ];
        }, $rows);
    } catch (Exception $e) {
        // Simpler fallback if tables don't exist
        try {
            $stmt = $pdo->query("SELECT s.id as story_id, s.title as story_title, u.username as author_name, 
                                        COALESCE(s.views, 0) as views, COALESCE(s.likes, 0) as favorites 
                                 FROM stories s LEFT JOIN users u ON s.author_id = u.id 
                                 WHERE s.status IN ('published','active') ORDER BY s.views DESC, s.created_at DESC LIMIT 50");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $rankings = array_map(function($r){
                $views = (int)($r['views'] ?? 0);
                return [
                    'story_id' => (int)$r['story_id'],
                    'story_title' => $r['story_title'],
                    'author' => ['name' => $r['author_name']],
                    'metrics' => [
                        'views' => $views,
                        'unique_views' => 0,
                        'favorites' => (int)($r['favorites'] ?? 0),
                        'comments' => 0,
                    ],
                    'score' => max(0.1, $views * 0.3),
                ];
            }, $rows);
        } catch (Exception $e2) {
            $rankings = [];
        }
    }
}
$topWriters = $rankingService->getTopWriters('monthly', 12);

// Helper function for story link
function story_link_by_row($row) {
    if (isset($row['slug']) && $row['slug']) {
        return site_url('/pages/book.php?id=' . $row['story_id']);
    }
    return site_url('/pages/book.php?id=' . ($row['story_id'] ?? $row['id'] ?? ''));
}
?>

<main class="flex-1">
    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-emerald-700 dark:text-emerald-400 mb-2">🏆 Rankings</h1>
            <p class="text-gray-600 dark:text-gray-400">Top stories by period and engagement</p>
        </div>

        <!-- Period Tabs -->
        <div class="flex gap-4 mb-8 border-b border-emerald-200 dark:border-emerald-900 flex-wrap sm:flex-nowrap">
            <a href="?period=daily" class="px-6 py-3 font-semibold transition border-b-2 text-sm sm:text-base <?= $period === 'daily' ? 'text-emerald-600 border-emerald-600' : 'text-gray-600 dark:text-gray-400 border-transparent hover:text-emerald-600' ?>">
                📅 Daily
            </a>
            <a href="?period=weekly" class="px-6 py-3 font-semibold transition border-b-2 text-sm sm:text-base <?= $period === 'weekly' ? 'text-emerald-600 border-emerald-600' : 'text-gray-600 dark:text-gray-400 border-transparent hover:text-emerald-600' ?>">
                📆 Weekly
            </a>
            <a href="?period=monthly" class="px-6 py-3 font-semibold transition border-b-2 text-sm sm:text-base <?= $period === 'monthly' ? 'text-emerald-600 border-emerald-600' : 'text-gray-600 dark:text-gray-400 border-transparent hover:text-emerald-600' ?>">
                📋 Monthly
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Rankings -->
            <div class="lg:col-span-2">
                <?php if (!empty($rankings)): ?>
                    <div class="space-y-4">
                        <?php foreach ($rankings as $idx => $story): ?>
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 sm:p-6 shadow hover:shadow-lg transition flex gap-4 items-start">
                                <!-- Rank Badge -->
                                <div class="flex-shrink-0 w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-emerald-400 to-emerald-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
                                    <?php 
                                    if ($idx < 3) {
                                        echo ['🥇', '🥈', '🥉'][$idx];
                                    } else {
                                        echo '#' . ($idx + 1);
                                    }
                                    ?>
                                </div>

                                <!-- Story Info -->
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-bold text-gray-900 dark:text-white mb-1 truncate text-sm sm:text-base">
                                        <a href="<?= story_link_by_row($story) ?>" class="hover:text-emerald-600 dark:hover:text-emerald-400">
                                            <?= htmlspecialchars($story['story_title'] ?? 'Untitled') ?>
                                        </a>
                                    </h3>
                                    <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 mb-2">
                                        by <strong><?= htmlspecialchars($story['author']['name'] ?? $story['author_name'] ?? 'Unknown') ?></strong>
                                    </p>

                                    <!-- Metrics -->
                                    <div class="flex flex-wrap gap-2 sm:gap-4 text-xs sm:text-sm text-gray-600 dark:text-gray-400">
                                        <span title="Views">👁️ <?= number_format($story['metrics']['views'] ?? 0) ?></span>
                                        <span title="Users Reading">👤 <?= number_format($story['metrics']['unique_views'] ?? 0) ?></span>
                                        <span title="Support Points">❤️ <?= number_format($story['metrics']['favorites'] ?? 0) ?></span>
                                        <span title="Comments">💬 <?= number_format($story['metrics']['comments'] ?? 0) ?></span>
                                    </div>

                                    <!-- Score Bar -->
                                    <div class="mt-2 bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                        <div class="bg-gradient-to-r from-emerald-400 to-emerald-600 h-2 transition" style="width: <?= min($story['score'] * 100, 100) ?>%"></div>
                                    </div>
                                </div>

                                <!-- Score Display -->
                                <div class="flex-shrink-0 text-right">
                                    <div class="text-lg sm:text-xl font-bold text-emerald-600 dark:text-emerald-400">
                                        <?= number_format($story['score'], 2) ?>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Score</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-8 text-center shadow">
                        <p class="text-gray-600 dark:text-gray-400 mb-2">📊 No rankings yet for <?= ucfirst($period) ?></p>
                        <p class="text-sm text-gray-500 dark:text-gray-500">Come back soon as stories get rated and read!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <!-- Top Writers -->
                <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">👑 Top Writers (Monthly)</h2>
                    
                    <?php if (!empty($topWriters)): ?>
                        <div class="space-y-3">
                            <?php foreach ($topWriters as $idx => $writer): ?>
                                <div class="flex items-center gap-3 pb-3 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
                                    <div class="flex-shrink-0 w-8 h-8 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                        <?= $idx + 1 ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-gray-900 dark:text-white truncate text-sm">
                                            <?= htmlspecialchars($writer['username'] ?? 'Unknown') ?>
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            👁️ <?= number_format($writer['total_views']) ?> views
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center">No writers yet</p>
                    <?php endif; ?>
                </div>

                <!-- Algorithm Info -->
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mt-6 border border-blue-200 dark:border-blue-800 text-sm">
                    <p class="font-semibold text-blue-900 dark:text-blue-300 mb-2">ℹ️ How Rankings Work</p>
                    <ul class="text-xs text-blue-800 dark:text-blue-200 space-y-1">
                        <li>📊 Views (30%)</li>
                        <li>👤 Users Reading (20%)</li>
                        <li>❤️ Support Points (20%)</li>
                        <li>💬 Comments (15%)</li>
                        <li>⏱️ Reading Time (10%)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>
