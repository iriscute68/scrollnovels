<?php
// admin.php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireLogin();

// Check if user is admin - simplified approach since user_roles table may not exist
$isAdmin = false;

// Method 1: Check if user_id = 1 (first user, likely admin)
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1) {
    $isAdmin = true;
}

// Method 2: Check session admin_id (legacy)
if (!$isAdmin && isset($_SESSION['admin_id']) && $_SESSION['admin_id']) {
    $isAdmin = true;
}

// Method 3: Try to check user_roles if table exists
if (!$isAdmin && function_exists('hasRole')) {
    try {
        if (hasRole('admin') || hasRole('superadmin') || hasRole('moderator')) {
            $isAdmin = true;
        }
    } catch (Exception $e) {
        // Table doesn't exist or other error
    }
}

// Method 4: Check admin users list from settings
if (!$isAdmin) {
    try {
        $stmt = $pdo->prepare("SELECT admin_users FROM settings LIMIT 1");
        $stmt->execute();
        $settings = $stmt->fetch();
        if ($settings && $settings['admin_users']) {
            $adminIds = json_decode($settings['admin_users'], true) ?? [];
            if (is_array($adminIds) && in_array($_SESSION['user_id'], $adminIds)) {
                $isAdmin = true;
            }
        }
    } catch (Exception $e) {
        // ignore
    }
}

if (!$isAdmin) {
    header("Location: " . site_url('/pages/dashboard.php'));
    exit;
}

// Get current page from parameter
$page = $_GET['page'] ?? 'dashboard';

// Stats
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_stories' => $pdo->query("SELECT COUNT(*) FROM stories")->fetchColumn(),
    'total_chapters' => $pdo->query("SELECT COUNT(*) FROM chapters")->fetchColumn(),
    'pending_stories' => $pdo->query("SELECT COUNT(*) FROM stories WHERE status = 'pending'")->fetchColumn(),
    'pending_verification' => $pdo->query("SELECT COUNT(*) FROM verification_requests WHERE status = 'pending'")->fetchColumn(),
    'total_donations' => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status = 'completed'")->fetchColumn(),
    'active_ads' => $pdo->query("SELECT COUNT(*) FROM ads WHERE status = 'active'")->fetchColumn(),
];

// Recent activity
$activities = $pdo->query("
    SELECT 'story' AS type, s.title, u.username, s.created_at
    FROM stories s JOIN users u ON s.author_id = u.id
    UNION ALL
    SELECT 'donation', CONCAT('Donation $', d.amount), u.username, d.created_at
    FROM donations d JOIN users u ON d.user_id = u.id
    UNION ALL
    SELECT 'thread', t.title, u.username, t.created_at
    FROM forum_topics t JOIN users u ON t.author_id = u.id
    ORDER BY created_at DESC LIMIT 10
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - Scroll Novels</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
            position: sticky;
            top: 0;
        }
        .sidebar-title {
            color: white;
            padding: 15px 20px;
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 20px;
        }
        .sidebar-title:first-child {
            margin-top: 0;
        }
        .sidebar-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        .sidebar-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: white;
        }
        .sidebar-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            border-left-color: white;
            font-weight: bold;
        }
        .sidebar-link i {
            width: 20px;
            text-align: center;
        }
        .badge-notification {
            position: absolute;
            right: 10px;
            top: 10px;
            background: #ff6b6b;
            color: white;
            font-size: 10px;
            padding: 3px 6px;
            border-radius: 10px;
        }
        .content-area {
            min-height: 100vh;
            background: #f8f9fa;
        }
    </style>
</head>
<body>
<?php @include dirname(__DIR__) . '/includes/navbar.php'; ?>

<div class="d-flex">
    <!-- Sidebar -->
    <aside class="sidebar col-md-3">
        <div class="sidebar-title"><i class="fas fa-th-large"></i> Dashboard</div>
        <a href="?page=dashboard" class="sidebar-link <?= $page === 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i> Overview
        </a>

        <div class="sidebar-title"><i class="fas fa-users"></i> Users</div>
        <a href="?page=users" class="sidebar-link <?= $page === 'users' ? 'active' : '' ?>">
            <i class="fas fa-list"></i> All Users
        </a>
        <a href="?page=verify-artist" class="sidebar-link <?= $page === 'verify-artist' ? 'active' : '' ?>" style="position: relative;">
            <i class="fas fa-palette"></i> Artist Verification
            <?php if ($stats['pending_verification'] > 0): ?>
                <span class="badge-notification"><?= $stats['pending_verification'] ?></span>
            <?php endif; ?>
        </a>
        <a href="?page=verify-editor" class="sidebar-link <?= $page === 'verify-editor' ? 'active' : '' ?>" style="position: relative;">
            <i class="fas fa-pen-fancy"></i> Editor Verification
            <?php if ($stats['pending_verification'] > 0): ?>
                <span class="badge-notification"><?= $stats['pending_verification'] ?></span>
            <?php endif; ?>
        </a>

        <div class="sidebar-title"><i class="fas fa-book"></i> Content</div>
        <a href="?page=stories" class="sidebar-link <?= $page === 'stories' ? 'active' : '' ?>">
            <i class="fas fa-book-open"></i> Stories
        </a>
        <a href="?page=blog" class="sidebar-link <?= $page === 'blog' ? 'active' : '' ?>">
            <i class="fas fa-newspaper"></i> Blog Posts
        </a>
        <a href="?page=comments" class="sidebar-link <?= $page === 'comments' ? 'active' : '' ?>">
            <i class="fas fa-comments"></i> Comments
        </a>
        <a href="?page=ads" class="sidebar-link <?= $page === 'ads' ? 'active' : '' ?>">
            <i class="fas fa-bullhorn"></i> Ads
        </a>
        <a href="?page=tags" class="sidebar-link <?= $page === 'tags' ? 'active' : '' ?>">
            <i class="fas fa-tags"></i> Tags
        </a>

        <div class="sidebar-title"><i class="fas fa-cogs"></i> Management</div>
        <a href="?page=competitions" class="sidebar-link <?= $page === 'competitions' ? 'active' : '' ?>">
            <i class="fas fa-trophy"></i> Competitions
        </a>
        <a href="?page=forum" class="sidebar-link <?= $page === 'forum' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Forum Moderation
        </a>
        <a href="?page=support" class="sidebar-link <?= $page === 'support' ? 'active' : '' ?>">
            <i class="fas fa-life-ring"></i> Support Tickets
        </a>
        <a href="?page=reports" class="sidebar-link <?= $page === 'reports' ? 'active' : '' ?>">
            <i class="fas fa-flag"></i> Content Reports
        </a>
        <a href="?page=announcements" class="sidebar-link <?= $page === 'announcements' ? 'active' : '' ?>">
            <i class="fas fa-bullhorn"></i> Announcements
        </a>
        <a href="?page=guides" class="sidebar-link <?= $page === 'guides' ? 'active' : '' ?>">
            <i class="fas fa-book-reader"></i> Guide Pages
        </a>
        <a href="?page=chat" class="sidebar-link <?= $page === 'chat' ? 'active' : '' ?>">
            <i class="fas fa-comments"></i> Chat Management
        </a>

        <div class="sidebar-title"><i class="fas fa-shield-alt"></i> Administration</div>
        <a href="?page=staff" class="sidebar-link <?= $page === 'staff' ? 'active' : '' ?>">
            <i class="fas fa-users-cog"></i> Admins & Staff
        </a>
        <a href="?page=achievements" class="sidebar-link <?= $page === 'achievements' ? 'active' : '' ?>">
            <i class="fas fa-star"></i> Achievements
        </a>

        <div class="sidebar-title"><i class="fas fa-chart-bar"></i> Analytics</div>
        <a href="?page=analytics" class="sidebar-link <?= $page === 'analytics' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i> Analytics
        </a>
    </aside>

    <!-- Main Content -->
    <main class="content-area col-md-9 p-4">
        <h2 class="mb-4">Admin Dashboard</h2>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5><?= $stats['total_users'] ?> <i class="fas fa-users"></i></h5>
                        <small>Total Users</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5><?= $stats['total_stories'] ?> <i class="fas fa-book"></i></h5>
                        <small>Stories</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5><?= $stats['pending_stories'] ?> <i class="fas fa-clock"></i></h5>
                        <small>Pending</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5>$<?= number_format($stats['total_donations'], 2) ?> <i class="fas fa-dollar-sign"></i></h5>
                        <small>Revenue</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <h5><?= $stats['active_ads'] ?> <i class="fas fa-bullhorn"></i></h5>
                        <small>Active Ads</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-secondary">
                    <div class="card-body">
                        <h5><?= $stats['pending_verification'] ?> <i class="fas fa-check-circle"></i></h5>
                        <small>Pending Verify</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <?php

        $adminPageDir = dirname(__FILE__) . '/pages/';
        
        if ($page === 'users' && file_exists($adminPageDir . 'users.php')):
            include $adminPageDir . 'users.php';
        elseif ($page === 'verify-artist' && file_exists($adminPageDir . 'verify-artist.php')):
            include $adminPageDir . 'verify-artist.php';
        elseif ($page === 'verify-editor' && file_exists($adminPageDir . 'verify-editor.php')):
            include $adminPageDir . 'verify-editor.php';
        elseif ($page === 'stories' && file_exists($adminPageDir . 'stories.php')):
            include $adminPageDir . 'stories.php';
        elseif ($page === 'blog' && file_exists($adminPageDir . 'blog.php')):
            include $adminPageDir . 'blog.php';
        elseif ($page === 'comments' && file_exists($adminPageDir . 'comments.php')):
            include $adminPageDir . 'comments.php';
        elseif ($page === 'ads' && file_exists($adminPageDir . 'ads.php')):
            include $adminPageDir . 'ads.php';
        elseif ($page === 'tags' && file_exists($adminPageDir . 'tags.php')):
            include $adminPageDir . 'tags.php';
        elseif ($page === 'competitions' && file_exists($adminPageDir . 'competitions.php')):
            include $adminPageDir . 'competitions.php';
        elseif ($page === 'forum' && file_exists($adminPageDir . 'forum.php')):
            include $adminPageDir . 'forum.php';
        elseif ($page === 'support' && file_exists($adminPageDir . 'support.php')):
            include $adminPageDir . 'support.php';
        elseif ($page === 'reports' && file_exists($adminPageDir . 'reports.php')):
            include $adminPageDir . 'reports.php';
        elseif ($page === 'announcements' && file_exists($adminPageDir . 'announcements.php')):
            include $adminPageDir . 'announcements.php';
        elseif ($page === 'guides' && file_exists($adminPageDir . 'guides.php')):
            include $adminPageDir . 'guides.php';
        elseif ($page === 'chat' && file_exists($adminPageDir . 'chat.php')):
            include $adminPageDir . 'chat.php';
        elseif ($page === 'staff' && file_exists($adminPageDir . 'staff.php')):
            include $adminPageDir . 'staff.php';
        elseif ($page === 'achievements' && file_exists($adminPageDir . 'achievements.php')):
            include $adminPageDir . 'achievements.php';
        elseif ($page === 'analytics' && file_exists($adminPageDir . 'analytics.php')):
            include $adminPageDir . 'analytics.php';
        elseif (file_exists($adminPageDir . 'dashboard.php')):
            include $adminPageDir . 'dashboard.php';
        else:
            echo '<div class="alert alert-danger">Dashboard page not found</div>';
        endif;
        ?>
    </main>
</div>

<script>
// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function htmlEscape(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}
</script>
<!-- Bootstrap JS for modals -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
