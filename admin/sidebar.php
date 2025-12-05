<?php
// Minimal admin sidebar to restore navigation when admin/inc/sidebar.php is missing
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<aside class="fixed left-0 top-0 h-full w-64 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 p-4 overflow-auto">
    <div class="mb-6">
        <h3 class="text-xl font-bold text-emerald-600">Admin</h3>
        <p class="text-xs text-gray-500">Control Panel</p>
    </div>
    <nav class="space-y-2 text-sm">
        <a href="<?= site_url('/admin/dashboard.php') ?>" class="block px-3 py-2 rounded hover:bg-emerald-50">Overview</a>
        <a href="<?= site_url('/admin/users.php') ?>" class="block px-3 py-2 rounded hover:bg-emerald-50">Users</a>
        <a href="<?= site_url('/admin/stories.php') ?>" class="block px-3 py-2 rounded hover:bg-emerald-50">Stories</a>
        <a href="<?= site_url('/admin/chapters.php') ?>" class="block px-3 py-2 rounded hover:bg-emerald-50">Chapters</a>
        <a href="<?= site_url('/admin/comments.php') ?>" class="block px-3 py-2 rounded hover:bg-emerald-50">Comments</a>
        <a href="<?= site_url('/admin/tags.php') ?>" class="block px-3 py-2 rounded hover:bg-emerald-50">Tags</a>
        <a href="<?= site_url('/admin/monetization.php') ?>" class="block px-3 py-2 rounded hover:bg-emerald-50">Monetization</a>
        <a href="<?= site_url('/admin/reports.php') ?>" class="block px-3 py-2 rounded hover:bg-emerald-50">Reports</a>
        <a href="<?= site_url('/admin/analytics.php') ?>" class="block px-3 py-2 rounded hover:bg-emerald-50">Analytics</a>
        <!-- Announcements removed from admin sidebar per request -->
        <a href="<?= site_url('/admin/coins.php') ?>" class="block px-3 py-2 rounded hover:bg-emerald-50">Coins</a>
        <a href="<?= site_url('/admin/achievements.php') ?>" class="block px-3 py-2 rounded hover:bg-emerald-50">Achievements</a>
        <a href="<?= site_url('/admin/staff.php') ?>" class="block px-3 py-2 rounded hover:bg-emerald-50">Staff</a>
        <a href="<?= site_url('/admin/settings.php') ?>" class="block px-3 py-2 rounded hover:bg-emerald-50">Settings</a>
        <a href="<?= site_url('/admin/developer.php') ?>" class="block px-3 py-2 rounded hover:bg-emerald-50">Developer</a>
        <a href="<?= site_url('/admin/support.php') ?>" class="block px-3 py-2 rounded hover:bg-emerald-50">Support</a>
    </nav>
</aside>
<?php
// Wrapper to include the admin sidebar UI
if (file_exists(__DIR__ . '/inc/sidebar.php')) {
    require_once __DIR__ . '/inc/sidebar.php';
    return;
}

// Minimal fallback sidebar
?>
<div style="width:220px;background:#111;padding:12px;color:#ddd;position:fixed;left:0;top:0;height:100vh;">
  <h3 style="font-weight:700;margin-bottom:8px;">Admin</h3>
  <nav>
    <a href="dashboard.php" style="display:block;color:#cfd8dc;padding:6px 0;">Overview</a>
    <a href="users.php" style="display:block;color:#cfd8dc;padding:6px 0;">Users</a>
    <a href="stories.php" style="display:block;color:#cfd8dc;padding:6px 0;">Stories</a>
  </nav>
</div>
