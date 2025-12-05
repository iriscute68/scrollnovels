<?php
// pages/blog_index.php - Public blog listing page
session_start();
require_once __DIR__ . '/../config/db.php';

// Get filters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build where clause
$where = ["status = 'published'"];
$params = [];

if ($category) {
    $where[] = "category = ?";
    $params[] = $category;
}

if ($search) {
    $where[] = "(title LIKE ? OR tags LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(" AND ", $where);

// Get featured posts
$featured_stmt = $pdo->prepare("
    SELECT id, title, slug, excerpt, cover_image, category, views, created_at, user_id
    FROM posts
    WHERE status = 'published'
    ORDER BY views DESC, created_at DESC
    LIMIT 3
");
$featured_stmt->execute();
$featured = $featured_stmt->fetchAll();

// Get all posts with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("
    SELECT id, title, slug, excerpt, cover_image, category, views, created_at, user_id
    FROM posts
    WHERE $where_clause
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$limit, $offset]));
$posts = $stmt->fetchAll();

// Get total count
$count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM posts WHERE $where_clause");
$count_stmt->execute($params);
$total = $count_stmt->fetch()['total'];

// Get categories
$categories_stmt = $pdo->query("SELECT DISTINCT category FROM posts WHERE status = 'published' ORDER BY category");
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get trending posts
$trending_stmt = $pdo->query("
    SELECT id, title, slug, views FROM posts
    WHERE status = 'published'
    ORDER BY views DESC, created_at DESC
    LIMIT 5
");
$trending = $trending_stmt->fetchAll();

// Merge blog posts and announcements for blog index
$blog_announcements = [];
try {
    $stmt = $pdo->query("SELECT id, title, slug, excerpt, cover_image, category, views, created_at, 'blog' as type FROM posts WHERE status = 'published' ORDER BY created_at DESC LIMIT 20");
    $blog_announcements = $stmt->fetchAll();
    $stmt2 = $pdo->query("SELECT id, title, slug, content as excerpt, NULL as cover_image, 'Announcement' as category, views, created_at, 'announcement' as type FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 10");
    $blog_announcements = array_merge($blog_announcements, $stmt2->fetchAll());
    usort($blog_announcements, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
} catch (Exception $e) {}
?>
<?php
    $page_title = 'Blog - Scroll Novels';
    $page_head = '<style> body { background: linear-gradient(135deg, #0f0820 0%, #1a0f3a 100%); } .card { background: #1a0f3a; border: 1px solid rgba(212,175,55,0.1); } .card:hover { border-color: rgba(212,175,55,0.3); } </style>';
    require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-12 mt-16">
    <!-- Hero Section -->
    <div class="mb-12">
        <h1 class="text-5xl font-bold text-emerald-700 dark:text-emerald-400 mb-4">📝 Scroll Novels Blog</h1>
        <p class="text-xl text-gray-600 dark:text-gray-400">Updates, events, announcements, and community highlights</p>
    </div>

    <!-- Featured Posts Carousel -->
    <?php if (!empty($featured)): ?>
        <div class="mb-12">
            <div class="relative bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-lg">
                <!-- Featured carousel -->
                <div class="flex overflow-x-auto snap-x snap-mandatory h-96">
                    <?php $featured_count = 0; foreach ($featured as $post): $featured_count++; ?>
                        <div class="min-w-full snap-center relative flex">
                            <?php if ($post['cover_image']): ?>
                                <img src="<?= htmlspecialchars($post['cover_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-full object-cover" />
                            <?php else: ?>
                                <div class="w-full h-full bg-gradient-to-br from-emerald-400 to-blue-500 flex items-center justify-center">
                                    <span class="text-6xl">📚</span>
                                </div>
                            <?php endif; ?>
                            <div class="absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent flex flex-col justify-end p-8">
                                <span class="inline-block px-3 py-1 bg-emerald-600 text-white text-xs font-semibold rounded mb-3 w-fit">
                                    <?= htmlspecialchars($post['category']) ?>
                                </span>
                                <h2 class="text-3xl font-bold text-white mb-2 line-clamp-2"><?= htmlspecialchars($post['title']) ?></h2>
                                <p class="text-gray-200 text-sm mb-3">By <?= htmlspecialchars($post['author_name'] ?? 'Staff') ?> • <?= date('M d, Y', strtotime($post['created_at'])) ?></p>
                                <a href="blog_post.php?slug=<?= urlencode($post['slug']) ?>" class="inline-block text-white hover:text-emerald-300 text-sm font-medium">Read Full Post →</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Carousel controls -->
                <?php if ($featured_count > 1): ?>
                    <div class="absolute bottom-4 right-4 flex gap-2">
                        <button onclick="carousel.prev()" class="p-2 bg-white dark:bg-gray-700 rounded-full hover:bg-emerald-500 transition">←</button>
                        <button onclick="carousel.next()" class="p-2 bg-white dark:bg-gray-700 rounded-full hover:bg-emerald-500 transition">→</button>
                    </div>
                    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
                        <?php for ($i = 0; $i < $featured_count; $i++): ?>
                            <button class="w-2 h-2 rounded-full bg-white opacity-50 hover:opacity-100 transition" onclick="carousel.goTo(<?= $i ?>)"></button>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Featured stats -->
            <div class="grid grid-cols-3 gap-4 mt-6">
                <div class="bg-emerald-50 dark:bg-emerald-900/20 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-400"><?= $featured[0]['views'] ?? 0 ?></div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Views</div>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-blue-700 dark:text-blue-400"><?= count($posts) ?></div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Articles</div>
                </div>
                <div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-purple-700 dark:text-purple-400"><?= count($categories) ?></div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Categories</div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar (Left) -->
        <div class="space-y-6">
            <!-- Categories -->
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow border border-emerald-200 dark:border-emerald-900">
                <h3 class="font-bold text-lg text-emerald-700 dark:text-emerald-400 mb-4">📂 Categories</h3>
                <div class="space-y-2">
                    <a href="blog_index.php" class="block px-3 py-2 rounded hover:bg-emerald-50 dark:hover:bg-emerald-900/30 <?= !$category ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300' : 'text-gray-700 dark:text-gray-300' ?>">
                        All Posts
                    </a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="?category=<?= urlencode($cat) ?>" class="block px-3 py-2 rounded hover:bg-emerald-50 dark:hover:bg-emerald-900/30 <?= $category === $cat ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300' : 'text-gray-700 dark:text-gray-300' ?>">
                            <?= htmlspecialchars($cat) ?> (<?php 
                                $c_stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE category = ? AND status = 'published'");
                                $c_stmt->execute([$cat]);
                                echo $c_stmt->fetchColumn();
                            ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Trending Posts -->
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow border border-yellow-200 dark:border-yellow-900">
                <h3 class="font-bold text-lg text-yellow-700 dark:text-yellow-400 mb-4">🔥 Trending Posts</h3>
                <ol class="space-y-3 list-decimal list-inside">
                    <?php $rank = 1; foreach ($trending as $post): ?>
                        <li class="text-sm">
                            <a href="blog_post.php?slug=<?= urlencode($post['slug']) ?>" class="hover:text-emerald-600 dark:hover:text-emerald-400 line-clamp-2">
                                <?= htmlspecialchars($post['title']) ?>
                            </a>
                            <div class="text-xs text-gray-500 dark:text-gray-400">👁️ <?= $post['views'] ?> views</div>
                        </li>
                        <?php $rank++; endforeach; ?>
                </ol>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-3">
            <!-- Search -->
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow mb-8">
                <form method="get" class="flex gap-2">
                    <input type="text" name="search" placeholder="Search blog posts..." 
                           class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                           value="<?= htmlspecialchars($search) ?>" />
                    <button type="submit" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">🔍 Search</button>
                </form>
            </div>

            <!-- Blog Posts List -->
            <?php if (empty($posts)): ?>
                <div class="bg-blue-50 dark:bg-blue-900/20 p-12 rounded-lg text-center border border-blue-200 dark:border-blue-800">
                    <p class="text-gray-600 dark:text-gray-400 text-lg">No blog posts found</p>
                </div>
            <?php else: ?>
                <div class="space-y-6 mb-8">
                    <?php foreach ($posts as $post): ?>
                        <div class="bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow hover:shadow-lg transition border-l-4 border-emerald-500">
                            <div class="flex gap-6 p-6">
                                <?php if ($post['cover_image']): ?>
                                    <img src="<?= htmlspecialchars($post['cover_image']) ?>" alt="" class="w-40 h-32 rounded object-cover flex-shrink-0" />
                                <?php endif; ?>
                                <div class="flex-1">
                                    <div class="inline-block px-3 py-1 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 text-xs font-semibold rounded mb-2">
                                        ✨ <?= htmlspecialchars($post['category']) ?>
                                    </div>
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2 hover:text-emerald-600">
                                        <a href="blog_post.php?slug=<?= urlencode($post['slug']) ?>">
                                            <?= htmlspecialchars($post['title']) ?>
                                        </a>
                                    </h3>
                                    <?php $excerpt_text = $post['excerpt'] ?? (isset($post['content']) ? substr(strip_tags($post['content']), 0, 150) : ''); ?>
                                    <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 line-clamp-2"><?= htmlspecialchars($excerpt_text) ?></p>
                                    <?php 
                                        $comment_count = 0;
                                        try {
                                            $c_stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM post_comments WHERE post_id = ?");
                                            $c_stmt->execute([$post['id']]);
                                            $comment_count = $c_stmt->fetchColumn() ?? 0;
                                        } catch (Exception $e) {
                                            $comment_count = 0;
                                        }
                                    ?>
                                    <div class="flex justify-between items-center text-sm text-gray-500 dark:text-gray-400">
                                        <div>👁️ <?= $post['views'] ?> views • 💬 <?= $comment_count ?> comments</div>
                                        <a href="blog_post.php?slug=<?= urlencode($post['slug']) ?>" class="text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300 font-medium">Read More →</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total > $limit): ?>
                    <div class="flex justify-center gap-2 flex-wrap mb-8">
                        <?php 
                            $total_pages = ceil($total / $limit);
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                        ?>
                        <?php if ($page > 1): ?>
                            <a href="?page=1&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>" class="px-4 py-2 bg-white dark:bg-gray-800 rounded hover:bg-emerald-100 dark:hover:bg-emerald-900/30">«</a>
                        <?php endif; ?>
                        <?php for ($p = $start; $p <= $end; $p++): ?>
                            <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>"
                               class="px-4 py-2 rounded <?= $p === $page ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-gray-800 hover:bg-emerald-100 dark:hover:bg-emerald-900/30' ?>">
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>" class="px-4 py-2 bg-white dark:bg-gray-800 rounded hover:bg-emerald-100 dark:hover:bg-emerald-900/30">»</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Merged Blog Posts and Announcements Section -->
    <div class="max-w-7xl mx-auto px-4 py-12 mt-16">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($blog_announcements as $item): ?>
                <div class="card p-6 rounded-lg mb-6">
                    <a href="<?= $item['type'] === 'blog' ? site_url('/pages/blog_post.php?slug=' . urlencode($item['slug'])) : '#' ?>" class="font-bold text-emerald-700 dark:text-emerald-300 text-lg">
                        <?= htmlspecialchars($item['title']) ?>
                    </a>
                    <span class="text-xs text-gray-500 ml-2">(<?= htmlspecialchars($item['category']) ?>)</span>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1"><?= htmlspecialchars(substr(strip_tags($item['excerpt']),0,180)) ?>...</p>
                    <span class="text-xs text-gray-400"><?= date('M d, Y', strtotime($item['created_at'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

