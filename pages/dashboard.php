<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$user = null;

// Fetch user details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    header("Location: " . site_url());
    exit;
}

if (!$user) {
    header("Location: " . site_url('/pages/logout.php'));
    exit;
}

// Fetch user's stories stats
$stories = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(views), 0) as total_views FROM stories WHERE author_id = $user_id")->fetch();
$total_stories = $stories['count'] ?? 0;
$total_views = format_number($stories['total_views'] ?? 0);

// Fetch recent stories for "My Stories" grid
$my_stories = $pdo->query("SELECT id, title, slug, COALESCE(cover, '') as cover, views, status FROM stories WHERE author_id = $user_id ORDER BY created_at DESC LIMIT 6")->fetchAll();

// Earnings and points (stubbed - customize per your data model)
try {
    $earnings = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE recipient_id = $user_id")->fetch();
    $total_earnings = isset($earnings['total']) ? number_format((float)$earnings['total'], 2) : '0.00';
} catch (Exception $e) {
    $total_earnings = '0.00';
}

// Author Stats - Fictions
try {
    $fictions = $pdo->query("SELECT COUNT(*) as count FROM stories WHERE author_id = $user_id AND status = 'published'")->fetch();
    $total_fictions = $fictions['count'] ?? 0;
} catch (Exception $e) {
    $total_fictions = 0;
}

// Author Stats - Total Chapters
try {
    $chapters = $pdo->query("SELECT COUNT(*) as count FROM chapters WHERE story_id IN (SELECT id FROM stories WHERE author_id = $user_id)")->fetch();
    $total_chapters = $chapters['count'] ?? 0;
} catch (Exception $e) {
    $total_chapters = 0;
}

// Author Stats - Total Words
try {
    $words = $pdo->query("SELECT COALESCE(SUM(CHAR_LENGTH(content) - CHAR_LENGTH(REPLACE(content, ' ', '')) + 1), 0) as total_words FROM chapters WHERE story_id IN (SELECT id FROM stories WHERE author_id = $user_id)")->fetch();
    $total_words = format_number($words['total_words'] ?? 0);
} catch (Exception $e) {
    $total_words = '0';
}

// Author Stats - Reviews Received
try {
    $reviews = $pdo->query("SELECT COUNT(*) as count FROM reviews WHERE story_id IN (SELECT id FROM stories WHERE author_id = $user_id)")->fetch();
    $total_reviews = $reviews['count'] ?? 0;
} catch (Exception $e) {
    $total_reviews = 0;
}

// Author Stats - Unique Followers
try {
    $followers = $pdo->query("SELECT COUNT(DISTINCT follower_id) as count FROM follows WHERE following_id = $user_id")->fetch();
    $total_followers = $followers['count'] ?? 0;
} catch (Exception $e) {
    $total_followers = 0;
}

// Load book card component
if (file_exists(dirname(__DIR__) . '/includes/components/book-card.php')) {
    require_once dirname(__DIR__) . '/includes/components/book-card.php';
}

$userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Scroll Novels</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = { darkMode: 'class' };
    </script>
    
    <link rel="stylesheet" href="<?= asset_url('css/global.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/theme.css') ?>">
    <script src="<?= asset_url('js/theme.js') ?>" defer></script>
    <style>
        :root { --transition-base: 200ms ease-in-out; }
        body { transition: background-color var(--transition-base), color var(--transition-base); }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-gradient-to-b from-emerald-50 to-green-100 dark:from-gray-900 dark:to-gray-800 text-emerald-900 dark:text-emerald-50">

<!-- Header -->
<header class="bg-white dark:bg-gray-900 shadow border-b border-emerald-200 dark:border-emerald-900 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="text-3xl">📜</div>
            <h1 class="text-xl font-bold text-emerald-600 dark:text-emerald-400">Scroll Novels</h1>
        </div>
        <nav class="hidden md:flex flex-wrap gap-3 text-sm font-medium">
            <a href="<?= site_url() ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Home</a>
            <a href="<?= site_url('/pages/browse.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Browse</a>
            <a href="<?= site_url('/pages/community.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Community</a>
            <a href="<?= site_url('/pages/website-rules.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Rules</a>
        </nav>
        <div class="flex items-center gap-4">
            <button onclick="toggleTheme()" class="flex items-center gap-2 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 px-3 py-2 rounded-lg transition-colors">
                <span class="dark:hidden text-xl">☀️</span>
                <span class="hidden dark:block text-xl">🌙</span>
            </button>
            <button id="sidebarToggle" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition-colors text-sm font-medium">
                👤 <?= htmlspecialchars(substr($userName, 0, 15)) ?: 'User' ?>
            </button>
        </div>
    </div>
</header>

<!-- Sidebar Overlay -->
<div id="sidebarOverlay" class="hidden fixed inset-0 bg-black/50 z-40"></div>

<!-- Sidebar -->
<aside id="sidebar" class="fixed right-0 top-0 h-screen w-64 bg-white dark:bg-gray-800 shadow-lg transform translate-x-full transition-transform z-50">
    <div class="p-4 border-b border-emerald-200 dark:border-emerald-900 flex justify-between items-center">
        <h3 class="text-lg font-bold text-emerald-600">Menu</h3>
        <button id="closeSidebar" class="text-2xl">&times;</button>
    </div>
    <nav class="p-4 space-y-3 text-sm">
        <a href="<?= site_url('/pages/profile.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">👤 Profile</a>
        <a href="<?= site_url('/pages/achievements.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">🏆 Achievements</a>
        <a href="<?= site_url('/pages/points-dashboard.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">⭐ Points & Rewards</a>
        <a href="<?= site_url('/pages/dashboard.php') ?>" class="block px-3 py-2 rounded-lg bg-emerald-100 dark:bg-emerald-900/50">📊 Dashboard</a>
        <a href="<?= site_url('/pages/reading-list.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">📖 My Library</a>
        <a href="<?= site_url('/pages/write-story.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">✍️ Write Story</a>
        <a href="<?= site_url('/pages/chat.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">💬 Chat</a>
        <hr class="my-2 border-emerald-200">
        <a href="<?= site_url('/pages/logout.php') ?>" class="block px-3 py-2 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">🚪 Logout</a>
    </nav>
</aside>

<!-- Main Content -->
<main class="flex-1">
    <div class="max-w-7xl mx-auto px-4 py-12">
        <!-- Page Title -->
        <div class="mb-8">
            <h2 class="text-4xl font-bold text-emerald-700 dark:text-emerald-400 mb-2">Writer Dashboard</h2>
            <p class="text-gray-600 dark:text-gray-400">Manage your stories, track analytics, and grow your audience</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-12">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow border border-emerald-200 dark:border-emerald-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 dark:text-gray-400 text-xs font-medium">Fictions</p>
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-2"><?= $total_fictions ?></p>
                    </div>
                    <div class="text-3xl">📚</div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow border border-emerald-200 dark:border-emerald-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 dark:text-gray-400 text-xs font-medium">Chapters</p>
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-2"><?= $total_chapters ?></p>
                    </div>
                    <div class="text-3xl">📖</div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow border border-emerald-200 dark:border-emerald-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 dark:text-gray-400 text-xs font-medium">Total Words</p>
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-2"><?= $total_words ?></p>
                    </div>
                    <div class="text-3xl">✍️</div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow border border-emerald-200 dark:border-emerald-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 dark:text-gray-400 text-xs font-medium">Reviews</p>
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-2"><?= $total_reviews ?></p>
                    </div>
                    <div class="text-3xl">⭐</div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow border border-emerald-200 dark:border-emerald-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 dark:text-gray-400 text-xs font-medium">Followers</p>
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-2"><?= $total_followers ?></p>
                    </div>
                    <div class="text-3xl">👥</div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow border border-emerald-200 dark:border-emerald-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 dark:text-gray-400 text-xs font-medium">Earnings</p>
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-2">$<?= $total_earnings ?></p>
                    </div>
                    <div class="text-3xl">💰</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow border border-emerald-200 dark:border-emerald-900 mb-12">
            <h3 class="text-xl font-bold text-emerald-700 dark:text-emerald-400 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <a href="<?= site_url('/pages/write-story.php') ?>" class="px-4 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors text-center">✍️ Write New Story</a>
                <a href="<?= site_url('/pages/analytics.php') ?>" class="px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors text-center">📊 View Analytics</a>
                <a href="<?= site_url('/pages/ads.php') ?>" class="px-4 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition-colors text-center">📢 Manage Ads</a>
                <a href="<?= site_url('/pages/dashboard.php?action=support') ?>" class="px-4 py-3 bg-pink-600 hover:bg-pink-700 text-white rounded-lg font-medium transition-colors text-center">❤️ Support Links</a>
            </div>
        </div>

        <!-- My Stories -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow border border-emerald-200 dark:border-emerald-900">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">My Stories</h3>
                <a href="<?= site_url('/pages/write-story.php') ?>" class="text-sm px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg">+ New Story</a>
            </div>

            <?php if (empty($my_stories)): ?>
                <div class="text-center py-12">
                    <p class="text-gray-600 dark:text-gray-400 mb-4">You haven't published any stories yet</p>
                    <a href="<?= site_url('/pages/write-story.php') ?>" class="inline-block px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg">Start Writing</a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($my_stories as $story): ?>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-600 hover:shadow-lg transition-shadow cursor-pointer" onclick="openStoryModal(<?= $story['id'] ?>)">
                            <?php if (!empty($story['cover'])): ?>
                                <img src="<?= htmlspecialchars($story['cover']) ?>" alt="<?= htmlspecialchars($story['title']) ?>" class="w-full h-40 object-cover">
                            <?php else: ?>
                                <div class="w-full h-40 bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-3xl">📚</div>
                            <?php endif; ?>
                            <div class="p-4">
                                <h4 class="font-bold text-lg text-emerald-700 dark:text-emerald-400 mb-1"><?= htmlspecialchars($story['title']) ?></h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">👁️ <?= format_number($story['views']) ?> views</p>
                            <div class="flex gap-2">
                                    <a href="<?= site_url('/pages/book.php?id=' . $story['id']) ?>" class="flex-1 text-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded" onclick="event.stopPropagation();">View</a>
                                    <a href="<?= site_url('/pages/book-dashboard.php?id=' . $story['id']) ?>" class="flex-1 text-center px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm rounded" onclick="event.stopPropagation();">Manage</a>
                                    <a href="<?= site_url('/pages/write-story.php?edit=' . $story['id']) ?>" class="flex-1 text-center px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded" onclick="event.stopPropagation();">Edit</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Footer -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Story Details Modal -->
<div id="storyModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 id="modalTitle" class="text-2xl font-bold text-emerald-700 dark:text-emerald-400"></h3>
            <button onclick="closeStoryModal()" class="text-2xl text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">×</button>
        </div>

        <!-- Story Info -->
        <div class="mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex gap-4 mb-4">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Views</p>
                    <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400" id="modalViews">0</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Status</p>
                    <p class="text-lg font-bold text-blue-600 dark:text-blue-400" id="modalStatus">Draft</p>
                </div>
            </div>
            <div id="modalDescription"></div>
        </div>

        <!-- Chapters -->
        <div class="mb-6">
            <div class="flex justify-between items-center mb-4">
                <h4 class="text-lg font-bold text-emerald-700 dark:text-emerald-400">Chapters</h4>
                <button onclick="addChapter()" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded">+ Add Chapter</button>
            </div>
            <div id="chaptersList" class="space-y-2"></div>
        </div>

        <!-- Actions -->
        <div class="flex gap-3 border-t border-gray-200 dark:border-gray-700 pt-6">
            <button onclick="publishStory()" class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium">📤 Publish</button>
            <button onclick="unpublishStory()" class="flex-1 px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg font-medium">📥 Unpublish</button>
            <button onclick="closeStoryModal()" class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-medium">Close</button>
        </div>
    </div>
</div>

<script>
let currentStoryId = null;

function openStoryModal(storyId) {
    currentStoryId = storyId;
    fetch('<?= site_url('/api/story-details.php') ?>?id=' + storyId)
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                const s = data.story;
                document.getElementById('modalTitle').textContent = s.title;
                document.getElementById('modalViews').textContent = s.views.toLocaleString();
                document.getElementById('modalStatus').textContent = s.status.charAt(0).toUpperCase() + s.status.slice(1);
                document.getElementById('modalDescription').innerHTML = '<p class="text-gray-700 dark:text-gray-300">' + (s.description || 'No description') + '</p>';

                // Chapters list
                const chaptersHtml = data.chapters.map(c => `
                    <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg flex justify-between items-center">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-gray-100">Chapter ${c.chapter_number}: ${c.title || 'Untitled'}</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">${c.word_count || 0} words • ${new Date(c.created_at).toLocaleDateString()}</p>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="editChapter(${c.id})" class="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded">Edit</button>
                            <button onclick="deleteChapter(${c.id})" class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white text-xs rounded">Delete</button>
                        </div>
                    </div>
                `).join('');
                document.getElementById('chaptersList').innerHTML = chaptersHtml || '<p class="text-gray-600 dark:text-gray-400">No chapters yet</p>';

                document.getElementById('storyModal').classList.remove('hidden');
            }
        })
        .catch(e => console.error(e));
}

function closeStoryModal() {
    document.getElementById('storyModal').classList.add('hidden');
    currentStoryId = null;
}

function addChapter() {
    if (currentStoryId) {
        window.location.href = '<?= site_url('/story/chapter_edit.php') ?>?story_id=' + currentStoryId;
    }
}

function editChapter(chapterId) {
    if (currentStoryId) {
        window.location.href = '<?= site_url('/story/chapter_edit.php') ?>?story_id=' + currentStoryId + '&chapter_id=' + chapterId;
    }
}

function deleteChapter(chapterId) {
    if (confirm('Delete this chapter?')) {
        fetch('/scrollnovels/api/chapters.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ chapter_id: chapterId, story_id: currentStoryId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Chapter deleted successfully!');
                loadUserStories();
            } else {
                alert('Error deleting chapter: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting chapter. Please try again.');
        });
    }
}

function publishStory() {
    if (currentStoryId && confirm('Publish this story?')) {
        fetch('/scrollnovels/api/stories.php?action=publish', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ story_id: currentStoryId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Story published successfully!');
                loadUserStories();
                closeStoryModal();
            } else {
                alert('Error publishing story: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error publishing story. Please try again.');
        });
    }
}

function unpublishStory() {
    if (currentStoryId && confirm('Unpublish this story?')) {
        fetch('/scrollnovels/api/stories.php?action=unpublish', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ story_id: currentStoryId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Story unpublished successfully!');
                loadUserStories();
                closeStoryModal();
            } else {
                alert('Error unpublishing story: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error unpublishing story. Please try again.');
        });
    }
}

// Close modal when clicking outside
document.getElementById('storyModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeStoryModal();
});
</script>
<!-- Support Links Modal (Ko-fi / Patreon) -->
<div id="supportModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-8 max-w-md w-full mx-4">
        <h3 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400 mb-4">❤️ Support Links</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-6 text-sm">Readers can support you by tapping on your books with Ko-fi or Patreon donation links.</p>
        <form id="supportLinksForm" class="space-y-4">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ko-fi Link</label>
                <input type="url" name="kofi_url" placeholder="https://ko-fi.com/yourname" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white">
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">e.g., https://ko-fi.com/yourname</p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Patreon Link</label>
                <input type="url" name="patreon_url" placeholder="https://patreon.com/yourname" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white">
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">e.g., https://patreon.com/yourname</p>
            </div>
            <div class="mb-4 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                <p class="text-xs text-blue-700 dark:text-blue-300">💡 Readers can tap on your books in their library to support you directly!</p>
            </div>
            <div id="supportMessage" class="hidden mb-4 p-3 rounded-lg"></div>
            <div class="flex gap-4">
                <button type="submit" class="flex-1 px-4 py-2 bg-pink-600 hover:bg-pink-700 text-white rounded-lg font-medium">Save Links</button>
                <button type="button" onclick="document.getElementById('supportModal').classList.add('hidden')" class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-medium">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if support links action is requested
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'support') {
        loadSupportLinksForm();
        document.getElementById('supportModal').classList.remove('hidden');
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    
    // Handle support links form submission
    const supportForm = document.getElementById('supportLinksForm');
    if (supportForm) {
        supportForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const kofi = document.querySelector('input[name="kofi_url"]').value.trim();
            const patreon = document.querySelector('input[name="patreon_url"]').value.trim();
            
            if (!kofi && !patreon) {
                showSupportMessage('Please provide at least one support link', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('kofi_url', kofi);
            formData.append('patreon_url', patreon);
            
            fetch('<?= site_url('/api/save-support-links.php') ?>', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showSupportMessage('✅ ' + data.message, 'success');
                    setTimeout(() => {
                        document.getElementById('supportModal').classList.add('hidden');
                        location.reload();
                    }, 1500);
                } else {
                    showSupportMessage('❌ ' + (data.error || 'Failed to save'), 'error');
                }
            })
            .catch(e => {
                showSupportMessage('❌ Error: ' + e.message, 'error');
                console.error('Error:', e);
            });
        });
    }
});

// Load current support links for the user
function loadSupportLinksForm() {
    fetch('<?= site_url('/api/get-user-support-links.php') ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.querySelector('input[name="kofi_url"]').value = data.kofi || '';
                document.querySelector('input[name="patreon_url"]').value = data.patreon || '';
            }
        })
        .catch(e => console.error('Error loading support links:', e));
}

function showSupportMessage(message, type) {
    const messageEl = document.getElementById('supportMessage');
    messageEl.textContent = message;
    messageEl.className = type === 'success' 
        ? 'block bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300'
        : 'block bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300';
}

document.getElementById('sidebarToggle').addEventListener('click', function(e){
    e.preventDefault();
    document.getElementById('sidebar').classList.remove('translate-x-full');
    document.getElementById('sidebarOverlay').classList.remove('hidden');
});

document.getElementById('closeSidebar').addEventListener('click', function(){
    document.getElementById('sidebar').classList.add('translate-x-full');
    document.getElementById('sidebarOverlay').classList.add('hidden');
});
</script>

</body>
</html>
