<?php
// Simple topbar for admin area
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<div class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-4 py-3">
    <div class="max-w-7xl mx-auto flex items-center justify-between">
        <div class="flex items-center gap-4">
            <h2 class="text-lg font-semibold text-emerald-600">Admin Panel</h2>
        </div>
        <div>
            <span class="text-sm text-gray-600 dark:text-gray-300"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
        </div>
    </div>
</div>
<?php
// Simple topbar used by admin pages that require topbar.php
// Prefer including a centralized file if present
if (file_exists(__DIR__ . '/inc/topbar.php')) {
    require_once __DIR__ . '/inc/topbar.php';
    return;
}

// Minimal topbar fallback
?>
<div style="margin-left:220px;padding:12px 20px;background:#0b0d10;border-bottom:1px solid #1f2937;color:#e6eef6;position:sticky;top:0;z-index:40;">
  <div style="display:flex;align-items:center;justify-content:space-between;max-width:1200px;margin:0 auto;">
    <div><strong>Admin Panel</strong></div>
    <div>
      <a href="/" style="color:#9ae6b4;margin-right:12px;">View site</a>
      <a href="logout.php" style="color:#fca5a5;">Logout</a>
    </div>
  </div>
</div>
