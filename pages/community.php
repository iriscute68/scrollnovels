<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$isLoggedIn = isset($_SESSION['user_id']);
$category = $_GET['category'] ?? 'all';

// Enhanced community categories with icons and descriptions
$categories_info = array(
    'all' => array('emoji' => '🌍', 'name' => 'All Topics', 'desc' => 'Explore all community discussions'),
    'writing-advice' => array('emoji' => '✍️', 'name' => 'Writing Advice', 'desc' => 'Tips, techniques, and writing craft discussions'),
    'feedback' => array('emoji' => '💬', 'name' => 'Story Feedback', 'desc' => 'Get constructive criticism for your work'),
    'genres' => array('emoji' => '📚', 'name' => 'Genre Discussions', 'desc' => 'Fantasy, Romance, Sci-Fi, Mystery and more'),
    'events' => array('emoji' => '🎉', 'name' => 'Community Events', 'desc' => 'Writing challenges and community gatherings'),
    'technical' => array('emoji' => '🔧', 'name' => 'Technical Help', 'desc' => 'Platform features and technical issues'),
    'announcements' => array('emoji' => '📢', 'name' => 'Announcements', 'desc' => 'Important platform updates and news'),
    'collaboration' => array('emoji' => '🤝', 'name' => 'Collaboration', 'desc' => 'Find writing partners and collaborate'),
    'showcase' => array('emoji' => '⭐', 'name' => 'Showcase', 'desc' => 'Share your best work and achievements'),
    'off-topic' => array('emoji' => '💭', 'name' => 'Off-Topic', 'desc' => 'General chat and off-topic discussions')
);

try {
    // Create community_posts if missing (useful if migrations haven't been applied)
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        author_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        category VARCHAR(100),
        content LONGTEXT NOT NULL,
        tags VARCHAR(255),
        views INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Create community_replies if missing
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        author_id INT NOT NULL,
        content LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Create community_helpful if missing
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_helpful (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        is_helpful BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");;

    $query = "SELECT cp.id, cp.title, cp.category, cp.created_at, cp.views, u.username,
           (SELECT COUNT(*) FROM community_replies WHERE post_id = cp.id) as reply_count,
           (SELECT COUNT(*) FROM community_helpful WHERE post_id = cp.id AND is_helpful = 1) as helpful_count
    FROM community_posts cp
    JOIN users u ON cp.author_id = u.id WHERE 1=1";
    
    $params = [];
    if ($category !== 'all') {
        $query .= " AND cp.category = ?";
        $params[] = $category;
    }
    
    $query .= " ORDER BY cp.created_at DESC LIMIT 50";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $posts = [];
}

// Get category counts
$category_counts = array();
try {
    foreach (array_keys($categories_info) as $cat) {
        if ($cat === 'all') continue;
        $count_stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM community_posts WHERE category = ?");
        $count_stmt->execute([$cat]);
        $result = $count_stmt->fetch(PDO::FETCH_ASSOC);
        $category_counts[$cat] = $result['cnt'] ?? 0;
    }
} catch (Exception $e) {
    foreach (array_keys($categories_info) as $cat) {
        if ($cat === 'all') continue;
        $category_counts[$cat] = 0;
    }
}

// Get total count
$total_posts = 0;
try {
    $total_stmt = $pdo->query("SELECT COUNT(*) FROM community_posts");
    $total_posts = $total_stmt->fetchColumn();
} catch (Exception $e) {
    $total_posts = 0;
}

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="min-h-screen bg-gradient-to-b from-slate-50 to-white dark:from-slate-900 dark:to-slate-800">
    <div class="max-w-7xl mx-auto px-4 py-12">
        
        <!-- Header -->
        <div class="mb-12">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <span class="text-5xl">💬</span>
                    <div>
                        <h1 class="text-4xl font-bold text-slate-900 dark:text-white">Community Forum</h1>
                        <p class="text-slate-600 dark:text-slate-300 mt-2">Join discussions, share knowledge, and connect with fellow writers</p>
                    </div>
                </div>
                <?php if ($isLoggedIn): ?>
                    <a href="<?= SITE_URL ?>/pages/community-create.php" 
                       class="inline-flex items-center gap-2 px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-semibold shadow-md transition">
                        ✍️ New Topic
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Category Cards Grid -->
        <div class="mb-12">
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white mb-6">📂 Categories</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <?php foreach ($categories_info as $key => $cat_info): ?>
                    <?php if ($key === 'all'): ?>
                        <a href="?category=all" class="group bg-gradient-to-br from-emerald-500 to-teal-600 text-white rounded-lg p-6 shadow-md hover:shadow-lg transition transform hover:scale-105 <?= $category === 'all' ? 'ring-2 ring-emerald-300' : '' ?>">
                            <div class="text-3xl mb-2"><?= $cat_info['emoji'] ?></div>
                            <h3 class="font-bold text-lg mb-1"><?= $cat_info['name'] ?></h3>
                            <p class="text-sm text-emerald-100"><?= $total_posts ?> posts</p>
                        </a>
                    <?php else: ?>
                        <a href="?category=<?= $key ?>" class="group bg-white dark:bg-slate-800 rounded-lg p-6 shadow-md border-2 border-slate-200 dark:border-slate-700 hover:border-emerald-500 dark:hover:border-emerald-500 hover:shadow-lg transition transform hover:scale-105 <?= $category === $key ? 'border-emerald-500 ring-2 ring-emerald-300' : '' ?>">
                            <div class="text-3xl mb-2"><?= $cat_info['emoji'] ?></div>
                            <h3 class="font-bold text-slate-900 dark:text-white"><?= $cat_info['name'] ?></h3>
                            <p class="text-xs text-slate-600 dark:text-slate-400 mt-1 line-clamp-2"><?= $cat_info['desc'] ?></p>
                            <div class="mt-3 text-sm font-semibold text-emerald-600 dark:text-emerald-400"><?= $category_counts[$key] ?? 0 ?> discussions</div>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Posts List -->
        <div class="mt-12">
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white mb-6">
                <?php if ($category !== 'all' && isset($categories_info[$category])): ?>
                    <?= $categories_info[$category]['emoji'] ?> <?= $categories_info[$category]['name'] ?>
                <?php else: ?>
                    Recent Discussions
                <?php endif; ?>
            </h2>

            <?php if (empty($posts)): ?>
                <div class="bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-200 dark:border-blue-800 rounded-lg p-12 text-center">
                    <div class="text-5xl mb-4">📭</div>
                    <p class="text-slate-700 dark:text-slate-300 font-semibold text-lg">No discussions yet</p>
                    <p class="text-slate-600 dark:text-slate-400 text-sm mt-2">Be the first to start a conversation!</p>
                    <?php if ($isLoggedIn): ?>
                        <a href="<?= SITE_URL ?>/pages/community-create.php" class="mt-4 inline-block px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-semibold">
                            Start Discussion
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($posts as $post): ?>
                        <a href="<?= SITE_URL ?>/pages/community-thread.php?id=<?= $post['id'] ?>"
                           class="block bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-6 
                           hover:border-emerald-400 dark:hover:border-emerald-500 hover:shadow-lg transition">
                            
                            <div class="flex gap-4">
                                <!-- Author Avatar -->
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center text-white font-bold text-sm">
                                        <?= strtoupper(substr($post['username'], 0, 1)) ?>
                                    </div>
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex-1">
                                            <h3 class="text-lg font-bold text-slate-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400 transition line-clamp-2">
                                                <?= htmlspecialchars($post['title']) ?>
                                            </h3>
                                            <div class="flex items-center gap-3 mt-2 text-sm text-slate-600 dark:text-slate-400">
                                                <span class="font-medium"><?= htmlspecialchars($post['username']) ?></span>
                                                <span>•</span>
                                                <span><?= date('M d, Y', strtotime($post['created_at'])) ?></span>
                                                <span class="inline-block px-2 py-1 bg-slate-100 dark:bg-slate-700 rounded text-xs font-semibold 
                                                text-slate-700 dark:text-slate-300">
                                                    <?php if (isset($categories_info[$post['category']])): ?>
                                                        <?= $categories_info[$post['category']]['emoji'] ?> <?= $categories_info[$post['category']]['name'] ?>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars($post['category']) ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Stats -->
                                        <div class="flex-shrink-0 text-right">
                                            <div class="grid grid-cols-3 gap-4 text-center">
                                                <div>
                                                    <div class="text-lg font-bold text-slate-900 dark:text-white"><?= $post['views'] ?? 0 ?></div>
                                                    <div class="text-xs text-slate-500 dark:text-slate-400">👁️ Views</div>
                                                </div>
                                                <div>
                                                    <div class="text-lg font-bold text-slate-900 dark:text-white"><?= $post['reply_count'] ?></div>
                                                    <div class="text-xs text-slate-500 dark:text-slate-400">💬 Replies</div>
                                                </div>
                                                <div>
                                                    <div class="text-lg font-bold text-slate-900 dark:text-white"><?= $post['helpful_count'] ?></div>
                                                    <div class="text-xs text-slate-500 dark:text-slate-400">👍 Helpful</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php';

