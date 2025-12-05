<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$page_title = 'Rankings';
require_once dirname(__DIR__) . '/includes/header.php';

// Get selected period (default: daily)
$period = $_GET['period'] ?? 'daily';
if (!in_array($period, ['daily', 'weekly', 'monthly'])) {
    $period = 'daily';
}

// Ensure story_stats table exists and populate it
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS story_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        story_id INT NOT NULL,
        period ENUM('daily','weekly','monthly') NOT NULL,
        views INT DEFAULT 0,
        unique_views INT DEFAULT 0,
        comments INT DEFAULT 0,
        favorites INT DEFAULT 0,
        reading_minutes INT DEFAULT 0,
        boost_score INT DEFAULT 0,
        last_reset DATETIME,
        UNIQUE KEY unique_story_period (story_id, period)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Initialize story_stats for all published stories that don't have stats yet
    $pdo->exec("INSERT IGNORE INTO story_stats (story_id, period, views, unique_views, comments, favorites, reading_minutes, boost_score, last_reset)
        SELECT DISTINCT s.id, 'daily', COALESCE(s.views, 0), 0, 0, 0, 0, 0, NOW()
        FROM stories s
        WHERE s.status = 'published'
        AND NOT EXISTS (SELECT 1 FROM story_stats ss WHERE ss.story_id = s.id AND ss.period = 'daily')
    ");
    $pdo->exec("INSERT IGNORE INTO story_stats (story_id, period, views, unique_views, comments, favorites, reading_minutes, boost_score, last_reset)
        SELECT DISTINCT s.id, 'weekly', COALESCE(s.views, 0), 0, 0, 0, 0, 0, NOW()
        FROM stories s
        WHERE s.status = 'published'
        AND NOT EXISTS (SELECT 1 FROM story_stats ss WHERE ss.story_id = s.id AND ss.period = 'weekly')
    ");
    $pdo->exec("INSERT IGNORE INTO story_stats (story_id, period, views, unique_views, comments, favorites, reading_minutes, boost_score, last_reset)
        SELECT DISTINCT s.id, 'monthly', COALESCE(s.views, 0), 0, 0, 0, 0, 0, NOW()
        FROM stories s
        WHERE s.status = 'published'
        AND NOT EXISTS (SELECT 1 FROM story_stats ss WHERE ss.story_id = s.id AND ss.period = 'monthly')
    ");
} catch (Exception $e) {
    // ignore if table already exists
}

// Fetch top stories for the selected period with ranking formula
// Formula: (views * 0.4) + (likes * 0.4) + (comments * 0.2)
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.id as story_id,
            s.title,
            s.slug,
            s.cover,
            u.username as author_name,
            COALESCE(s.views, 0) as views,
            COALESCE(s.likes, 0) as likes,
            COALESCE(s.comments, 0) as comments,
            (
                (COALESCE(s.views, 0) * 0.4) +
                (COALESCE(s.likes, 0) * 0.4) +
                (COALESCE(s.comments, 0) * 0.2)
            ) AS ranking_score
        FROM stories s
        LEFT JOIN users u ON s.author_id = u.id
        WHERE s.status = 'published'
        ORDER BY ranking_score DESC, s.views DESC
        LIMIT 50
    ");
    $stmt->execute();
    $rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rankings = [];
    error_log('Rankings query error: ' . $e->getMessage());
}

// Top writers (by total views)
try {
    $topWriters = $pdo->query("
        SELECT u.id, u.username, COUNT(s.id) as story_count, COALESCE(SUM(s.views),0) as total_views 
        FROM users u 
        LEFT JOIN stories s ON u.id = s.author_id 
        GROUP BY u.id 
        ORDER BY total_views DESC 
        LIMIT 12
    ")->fetchAll();
} catch (Exception $e) {
    $topWriters = [];
}

// Helper for story link
function story_link_by_row($row) {
    $slug = $row['slug'] ?? '';
    if ($slug) return site_url('/pages/story.php?slug=' . urlencode($slug));
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
    <div class="flex gap-4 mb-8 border-b border-emerald-200 dark:border-emerald-900">
        <a href="?period=daily" class="px-6 py-3 font-semibold transition border-b-2 <?= $period === 'daily' ? 'text-emerald-600 border-emerald-600' : 'text-gray-600 dark:text-gray-400 border-transparent hover:text-emerald-600' ?>">
            📅 Daily
        </a>
        <a href="?period=weekly" class="px-6 py-3 font-semibold transition border-b-2 <?= $period === 'weekly' ? 'text-emerald-600 border-emerald-600' : 'text-gray-600 dark:text-gray-400 border-transparent hover:text-emerald-600' ?>">
            📆 Weekly
        </a>
        <a href="?period=monthly" class="px-6 py-3 font-semibold transition border-b-2 <?= $period === 'monthly' ? 'text-emerald-600 border-emerald-600' : 'text-gray-600 dark:text-gray-400 border-transparent hover:text-emerald-600' ?>">
            📋 Monthly
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Rankings -->
        <div class="lg:col-span-2">
            <?php if (!empty($rankings)): ?>
                <div class="space-y-4">
                    <?php foreach ($rankings as $idx => $story): ?>
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow hover:shadow-lg transition flex gap-4 items-start">
                            <!-- Rank Badge -->
                            <div class="flex-shrink-0 w-12 h-12 bg-gradient-to-br from-emerald-400 to-emerald-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
                                <?php 
                                if ($idx < 3) {
                                    echo ['🥇', '🥈', '🥉'][$idx];
                                } else {
                                    echo '#' . ($idx + 1);
                                }
                                ?>
                            </div>

                            <!-- Cover Image -->
                            <div class="w-16 h-24 bg-gray-100 dark:bg-gray-700 overflow-hidden rounded flex-shrink-0">
                                <?php if (!empty($story['cover'])): ?>
                                    <img src="<?= htmlspecialchars($story['cover']) ?>" alt="<?= htmlspecialchars($story['title']) ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-2xl">📚</div>
                                <?php endif; ?>
                            </div>

                            <!-- Story Info -->
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white line-clamp-2">
                                    <a href="<?= htmlspecialchars(story_link_by_row($story)) ?>" class="hover:text-emerald-600">
                                        <?= htmlspecialchars($story['title']) ?>
                                    </a>
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">by <?= htmlspecialchars($story['author_name'] ?? 'Unknown') ?></p>
                                
                                <!-- Stats Row -->
                                <div class="flex flex-wrap gap-4 mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    <span>👁️ <?= format_number($story['views']) ?> views</span>
                                    <span>❤️ <?= format_number($story['favorites']) ?> favorites</span>
                                    <span>💬 <?= format_number($story['comments']) ?> comments</span>
                                    <span>⏱️ <?= format_number($story['reading_minutes']) ?> min read</span>
                                </div>

                                <!-- Score Bar -->
                                <div class="mt-2 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div class="bg-gradient-to-r from-emerald-400 to-emerald-600 h-2 rounded-full" style="width: <?= min(100, ($story['ranking_score'] / max(1, max($rankings[0]['ranking_score'] ?? 1))) * 100) ?>%"></div>
                                </div>
                            </div>

                            <!-- Score Badge -->
                            <div class="flex-shrink-0 text-right">
                                <div class="text-2xl font-bold text-emerald-600">
                                    <?= number_format($story['ranking_score'], 1) ?>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">score</div>
                                <a href="<?= htmlspecialchars(story_link_by_row($story)) ?>" class="mt-2 inline-block px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-medium transition text-sm">
                                    📖 Read
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-6 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg text-center text-gray-700 dark:text-gray-300">
                    <p class="text-lg font-medium">No rankings yet for <?= ucfirst($period) ?></p>
                    <p class="text-sm mt-1">Come back soon as stories get rated and read!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <aside class="space-y-6">
            <!-- Ranking Formula Info -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <h3 class="font-bold text-blue-700 dark:text-blue-400 mb-3">📊 How We Rank</h3>
                <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                    <li>👁️ Views: 30%</li>
                    <li>👁️ Unique Views: 20%</li>
                    <li>❤️ Favorites: 20%</li>
                    <li>💬 Comments: 15%</li>
                    <li>⏱️ Reading Time: 10%</li>
                    <li>⬆️ Story Boost: 5%</li>
                </ul>
            </div>

            <!-- Top Writers -->
            <section class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow border border-emerald-200 dark:border-emerald-900">
                <h3 class="font-bold text-lg text-emerald-700 dark:text-emerald-400 mb-3">👑 Top Writers</h3>
                <ol class="list-decimal list-inside text-sm space-y-2">
                    <?php foreach (array_slice($topWriters, 0, 10) as $w): ?>
                        <li class="text-gray-700 dark:text-gray-300">
                            <a href="<?= site_url('/pages/profile.php?user=' . urlencode($w['username'])) ?>" class="hover:text-emerald-600 font-medium">
                                <?= htmlspecialchars($w['username']) ?>
                            </a>
                            <span class="text-xs text-gray-500 dark:text-gray-400"> — <?= format_number($w['total_views']) ?> views</span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>

            <!-- Quick Links -->
            <section class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-4 border border-emerald-200 dark:border-emerald-800">
                <h3 class="font-bold text-emerald-700 dark:text-emerald-400 mb-3">🔗 Quick Links</h3>
                <div class="space-y-2">
                    <a href="<?= site_url('/pages/browse.php') ?>" class="block px-3 py-2 bg-white dark:bg-gray-800 text-emerald-700 dark:text-emerald-400 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition font-medium text-sm">
                        📚 Browse Stories
                    </a>
                    <a href="<?= site_url('/pages/write-story.php') ?>" class="block px-3 py-2 bg-white dark:bg-gray-800 text-emerald-700 dark:text-emerald-400 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition font-medium text-sm">
                        ✍️ Write a Story
                    </a>
                </div>
            </section>
        </aside>
    </div>

</div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


