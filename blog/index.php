<?php
// blog/index.php - Blog listing page with featured/trending/categories
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Fetch featured posts (highest views)
$featured = $pdo->query("
    SELECT p.*, u.username 
    FROM posts p 
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.status = 'published'
    ORDER BY p.views DESC 
    LIMIT 3
")->fetchAll();

// Fetch recent posts
$recent = $pdo->query("
    SELECT p.*, u.username 
    FROM posts p 
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.status = 'published'
    ORDER BY p.published_at DESC 
    LIMIT 12
")->fetchAll();

// Fetch trending posts
$trending = $pdo->query("
    SELECT id, title, slug, views 
    FROM posts 
    WHERE status = 'published'
    ORDER BY views DESC 
    LIMIT 5
")->fetchAll();

// Fetch categories
$categories = $pdo->query("
    SELECT category, COUNT(*) as cnt 
    FROM posts 
    WHERE status = 'published'
    GROUP BY category 
    ORDER BY cnt DESC
")->fetchAll();

$userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = ($_SESSION['role'] ?? null) === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Blog - Scroll Novels</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= asset_url('css/theme.css') ?>">
</head>
<body class="bg-gray-900 text-white">

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="max-w-7xl mx-auto p-6 mt-16 grid lg:grid-cols-3 gap-8">
    <!-- Main Content -->
    <div class="lg:col-span-2">
        <!-- Featured Carousel -->
        <?php if (!empty($featured)): ?>
            <div class="mb-12">
                <h2 class="text-2xl font-bold mb-4">⭐ Featured</h2>
                <div class="grid gap-4">
                    <?php foreach (array_slice($featured, 0, 1) as $post): ?>
                        <div class="bg-gray-800 rounded-lg overflow-hidden hover:shadow-lg transition group cursor-pointer">
                            <?php if ($post['cover_image']): ?>
                                <img src="<?= htmlspecialchars($post['cover_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" 
                                     class="w-full h-64 object-cover group-hover:opacity-75 transition">
                            <?php else: ?>
                                <div class="w-full h-64 bg-gradient-to-br from-purple-600 to-blue-600 flex items-center justify-center text-4xl">📝</div>
                            <?php endif; ?>
                            <div class="p-4">
                                <h3 class="text-xl font-bold hover:text-gold">
                                    <a href="<?= site_url('/blog/post.php?slug=' . urlencode($post['slug'])) ?>">
                                        <?= htmlspecialchars(substr($post['title'], 0, 60)) ?>
                                    </a>
                                </h3>
                                <p class="text-sm text-gray-400">By <?= htmlspecialchars($post['username'] ?? 'Admin') ?> • <?= date('M d, Y', strtotime($post['created_at'])) ?></p>
                                <p class="text-gray-300 mt-2 line-clamp-2"><?= htmlspecialchars(substr(strip_tags($post['excerpt'] ?: $post['content']), 0, 150)) ?>...</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Posts Grid -->
        <div>
            <h2 class="text-2xl font-bold mb-4">📰 Latest Posts</h2>
            <div class="grid grid-cols-2 gap-4">
                <?php foreach ($recent as $post): ?>
                    <div class="bg-gray-800 rounded-lg p-4 hover:shadow-lg transition">
                        <h3 class="font-bold text-lg mb-2 line-clamp-2">
                            <a href="<?= site_url('/blog/post.php?slug=' . urlencode($post['slug'])) ?>" class="hover:text-gold">
                                <?= htmlspecialchars($post['title']) ?>
                            </a>
                        </h3>
                        <p class="text-xs text-gray-400 mb-2">
                            <?= date('M d', strtotime($post['created_at'])) ?> • By <?= htmlspecialchars($post['username'] ?? 'Admin') ?>
                        </p>
                        <p class="text-sm text-gray-300 line-clamp-2"><?= htmlspecialchars(substr(strip_tags($post['excerpt'] ?: $post['content']), 0, 100)) ?>...</p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <aside class="lg:col-span-1">
        <!-- New Post Button (Admin) -->
        <?php if ($isAdmin): ?>
            <div class="mb-8">
                <a href="<?= site_url('/blog/create.php') ?>" class="w-full block px-4 py-3 bg-gold text-midnight font-bold rounded-lg text-center hover:bg-yellow-400 transition">
                    ✍️ Write New Post
                </a>
            </div>
        <?php endif; ?>

        <!-- Trending -->
        <div class="bg-gray-800 rounded-lg p-4 mb-6">
            <h3 class="text-lg font-bold mb-3">🔥 Trending</h3>
            <ul class="space-y-2">
                <?php foreach ($trending as $t): ?>
                    <li>
                        <a href="<?= site_url('/blog/post.php?slug=' . urlencode($t['slug'])) ?>" class="text-sm hover:text-gold">
                            <?= htmlspecialchars(substr($t['title'], 0, 40)) ?>
                        </a>
                        <span class="text-xs text-gray-500">👁️ <?= format_number($t['views']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Categories -->
        <div class="bg-gray-800 rounded-lg p-4">
            <h3 class="text-lg font-bold mb-3">📂 Categories</h3>
            <div class="space-y-2">
                <?php foreach ($categories as $cat): ?>
                    <a href="<?= site_url('/blog/?category=' . urlencode($cat['category'])) ?>" 
                       class="block text-sm hover:text-gold">
                        <?= htmlspecialchars($cat['category']) ?> (<?= $cat['cnt'] ?>)
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Subscribe -->
        <div class="bg-gray-800 rounded-lg p-4 mt-6 border-l-4 border-gold">
            <h3 class="font-bold mb-2">📬 Subscribe</h3>
            <p class="text-xs text-gray-300 mb-3">Get updates on new posts and announcements</p>
            <form class="space-y-2">
                <input type="email" placeholder="your@email.com" class="w-full px-2 py-1 bg-gray-700 text-white text-sm rounded" required>
                <button type="submit" class="w-full px-2 py-1 bg-gold text-midnight text-sm font-bold rounded hover:bg-yellow-400">Subscribe</button>
            </form>
        </div>
    </aside>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
