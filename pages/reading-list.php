<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/config/db.php';

requireLogin();

// Ensure reading_progress table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS reading_progress (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        story_id INT UNSIGNED NOT NULL,
        chapter_number INT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_progress (user_id, story_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    // ignore
}

$user_id = $_SESSION['user_id'];

// Get search parameter
$search = $_GET['search'] ?? '';

// Fetch bookmarked stories with reading progress
$query = "
    SELECT 
        s.id, s.title, s.slug, COALESCE(s.cover, '') as cover, s.description, 
        u.username as author_name,
        COUNT(DISTINCT c.id) as total_chapters,
        COALESCE(MAX(CASE WHEN rp.user_id = ? THEN rp.chapter_number ELSE 0 END), 0) as last_chapter_read,
        CASE 
            WHEN COUNT(DISTINCT c.id) > 0 
            THEN ROUND((COALESCE(MAX(CASE WHEN rp.user_id = ? THEN rp.chapter_number ELSE 0 END), 0) / COUNT(DISTINCT c.id)) * 100)
            ELSE 0
        END as progress_percent
    FROM saved_stories ss
    JOIN stories s ON ss.story_id = s.id
    JOIN users u ON s.author_id = u.id
    LEFT JOIN chapters c ON s.id = c.story_id
    LEFT JOIN reading_progress rp ON s.id = rp.story_id AND rp.user_id = ?
    WHERE ss.user_id = ?
";

$params = [$user_id, $user_id, $user_id, $user_id];

if (!empty($search)) {
    $query .= " AND (s.title LIKE ? OR s.description LIKE ? OR u.username LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params = [$user_id, $user_id, $user_id, $user_id, $searchTerm, $searchTerm, $searchTerm];
}

$query .= " GROUP BY s.id, s.title, s.slug, COALESCE(s.cover, ''), s.description, u.username ORDER BY ss.id DESC";

// Fetch bookmarked stories with reading progress
$bookmarked = $pdo->prepare($query);
$bookmarked->execute($params);
$bookmarked = $bookmarked->fetchAll();

$userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);
?>

<?php
    $page_title = 'My Library - Scroll Novels';
    $page_head = '<script src="https://cdn.tailwindcss.com"></script>'
        . '<script>tailwind.config={darkMode:"class"};</script>'
        . '<link rel="stylesheet" href="' . asset_url('css/global.css') . '">'
        . '<link rel="stylesheet" href="' . asset_url('css/theme.css') . '">'
        . '<script src="' . asset_url('js/theme.js') . '" defer></script>'
        . '<style>:root{--transition-base:200ms ease-in-out}body{transition:background-color var(--transition-base),color var(--transition-base)}</style>';

    require_once __DIR__ . '/../includes/header.php';
?>

<!-- Main Content -->
<main class="flex-1">
    <div class="max-w-7xl mx-auto px-4 py-12">
        <!-- Page Title -->
        <div class="mb-8">
            <h2 class="text-4xl font-bold text-emerald-700 dark:text-emerald-400 mb-2">📖 My Reading Library</h2>
            <p class="text-gray-600 dark:text-gray-400">Continue reading your bookmarked stories</p>
        </div>

        <!-- Search Bar -->
        <div class="mb-6">
            <form method="get" class="flex gap-2">
                <input type="text" name="search" placeholder="Search by title, author..." value="<?= htmlspecialchars($search) ?>" 
                       class="flex-1 px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 text-emerald-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <button type="submit" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors">
                    Search
                </button>
                <?php if (!empty($search)): ?>
                    <a href="<?= site_url('/pages/reading-list.php') ?>" class="px-6 py-2 bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 text-gray-900 dark:text-white rounded-lg font-medium transition-colors">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($bookmarked)): ?>
            <!-- Empty State -->
            <div class="bg-white dark:bg-gray-800 rounded-lg p-12 shadow border border-emerald-200 dark:border-emerald-900 text-center">
                <div class="text-6xl mb-4">📚</div>
                <p class="text-lg text-gray-600 dark:text-gray-400 mb-6">You haven't bookmarked any stories yet</p>
                <a href="<?= site_url('/pages/browse.php') ?>" class="inline-block px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium">Browse Stories</a>
            </div>
        <?php else: ?>
            <!-- Stories List -->
            <div class="space-y-4">
                <?php foreach ($bookmarked as $story): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow border border-emerald-200 dark:border-emerald-900 hover:shadow-lg transition-shadow">
                        <div class="flex gap-6">
                            <!-- Cover -->
                            <div class="flex-shrink-0">
                                <?php if (!empty($story['cover'])): ?>
                                    <img src="<?= htmlspecialchars($story['cover']) ?>" alt="<?= htmlspecialchars($story['title']) ?>" class="w-24 h-32 object-cover rounded-lg">
                                <?php else: ?>
                                    <div class="w-24 h-32 bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center rounded-lg text-3xl">📚</div>
                                <?php endif; ?>
                            </div>

                            <!-- Story Info -->
                            <div class="flex-1">
                                <h3 class="text-xl font-bold text-emerald-700 dark:text-emerald-400 mb-1">
                                    <a href="<?= site_url('/pages/book.php?id=' . $story['id']) ?>" class="hover:underline"><?= htmlspecialchars($story['title']) ?></a>
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">by <?= htmlspecialchars($story['author_name']) ?></p>
                                <p class="text-gray-600 dark:text-gray-400 line-clamp-2 mb-4"><?= htmlspecialchars(substr($story['description'], 0, 150)) ?>...</p>

                                <!-- Stats -->
                                <div class="flex gap-6 mb-4 text-sm text-gray-600 dark:text-gray-400">
                                    <span>📖 <?= $story['total_chapters'] ?> Chapters</span>
                                    <span>📍 Chapter <?= max(1, $story['last_chapter_read']) ?> of <?= $story['total_chapters'] ?></span>
                                </div>

                                <!-- Progress Bar -->
                                <div class="mb-4">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Reading Progress</span>
                                        <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400"><?= $story['progress_percent'] ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-emerald-600 h-2 rounded-full" style="width: <?= $story['progress_percent'] ?>%"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Button -->
                            <div class="flex flex-col gap-2 justify-center flex-shrink-0">
                                <a href="<?= site_url('/pages/read.php?id=' . $story['id'] . '&ch=' . ($story['last_chapter_read'] ?: 1)) ?>" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium text-center transition-colors">
                                    Continue Reading
                                </a>
                                <button onclick="removeBookmark(<?= $story['id'] ?>)" class="px-4 py-2 border-2 border-red-600 text-red-600 dark:border-red-400 dark:text-red-400 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 font-medium transition-colors">
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Footer -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const closeSidebarBtn = document.getElementById('closeSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('translate-x-full');
        sidebar.classList.add('translate-x-0');
        if (overlay) {
            overlay.classList.remove('hidden');
            overlay.classList.add('block');
        }
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('translate-x-full');
        sidebar.classList.remove('translate-x-0');
        if (overlay) {
            overlay.classList.add('hidden');
            overlay.classList.remove('block');
        }
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e){
            e.preventDefault();
            openSidebar();
        });
    }

    if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);
});

async function removeBookmark(storyId) {
    if (!confirm('Remove this story from your library?')) return;
    try {
        const res = await fetch('<?= site_url('/api/stories.php') ?>', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action: 'unsave', story_id: storyId})
        });
        if (res.ok) {
            location.reload();
        }
    } catch (err) { 
        console.error(err); 
    }
}
</script>

</body>
</html>
