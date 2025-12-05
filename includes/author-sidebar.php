<?php
// includes/author-sidebar.php - Author-specific sidebar navigation
if (!defined('SITE_URL')) {
    include __DIR__ . '/../config/config.php';
}
?>
<aside class="site-sidebar author-sidebar">
    <div class="sidebar-inner">
        <!-- Profile Quick Access -->
        <div class="bg-gradient-to-r from-emerald-100 to-green-100 dark:from-emerald-900/30 dark:to-green-900/30 p-4 rounded-lg mb-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-12 h-12 bg-emerald-500 rounded-full flex items-center justify-center text-white text-lg">✍️</div>
                <div>
                    <p class="font-bold text-emerald-700 dark:text-emerald-300">Author Mode</p>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400">Manage your stories</p>
                </div>
            </div>
        </div>

        <!-- Writing Tools -->
        <nav class="sidebar-section">
            <h4 class="sidebar-heading">📝 Writing</h4>
            <ul class="sidebar-list">
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/write-story.php">✨ Create Story</a></li>
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/write-chapter.php">📖 Add Chapter</a></li>
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/dashboard.php?tab=stories">📚 My Stories</a></li>
            </ul>
        </nav>

        <!-- Analytics & Stats -->
        <nav class="sidebar-section">
            <h4 class="sidebar-heading">📊 Analytics</h4>
            <ul class="sidebar-list">
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/analytics.php">📈 Dashboard</a></li>
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/dashboard.php?tab=stats">📋 Statistics</a></li>
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/dashboard.php?tab=reviews">⭐ Reviews</a></li>
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/dashboard.php?tab=earnings">💰 Earnings</a></li>
            </ul>
        </nav>

        <!-- Community & Engagement -->
        <nav class="sidebar-section">
            <h4 class="sidebar-heading">👥 Community</h4>
            <ul class="sidebar-list">
				<!-- Removed Competitions per request -->
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/artist.php">🎨 Artists</a></li>
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/community.php">👥 Community</a></li>
            </ul>
        </nav>

        <!-- Collaborations & Tools -->
        <nav class="sidebar-section">
            <h4 class="sidebar-heading">🤝 Collaborate</h4>
            <ul class="sidebar-list">
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/editor.php">✏️ Find Editors</a></li>
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/chat.php">💬 Messages</a></li>
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/contracts.php">📜 Contracts</a></li>
            </ul>
        </nav>

        <!-- Management -->
        <nav class="sidebar-section">
            <h4 class="sidebar-heading">⚙️ Manage</h4>
            <ul class="sidebar-list">
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/profile.php">👤 Profile</a></li>
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/settings.php">🔧 Settings</a></li>
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/donate.php">💝 Donations</a></li>
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/withdraw.php">💳 Withdraw</a></li>
            </ul>
        </nav>

        <!-- Support -->
        <nav class="sidebar-section">
            <h4 class="sidebar-heading">📞 Support</h4>
            <ul class="sidebar-list">
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/help.php">❓ Help Center</a></li>
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/contact.php">📧 Contact</a></li>
                <li><a href="<?= rtrim(SITE_URL, '/') ?>/pages/logout.php">🚪 Logout</a></li>
            </ul>
        </nav>
    </div>
</aside>

<style>
.author-sidebar {
    background: linear-gradient(180deg, #f0fdf4 0%, #f8fafc 100%);
}

.sidebar-heading {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.sidebar-list a {
    position: relative;
    padding-left: 25px;
}

.sidebar-list a:before {
    content: '';
    position: absolute;
    left: 0;
    width: 3px;
    height: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    transition: height 0.3s;
}

.sidebar-list a:hover:before {
    height: 100%;
}
</style>