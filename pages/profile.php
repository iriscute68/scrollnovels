<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/config/db.php';

// Accept either username (?user=) or user id (?user_id=) to view profiles
$username = trim($_GET['user'] ?? '');
$userIdParam = (int)($_GET['user_id'] ?? 0);

// If no user provided and user is logged in, redirect to their own profile
if (!$username && !$userIdParam && isLoggedIn()) {
    $currentUser = getCurrentUser();
    if ($currentUser) {
        header("Location: " . rtrim(SITE_URL, '/') . "/pages/profile.php?user=" . urlencode($currentUser['username']));
        exit;
    }
}

// If username missing but user_id provided, resolve to username
if (!$username && $userIdParam) {
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$userIdParam]);
    $resolved = $stmt->fetchColumn();
    if ($resolved) {
        $username = $resolved;
    }
}

if (!$username) {
    header("Location: " . rtrim(SITE_URL, '/'));
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    die("User not found");
}

$user_id = $user['id'];
$is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id;
$following = false;

// Load roles for this profile (if role tables exist). Fall back to legacy users.role
$user_roles = [];
try {
    $rstmt = $pdo->prepare("SELECT r.name FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?");
    $rstmt->execute([$user_id]);
    $user_roles = $rstmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Exception $e) {
    if (!empty($user['role'])) $user_roles = [$user['role']];
}

// Check if user is blocked
$isBlocked = false;
$userBlocking = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
    $stmt->execute([$_SESSION['user_id'], $user_id]);
    $isBlocked = (bool)$stmt->fetchColumn();
}

// Create follows table if doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS follows (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        follower_id INT UNSIGNED NOT NULL,
        following_id INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_follow (follower_id, following_id),
        INDEX idx_follower (follower_id),
        INDEX idx_following (following_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Table already exists
}

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$_SESSION['user_id'], $user_id]);
    $following = $stmt->fetch();
}

// Stats
$stats = [
    'stories' => $pdo->query("SELECT COUNT(*) FROM stories WHERE author_id = $user_id AND status = 'published'")->fetchColumn() ?? 0,
    'chapters' => $pdo->query("SELECT COUNT(*) FROM chapters WHERE story_id IN (SELECT id FROM stories WHERE author_id = $user_id)")->fetchColumn() ?? 0,
    'views' => $pdo->query("SELECT SUM(views) FROM stories WHERE author_id = $user_id")->fetchColumn() ?? 0,
    'followers' => $pdo->query("SELECT COUNT(*) FROM follows WHERE following_id = $user_id")->fetchColumn() ?? 0,
    'following' => $pdo->query("SELECT COUNT(*) FROM follows WHERE follower_id = $user_id")->fetchColumn() ?? 0,
];

// Calculate average rating from all reviews of author's stories
try {
    $avgRatingResult = $pdo->query("
        SELECT AVG(r.rating) as avg_rating
        FROM reviews r
        INNER JOIN stories s ON r.story_id = s.id
        WHERE s.author_id = $user_id
    ")->fetch();
    $stats['avg_rating'] = $avgRatingResult && $avgRatingResult['avg_rating'] ? round($avgRatingResult['avg_rating'], 1) : 0;
} catch (Exception $e) {
    $stats['avg_rating'] = 0;
}

// Calculate total likes (sum of all likes on author's stories)
// Actually this shows how many users added author's stories to their library
try {
    $totalLibraryAdds = $pdo->query("
        SELECT COUNT(*) as total_adds
        FROM saved_stories ss
        INNER JOIN stories s ON ss.story_id = s.id
        WHERE s.author_id = $user_id
    ")->fetch();
    $stats['total_likes'] = $totalLibraryAdds ? (int)$totalLibraryAdds['total_adds'] : 0;
} catch (Exception $e) {
    $stats['total_likes'] = 0;
}

// Stories
$stories = $pdo->prepare("
    SELECT s.id, s.title, s.slug, COALESCE(s.cover_image, '') as cover, s.status, s.views, 
           (SELECT COUNT(*) FROM chapters WHERE story_id = s.id) as chapters
    FROM stories s
    WHERE s.author_id = ?
    ORDER BY s.created_at DESC LIMIT 6
");
$stories->execute([$user_id]);
$stories = $stories->fetchAll();

// User Comments and Reviews
try {
    // Blog post comments
    $blogCommentsStmt = $pdo->prepare("
        SELECT bc.id, bc.comment_text, bc.created_at, 
               bp.id as blog_post_id, bp.title as post_title,
               'blog' as type
        FROM blog_comments bc
        LEFT JOIN blog_posts bp ON bc.blog_post_id = bp.id
        WHERE bc.user_id = ?
        ORDER BY bc.created_at DESC LIMIT 20
    ");
    $blogCommentsStmt->execute([$user_id]);
    $blogComments = $blogCommentsStmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
} catch (Exception $e) {
    $blogComments = [];
}

try {
    // Announcement comments
    $announcementCommentsStmt = $pdo->prepare("
        SELECT ac.id, ac.comment_text, ac.created_at,
               a.id as announcement_id, a.title as announcement_title,
               'announcement' as type
        FROM announcement_comments ac
        LEFT JOIN announcements a ON ac.announcement_id = a.id
        WHERE ac.user_id = ?
        ORDER BY ac.created_at DESC LIMIT 20
    ");
    $announcementCommentsStmt->execute([$user_id]);
    $announcementComments = $announcementCommentsStmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
} catch (Exception $e) {
    $announcementComments = [];
}

// Combine and sort by date
$userComments = array_merge($blogComments, $announcementComments);
usort($userComments, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

$userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@<?= htmlspecialchars($user['username']) ?> - Scroll Novels</title>
    
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
        <a href="<?= site_url('/pages/profile.php') ?>" class="block px-3 py-2 rounded-lg bg-emerald-100 dark:bg-emerald-900/50">👤 Profile</a>
        <a href="<?= site_url('/pages/achievements.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">🏆 Achievements</a>
        <a href="<?= site_url('/pages/points-dashboard.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">⭐ Points & Rewards</a>
        <a href="<?= site_url('/pages/dashboard.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">📊 Dashboard</a>
        <a href="<?= site_url('/pages/reading-list.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">📖 My Library</a>
        <a href="<?= site_url('/pages/write-story.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">✍️ Write Story</a>
        <a href="<?= site_url('/pages/chat.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">💬 Chat</a>
        <?php if ($is_owner): ?>
            <hr class="my-2 border-emerald-200">
            <a href="<?= site_url('/pages/profile-settings.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">⚙️ Settings</a>
        <?php endif; ?>
        <hr class="my-2 border-emerald-200">
        <a href="<?= site_url('/pages/logout.php') ?>" class="block px-3 py-2 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">🚪 Logout</a>
    </nav>
</aside>

<!-- Main Content -->
<main class="flex-1">
    <div class="max-w-7xl mx-auto px-4 py-12">
        <!-- Profile Header Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-8 shadow border border-emerald-200 dark:border-emerald-900 mb-8">
            <div class="flex flex-col md:flex-row gap-8 items-start">
                <!-- Avatar -->
                <div class="flex-shrink-0">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="<?= htmlspecialchars($user['profile_image']) ?>" alt="<?= htmlspecialchars($user['username']) ?>" class="w-24 h-24 md:w-32 md:h-32 rounded-full object-cover border-4 border-emerald-200 dark:border-emerald-700 shadow-lg">
                    <?php else: ?>
                        <div class="w-24 h-24 md:w-32 md:h-32 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-4xl border-4 border-emerald-200 dark:border-emerald-700">👤</div>
                    <?php endif; ?>
                </div>

                <!-- Profile Info -->
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                        <h1 class="text-3xl font-bold text-emerald-700 dark:text-emerald-400">@<?= htmlspecialchars($user['username']) ?></h1>
                        <?php
                        // Render role badges from user_roles (or legacy users.role)
                        $displayRoles = array_unique(array_filter($user_roles));
                        if (empty($displayRoles) && !empty($user['role'])) $displayRoles = [$user['role']];

                        $roleMap = [
                            'superadmin' => ['label' => 'Superadmin', 'emoji' => '👑', 'class' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'],
                            'admin' => ['label' => 'Admin', 'emoji' => '🛡️', 'class' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'],
                            'moderator' => ['label' => 'Moderator', 'emoji' => '🔧', 'class' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300'],
                            'mod' => ['label' => 'Moderator', 'emoji' => '🔧', 'class' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300'],
                            'editor' => ['label' => 'Editor', 'emoji' => '✏️', 'class' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'],
                            'artist' => ['label' => 'Artist', 'emoji' => '🎨', 'class' => 'bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-300'],
                            'author' => ['label' => 'Author', 'emoji' => '✍️', 'class' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'],
                            'contributor' => ['label' => 'Contributor', 'emoji' => '🤝', 'class' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300'],
                            'user' => ['label' => 'User', 'emoji' => '👤', 'class' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300'],
                        ];

                        foreach ($displayRoles as $r) {
                            $key = strtolower($r);
                            $meta = $roleMap[$key] ?? ['label' => ucfirst($key), 'emoji' => '🏷️', 'class' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300'];
                            echo '<span class="px-3 py-1 ' . $meta['class'] . ' rounded-full text-sm font-bold mr-2">' . $meta['emoji'] . ' ' . htmlspecialchars($meta['label']) . '</span>';
                        }
                        ?>

                        <?php if (isLoggedIn() && hasRole('admin') && !$is_owner): ?>
                            <div class="ml-2 inline-block">
                                <form id="adminRoleForm" class="inline-flex items-center gap-2" onsubmit="return submitRoleChange(event)">
                                    <input type="hidden" name="id" value="<?= (int)$user_id ?>">
                                    <select name="role" id="adminRoleSelect" class="px-2 py-1 border rounded text-sm">
                                        <option value="">-- Set Role --</option>
                                        <option value="user">User</option>
                                        <option value="author">Author</option>
                                        <option value="artist">Artist</option>
                                        <option value="editor">Editor</option>
                                        <option value="moderator">Moderator</option>
                                        <option value="admin">Admin</option>
                                        <option value="superadmin">Superadmin</option>
                                    </select>
                                    <button class="px-2 py-1 bg-emerald-600 text-white rounded text-sm">Update</button>
                                </form>
                                <div id="adminRoleMsg" class="text-xs mt-1"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-600 dark:text-gray-400 mb-4"><?= htmlspecialchars($user['bio'] ?? 'No bio yet') ?></p>
                    
                    <?php if (!empty($user['website'])): ?>
                        <p class="text-sm text-emerald-600 dark:text-emerald-400 mb-4">
                            🔗 <a href="<?= htmlspecialchars($user['website']) ?>" target="_blank" class="hover:underline"><?= htmlspecialchars($user['website']) ?></a>
                        </p>
                    <?php endif; ?>

                    <div class="flex gap-3">
                        <?php if ($is_owner): ?>
                            <a href="<?= site_url('/pages/profile-settings.php') ?>" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors">⚙️ Edit Profile</a>
                        <?php else: ?>
                            <button id="follow-btn" class="px-6 py-2 <?= $following ? 'bg-gray-400 hover:bg-gray-500' : 'bg-emerald-600 hover:bg-emerald-700' ?> text-white rounded-lg font-medium transition-colors" data-user="<?= $user_id ?>">
                                <?= $following ? '✓ Following' : '+ Follow' ?>
                            </button>
                            <button id="block-btn" class="px-6 py-2 <?= $isBlocked ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-600 hover:bg-gray-700' ?> text-white rounded-lg font-medium transition-colors" onclick="toggleBlockUser(<?= $user_id ?>)">
                                <?= $isBlocked ? '✓ Blocked' : '🚫 Block' ?>
                            </button>
                            <button onclick="openReportUserModal(<?= $user_id ?>, '<?= htmlspecialchars($user['username']) ?>')" class="px-6 py-2 border-2 border-red-600 text-red-600 dark:border-red-400 dark:text-red-400 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 font-medium transition-colors">� Report</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-12">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow border border-emerald-200 dark:border-emerald-900 text-center">
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($stats['stories']) ?></p>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Stories</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow border border-emerald-200 dark:border-emerald-900 text-center">
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($stats['followers']) ?></p>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Followers</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow border border-emerald-200 dark:border-emerald-900 text-center">
                <p class="text-2xl font-bold text-yellow-500">⭐ <?= $stats['avg_rating'] > 0 ? $stats['avg_rating'] : '—' ?></p>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Avg Rating</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow border border-emerald-200 dark:border-emerald-900 text-center">
                <p class="text-2xl font-bold text-red-500">💗 <?= number_format($stats['total_likes']) ?></p>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Library Adds</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow border border-emerald-200 dark:border-emerald-900 text-center">
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($stats['views']) ?></p>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Total Views</p>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-emerald-200 dark:border-emerald-900 mb-8 overflow-hidden">
            <div class="flex border-b border-gray-200 dark:border-gray-700">
                <button onclick="switchProfileTab('stories')" id="stories-tab" class="flex-1 px-6 py-4 font-semibold text-emerald-600 border-b-2 border-emerald-600 transition">
                    📚 Stories (<?= count($stories) ?>)
                </button>
                <button onclick="switchProfileTab('proclamations')" id="proclamations-tab" class="flex-1 px-6 py-4 font-semibold text-gray-600 dark:text-gray-400 border-b-2 border-transparent hover:border-gray-300 transition">
                    📣 Proclamations
                </button>
            </div>

            <!-- Stories Tab Content -->
            <div id="stories-content" class="p-6">
                <?php if (empty($stories)): ?>
                    <div class="text-center py-12">
                        <p class="text-gray-600 dark:text-gray-400 mb-4">No stories published yet</p>
                        <?php if ($is_owner): ?>
                            <a href="<?= site_url('/pages/write-story.php') ?>" class="inline-block px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg">Start Writing</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($stories as $story): ?>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-600 hover:shadow-lg transition-shadow">
                                <?php if (!empty($story['cover'])): ?>
                                    <img src="<?= htmlspecialchars($story['cover']) ?>" alt="<?= htmlspecialchars($story['title']) ?>" class="w-full h-40 object-cover">
                                <?php else: ?>
                                    <div class="w-full h-40 bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-3xl">📚</div>
                                <?php endif; ?>
                                <div class="p-4">
                                    <h3 class="font-bold text-lg text-emerald-700 dark:text-emerald-400 mb-1"><?= htmlspecialchars(substr($story['title'], 0, 40)) ?></h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                        📖 <?= $story['chapters'] ?> <?= $story['chapters'] == 1 ? 'Chapter' : 'Chapters' ?> • 👁️ <?= format_number($story['views']) ?> views
                                    </p>
                                    <div class="flex gap-2">
                                        <a href="<?= site_url('/pages/book.php?id=' . $story['id']) ?>" class="flex-1 text-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded">Read</a>
                                        <?php if ($is_owner): ?>
                                            <a href="<?= site_url('/pages/edit-story.php?id=' . $story['id']) ?>" class="flex-1 text-center px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded">Edit</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Proclamations Tab Content -->
            <div id="proclamations-content" class="p-6 hidden">
                <?php if ($is_owner): ?>
                <div class="mb-6">
                    <button id="showProclamationForm" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium">
                        ✍️ Create Proclamation
                    </button>
                </div>
                <div id="proclamationForm" class="mb-6 hidden bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <textarea id="proclamationContent" rows="4" placeholder="What's on your mind?" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white mb-3"></textarea>
                    <div class="flex gap-2">
                        <button id="submitProclamation" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded">Post</button>
                        <button id="cancelProclamation" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded text-gray-700 dark:text-gray-300">Cancel</button>
                    </div>
                    <div id="proclamationMsg" class="text-sm mt-2"></div>
                </div>
                <?php endif; ?>
                
                <div id="proclamationsList">
                    <p class="text-gray-600 dark:text-gray-400 text-center py-8">Loading proclamations...</p>
                </div>
            </div>
        </div>

        <!-- Comments & Reviews History -->
        <?php if (!empty($userComments)): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow border border-emerald-200 dark:border-emerald-900 mt-8">
            <h2 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400 mb-6">💬 Comments & Reviews</h2>
            
            <div class="space-y-4">
                <?php foreach (array_slice($userComments, 0, 10) as $comment): ?>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                On: <a href="<?= $comment['type'] === 'blog' ? site_url('/pages/blog-view.php?id=' . $comment['blog_post_id'] . '&type=blog') : site_url('/pages/blog-view.php?id=' . $comment['announcement_id'] . '&type=announcement') ?>" class="text-blue-600 dark:text-blue-400 hover:underline">
                                    <?= htmlspecialchars(substr($comment[$comment['type'] === 'blog' ? 'post_title' : 'announcement_title'] ?? 'Post', 0, 50)) ?>
                                </a>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-500">
                                <?= date('M d, Y · g:i A', strtotime($comment['created_at'])) ?>
                            </p>
                        </div>
                        <?php if ($is_owner): ?>
                        <div class="flex gap-2">
                            <button onclick="editComment('<?= htmlspecialchars(json_encode($comment)) ?>')" class="px-3 py-1 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded transition">✏️ Edit</button>
                            <button onclick="deleteComment(<?= $comment['id'] ?>, '<?= $comment['type'] ?>')" class="px-3 py-1 text-sm bg-red-600 hover:bg-red-700 text-white rounded transition">🗑️ Delete</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-900 dark:text-gray-200 text-sm leading-relaxed">
                        <?= htmlspecialchars(substr($comment['comment_text'], 0, 200)) ?><?= strlen($comment['comment_text']) > 200 ? '...' : '' ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($userComments) > 10): ?>
            <p class="text-center text-sm text-gray-600 dark:text-gray-400 mt-4">
                Showing 10 of <?= count($userComments) ?> comments
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Footer -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
const isLoggedIn = <?= json_encode($isLoggedIn) ?>;

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

    // Follow button
    const followBtn = document.getElementById('follow-btn');
    if (followBtn) {
        followBtn.addEventListener('click', async function(e) {
            const btn = e.currentTarget;
            const uid = btn.dataset.user;
            try {
                const res = await fetch('<?= site_url('/api/follow.php') ?>', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({user_id: uid})
                });
                const data = await res.json();
                if (data.ok) {
                    btn.classList.toggle('bg-emerald-600');
                    btn.classList.toggle('bg-gray-400');
                    btn.classList.toggle('hover:bg-emerald-700');
                    btn.classList.toggle('hover:bg-gray-500');
                    btn.textContent = data.following ? '✓ Following' : '+ Follow';
                }
            } catch (err) { 
                console.error(err); 
            }
        });
    }
    
    // Proclamations: fetch & owner create handlers
    const profileUserId = <?= (int)$user_id ?>;
    const isOwner = <?= $is_owner ? 'true' : 'false' ?>;
    const annList = document.getElementById('annList');
    const annForm = document.getElementById('annForm');
    const showAnnFormBtn = document.getElementById('showAnnFormBtn');

    async function fetchAnnouncements() {
        if (!annList) return;
        annList.innerHTML = '<p class="text-sm text-gray-600 dark:text-gray-400">Loading proclamations…</p>';
        try {
            const res = await fetch('<?= site_url('/api/proclamations_list.php') ?>?user_id=' + encodeURIComponent(profileUserId));
            const json = await res.json();
            if (json.success) {
                if (!json.proclamations.length) {
                    annList.innerHTML = '<p class="text-sm text-gray-600 dark:text-gray-400">No proclamations yet.</p>';
                    return;
                }
                const html = json.proclamations.map(a => `
                    <div class="border-b border-gray-100 dark:border-gray-700 py-3">
                        <div class="flex items-center justify-between">
                            <strong class="text-emerald-700 dark:text-emerald-300">${escapeHtml(a.title)}</strong>
                            <small class="text-xs text-gray-500">${new Date(a.created_at).toLocaleString()}</small>
                        </div>
                        ${a.summary ? `<p class="text-sm text-gray-600 dark:text-gray-400 mt-1">${escapeHtml(a.summary)}</p>` : ''}
                    </div>
                `).join('');
                annList.innerHTML = html;
            } else {
                annList.innerHTML = '<p class="text-sm text-red-600">Failed to load proclamations.</p>';
            }
        } catch (e) {
            console.error(e);
            annList.innerHTML = '<p class="text-sm text-red-600">Error loading proclamations.</p>';
        }
    }

    function escapeHtml(s) {
        if (!s) return '';
        return String(s).replace(/[&<>"']/g, function (m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[m]; });
    }

    if (showAnnFormBtn && annForm) {
        showAnnFormBtn.addEventListener('click', function(){ annForm.classList.toggle('hidden'); });
        document.getElementById('annCancel')?.addEventListener('click', function(e){ e.preventDefault(); annForm.classList.add('hidden'); });
        document.getElementById('annSubmit')?.addEventListener('click', async function(e){
            e.preventDefault();
            const title = document.getElementById('annTitle').value.trim();
            const summary = document.getElementById('annSummary').value.trim();
            const content = document.getElementById('annContent').value.trim();
            const msg = document.getElementById('annMsg');
            if (!title) { if (msg) msg.textContent = 'Title is required'; return; }
            try {
                const res = await fetch('<?= site_url('/api/proclamations_create.php') ?>', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({title, summary, message: content})
                });
                const j = await res.json();
                if (j.success) {
                    if (msg) { msg.textContent = 'Announcement posted'; msg.classList.remove('text-red-600'); msg.classList.add('text-green-600'); }
                    document.getElementById('annTitle').value = '';
                    document.getElementById('annSummary').value = '';
                    document.getElementById('annContent').value = '';
                    annForm.classList.add('hidden');
                    fetchAnnouncements();
                } else {
                    if (msg) { msg.textContent = j.error || 'Failed to post'; msg.classList.add('text-red-600'); }
                }
            } catch (err) {
                console.error(err);
                if (msg) { msg.textContent = 'Server error'; msg.classList.add('text-red-600'); }
            }
        });
    }

    // Initial load
    fetchAnnouncements();
});
</script>

<!-- Report User Modal -->
<div id="reportUserModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Report User</h3>
        <form id="reportUserForm" onsubmit="submitUserReport(event)">
            <input type="hidden" id="reportUserId" value="">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Category</label>
                <select id="reportCategory" name="category" required onchange="updateReasonDescription()" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-600">
                    <option value="">-- Select Category --</option>
                    <option value="harassment">Harassment or Bullying</option>
                    <option value="inappropriate">Inappropriate Content</option>
                    <option value="spam">Spam or Scam</option>
                    <option value="hate_speech">Hate Speech or Discrimination</option>
                    <option value="impersonation">Impersonation or Fraud</option>
                    <option value="other">Other Violation</option>
                </select>
                <p id="categoryDescription" class="text-xs text-gray-500 dark:text-gray-400 mt-1"></p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Reason</label>
                <input type="text" id="reportReason" name="reason" placeholder="Brief description of the issue" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-600">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Details (Optional)</label>
                <textarea id="reportDetails" name="details" rows="3" placeholder="Provide additional details..." class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-600"></textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition">Submit Report</button>
                <button type="button" onclick="closeReportUserModal()" class="flex-1 px-4 py-2 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 font-medium transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
const profileUserId = <?= $user_id ?>;
const isOwner = <?= $is_owner ? 'true' : 'false' ?>;

// Tab switching
function switchProfileTab(tab) {
    // Hide all tab contents
    document.getElementById('stories-content').classList.add('hidden');
    document.getElementById('proclamations-content').classList.add('hidden');
    
    // Remove active state from all tabs
    document.getElementById('stories-tab').classList.remove('text-emerald-600', 'border-emerald-600');
    document.getElementById('stories-tab').classList.add('text-gray-600', 'dark:text-gray-400', 'border-transparent');
    document.getElementById('proclamations-tab').classList.remove('text-emerald-600', 'border-emerald-600');
    document.getElementById('proclamations-tab').classList.add('text-gray-600', 'dark:text-gray-400', 'border-transparent');
    
    // Show selected tab content
    document.getElementById(tab + '-content').classList.remove('hidden');
    document.getElementById(tab + '-tab').classList.remove('text-gray-600', 'dark:text-gray-400', 'border-transparent');
    document.getElementById(tab + '-tab').classList.add('text-emerald-600', 'border-emerald-600');
    
    // Load proclamations when that tab is selected
    if (tab === 'proclamations') {
        loadProclamations();
    }
}

// Load proclamations for this user
function loadProclamations() {
    const container = document.getElementById('proclamationsList');
    container.innerHTML = '<p class="text-gray-600 dark:text-gray-400 text-center py-8">Loading proclamations...</p>';
    
    fetch('<?= site_url('/api/proclamations/get-user-proclamations.php') ?>?user_id=' + profileUserId)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.proclamations && data.proclamations.length > 0) {
                container.innerHTML = data.proclamations.map(p => `
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4 border border-gray-200 dark:border-gray-600">
                        <div class="flex items-start gap-3">
                            <img src="${p.profile_image || '<?= site_url('/assets/images/default-avatar.png') ?>'}" alt="Avatar" class="w-10 h-10 rounded-full object-cover">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-semibold text-gray-900 dark:text-white">${escapeHtml(p.username)}</span>
                                    <span class="text-xs text-gray-500">${formatDate(p.created_at)}</span>
                                </div>
                                <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">${escapeHtml(p.content)}</p>
                                ${p.images ? `<div class="mt-2"><img src="${p.images}" alt="Image" class="max-w-full rounded-lg max-h-64 object-cover"></div>` : ''}
                                <div class="flex items-center gap-4 mt-3 text-sm text-gray-500">
                                    <button onclick="likeProclamation(${p.id})" class="flex items-center gap-1 hover:text-red-500 transition">
                                        <span>${p.user_liked ? '❤️' : '🤍'}</span>
                                        <span>${p.likes_count || 0} Likes</span>
                                    </button>
                                    <button onclick="toggleReplies(${p.id})" class="flex items-center gap-1 hover:text-blue-500 transition">
                                        💬 <span>${p.replies_count || 0} Replies</span>
                                    </button>
                                </div>
                                <div id="replies-${p.id}" class="hidden mt-3 pl-4 border-l-2 border-gray-200 dark:border-gray-600"></div>
                            </div>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-center py-8">No proclamations yet.</p>';
            }
        })
        .catch(e => {
            console.error('Error loading proclamations:', e);
            container.innerHTML = '<p class="text-red-500 text-center py-8">Error loading proclamations.</p>';
        });
}

function escapeHtml(text) {
    const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
    return String(text || '').replace(/[&<>"']/g, m => map[m]);
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function likeProclamation(id) {
    fetch('<?= site_url('/api/proclamations/like.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({proclamation_id: id})
    }).then(r => r.json()).then(data => {
        if (data.success) loadProclamations();
    }).catch(e => console.error(e));
}

function toggleReplies(id) {
    const container = document.getElementById('replies-' + id);
    if (container.classList.contains('hidden')) {
        container.classList.remove('hidden');
        loadReplies(id);
    } else {
        container.classList.add('hidden');
    }
}

function loadReplies(id) {
    const container = document.getElementById('replies-' + id);
    container.innerHTML = '<p class="text-gray-500 text-sm">Loading...</p>';
    
    fetch('<?= site_url('/api/proclamations/get-replies.php') ?>?proclamation_id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.replies && data.replies.length > 0) {
                container.innerHTML = data.replies.map(r => `
                    <div class="bg-white dark:bg-gray-800 rounded p-3 mb-2">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-semibold text-sm text-gray-900 dark:text-white">${escapeHtml(r.username)}</span>
                            <span class="text-xs text-gray-500">${formatDate(r.created_at)}</span>
                        </div>
                        <p class="text-sm text-gray-700 dark:text-gray-300">${escapeHtml(r.content)}</p>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p class="text-gray-500 text-sm py-2">No replies yet.</p>';
            }
        })
        .catch(e => {
            container.innerHTML = '<p class="text-red-500 text-sm">Error loading replies.</p>';
        });
}

// Proclamation form handling
document.getElementById('showProclamationForm')?.addEventListener('click', function() {
    document.getElementById('proclamationForm').classList.remove('hidden');
    this.classList.add('hidden');
});

document.getElementById('cancelProclamation')?.addEventListener('click', function() {
    document.getElementById('proclamationForm').classList.add('hidden');
    document.getElementById('showProclamationForm').classList.remove('hidden');
    document.getElementById('proclamationContent').value = '';
});

document.getElementById('submitProclamation')?.addEventListener('click', function() {
    const content = document.getElementById('proclamationContent').value.trim();
    const msg = document.getElementById('proclamationMsg');
    
    if (!content) {
        msg.textContent = 'Please enter some content';
        msg.className = 'text-sm mt-2 text-red-500';
        return;
    }
    
    fetch('<?= site_url('/api/proclamations/create.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({content: content})
    }).then(r => r.json()).then(data => {
        if (data.success) {
            msg.textContent = 'Proclamation posted!';
            msg.className = 'text-sm mt-2 text-green-500';
            document.getElementById('proclamationContent').value = '';
            document.getElementById('proclamationForm').classList.add('hidden');
            document.getElementById('showProclamationForm').classList.remove('hidden');
            loadProclamations();
        } else {
            msg.textContent = data.error || 'Error posting';
            msg.className = 'text-sm mt-2 text-red-500';
        }
    }).catch(e => {
        msg.textContent = 'Network error';
        msg.className = 'text-sm mt-2 text-red-500';
    });
});

function toggleBlockUser(userId) {
    fetch('<?= site_url('/api/block-user.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({user_id: userId})
    }).then(r => r.json()).then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Error blocking user');
        }
    }).catch(e => console.error(e));
}

function editComment(commentData) {
    try {
        const comment = JSON.parse(commentData);
        const newText = prompt('Edit your comment:', comment.comment_text);
        if (newText === null) return;
        
        fetch('<?= site_url('/api/blog/edit-comment.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                comment_id: comment.id,
                type: comment.type,
                comment_text: newText
            })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                alert('Comment updated!');
                location.reload();
            } else {
                alert(data.error || 'Error updating comment');
            }
        }).catch(e => {
            console.error(e);
            alert('Error updating comment');
        });
    } catch (e) {
        console.error('Parse error:', e);
    }
}

function deleteComment(commentId, type) {
    if (!confirm('Are you sure you want to delete this comment?')) return;
    
    fetch('<?= site_url('/api/blog/delete-comment.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            comment_id: commentId,
            type: type
        })
    }).then(r => r.json()).then(data => {
        if (data.success) {
            alert('Comment deleted!');
            location.reload();
        } else {
            alert(data.error || 'Error deleting comment');
        }
    }).catch(e => {
        console.error(e);
        alert('Error deleting comment');
    });
}

function openReportUserModal(userId, username) {
    document.getElementById('reportUserId').value = userId;
    document.getElementById('reportUserModal').classList.remove('hidden');
}

function closeReportUserModal() {
    document.getElementById('reportUserModal').classList.add('hidden');
    document.getElementById('reportUserForm').reset();
}

function updateReasonDescription() {
    const category = document.getElementById('reportCategory').value;
    const descriptions = {
        'harassment': 'Report bullying, threats, or abusive behavior',
        'inappropriate': 'Report sexual, violent, or otherwise offensive content',
        'spam': 'Report spam, scams, or fraudulent activity',
        'hate_speech': 'Report hate speech or discriminatory content',
        'impersonation': 'Report fake profiles or identity fraud',
        'other': 'Report other violations of our community guidelines'
    };
    document.getElementById('categoryDescription').textContent = descriptions[category] || '';
}

function submitUserReport(event) {
    event.preventDefault();
    const userId = document.getElementById('reportUserId').value;
    const category = document.getElementById('reportCategory').value;
    const reason = document.getElementById('reportReason').value;
    const details = document.getElementById('reportDetails').value;

    if (!category) {
        alert('Please select a category');
        return;
    }

    fetch('<?= site_url('/api/report-user.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({user_id: userId, category: category, reason: reason, details: details})
    }).then(r => r.json()).then(data => {
        if (data.success) {
            alert('Report submitted successfully. Our team will review it shortly.');
            closeReportUserModal();
        } else {
            alert(data.error || 'Error submitting report');
        }
    }).catch(e => console.error(e));
}

// Close modal when clicking outside
document.getElementById('reportUserModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeReportUserModal();
});
</script>

</body>
</html>

<script>
// Follow button (AJAX)
document.getElementById('follow-btn')?.addEventListener('click', async function(e){
    const btn = e.currentTarget;
    const uid = btn.dataset.user;
    try {
        const res = await fetch(`${window.SITE_URL || ''}/api/follow.php`, {method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({user_id: uid})});
        const data = await res.json();
        if (data.ok) {
            btn.classList.toggle('btn-primary');
            btn.classList.toggle('btn-secondary');
            btn.innerHTML = data.following ? '<i class="fas fa-check"></i> Following' : '<i class="fas fa-user-plus"></i> Follow';
        }
    } catch (err) { console.error(err); }
});
</script>

<script>
async function submitRoleChange(e) {
    e.preventDefault();
    const form = e.target.closest ? e.target.closest('form') : document.getElementById('adminRoleForm');
    if (!form) return false;
    const id = form.querySelector('input[name="id"]').value;
    const role = form.querySelector('select[name="role"]').value;
    const msg = document.getElementById('adminRoleMsg');
    if (!role) { if (msg) msg.textContent = 'Please select a role'; return false; }
    try {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('role', role);
        const res = await fetch('<?= site_url('/admin/user_role_update.php') ?>', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        });
        const text = await res.text();
        if (res.ok) {
            if (msg) { msg.textContent = 'Role updated'; msg.classList.remove('text-red-600'); msg.classList.add('text-green-600'); }
            setTimeout(() => location.reload(), 800);
        } else {
            if (msg) { msg.textContent = text || 'Failed to update'; msg.classList.add('text-red-600'); }
        }
    } catch (err) {
        console.error(err);
        if (msg) { msg.textContent = 'Server error'; msg.classList.add('text-red-600'); }
    }
    return false;
}
</script>
