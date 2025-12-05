<?php
// Centralized header for all pages - Tailwind-based
// Load helpers and auth safely
if (file_exists(__DIR__ . '/functions.php')) {
	require_once __DIR__ . '/functions.php';
}
if (file_exists(__DIR__ . '/auth.php')) {
	require_once __DIR__ . '/auth.php';
}

// Provide minimal fallbacks if helpers are missing (prevents fatal errors when includes fail)
if (!function_exists('asset_url')) {
	function asset_url($path) {
		if (!defined('SITE_URL')) define('SITE_URL', 'http://localhost/scrollnovels');
		return rtrim(SITE_URL, '/') . '/assets/' . ltrim($path, '/');
	}
}
if (!function_exists('site_url')) {
	function site_url($path = '') {
		if (!defined('SITE_URL')) define('SITE_URL', 'http://localhost/scrollnovels');
		if (empty($path)) return rtrim(SITE_URL, '/');
		return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
	}
}

// Ensure Content-Type is set properly before any output
if (!headers_sent()) {
	header('Content-Type: text/html; charset=UTF-8');
}

// Make local variables available for templates
$userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= htmlspecialchars($page_title ?? 'Scroll Novels') ?></title>
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
	<link rel="stylesheet" href="<?= asset_url('css/support.css') ?>">
	<link rel="stylesheet" href="<?= asset_url('css/notifications.css') ?>">
	<script src="<?= asset_url('js/theme.js') ?>" defer></script>
	<?php
	// Fallback links: some dev setups may not resolve asset_url correctly in the browser.
	// Provide absolute-path fallbacks using SITE_URL path so CSS loads even if URL helpers misbehave.
	$definedSite = defined('SITE_URL') ? SITE_URL : 'http://localhost/scrollnovels';
	$sitePath = parse_url($definedSite, PHP_URL_PATH) ?: '';
	$fallbackBase = rtrim($sitePath, '/') . '/assets/css';
	?>
	<link rel="stylesheet" href="<?= $fallbackBase ?>/global.css">
	<link rel="stylesheet" href="<?= $fallbackBase ?>/theme.css">
	<style>
		:root { --transition-base: 200ms ease-in-out; }
		body { transition: background-color var(--transition-base), color var(--transition-base); }
	</style>
	<?php
	// Allow pages to inject additional head content (per-page CSS/JS)
	if (!empty($page_head)) echo $page_head;
	?>
	<!-- Notification Center JS -->
	<script src="<?= asset_url('js/notification-center.js') ?>" defer></script>
</head>
<body class="min-h-screen flex flex-col bg-gradient-to-b from-emerald-50 to-green-100 dark:from-gray-900 dark:to-gray-800 text-emerald-900 dark:text-emerald-50 relative">

<!-- Background Overlay for Readability -->
<div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.35); z-index: -1; pointer-events: none;"></div>

<!-- Header -->
<header class="bg-white dark:bg-gray-900 shadow border-b border-emerald-200 dark:border-emerald-900 sticky top-0 z-50 relative">
	<div class="max-w-7xl mx-auto px-4 py-3 md:py-4 flex items-center justify-between">
		<a href="<?= site_url() ?>" class="flex items-center gap-2 md:gap-3 hover:opacity-80 transition-opacity">
			<div class="text-2xl md:text-3xl">📜</div>
			<h1 class="text-lg md:text-xl font-bold text-emerald-600 dark:text-emerald-400">Scroll Novels</h1>
		</a>
		
		<!-- Desktop Navigation -->
		<nav class="hidden lg:flex flex-wrap gap-2 text-sm font-medium">
			<a href="<?= site_url() ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Home</a>
			<a href="<?= site_url('/pages/browse.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Browse</a>
			<a href="<?= site_url('/pages/webtoon.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Webtoons</a>
			<a href="<?= site_url('/pages/fanfic.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Fanfic</a>
			<a href="<?= site_url('/pages/rankings.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Rankings</a>
			<a href="<?= site_url('/pages/competitions.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Competitions</a>
			<a href="<?= site_url('/pages/blog.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Blog</a>
			<a href="<?= site_url('/pages/community.php') ?>" class="px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50">Community</a>
		</nav>
		
		<div class="flex items-center gap-2 md:gap-4">
			<!-- Notification Bell -->
			<?php if ($isLoggedIn): ?>
			<div class="relative" id="notificationBell">
				<button onclick="toggleNotificationDropdown()" class="flex items-center gap-2 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 px-2 md:px-3 py-2 rounded-lg transition-colors relative">
					<span class="text-lg md:text-xl">🔔</span>
					<span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full text-xs w-5 h-5 flex items-center justify-center font-bold hidden">0</span>
				</button>
				<!-- Notification Dropdown -->
				<div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 md:w-96 bg-white dark:bg-gray-800 rounded-lg shadow-2xl border border-emerald-200 dark:border-emerald-900 z-50 max-h-96 overflow-hidden flex flex-col">
					<div class="p-3 md:p-4 border-b border-emerald-200 dark:border-emerald-900 flex justify-between items-center">
						<h3 class="font-bold text-emerald-700 dark:text-emerald-400 text-sm md:text-base">Notifications</h3>
						<button onclick="markAllNotificationsRead()" class="text-xs px-2 py-1 hover:bg-emerald-100 dark:hover:bg-emerald-900/30 rounded">Mark all read</button>
					</div>
					<div id="notificationList" class="overflow-y-auto flex-1">
						<div class="p-4 text-center text-gray-500 dark:text-gray-400">Loading...</div>
					</div>
					<div class="p-2 md:p-3 border-t border-emerald-200 dark:border-emerald-900 text-center">
						<a href="<?= site_url('/pages/notifications.php') ?>" class="text-sm text-emerald-600 dark:text-emerald-400 hover:underline">View all notifications</a>
					</div>
				</div>
			</div>
			<?php endif; ?>
			
			<!-- Theme Toggle -->
			<button onclick="toggleTheme()" class="flex items-center gap-2 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 px-2 md:px-3 py-2 rounded-lg transition-colors">
				<span class="dark:hidden text-lg md:text-xl">☀️</span>
				<span class="hidden dark:block text-lg md:text-xl">🌙</span>
			</button>
			
			<!-- Mobile Menu Button -->
			<button id="mobileMenuBtn" class="lg:hidden flex items-center justify-center w-10 h-10 rounded-lg hover:bg-emerald-100 dark:hover:bg-emerald-900/50 transition-colors">
				<span class="text-xl">☰</span>
			</button>
			
			<!-- User Menu Button (hidden on small mobile) -->
			<button id="sidebarToggle" class="hidden sm:flex items-center gap-2 px-2 md:px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition-colors text-sm font-medium">
				👤 <span class="hidden md:inline"><?= htmlspecialchars(substr($userName, 0, 15)) ?: 'User' ?></span>
			</button>
		</div>
	</div>
	
	<!-- Mobile Navigation Menu -->
	<div id="mobileMenu" class="hidden lg:hidden bg-white dark:bg-gray-900 border-t border-emerald-200 dark:border-emerald-900">
		<nav class="px-4 py-3 space-y-1">
			<a href="<?= site_url() ?>" class="block px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-sm font-medium">🏠 Home</a>
			<a href="<?= site_url('/pages/browse.php') ?>" class="block px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-sm font-medium">📚 Browse</a>
			<a href="<?= site_url('/pages/webtoon.php') ?>" class="block px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-sm font-medium">🎨 Webtoons</a>
			<a href="<?= site_url('/pages/fanfic.php') ?>" class="block px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-sm font-medium">✍️ Fanfic</a>
			<a href="<?= site_url('/pages/rankings.php') ?>" class="block px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-sm font-medium">🏆 Rankings</a>
			<a href="<?= site_url('/pages/competitions.php') ?>" class="block px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-sm font-medium">🎯 Competitions</a>
			<a href="<?= site_url('/pages/blog.php') ?>" class="block px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-sm font-medium">📰 Blog</a>
			<a href="<?= site_url('/pages/community.php') ?>" class="block px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-sm font-medium">💬 Community</a>
			<?php if ($isLoggedIn): ?>
			<div class="border-t border-emerald-200 dark:border-emerald-700 pt-2 mt-2">
				<a href="<?= site_url('/pages/profile.php') ?>" class="block px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-sm font-medium">👤 Profile</a>
				<a href="<?= site_url('/pages/dashboard.php') ?>" class="block px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-sm font-medium">📊 Dashboard</a>
				<a href="<?= site_url('/pages/settings.php') ?>" class="block px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-sm font-medium">⚙️ Settings</a>
				<a href="<?= site_url('/pages/logout.php') ?>" class="block px-3 py-2 rounded-md hover:bg-red-100 dark:hover:bg-red-900/20 text-red-600 text-sm font-medium">🚪 Logout</a>
			</div>
			<?php else: ?>
			<div class="border-t border-emerald-200 dark:border-emerald-700 pt-2 mt-2">
				<a href="<?= site_url('/pages/login.php') ?>" class="block px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-sm font-medium">🔐 Login</a>
				<a href="<?= site_url('/pages/register.php') ?>" class="block px-3 py-2 rounded-md hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-sm font-medium">✍️ Register</a>
			</div>
			<?php endif; ?>
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
	<div class="p-4 space-y-3 text-sm overflow-y-auto flex-1">
		<?php if ($isLoggedIn): ?>
			<a href="<?= site_url('/pages/profile.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">👤 Profile</a>
			<a href="<?= site_url('/pages/achievements.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">🏆 Achievements</a>
			<a href="<?= site_url('/pages/points-dashboard.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">⭐ Points & Rewards</a>
			<a href="<?= site_url('/pages/chat.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">💬 Chat</a>
			<a href="<?= site_url('/pages/dashboard.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">📊 Dashboard</a>
			<a href="<?= site_url('/pages/reading-list.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">📖 My Library</a>
			<a href="<?= site_url('/pages/community.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">💬 Communities</a>
			<a href="<?= site_url('/pages/settings.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">⚙️ Settings</a>
			<a href="<?= site_url('/pages/support-settings.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">💝 Support Links</a>
			<a href="<?= site_url('/pages/blocked-users.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">🚫 Blocked Users</a>
			<hr class="my-2 border-emerald-200">
			<div class="px-3 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400">Create</div>
			<a href="<?= site_url('/pages/write-story.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700 font-semibold text-emerald-700 dark:text-emerald-300">✍️ Write New Story</a>
			<a href="<?= site_url('/pages/proclamations.php') ?>" class="block px-3 py-2 rounded-lg hover:bg-emerald-100 dark:hover:bg-gray-700">📣 Proclamations</a>
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

<!-- Expose SITE_URL -->
<script>window.SITE_URL = '<?= rtrim(defined('SITE_URL') ? SITE_URL : 'http://localhost/scrollnovels', '/') ?>';</script>

<!-- Ensure main content is not hidden under sticky header on smaller viewports -->
<script>
document.addEventListener('DOMContentLoaded', function(){
	try {
		function adjustMainPadding(){
			var hdr = document.querySelector('header');
			var main = document.querySelector('main');
			if(hdr && main){
				var h = hdr.offsetHeight || 64;
				main.style.paddingTop = h + 'px';
			}
		}
		adjustMainPadding();
		window.addEventListener('resize', adjustMainPadding);
	} catch(e){}
});
</script>

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

<!-- Theme Toggle JS -->
<script src="<?= asset_url('js/theme.js') ?>"></script>

<!-- Admin Login Functions -->
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

// Close modal when clicking outside
document.getElementById('adminLoginModal')?.addEventListener('click', function(e) {
	if (e.target === this) closeAdminLoginModal();
});
</script>

<!-- Global sidebar toggle (works on all pages) -->
<script>
document.addEventListener('DOMContentLoaded', function(){
	const sidebar = document.getElementById('sidebar');
	const sidebarToggle = document.getElementById('sidebarToggle');
	const closeSidebarBtn = document.getElementById('closeSidebar');
	const overlay = document.getElementById('sidebarOverlay');
	const mobileMenuBtn = document.getElementById('mobileMenuBtn');
	const mobileMenu = document.getElementById('mobileMenu');
	
	// Mobile menu toggle
	if (mobileMenuBtn && mobileMenu) {
		mobileMenuBtn.addEventListener('click', function(e) {
			e.preventDefault();
			mobileMenu.classList.toggle('hidden');
			// Change icon
			this.querySelector('span').textContent = mobileMenu.classList.contains('hidden') ? '☰' : '✕';
		});
	}
	
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
			// If not logged in, many pages already handle redirect; just open for now
			openSidebar();
		});
	}
	if(closeSidebarBtn) closeSidebarBtn.addEventListener('click', closeSidebar);
	if(overlay) overlay.addEventListener('click', closeSidebar);
});
</script>

<!-- Main Content -->
<main class="flex-1">

