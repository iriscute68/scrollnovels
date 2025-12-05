<?php
/**
 * Unified Admin Dashboard v3
 * Combines all features: Books, Users, Analytics, Donations, Support, Settings
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireLogin();

// Admin check
$isAdmin = false;
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1) {
    $isAdmin = true;
} elseif (isset($_SESSION['admin_id']) && $_SESSION['admin_id']) {
    $isAdmin = true;
}

if (!$isAdmin) {
    header("Location: " . site_url('/pages/dashboard.php'));
    exit;
}

$page = $_GET['page'] ?? 'dashboard';
$subpage = $_GET['subpage'] ?? '';

// Get all statistics
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?? 0,
    'total_stories' => $pdo->query("SELECT COUNT(*) FROM stories")->fetchColumn() ?? 0,
    'total_chapters' => $pdo->query("SELECT COUNT(*) FROM chapters")->fetchColumn() ?? 0,
    'pending_stories' => $pdo->query("SELECT COUNT(*) FROM stories WHERE status = 'pending'")->fetchColumn() ?? 0,
    'pending_verification' => $pdo->query("SELECT COUNT(*) FROM verification_requests WHERE status = 'pending'")->fetchColumn() ?? 0,
    'total_donations' => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status = 'completed'")->fetchColumn() ?? 0,
    'active_ads' => $pdo->query("SELECT COUNT(*) FROM ads WHERE status = 'active'")->fetchColumn() ?? 0,
    'support_tickets' => $pdo->query("SELECT COUNT(*) FROM support_messages WHERE resolved = 0")->fetchColumn() ?? 0,
];

// Recent activity
$activities = $pdo->query("
    SELECT 'story' AS type, s.title, u.username, s.created_at, s.id
    FROM stories s JOIN users u ON s.author_id = u.id
    UNION ALL
    SELECT 'donation', CONCAT('Donation $', d.amount), u.username, d.created_at, d.id
    FROM donations d JOIN users u ON d.user_id = u.id
    UNION ALL
    SELECT 'chapter', CONCAT('Chapter: ', c.title), u.username, c.created_at, c.id
    FROM chapters c JOIN stories s ON c.story_id = s.id JOIN users u ON s.author_id = u.id
    ORDER BY created_at DESC LIMIT 10
")->fetchAll() ?? [];

// Monthly revenue
$monthlyRevenue = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as revenue
    FROM donations
    WHERE status = 'completed'
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
")->fetchAll() ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Scroll Novels</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        :root {
            --bg-primary: #0f0f12;
            --bg-secondary: #141418;
            --border-color: #22222a;
            --text-primary: #e6e7ea;
            --text-secondary: #9aa0a6;
            --accent: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--bg-primary) 0%, #0b0b0d 100%);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-color);
            min-height: 100vh;
            padding: 20px 0;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            overflow-y: auto;
            z-index: 100;
        }

        .sidebar-brand {
            padding: 20px;
            font-size: 20px;
            font-weight: 700;
            color: var(--accent);
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-item {
            padding: 0;
            margin: 0;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            color: var(--accent);
            background: rgba(99, 102, 241, 0.1);
            border-left-color: var(--accent);
        }

        .sidebar-link i {
            width: 20px;
            text-align: center;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 12px;
            text-transform: uppercase;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--accent);
            margin-top: 8px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            color: var(--text-primary);
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            font-weight: 600;
            color: var(--text-secondary);
        }

        .badge-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-success { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .badge-danger { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .badge-info { background: rgba(99, 102, 241, 0.2); color: var(--accent); }

        .btn-action {
            padding: 6px 12px;
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--accent);
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-action:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--accent);
        }

        .btn-primary {
            background: var(--accent);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: #4f46e5;
        }

        .form-control, .form-select {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .form-control:focus, .form-select:focus {
            background: rgba(0, 0, 0, 0.3);
            border-color: var(--accent);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
            }

            .main-content {
                margin-left: 0;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-crown"></i> Admin
        </div>
        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="?page=dashboard" class="sidebar-link <?= $page === 'dashboard' ? 'active' : '' ?>">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
            </li>
            <li class="sidebar-item">
                <a href="books_management.php" class="sidebar-link">
                    <i class="fas fa-book"></i> Books
                </a>
            </li>
            <li class="sidebar-item">
                <a href="users.php" class="sidebar-link">
                    <i class="fas fa-users"></i> Users
                </a>
            </li>
            <li class="sidebar-item">
                <a href="?page=analytics" class="sidebar-link <?= $page === 'analytics' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i> Analytics
                </a>
            </li>
            <li class="sidebar-item">
                <a href="?page=donations" class="sidebar-link <?= $page === 'donations' ? 'active' : '' ?>">
                    <i class="fas fa-dollar-sign"></i> Donations
                </a>
            </li>
            <li class="sidebar-item">
                <a href="?page=support" class="sidebar-link <?= $page === 'support' ? 'active' : '' ?>">
                    <i class="fas fa-headset"></i> Support
                </a>
            </li>
            <li class="sidebar-item">
                <a href="?page=settings" class="sidebar-link <?= $page === 'settings' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>
            <li class="sidebar-item" style="margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 20px;">
                <a href="../pages/logout.php" class="sidebar-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php if ($page === 'dashboard'): ?>
            <div class="page-header">
                <div>
                    <h1>📊 Dashboard</h1>
                    <p style="color: var(--text-secondary);">Welcome back, Admin!</p>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Books</div>
                    <div class="stat-value"><?= number_format($stats['total_stories']) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Chapters</div>
                    <div class="stat-value"><?= number_format($stats['total_chapters']) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value" style="color: var(--warning);"><?= number_format($stats['pending_stories']) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Revenue</div>
                    <div class="stat-value" style="color: var(--success);">$<?= number_format($stats['total_donations'], 0) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Support</div>
                    <div class="stat-value"><?= number_format($stats['support_tickets']) ?></div>
                </div>
            </div>

            <div class="content-grid">
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-history"></i> Recent Activity
                    </div>
                    <table>
                        <tbody>
                            <?php foreach (array_slice($activities, 0, 8) as $activity): ?>
                            <tr>
                                <td>
                                    <small>
                                        <strong><?= htmlspecialchars($activity['username']) ?></strong><br>
                                        <span style="color: var(--text-secondary);"><?= date('M d, Y', strtotime($activity['created_at'])) ?></span>
                                    </small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-lightning-bolt"></i> Quick Actions
                    </div>
                    <div style="display: grid; gap: 10px;">
                        <a href="books_management.php" class="btn btn-primary" style="width: 100%; text-decoration: none; display: block; text-align: center;">
                            <i class="fas fa-plus"></i> Create New Book
                        </a>
                        <a href="users.php" class="btn btn-primary" style="width: 100%; text-decoration: none; display: block; text-align: center;">
                            <i class="fas fa-user-plus"></i> Manage Users
                        </a>
                        <a href="?page=support" class="btn btn-primary" style="width: 100%; text-decoration: none; display: block; text-align: center;">
                            <i class="fas fa-envelope"></i> Support Tickets
                        </a>
                    </div>
                </div>
            </div>

        <?php elseif ($page === 'donations'): ?>
            <div class="page-header">
                <h1>💰 Donations</h1>
            </div>

            <div class="card">
                <div class="card-title">
                    <i class="fas fa-list"></i> Recent Donations
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $donations = $pdo->query("
                            SELECT d.*, u.username 
                            FROM donations d 
                            LEFT JOIN users u ON d.user_id = u.id
                            ORDER BY d.created_at DESC 
                            LIMIT 20
                        ")->fetchAll();
                        
                        foreach ($donations as $don):
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($don['username'] ?? 'Anonymous') ?></td>
                            <td><strong>$<?= number_format($don['amount'], 2) ?></strong></td>
                            <td><?= htmlspecialchars($don['method'] ?? 'N/A') ?></td>
                            <td>
                                <span class="badge-status badge-<?= ($don['status'] ?? '') === 'completed' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($don['status'] ?? 'pending') ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($don['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($page === 'support'): ?>
            <div class="page-header">
                <h1>🎧 Support Tickets</h1>
            </div>

            <div class="card">
                <div class="card-title">
                    <i class="fas fa-tickets"></i> Open Tickets
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $tickets = $pdo->query("
                                SELECT s.*, u.username 
                                FROM support_messages s 
                                LEFT JOIN users u ON s.user_id = u.id
                                ORDER BY s.created_at DESC 
                                LIMIT 20
                            ")->fetchAll();
                            
                            foreach ($tickets as $ticket):
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($ticket['username'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars(substr($ticket['subject'] ?? 'No subject', 0, 40)) ?></td>
                                <td><span class="badge-status badge-info">Open</span></td>
                                <td><?= date('M d, Y', strtotime($ticket['created_at'])) ?></td>
                            </tr>
                            <?php endforeach;
                        } catch (Exception $e) {
                            echo '<tr><td colspan="4">No support tickets found</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
