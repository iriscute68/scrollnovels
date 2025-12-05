<?php
// Announcements Page - Enhanced Integration
if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['username'] ?? '';

// Handle announcement creation for authors
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_announcement') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        
        if ($title && $content) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO announcements (author_id, title, content, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$userId, $title, $content]);
                $success = 'Announcement created successfully!';
            } catch (Exception $e) {
                $error = 'Error creating announcement: ' . $e->getMessage();
            }
        }
    }
}

// Get announcements from database + sample announcements
$database_announcements = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.id, a.title, a.content, a.created_at, u.username as author_name, u.id as author_id,
               COUNT(DISTINCT r.id) as reply_count
        FROM announcements a
        LEFT JOIN users u ON a.author_id = u.id
        LEFT JOIN announcement_replies r ON a.id = r.announcement_id
        WHERE a.author_id = ? OR a.author_id IN (
            SELECT author_id FROM follows WHERE follower_id = ?
        )
        GROUP BY a.id
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$userId, $userId]);
    $database_announcements = $stmt->fetchAll();
} catch (Exception $e) {
    $database_announcements = [];
}

// Sample announcements data for display if database is empty
$sample_announcements = [
  [
    'id' => 1,
    'title' => 'New Fantasy Collection Now Available',
    'content' => 'Explore amazing new fantasy stories from talented writers around the world. Join our community today!',
    'image' => '🌟',
    'date' => 'Nov 20, 2025',
    'link' => '/scrollnovels/pages/blog.php?id=1',
    'author_name' => 'Platform Admin'
  ],
  [
    'id' => 2,
    'title' => 'Limited Time Contest: Win $5,000',
    'content' => 'Submit your best story and compete for amazing prizes. The contest ends on December 15th!',
    'image' => '🏆',
    'date' => 'Nov 18, 2025',
    'link' => '/scrollnovels/pages/competitions.php',
    'author_name' => 'Platform Admin'
  ],
  [
    'id' => 3,
    'title' => 'Platform Updates and New Features',
    'content' => 'We\'ve launched new features including better search, improved recommendations, and more!',
    'image' => '✨',
    'date' => 'Nov 15, 2025',
    'link' => '/scrollnovels/pages/blog.php?id=3',
    'author_name' => 'Platform Admin'
  ]
];

// Use database announcements if available, otherwise use samples
$announcements = !empty($database_announcements) ? $database_announcements : $sample_announcements;

// Get user's followers count if author
$followerCount = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM follows WHERE author_id = ?");
    $stmt->execute([$userId]);
    $followerCount = $stmt->fetch()['cnt'];
} catch (Exception $e) {
    $followerCount = 0;
}
?>
<?php
    $page_title = 'Announcements - Scroll Novels';
    $page_head = '';
    require_once __DIR__ . '/../includes/header.php';
?>
<style>
    .announcement-card {
        transition: all 0.3s ease;
        animation: slideIn 0.5s ease-out;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .announcement-card:hover {
        box-shadow: 0 12px 24px rgba(16, 185, 129, 0.15);
        transform: translateY(-2px);
    }
    
    .announcements-hero {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        padding: 2rem 0;
        margin-bottom: 2rem;
        color: white;
    }
    
    .announcements-hero h1 {
        font-size: 2.5rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }
    
    .announcements-hero p {
        font-size: 1.1rem;
        opacity: 0.9;
    }
    
    @media (max-width: 768px) {
        .announcements-hero h1 {
            font-size: 1.875rem;
        }
    }
</style>

<div class="max-w-4xl mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-12">
        <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">📢 Announcements</h1>
        <p class="text-gray-600 dark:text-gray-400">
            Follow your favorite authors and see their latest announcements here.
        </p>
    </div>

    <?php if ($isLoggedIn): ?>
        <!-- Messages -->
        <?php if (isset($success)): ?>
            <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/30 border border-green-500 rounded-lg">
                <p class="text-green-700 dark:text-green-400">✅ <?= htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/30 border border-red-500 rounded-lg">
                <p class="text-red-700 dark:text-red-400">❌ <?= htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <!-- Create Announcement Form (for authors) -->
        <div class="mb-8 bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">📝 Create Announcement</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Share updates with your followers! (<?= $followerCount; ?> followers)
            </p>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="create_announcement">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Title
                    </label>
                    <input type="text" name="title" required class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Announcement title">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Message
                    </label>
                    <textarea name="content" required rows="4" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="What do you want to announce?"></textarea>
                </div>
                
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2 px-6 rounded-lg transition-colors">
                    Post Announcement
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Announcements Feed - ALWAYS DISPLAY FOR ALL USERS -->
    <div class="space-y-6">
        <?php if (empty($announcements)): ?>
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <p class="text-gray-500 dark:text-gray-400 mb-4">No announcements yet.</p>
                <p class="text-sm text-gray-600 dark:text-gray-500">Check back soon for exciting updates!</p>
            </div>
        <?php else: ?>
            <?php foreach ($announcements as $announcement): ?>
                <a href="<?= site_url('/pages/blog-view.php?id=' . $announcement['id']) ?>" class="announcement-card bg-white dark:bg-gray-800 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-shadow block" style="text-decoration: none; color: inherit;">
                    <!-- Card Header with Icon -->
                    <div class="bg-gradient-to-r from-emerald-500 to-teal-600 p-6 text-white flex items-center gap-4">
                        <div class="text-4xl"><?= isset($announcement['image']) ? $announcement['image'] : '📢'; ?></div>
                        <div>
                            <h3 class="text-xl font-bold"><?= htmlspecialchars($announcement['title']); ?></h3>
                            <p class="text-emerald-100 text-sm"><?= isset($announcement['date']) ? htmlspecialchars($announcement['date']) : date('M d, Y', strtotime($announcement['created_at'] ?? 'now')); ?></p>
                        </div>
                    </div>

                    <!-- Card Content -->
                    <div class="p-6">
                        <div class="text-gray-700 dark:text-gray-300 mb-4 announcement-content">
                            <?= $announcement['content']; ?>
                        </div>
                        
                        <!-- Author Info -->
                        <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-sm">
                                    <?= strtoupper(substr($announcement['author_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <a href="<?= site_url('/pages/blog-view.php?id=' . $announcement['id']) ?>" class="font-semibold text-gray-900 dark:text-white hover:text-emerald-600 dark:hover:text-emerald-400">
                                            <?= htmlspecialchars($announcement['author_name']); ?>
                                        </a>
                                </div>
                            </div>
                            <a href="<?= site_url('/pages/blog-view.php?id=' . $announcement['id']) ?>" class="inline-flex items-center gap-2 text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 font-medium">
                                Read More →
                            </a>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>

