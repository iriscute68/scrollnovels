<?php
// index.php - Home page with all logic here to avoid include issues
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (session_status() === PHP_SESSION_NONE) session_start();

// Load config and DB
require_once __DIR__ . '/config.php';
@include_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);

// Set current page for navigation highlighting
$currentPage = 'home';
$page_title = 'Home - Scroll Novels';

// Load book card component
if (file_exists(__DIR__ . '/includes/components/book-card.php')) {
    require_once __DIR__ . '/includes/components/book-card.php';
}

// Queries for homepage content
try {
    $featured = $pdo->query(
        "SELECT s.id, s.title, s.slug, COALESCE(s.cover_image, s.cover, '') as cover, s.synopsis as description, u.username as author_name, s.views, "
        . "COALESCE(ROUND((SELECT AVG(rating) FROM reviews r WHERE r.story_id = s.id), 1), 0) as rating "
        . "FROM stories s LEFT JOIN users u ON s.author_id = u.id "
        . "ORDER BY s.views DESC LIMIT 8"
    )->fetchAll();
} catch (Exception $e) {
    $featured = [];
}

try {
    $trending = $pdo->query(
        "SELECT s.id, s.title, s.slug, COALESCE(s.cover_image, s.cover, '') as cover, s.synopsis as description, u.username as author_name, s.views, "
        . "COALESCE(ROUND((SELECT AVG(rating) FROM reviews r WHERE r.story_id = s.id), 1), 0) as rating "
        . "FROM stories s LEFT JOIN users u ON s.author_id = u.id "
        . "ORDER BY s.updated_at DESC LIMIT 8"
    )->fetchAll();
} catch (Exception $e) {
    $trending = [];
}

try {
    $new = $pdo->query(
        "SELECT s.id, s.title, s.slug, COALESCE(s.cover_image, s.cover, '') as cover, s.synopsis as description, u.username as author_name, s.views, "
        . "COALESCE(ROUND((SELECT AVG(rating) FROM reviews r WHERE r.story_id = s.id), 1), 0) as rating "
        . "FROM stories s LEFT JOIN users u ON s.author_id = u.id "
        . "ORDER BY s.created_at DESC LIMIT 8"
    )->fetchAll();
} catch (Exception $e) {
    $new = [];
}

// Query sponsored stories from active ads
$sponsored = [];
try {
    $sponsored = $pdo->query(
        "SELECT s.id, s.title, s.slug, COALESCE(s.cover_image, s.cover, '') as cover, s.synopsis as description, u.username as author_name, s.views, "
        . "COALESCE(ROUND((SELECT AVG(rating) FROM reviews r WHERE r.story_id = s.id), 1), 0) as rating, "
        . "a.id as ad_id "
        . "FROM ads a "
        . "INNER JOIN stories s ON a.story_id = s.id "
        . "LEFT JOIN users u ON s.author_id = u.id "
        . "WHERE a.status = 'active' AND a.story_id IS NOT NULL "
        . "ORDER BY a.budget DESC, a.created_at DESC LIMIT 8"
    )->fetchAll();
    
    // Track impressions for displayed ads
    if (!empty($sponsored)) {
        $adIds = array_column($sponsored, 'ad_id');
        if (!empty($adIds)) {
            $placeholders = implode(',', array_fill(0, count($adIds), '?'));
            $pdo->prepare("UPDATE ads SET impressions = impressions + 1 WHERE id IN ($placeholders)")->execute($adIds);
        }
    }
} catch (Exception $e) {
    $sponsored = [];
}

$blog_posts = [];
try {
    // Get announcements for homepage carousel
    $ann_stmt = $pdo->prepare("
        SELECT id, title, content as excerpt, created_at 
        FROM announcements 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $ann_stmt->execute();
    $blog_posts = $ann_stmt->fetchAll();
} catch (Exception $e) {
    $blog_posts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = { darkMode: 'class' };
    </script>
    <!-- Apply saved theme as early as possible to avoid FOUC -->
    <script>
        (function(){
            try {
                var t = localStorage.getItem('scroll-novels-theme');
                if (t === 'dark') document.documentElement.classList.add('dark');
            } catch(e){}
        })();
    </script>
    <link rel="stylesheet" href="<?= asset_url('css/global.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/theme.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/interaction-fix.css') ?>">
    <script src="<?= asset_url('js/theme.js') ?>" defer></script>
    <script src="<?= asset_url('js/interaction-fix.js') ?>" defer></script>
    <style>
        :root { --transition-base: 200ms ease-in-out; }
        body { transition: background-color var(--transition-base), color var(--transition-base); }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-gradient-to-b from-emerald-50 to-green-100 dark:from-gray-900 dark:to-gray-800 text-emerald-900 dark:text-emerald-50">

<!-- Header -->
<header class="bg-white dark:bg-gray-900 shadow border-b border-emerald-200 dark:border-emerald-900 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
        <a href="<?= site_url() ?>" class="flex items-center gap-3 hover:opacity-80 transition-opacity">
            <div class="text-3xl">📜</div>
            <h1 class="text-xl font-bold text-emerald-600 dark:text-emerald-400">Scroll Novels</h1>
        </a>
        
        <!-- Mobile hamburger menu button -->
        <button id="mobileMenuBtn" class="md:hidden flex items-center justify-center w-10 h-10 rounded-lg hover:bg-emerald-100 dark:hover:bg-emerald-900/50 transition-colors">
            <svg class="w-6 h-6 text-emerald-700 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
        
        <nav class="hidden md:flex flex-wrap gap-3 text-sm font-medium">
            <a href="<?= site_url() ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Home</a>
            <a href="<?= site_url('/pages/browse.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Browse</a>
            <a href="<?= site_url('/pages/webtoon.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Webtoons</a>
            <a href="<?= site_url('/pages/fanfic.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Fanfic</a>
            <a href="<?= site_url('/pages/rankings.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Rankings</a>
            <a href="<?= site_url('/pages/competitions.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Competitions</a>
            <a href="<?= site_url('/pages/blog.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Blog</a>
            <a href="<?= site_url('/pages/website-rules.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Rules</a>
            <a href="<?= site_url('/pages/community.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Community</a>
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
    
    <!-- Mobile Navigation Menu (hidden by default) -->
    <div id="mobileNavMenu" class="hidden md:hidden bg-white dark:bg-gray-800 border-t border-emerald-200 dark:border-emerald-700 shadow-lg">
        <nav class="flex flex-col p-4 space-y-1 text-sm font-medium">
            <a href="<?= site_url() ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Home</a>
            <a href="<?= site_url('/pages/browse.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Browse</a>
            <a href="<?= site_url('/pages/webtoon.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Webtoons</a>
            <a href="<?= site_url('/pages/fanfic.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Fanfic</a>
            <a href="<?= site_url('/pages/rankings.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Rankings</a>
            <a href="<?= site_url('/pages/competitions.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Competitions</a>
            <a href="<?= site_url('/pages/blog.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Blog</a>
            <a href="<?= site_url('/pages/website-rules.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Rules</a>
            <a href="<?= site_url('/pages/community.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Community</a>
        </nav>
    </div>
</header>

<!-- Sidebar Overlay -->
<div id="sidebarOverlay" class="hidden fixed inset-0 bg-black/50 z-40"></div>

<!-- Sidebar -->
<aside id="sidebar" class="fixed right-0 top-0 h-screen w-64 bg-white dark:bg-gray-800 shadow-lg transform translate-x-full transition-transform z-50 flex flex-col">
    <div class="p-4 border-b border-emerald-200 dark:border-emerald-900 flex justify-between items-center flex-shrink-0">
        <h3 class="text-lg font-bold text-emerald-600">Menu</h3>
        <button id="closeSidebar" class="text-2xl">&times;</button>
    </div>
    <nav class="p-4 space-y-3 text-sm overflow-y-auto flex-1">
        <?php if ($isLoggedIn): ?>
            <a href="<?= site_url('/pages/profile.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">👤 Profile</a>
            <a href="<?= site_url('/pages/achievements.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">🏆 Achievements</a>
            <a href="<?= site_url('/pages/points-dashboard.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">⭐ Points & Rewards</a>
            <a href="<?= site_url('/pages/chat.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">💬 Chat</a>
            <a href="<?= site_url('/pages/dashboard.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">📊 Dashboard</a>
            <a href="<?= site_url('/pages/reading-list.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">📖 My Library</a>
            <a href="<?= site_url('/pages/community.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">💬 Communities</a>
            <a href="<?= site_url('/pages/settings.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">⚙️ Settings</a>
            <a href="<?= site_url('/pages/blocked-users.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">🚫 Blocked Users</a>
            <hr class="my-2 border-emerald-200">
            <div class="px-3 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400">Opportunities</div>
            <a href="<?= site_url('/pages/become-verified.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">⭐ Get Verified</a>
            <a href="<?= site_url('/pages/competitions.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">🎯 Competitions</a>
            <a href="https://vgen.co/StudioSoulo" target="_blank" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">🎨 Find Artist</a>
            <a href="<?= site_url('/pages/guides.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">📚 Guides</a>
            <hr class="my-2 border-emerald-200">
            <div class="px-3 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400">Support</div>
            <a href="<?= site_url('/pages/support.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">🎫 Support Tickets</a>
            <a href="<?= site_url('/pages/donate.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">❤️ Donate</a>
            <?php 
            $userRoles = $_SESSION['roles'] ?? [];
            if (is_string($userRoles)) {
                $userRoles = json_decode($userRoles, true) ?: [];
            }
            if (in_array('admin', $userRoles) || in_array('mod', $userRoles) || in_array('moderator', $userRoles)):
            ?>
            <hr class="my-2 border-red-300">
            <div class="px-3 py-2 text-xs font-semibold text-red-600 dark:text-red-400">Admin</div>
            <a href="<?= site_url('/admin/dashboard.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400">🔒 Admin Dashboard</a>
            <?php endif; ?>
            <hr class="my-2 border-emerald-200">
            <a href="<?= site_url('/pages/logout.php') ?>" class="block px-3 py-2 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">🚪 Logout</a>
        <?php else: ?>
            <a href="<?= site_url('/pages/login.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">🔐 Login</a>
            <a href="<?= site_url('/pages/register.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">✍️ Register</a>
            <hr class="my-2 border-emerald-200">
            <button onclick="openAdminLoginModal()" class="w-full text-left px-3 py-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 font-medium">🔒 Admin Login</button>
        <?php endif; ?>
    </nav>
</aside>

<script>window.SITE_URL = '<?= rtrim(SITE_URL, '/') ?>';</script>

<!-- Admin Login Modal -->
<div id="adminLoginModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-8">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">🔒 Admin Login</h3>
            <button onclick="closeAdminLoginModal()" class="text-2xl text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">×</button>
        </div>
        <form id="adminLoginForm" class="space-y-4" autocomplete="off">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Admin Email</label>
                <input type="email" id="adminEmail" placeholder="Enter admin email" autocomplete="off" required
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                <input type="password" id="adminPassword" placeholder="Enter password" autocomplete="off" required
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-500">
            </div>
            <div id="adminLoginMessage" class="hidden p-3 rounded-lg text-sm"></div>
            <button type="submit" class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors">
                Login to Admin Panel
            </button>
        </form>
        <p class="text-xs text-gray-500 dark:text-gray-400 text-center mt-4">
            This section is for administrators only
        </p>
    </div>
</div>

<script>
function openAdminLoginModal() {
    document.getElementById('adminLoginModal').classList.remove('hidden');
    document.getElementById('adminEmail').focus();
}

function closeAdminLoginModal() {
    document.getElementById('adminLoginModal').classList.add('hidden');
    document.getElementById('adminLoginForm').reset();
    document.getElementById('adminLoginMessage').classList.add('hidden');
}

document.getElementById('adminLoginForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const email = document.getElementById('adminEmail').value;
    const password = document.getElementById('adminPassword').value;
    const messageEl = document.getElementById('adminLoginMessage');
    try {
        const response = await fetch('<?= site_url('/api/admin-login.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password }),
            credentials: 'same-origin'
        });
        const data = await response.json();
        if (data.success) {
            messageEl.textContent = '✅ Login successful! Redirecting...';
            messageEl.className = 'p-3 rounded-lg text-sm bg-emerald-100 dark:bg-emerald-900/30 border border-emerald-300 dark:border-emerald-700 text-emerald-800 dark:text-emerald-300';
            messageEl.classList.remove('hidden');
            setTimeout(() => {
                window.location.href = '<?= site_url('/admin/dashboard.php') ?>';
            }, 1500);
        } else {
            messageEl.textContent = '❌ ' + (data.error || 'Login failed');
            messageEl.className = 'p-3 rounded-lg text-sm bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-800 dark:text-red-300';
            messageEl.classList.remove('hidden');
        }
    } catch (err) {
        messageEl.textContent = '❌ Error: ' + err.message;
        messageEl.className = 'p-3 rounded-lg text-sm bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-800 dark:text-red-300';
        messageEl.classList.remove('hidden');
    }
});

document.getElementById('adminLoginModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeAdminLoginModal();
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const closeSidebarBtn = document.getElementById('closeSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    function openSidebar(){
        if(!sidebar) return;
        sidebar.classList.remove('translate-x-full');
        sidebar.classList.add('translate-x-0');
        overlay && overlay.classList.remove('hidden');
    }
    function closeSidebar(){
        if(!sidebar) return;
        sidebar.classList.add('translate-x-full');
        sidebar.classList.remove('translate-x-0');
        overlay && overlay.classList.add('hidden');
    }
    if(sidebarToggle){
        sidebarToggle.addEventListener('click', function(e){
            e.preventDefault();
            openSidebar();
        });
    }
    if(closeSidebarBtn) closeSidebarBtn.addEventListener('click', closeSidebar);
    if(overlay) overlay.addEventListener('click', closeSidebar);
});
</script>

<!-- Main Content -->
<main class="flex-1">

    <!-- ANNOUNCEMENTS SECTION -->
    <section class="bg-gradient-to-r from-emerald-100 to-green-100 dark:from-emerald-900/30 dark:to-green-900/30 py-6 border-b border-emerald-200 dark:border-emerald-900">
        <div class="max-w-7xl mx-auto px-4">
            <?php if (!empty($blog_posts)): ?>
                <div class="space-y-4">
                    <h2 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">📢 Latest Announcements</h2>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden border border-emerald-200 dark:border-emerald-900">
                        <div class="relative">
                            <div id="announcementCarousel" class="relative">
                                <?php foreach ($blog_posts as $idx => $post): ?>
                                    <div class="announcement-slide <?= $idx === 0 ? 'block' : 'hidden' ?> p-6 animate-fadeIn" data-slide="<?= $idx ?>">
                                        <a href="<?= site_url('/pages/blog-view.php?id=' . $post['id'] . '&type=announcement') ?>" class="block hover:bg-emerald-50 dark:hover:bg-gray-700 rounded-lg transition-colors">
                                            <div class="flex items-start gap-4">
                                                <div class="text-4xl">📣</div>
                                                <div class="flex-1">
                                                    <h3 class="font-bold text-emerald-700 dark:text-emerald-300 mb-2 text-lg hover:underline"><?= htmlspecialchars($post['title']) ?></h3>
                                                    <p class="text-gray-600 dark:text-gray-400 mb-4"><?= htmlspecialchars(strip_tags($post['excerpt'] ?? '')) ?></p>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        📅 <?= date('M d, Y', strtotime($post['created_at'])) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="absolute inset-0 flex items-center justify-between px-4 pointer-events-none">
                                <button id="prevAnnouncement" class="pointer-events-auto bg-emerald-600 hover:bg-emerald-700 text-white rounded-full p-2 transition-colors">
                                    <span class="text-xl">‹</span>
                                </button>
                                <button id="nextAnnouncement" class="pointer-events-auto bg-emerald-600 hover:bg-emerald-700 text-white rounded-full p-2 transition-colors">
                                    <span class="text-xl">›</span>
                                </button>
                            </div>
                        </div>
                        <div class="bg-emerald-50 dark:bg-gray-700 px-6 py-3 flex justify-center gap-2">
                            <?php foreach ($blog_posts as $idx => $post): ?>
                                <button class="announcement-dot w-2 h-2 rounded-full <?= $idx === 0 ? 'bg-emerald-600' : 'bg-gray-400' ?> transition-colors" data-slide="<?= $idx ?>"></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <a href="<?= site_url('/pages/blog.php') ?>" class="text-emerald-600 hover:text-emerald-700 text-sm font-medium">View all announcements →</a>
                </div>
                <style>
                    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
                    .animate-fadeIn { animation: fadeIn 0.3s ease-in; }
                </style>
                <script>
                    let currentSlide = 0;
                    const slides = document.querySelectorAll('.announcement-slide');
                    const dots = document.querySelectorAll('.announcement-dot');
                    const totalSlides = slides.length;
                    function showSlide(n) {
                        if (n >= totalSlides) currentSlide = 0;
                        if (n < 0) currentSlide = totalSlides - 1;
                        slides.forEach(slide => slide.classList.add('hidden'));
                        slides.forEach(slide => slide.classList.remove('block'));
                        dots.forEach(dot => dot.classList.add('bg-gray-400'));
                        dots.forEach(dot => dot.classList.remove('bg-emerald-600'));
                        slides[currentSlide].classList.remove('hidden');
                        slides[currentSlide].classList.add('block');
                        dots[currentSlide].classList.remove('bg-gray-400');
                        dots[currentSlide].classList.add('bg-emerald-600');
                    }
                    document.getElementById('nextAnnouncement')?.addEventListener('click', () => { currentSlide++; showSlide(currentSlide); });
                    document.getElementById('prevAnnouncement')?.addEventListener('click', () => { currentSlide--; showSlide(currentSlide); });
                    document.querySelectorAll('.announcement-dot').forEach((dot, idx) => { dot.addEventListener('click', () => { currentSlide = idx; showSlide(currentSlide); }); });
                </script>
            <?php else: ?>
                <div class="bg-white dark:bg-gray-800 border border-emerald-200 dark:border-emerald-900 rounded-xl shadow p-4">
                    <h2 class="text-lg font-bold text-emerald-700 dark:text-emerald-400 mb-2">📢 Announcements</h2>
                    <ul class="text-sm text-emerald-800 dark:text-emerald-200 list-disc pl-5 space-y-1">
                        <li>Welcome to Scroll Novels!</li>
                        <li>Author promotion week - buy ads for your story!</li>
                        <li>Artist and Editor applications now open.</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- MAIN CONTENT -->
    <div class="max-w-7xl mx-auto px-4 py-12 space-y-20">

        <!-- FEATURED STORIES -->
        <section>
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">🌟 Featured Stories</h3>
                <a href="<?= site_url('/pages/browse.php?sort=featured') ?>" class="text-sm text-emerald-700 dark:text-emerald-400 hover:underline">View All</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-4">
                <?php foreach (array_slice($featured, 0, 8) as $f): ?>
                    <div class="relative">
                        <?php render_book_card($f, false, true); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="flex justify-center mt-8">
                <a href="<?= site_url('/pages/browse.php?sort=featured') ?>" class="inline-flex items-center px-6 py-2 border border-emerald-300 text-sm font-medium rounded-md text-emerald-700 dark:text-emerald-400 bg-white dark:bg-gray-800 hover:bg-emerald-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                    View More Featured Stories
                </a>
            </div>
        </section>

        <!-- TRENDING -->
        <section>
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">🔥 Trending Now</h3>
                <a href="<?= site_url('/pages/browse.php?sort=trending') ?>" class="text-sm text-emerald-700 dark:text-emerald-400 hover:underline">View All</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-4">
                <?php foreach (array_slice($trending, 0, 8) as $f): ?>
                    <div class="relative">
                        <?php render_book_card($f, false, true); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="flex justify-center mt-8">
                <a href="<?= site_url('/pages/browse.php?sort=trending') ?>" class="inline-flex items-center px-6 py-2 border border-emerald-300 text-sm font-medium rounded-md text-emerald-700 dark:text-emerald-400 bg-white dark:bg-gray-800 hover:bg-emerald-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                    View More Trending Stories
                </a>
            </div>
        </section>

        <!-- NEW UPDATES -->
        <section>
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">🌱 New Updates</h3>
                <a href="<?= site_url('/pages/browse.php?sort=new') ?>" class="text-sm text-emerald-700 dark:text-emerald-400 hover:underline">View All</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-4">
                <?php foreach (array_slice($new, 0, 8) as $f): ?>
                    <div class="relative">
                        <?php render_book_card($f, false, true); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="flex justify-center mt-8">
                <a href="<?= site_url('/pages/browse.php?sort=new') ?>" class="inline-flex items-center px-6 py-2 border border-emerald-300 text-sm font-medium rounded-md text-emerald-700 dark:text-emerald-400 bg-white dark:bg-gray-800 hover:bg-emerald-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                    View More New Updates
                </a>
            </div>
        </section>

        <!-- SPONSORED -->
        <?php if (!empty($sponsored)): ?>
        <section>
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">📢 Sponsored Books</h3>
                <a href="<?= site_url('/pages/ads.php') ?>" class="text-sm text-emerald-700 dark:text-emerald-400 hover:underline">Advertise Your Story</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-4">
                <?php foreach ($sponsored as $s): ?>
                    <div class="relative">
                        <div class="absolute top-2 left-2 z-10 bg-yellow-500 text-white text-xs font-bold px-2 py-0.5 rounded">AD</div>
                        <?php render_book_card($s, false, true); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    </div>

</main>

<!-- Footer -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileNavMenu = document.getElementById('mobileNavMenu');
    
    if (mobileMenuBtn && mobileNavMenu) {
        mobileMenuBtn.addEventListener('click', function() {
            mobileNavMenu.classList.toggle('hidden');
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!mobileMenuBtn.contains(e.target) && !mobileNavMenu.contains(e.target)) {
                mobileNavMenu.classList.add('hidden');
            }
        });
    }
});
</script>

</body>
</html>