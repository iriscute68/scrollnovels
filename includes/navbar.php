<?php 
if (!defined('SITE_URL')) { 
    require_once __DIR__ . '/../config/config.php'; 
}
if (!function_exists('isApprovedAdmin')) {
    require_once __DIR__ . '/functions.php';
}
require_once __DIR__ . '/auth.php';
?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= rtrim(SITE_URL, '/') ?>">
            <i class="fas fa-book"></i> Scroll Novels
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= rtrim(SITE_URL, '/') ?>">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= rtrim(SITE_URL, '/') ?>/pages/browse.php">Browse</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= rtrim(SITE_URL, '/') ?>/pages/rankings.php">Rankings</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= rtrim(SITE_URL, '/') ?>/pages/blog.php">Blog</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= rtrim(SITE_URL, '/') ?>/pages/community.php">Community</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= rtrim(SITE_URL, '/') ?>/pages/guides.php">📚 Guides</a>
                </li>
                <!-- Theme Toggle Button -->
                <li class="nav-item">
                    <button class="nav-link btn btn-link" id="themeToggleBtn" style="border: none; background: none; cursor: pointer; padding: 0.5rem 1rem;">
                        <i class="fas fa-moon"></i>
                    </button>
                </li>
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span id="notif-badge" class="badge bg-danger" style="display:none;"></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="width:320px;">
                            <div id="notif-preview" style="max-height: 400px; overflow-y: auto;">
                                <li style="text-align: center; padding: 1.5rem; color: #9ca3af;">
                                    <p style="margin: 0;">Loading notifications...</p>
                                </li>
                            </div>
                            <li><hr class="dropdown-divider" style="margin: 0;"></li>
                            <li><a class="dropdown-item" href="<?= rtrim(SITE_URL, '/') ?>/pages/notifications.php" style="text-align: center; font-weight: 600;">See All Notifications</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="min-width: 220px;">
                            <!-- My Account Section -->
                            <li><h6 class="dropdown-header">My Account</h6></li>
                            <li><a class="dropdown-item" href="<?= rtrim(SITE_URL, '/') ?>/pages/profile.php?user=<?= urlencode($_SESSION['username'] ?? '') ?>"><i class="fas fa-user-circle"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="<?= rtrim(SITE_URL, '/') ?>/pages/dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                            <li><a class="dropdown-item" href="<?= rtrim(SITE_URL, '/') ?>/pages/notification.php"><i class="fas fa-bell"></i> Notifications</a></li>
                            <li><a class="dropdown-item" href="<?= rtrim(SITE_URL, '/') ?>/pages/reading-list.php"><i class="fas fa-bookmark"></i> My Library</a></li>
                            <li><a class="dropdown-item" href="<?= rtrim(SITE_URL, '/') ?>/pages/write-story.php"><i class="fas fa-pen"></i> Write Story</a></li>
                            <li><a class="dropdown-item" href="<?= rtrim(SITE_URL, '/') ?>/pages/chat.php"><i class="fas fa-comments"></i> Messages</a></li>
                            <li><a class="dropdown-item" href="<?= rtrim(SITE_URL, '/') ?>/pages/settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                            
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- Opportunities Section -->
                            <li><h6 class="dropdown-header">Opportunities</h6></li>
                            <li><a class="dropdown-item" href="<?= rtrim(SITE_URL, '/') ?>/pages/editor.php"><i class="fas fa-briefcase"></i> Become Editor</a></li>
                            <li><a class="dropdown-item" href="<?= rtrim(SITE_URL, '/') ?>/pages/premium.php"><i class="fas fa-star"></i> Premium</a></li>
                            
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- Admin Section (if applicable) -->
                            <?php if (isApprovedAdmin()): ?>
                                <li><h6 class="dropdown-header">Administration</h6></li>
                                <li><a class="dropdown-item" href="<?= rtrim(SITE_URL, '/') ?>/admin/index.php"><i class="fas fa-cogs"></i> Admin Panel</a></li>
                                <li><a class="dropdown-item" href="<?= rtrim(SITE_URL, '/') ?>/admin/users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                                <li><a class="dropdown-item" href="<?= rtrim(SITE_URL, '/') ?>/admin/stories.php"><i class="fas fa-book"></i> Manage Stories</a></li>
                                <li><a class="dropdown-item" href="<?= rtrim(SITE_URL, '/') ?>/admin/donations.php"><i class="fas fa-heart"></i> Donations</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            
                            <!-- Support Section -->
                            <li><h6 class="dropdown-header">Support</h6></li>
                            <li><a class="dropdown-item" href="<?= rtrim(SITE_URL, '/') ?>/pages/contact.php"><i class="fas fa-envelope"></i> Contact</a></li>
                            <li><a class="dropdown-item" href="<?= rtrim(SITE_URL, '/') ?>/pages/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= rtrim(SITE_URL, '/') ?>/pages/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= rtrim(SITE_URL, '/') ?>/pages/register.php">Sign Up</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<script>
<?php if (isLoggedIn()): ?>
// Load notifications in dropdown
function loadNotificationsPreview() {
    fetch('<?= rtrim(SITE_URL, '/') ?>/api/notifications/get-notifications.php?limit=5').then(r => r.json()).then(data => {
        const badge = document.getElementById('notif-badge');
        const container = document.getElementById('notif-preview');
        
        if (data.success && data.data && data.data.length > 0) {
            badge.textContent = data.total || data.data.length;
            badge.style.display = 'inline-block';
            
            const html = data.data.map(n => `
                <li style="padding: 0.75rem 0.5rem;">
                    <a class="dropdown-item" href="${n.link || '#'}" style="font-size: 0.9rem; padding: 0.5rem 0.75rem; border-radius: 4px; ${n.is_read ? 'opacity: 0.7;' : 'background: #f0fdf4; border-left: 3px solid #10b981;'} display: block;">
                        <strong style="display: block; margin-bottom: 0.25rem;">${n.title || 'Notification'}</strong>
                        <span style="display: block; color: #6b7280; font-size: 0.85rem;">${(n.message || n.type || '').substring(0, 60)}${(n.message || n.type || '').length > 60 ? '...' : ''}</span>
                        <small style="display: block; color: #9ca3af; margin-top: 0.25rem;">${n.time_ago || 'Recently'}</small>
                    </a>
                </li>
            `).join('');
            container.innerHTML = html;
        } else {
            badge.style.display = 'none';
            container.innerHTML = '<li style="text-align: center; padding: 1.5rem; color: #9ca3af;"><p style="margin: 0;">No notifications</p></li>';
        }
    }).catch(e => {
        console.error('Error loading notifications:', e);
        document.getElementById('notif-preview').innerHTML = '<li style="text-align: center; padding: 1rem; color: #ef4444;"><p style="margin: 0;">Error loading</p></li>';
    });
}

loadNotificationsPreview();
setInterval(loadNotificationsPreview, 30000);
<?php endif; ?>
</script>
<script>
// Wire theme toggle to theme.js if available
document.addEventListener('DOMContentLoaded', function(){
    const btn = document.getElementById('themeToggleBtn');
    if (!btn) return;
    // update icon from theme.js state if available
    function updateIcon() {
        try {
            const theme = (window.ScrollNovels && window.ScrollNovels.getTheme) ? window.ScrollNovels.getTheme() : (document.documentElement.getAttribute('data-theme') || 'light');
            btn.innerHTML = theme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        } catch(e) { /* ignore */ }
    }
    btn.addEventListener('click', function(e){
        e.preventDefault();
        try {
            if (window.ScrollNovels && window.ScrollNovels.toggleTheme) {
                window.ScrollNovels.toggleTheme();
            } else {
                // fallback
                const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                const next = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('scroll-novels-theme', next);
            }
            updateIcon();
        } catch (err) { console.error(err); }
    });
    updateIcon();
});
</script>