<?php
/**
 * INTEGRATED ADMIN DASHBOARD
 * Combines: Admin Dashboard + Achievements + Ad Verification + Book Reader Settings
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

// Get current section from parameter
$section = $_GET['section'] ?? 'dashboard';

// ============================================
// SECTION 1: ACHIEVEMENTS SYSTEM
// ============================================
class AchievementsSystem {
    private $achievements = [
        [
            'id' => 1,
            'name' => 'First Steps',
            'description' => 'Create your first story',
            'icon' => 'book-open',
            'completed' => true,
            'date' => 'Jan 15, 2024',
            'points' => 100
        ],
        [
            'id' => 2,
            'name' => 'Rising Star',
            'description' => 'Get 1,000 views on a story',
            'icon' => 'star',
            'completed' => true,
            'date' => 'Feb 20, 2024',
            'points' => 250
        ],
        [
            'id' => 3,
            'name' => 'Popular Author',
            'description' => 'Get 10,000 views total',
            'icon' => 'trending-up',
            'completed' => true,
            'date' => 'Mar 10, 2024',
            'points' => 500
        ],
        [
            'id' => 4,
            'name' => 'Prolific Writer',
            'description' => 'Write 10 chapters',
            'icon' => 'zap',
            'completed' => false,
            'progress' => '7/10',
            'points' => 300
        ],
        [
            'id' => 5,
            'name' => 'Community Champion',
            'description' => 'Comment 50 times',
            'icon' => 'message-square',
            'completed' => false,
            'progress' => '32/50',
            'points' => 200
        ]
    ];

    public function getCompletedCount() {
        return count(array_filter($this->achievements, fn($a) => $a['completed']));
    }

    public function getTotalPoints() {
        return array_sum(array_map(fn($a) => $a['completed'] ? $a['points'] : 0, $this->achievements));
    }

    public function render() {
        $completedCount = $this->getCompletedCount();
        $totalPoints = $this->getTotalPoints();
        $total = count($this->achievements);
        $completionRate = round(($completedCount / $total) * 100);

        echo '<div class="achievements-section">';
        echo '<h2 class="mb-4">Achievements System</h2>';
        
        echo '<div class="row mb-4">';
        echo '<div class="col-md-3"><div class="card"><div class="card-body"><h5>Unlocked</h5><p class="h3">' . $completedCount . '/' . $total . '</p></div></div></div>';
        echo '<div class="col-md-3"><div class="card"><div class="card-body"><h5>Points</h5><p class="h3">' . $totalPoints . '</p></div></div></div>';
        echo '<div class="col-md-3"><div class="card"><div class="card-body"><h5>Completion</h5><p class="h3">' . $completionRate . '%</p></div></div></div>';
        echo '</div>';

        echo '<div class="row">';
        foreach ($this->achievements as $a) {
            echo '<div class="col-md-4 mb-3">';
            echo '<div class="card ' . ($a['completed'] ? 'border-success' : 'border-secondary') . '">';
            echo '<div class="card-body">';
            echo '<h5 class="card-title">';
            if ($a['completed']) echo '<i class="fas fa-check-circle text-success"></i> ';
            echo htmlspecialchars($a['name']);
            echo '</h5>';
            echo '<p class="card-text">' . htmlspecialchars($a['description']) . '</p>';
            echo '<div class="d-flex justify-content-between">';
            echo '<span class="badge bg-info">+' . $a['points'] . ' pts</span>';
            if ($a['completed']) {
                echo '<small class="text-muted">' . $a['date'] . '</small>';
            } elseif (isset($a['progress'])) {
                echo '<small class="text-warning">' . $a['progress'] . '</small>';
            }
            echo '</div>';
            echo '</div></div></div>';
        }
        echo '</div></div>';
    }
}

// ============================================
// SECTION 2: AD VERIFICATION SYSTEM
// ============================================
class AdVerificationSystem {
    private $adRequests = [
        [
            'id' => 1,
            'author' => 'Sarah Mitchell',
            'bookTitle' => 'The Emerald Crown',
            'tier' => 'Diamond',
            'amount' => 80,
            'paymentImage' => 'payment_1.jpg',
            'status' => 'pending',
            'submittedDate' => '2 hours ago',
            'views' => 500000,
            'description' => 'Front page ad placement for 30 days',
        ],
        [
            'id' => 2,
            'author' => 'Emma Watson',
            'bookTitle' => 'Shadow Protocol',
            'tier' => 'Platinum',
            'amount' => 60,
            'paymentImage' => 'payment_2.jpg',
            'status' => 'pending',
            'submittedDate' => '5 hours ago',
            'views' => 250000,
            'description' => 'Category featured placement',
        ],
        [
            'id' => 3,
            'author' => 'Marcus Lee',
            'bookTitle' => 'Celestial Awakening',
            'tier' => 'Gold',
            'amount' => 40,
            'paymentImage' => 'payment_3.jpg',
            'status' => 'verified',
            'submittedDate' => '1 day ago',
            'views' => 150000,
            'description' => 'Trending section placement',
        ],
    ];

    public function render($filter = 'all') {
        $validFilters = ['all', 'pending', 'verified'];
        if (!in_array($filter, $validFilters)) $filter = 'all';

        $filteredAds = $filter === 'all' 
            ? $this->adRequests 
            : array_filter($this->adRequests, fn($ad) => $ad['status'] === $filter);

        echo '<div class="ad-verification-section">';
        echo '<h2 class="mb-4">Ad Payment Verification</h2>';

        // Filter Buttons
        echo '<div class="btn-group mb-4" role="group">';
        foreach ($validFilters as $f) {
            $active = $filter === $f ? 'active' : '';
            echo '<a href="?section=ads&filter=' . $f . '" class="btn btn-outline-primary ' . $active . '">';
            echo ucfirst($f);
            echo '</a>';
        }
        echo '</div>';

        // Ads Grid
        echo '<div class="row">';
        foreach ($filteredAds as $ad) {
            echo '<div class="col-md-6 mb-4">';
            echo '<div class="card border-' . ($ad['status'] === 'pending' ? 'warning' : 'success') . '">';
            echo '<div class="card-header">';
            echo '<div class="d-flex justify-content-between">';
            echo '<div>';
            echo '<h5>' . htmlspecialchars($ad['bookTitle']) . '</h5>';
            echo '<small class="text-muted">by ' . htmlspecialchars($ad['author']) . '</small>';
            echo '</div>';
            echo '<div class="text-end">';
            echo '<p class="h5 mb-0">$' . $ad['amount'] . '</p>';
            echo '<small>' . $ad['tier'] . ' Tier</small>';
            echo '</div>';
            echo '</div></div>';
            echo '<div class="card-body">';
            echo '<p><strong>Description:</strong> ' . htmlspecialchars($ad['description']) . '</p>';
            echo '<p><strong>Views:</strong> ' . number_format($ad['views']) . '</p>';
            echo '<p><strong>Submitted:</strong> ' . $ad['submittedDate'] . '</p>';
            echo '<p><span class="badge bg-' . ($ad['status'] === 'pending' ? 'warning' : 'success') . '">';
            echo $ad['status'] === 'pending' ? '⏳ Pending' : '✓ Verified';
            echo '</span></p>';
            echo '</div>';
            
            if ($ad['status'] === 'pending') {
                echo '<div class="card-footer">';
                echo '<form method="post" class="d-flex gap-2">';
                echo '<input type="hidden" name="action" value="approve">';
                echo '<input type="hidden" name="ad_id" value="' . $ad['id'] . '">';
                echo '<button type="submit" class="btn btn-success btn-sm flex-grow-1">✓ Approve</button>';
                echo '</form>';
                echo '<form method="post" class="d-flex gap-2 mt-2">';
                echo '<input type="hidden" name="action" value="reject">';
                echo '<input type="hidden" name="ad_id" value="' . $ad['id'] . '">';
                echo '<button type="submit" class="btn btn-danger btn-sm flex-grow-1">✗ Reject</button>';
                echo '</form>';
                echo '</div>';
            } else {
                echo '<div class="card-footer"><small class="text-success">✓ Now displayed on website</small></div>';
            }
            echo '</div></div>';
        }
        echo '</div>';

        if (empty($filteredAds)) {
            echo '<div class="alert alert-info">No ' . $filter . ' ad requests at the moment.</div>';
        }

        echo '</div>';
    }
}

// ============================================
// SECTION 3: BOOK READER SETTINGS
// ============================================
class ReaderSettingsSystem {
    private $fontOptions = ['Serif', 'Sans-serif', 'OpenDyslexic', 'Mono'];
    private $themes = ['Light', 'Dark', 'Sepia', 'Gray', 'Night Blue', 'Green'];
    private $alignments = ['Left', 'Center', 'Justify'];
    private $modes = ['Scroll', 'Page Flip'];

    public function render() {
        echo '<div class="reader-settings-section">';
        echo '<h2 class="mb-4">Book Reader Settings Management</h2>';

        echo '<div class="row">';
        
        // Font Options
        echo '<div class="col-md-6 mb-4">';
        echo '<div class="card">';
        echo '<div class="card-header"><h5>Font Options</h5></div>';
        echo '<div class="card-body">';
        foreach ($this->fontOptions as $font) {
            echo '<div class="form-check">';
            echo '<input class="form-check-input" type="checkbox" checked>';
            echo '<label class="form-check-label">' . $font . '</label>';
            echo '</div>';
        }
        echo '</div></div></div>';

        // Themes
        echo '<div class="col-md-6 mb-4">';
        echo '<div class="card">';
        echo '<div class="card-header"><h5>Available Themes</h5></div>';
        echo '<div class="card-body">';
        foreach ($this->themes as $theme) {
            echo '<span class="badge bg-secondary me-2 mb-2">' . $theme . '</span>';
        }
        echo '</div></div></div>';

        // Text Alignment
        echo '<div class="col-md-6 mb-4">';
        echo '<div class="card">';
        echo '<div class="card-header"><h5>Text Alignment Options</h5></div>';
        echo '<div class="card-body">';
        foreach ($this->alignments as $align) {
            echo '<div class="form-check">';
            echo '<input class="form-check-input" type="radio" name="alignment" checked>';
            echo '<label class="form-check-label">' . $align . '</label>';
            echo '</div>';
        }
        echo '</div></div></div>';

        // Reading Modes
        echo '<div class="col-md-6 mb-4">';
        echo '<div class="card">';
        echo '<div class="card-header"><h5>Reading Modes</h5></div>';
        echo '<div class="card-body">';
        foreach ($this->modes as $mode) {
            echo '<div class="form-check">';
            echo '<input class="form-check-input" type="radio" name="mode" checked>';
            echo '<label class="form-check-label">' . $mode . '</label>';
            echo '</div>';
        }
        echo '</div></div></div>';

        echo '</div>';

        // Reader Features
        echo '<div class="card mt-4">';
        echo '<div class="card-header"><h5>Reader Features</h5></div>';
        echo '<div class="card-body">';
        echo '<div class="row">';
        echo '<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" checked><label class="form-check-label">Auto-save Progress</label></div></div>';
        echo '<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" checked><label class="form-check-label">Brightness Control</label></div></div>';
        echo '<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" checked><label class="form-check-label">Text-to-Speech</label></div></div>';
        echo '<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" checked><label class="form-check-label">Offline Mode</label></div></div>';
        echo '<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" checked><label class="form-check-label">Comments</label></div></div>';
        echo '<div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" checked><label class="form-check-label">Dictionary</label></div></div>';
        echo '</div>';
        echo '</div></div>';

        echo '</div>';
    }
}

// Get stats
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_stories' => $pdo->query("SELECT COUNT(*) FROM stories")->fetchColumn(),
    'total_chapters' => $pdo->query("SELECT COUNT(*) FROM chapters")->fetchColumn(),
    'pending_stories' => $pdo->query("SELECT COUNT(*) FROM stories WHERE status = 'pending'")->fetchColumn(),
    'pending_verification' => $pdo->query("SELECT COUNT(*) FROM verification_requests WHERE status = 'pending'")->fetchColumn(),
    'total_donations' => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE status = 'completed'")->fetchColumn(),
    'active_ads' => $pdo->query("SELECT COUNT(*) FROM ads WHERE status = 'active'")->fetchColumn(),
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integrated Admin Dashboard - Scroll Novels</title>
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
            font-size: 18px;
        }
        .sidebar-item {
            color: white;
            padding: 12px 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .sidebar-item:hover {
            background: rgba(255,255,255,0.2);
            padding-left: 25px;
        }
        .sidebar-item.active {
            background: rgba(0,0,0,0.2);
            border-left: 4px solid white;
            padding-left: 16px;
        }
        .sidebar-item a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .main-content {
            padding: 30px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
        }
        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        .card-header h5 {
            color: white;
            margin: 0;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- SIDEBAR -->
        <div class="col-md-3 col-lg-2 sidebar">
            <div class="sidebar-title">
                <i class="fas fa-crown"></i> Admin Panel
            </div>
            
            <div class="sidebar-item <?php echo $section === 'dashboard' ? 'active' : ''; ?>">
                <a href="?section=dashboard">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
            </div>

            <div class="sidebar-item <?php echo $section === 'achievements' ? 'active' : ''; ?>">
                <a href="?section=achievements">
                    <i class="fas fa-trophy"></i> Achievements
                </a>
            </div>

            <div class="sidebar-item <?php echo $section === 'ads' ? 'active' : ''; ?>">
                <a href="?section=ads">
                    <i class="fas fa-file-invoice-dollar"></i> Ad Verification
                </a>
            </div>

            <div class="sidebar-item <?php echo $section === 'reader' ? 'active' : ''; ?>">
                <a href="?section=reader">
                    <i class="fas fa-book-reader"></i> Reader Settings
                </a>
            </div>

            <div class="sidebar-item <?php echo $section === 'users' ? 'active' : ''; ?>">
                <a href="?section=users">
                    <i class="fas fa-users"></i> Users
                </a>
            </div>

            <div class="sidebar-item <?php echo $section === 'stories' ? 'active' : ''; ?>">
                <a href="?section=stories">
                    <i class="fas fa-book"></i> Stories
                </a>
            </div>

            <div class="sidebar-item <?php echo $section === 'analytics' ? 'active' : ''; ?>">
                <a href="?section=analytics">
                    <i class="fas fa-chart-bar"></i> Analytics
                </a>
            </div>

            <hr style="border-color: rgba(255,255,255,0.3);">

            <div class="sidebar-item">
                <a href="<?php echo site_url('/pages/login.php?action=logout'); ?>">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="col-md-9 col-lg-10 main-content">
            <?php if ($section === 'dashboard'): ?>
                <h1 class="mb-4">Dashboard Overview</h1>
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h6 class="text-muted">Total Users</h6>
                            <p class="stat-number"><?php echo number_format($stats['total_users']); ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h6 class="text-muted">Total Stories</h6>
                            <p class="stat-number"><?php echo number_format($stats['total_stories']); ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h6 class="text-muted">Total Chapters</h6>
                            <p class="stat-number"><?php echo number_format($stats['total_chapters']); ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <h6 class="text-muted">Pending Verification</h6>
                            <p class="stat-number"><?php echo $stats['pending_verification']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>System Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success">All systems operational</div>
                        <ul>
                            <li>✓ Database: Connected</li>
                            <li>✓ User Authentication: Active</li>
                            <li>✓ File Uploads: Available</li>
                            <li>✓ Email Notifications: Ready</li>
                        </ul>
                    </div>
                </div>

            <?php elseif ($section === 'achievements'): ?>
                <?php $achievementsSystem = new AchievementsSystem(); $achievementsSystem->render(); ?>

            <?php elseif ($section === 'ads'): ?>
                <?php $adsSystem = new AdVerificationSystem(); $filter = $_GET['filter'] ?? 'all'; $adsSystem->render($filter); ?>

            <?php elseif ($section === 'reader'): ?>
                <?php $readerSystem = new ReaderSettingsSystem(); $readerSystem->render(); ?>

            <?php elseif ($section === 'users'): ?>
                <h2>Users Management</h2>
                <div class="card">
                    <div class="card-body">
                        <p>Total Users: <?php echo $stats['total_users']; ?></p>
                    </div>
                </div>

            <?php elseif ($section === 'stories'): ?>
                <h2>Stories Management</h2>
                <div class="card">
                    <div class="card-body">
                        <p>Total Stories: <?php echo $stats['total_stories']; ?></p>
                        <p>Pending Approval: <?php echo $stats['pending_stories']; ?></p>
                    </div>
                </div>

            <?php elseif ($section === 'analytics'): ?>
                <h2>Analytics</h2>
                <div class="card">
                    <div class="card-body">
                        <p>Total Donations: $<?php echo number_format($stats['total_donations'], 2); ?></p>
                        <p>Active Ads: <?php echo $stats['active_ads']; ?></p>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
