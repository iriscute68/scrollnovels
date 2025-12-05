<?php
// pages/analytics.php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';

requireLogin();

// Get user's stories
$stories = $pdo->prepare("SELECT id, title FROM stories WHERE author_id = ? ORDER BY created_at DESC");
$stories->execute([$_SESSION['user_id']]);
$userStories = $stories->fetchAll();

// Get analytics data
$viewsData = [];
$viewsByStory = [];
$totalViews = 0;
$totalReviews = 0;
$totalFollowers = 0;

foreach ($userStories as $story) {
    $viewsStmt = $pdo->prepare("SELECT COALESCE(views, 0) as views FROM stories WHERE id = ?");
    $viewsStmt->execute([$story['id']]);
    $result = $viewsStmt->fetch();
    $views = (int)($result['views'] ?? 0);
    $totalViews += $views;
    $viewsByStory[$story['id']] = [
        'title' => $story['title'],
        'views' => $views
    ];
}

// Get reviews count
$reviewStmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE story_id IN (SELECT id FROM stories WHERE author_id = ?)");
$reviewStmt->execute([$_SESSION['user_id']]);
$totalReviews = $reviewStmt->fetchColumn() ?? 0;

// Get unique followers
$followerStmt = $pdo->prepare("SELECT COUNT(DISTINCT follower_id) FROM user_follows WHERE following_id = ?");
$followerStmt->execute([$_SESSION['user_id']]);
$totalFollowers = $followerStmt->fetchColumn() ?? 0;

// Get reading status breakdown
$readingStmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'reading' THEN 1 END) as reading,
        COUNT(CASE WHEN status = 'planned' THEN 1 END) as planned,
        COUNT(CASE WHEN status = 'abandoned' THEN 1 END) as abandoned,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
    FROM user_list_status
    WHERE story_id IN (SELECT id FROM stories WHERE author_id = ?)
");
$readingStmt->execute([$_SESSION['user_id']]);
$readingStats = $readingStmt->fetch();
?>
<?php
    $page_title = 'Analytics - Scroll Novels';
    $page_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <style>
    :root {
        --primary-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
        --card-shadow: 0 10px 40px rgba(16, 185, 129, 0.1);
        --card-hover-shadow: 0 20px 60px rgba(16, 185, 129, 0.2);
    }
    body { 
        background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 50%, #d1fae5 100%); 
        min-height: 100vh; 
        font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif;
    }
    .analytics-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 2rem;
    }
    .page-header {
        background: var(--primary-gradient);
        border-radius: 20px;
        padding: 2.5rem;
        margin-bottom: 2rem;
        color: white;
        box-shadow: var(--card-shadow);
    }
    .page-header h2 {
        font-size: 2rem;
        font-weight: 800;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .page-header p {
        margin: 0.5rem 0 0;
        opacity: 0.9;
    }
    .stat-card { 
        background: white; 
        border-radius: 20px; 
        padding: 1.75rem; 
        box-shadow: var(--card-shadow);
        border: 1px solid rgba(16, 185, 129, 0.1);
        margin-bottom: 1.5rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    .stat-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--primary-gradient);
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-hover-shadow);
    }
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }
    .stat-icon.views { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #2563eb; }
    .stat-icon.reviews { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #d97706; }
    .stat-icon.followers { background: linear-gradient(135deg, #fce7f3, #fbcfe8); color: #db2777; }
    .stat-icon.stories { background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: #059669; }
    .stat-value { 
        font-size: 2.5rem; 
        font-weight: 800; 
        color: #1f2937;
        line-height: 1;
        margin-bottom: 0.5rem;
    }
    .stat-label { 
        color: #6b7280; 
        font-size: 0.875rem; 
        text-transform: uppercase; 
        letter-spacing: 1px;
        font-weight: 600;
    }
    .chart-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        border: 1px solid rgba(16, 185, 129, 0.1);
        margin-bottom: 1.5rem;
    }
    .chart-card h5 {
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .chart-container { 
        position: relative; 
        height: 280px; 
    }
    .table-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        border: 1px solid rgba(16, 185, 129, 0.1);
    }
    .table-card h5 {
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 1rem;
    }
    .table {
        margin: 0;
    }
    .table thead th {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        font-weight: 600;
        border: none;
        padding: 1rem;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .table thead th:first-child { border-radius: 12px 0 0 0; }
    .table thead th:last-child { border-radius: 0 12px 0 0; }
    .table tbody td {
        padding: 1rem;
        vertical-align: middle;
        border-color: #f3f4f6;
    }
    .table tbody tr:hover {
        background-color: #f0fdf4;
    }
    .table tbody tr:last-child td:first-child { border-radius: 0 0 0 12px; }
    .table tbody tr:last-child td:last-child { border-radius: 0 0 12px 0; }
    .badge {
        font-weight: 600;
        padding: 0.5rem 1rem;
        border-radius: 50px;
    }
    .badge.bg-success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
    }
    .story-link {
        color: #1f2937;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.2s;
    }
    .story-link:hover {
        color: #10b981;
    }
    @media (max-width: 768px) {
        .analytics-container { padding: 1rem; }
        .stat-value { font-size: 2rem; }
        .page-header { padding: 1.5rem; }
    }
    </style>';
    require_once __DIR__ . '/../includes/header.php';
?>

<div class="analytics-container">
    <!-- Page Header -->
    <div class="page-header">
        <h2><i class="fas fa-chart-line"></i> Your Analytics</h2>
        <p>Track your stories' performance and audience engagement</p>
    </div>
    
    <!-- Stats Cards -->
    <div class="row">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon views"><i class="fas fa-eye"></i></div>
                <div class="stat-value"><?= number_format($totalViews) ?></div>
                <div class="stat-label">Total Views</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon reviews"><i class="fas fa-star"></i></div>
                <div class="stat-value"><?= number_format($totalReviews) ?></div>
                <div class="stat-label">Total Reviews</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon followers"><i class="fas fa-heart"></i></div>
                <div class="stat-value"><?= number_format($totalFollowers) ?></div>
                <div class="stat-label">Followers</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon stories"><i class="fas fa-book"></i></div>
                <div class="stat-value"><?= count($userStories) ?></div>
                <div class="stat-label">Stories</div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <div class="col-md-6">
            <div class="chart-card">
                <h5><i class="fas fa-chart-pie text-emerald-500"></i> Reader Status Breakdown</h5>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-card">
                <h5><i class="fas fa-chart-bar text-emerald-500"></i> Views by Story</h5>
                <div class="chart-container">
                    <canvas id="storiesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Story Details Table -->
    <div class="table-card">
        <h5><i class="fas fa-list text-emerald-500"></i> Story Details</h5>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Story Title</th>
                        <th>Views</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($viewsByStory)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">No stories yet. <a href="<?= site_url('/pages/write-story.php') ?>">Create your first story!</a></td></tr>
                    <?php else: ?>
                        <?php foreach ($viewsByStory as $id => $data): ?>
                            <tr>
                                <td><a href="<?= rtrim(SITE_URL, '/') ?>/pages/book.php?id=<?= $id ?>" class="story-link"><?= htmlspecialchars($data['title']) ?></a></td>
                                <td><strong><?= number_format($data['views']) ?></strong></td>
                                <td><span class="badge bg-success">Active</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Status Chart
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Reading', 'Planned', 'Abandoned', 'Completed'],
                datasets: [{
                    data: [
                        <?= $readingStats['reading'] ?? 0 ?>,
                        <?= $readingStats['planned'] ?? 0 ?>,
                        <?= $readingStats['abandoned'] ?? 0 ?>,
                        <?= $readingStats['completed'] ?? 0 ?>
                    ],
                    backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6'],
                    borderWidth: 0
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 20, usePointStyle: true }
                    }
                },
                cutout: '65%'
            }
        });
    }

    // Stories Chart
    const storiesCtx = document.getElementById('storiesChart');
    if (storiesCtx) {
        new Chart(storiesCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_values(array_map(fn($s) => strlen($s['title']) > 20 ? substr($s['title'], 0, 20) . '...' : $s['title'], $viewsByStory))) ?>,
                datasets: [{
                    label: 'Views',
                    data: <?= json_encode(array_values(array_map(fn($s) => $s['views'], $viewsByStory))) ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: '#10b981',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>
