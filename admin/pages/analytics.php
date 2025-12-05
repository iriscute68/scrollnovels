<?php
// admin/pages/analytics.php

// Get real-time analytics data
try {
    // Users online (active in last 15 minutes)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE last_active > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $usersOnline = $stmt->fetch()['count'] ?? 0;
    
    // New users today
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()");
    $newUsersToday = $stmt->fetch()['count'] ?? 0;
    
    // New users this week
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $newUsersWeek = $stmt->fetch()['count'] ?? 0;
    
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $totalUsers = $stmt->fetch()['count'] ?? 0;
    
    // Total stories
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM stories");
    $totalStories = $stmt->fetch()['count'] ?? 0;
    
    // Total chapters
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM chapters");
    $totalChapters = $stmt->fetch()['count'] ?? 0;
    
    // Total views
    $stmt = $pdo->query("SELECT SUM(views) as total FROM stories");
    $totalViews = $stmt->fetch()['total'] ?? 0;
    
    // Daily views for last 7 days
    $stmt = $pdo->query("
        SELECT DATE(created_at) as date, SUM(views) as views 
        FROM stories 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $dailyViewsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // User signups for last 7 days
    $stmt = $pdo->query("
        SELECT DATE(created_at) as date, COUNT(*) as signups 
        FROM users 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $dailySignupsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top genres
    $stmt = $pdo->query("
        SELECT genre, COUNT(*) as count 
        FROM stories 
        WHERE genre IS NOT NULL AND genre != ''
        GROUP BY genre 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $topGenres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Country distribution (if we have country data)
    $hasCountryColumn = false;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'country'");
        $hasCountryColumn = $stmt->rowCount() > 0;
    } catch (Exception $e) {}
    
    $countryData = [];
    if ($hasCountryColumn) {
        $stmt = $pdo->query("
            SELECT country, COUNT(*) as count 
            FROM users 
            WHERE country IS NOT NULL AND country != ''
            GROUP BY country 
            ORDER BY count DESC 
            LIMIT 10
        ");
        $countryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Prepare chart data
    $viewsLabels = [];
    $viewsData = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $viewsLabels[] = date('D', strtotime($date));
        $viewsData[$date] = 0;
    }
    foreach ($dailyViewsData as $row) {
        if (isset($viewsData[$row['date']])) {
            $viewsData[$row['date']] = (int)$row['views'];
        }
    }
    
    $signupsLabels = [];
    $signupsData = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $signupsLabels[] = date('D', strtotime($date));
        $signupsData[$date] = 0;
    }
    foreach ($dailySignupsData as $row) {
        if (isset($signupsData[$row['date']])) {
            $signupsData[$row['date']] = (int)$row['signups'];
        }
    }
    
} catch (Exception $e) {
    $usersOnline = 0;
    $newUsersToday = 0;
    $newUsersWeek = 0;
    $totalUsers = 0;
    $totalStories = 0;
    $totalChapters = 0;
    $totalViews = 0;
    $viewsLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $viewsData = [0, 0, 0, 0, 0, 0, 0];
    $signupsLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $signupsData = [0, 0, 0, 0, 0, 0, 0];
    $topGenres = [];
    $countryData = [];
}
?>

<h4>📊 Site Analytics</h4>

<!-- Real-time Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Users Online</h6>
                        <h2 class="mb-0"><?= number_format($usersOnline) ?></h2>
                        <small>Last 15 minutes</small>
                    </div>
                    <i class="bi bi-people-fill fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">New Today</h6>
                        <h2 class="mb-0"><?= number_format($newUsersToday) ?></h2>
                        <small>Signups today</small>
                    </div>
                    <i class="bi bi-person-plus-fill fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">This Week</h6>
                        <h2 class="mb-0"><?= number_format($newUsersWeek) ?></h2>
                        <small>New users</small>
                    </div>
                    <i class="bi bi-graph-up fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Total Views</h6>
                        <h2 class="mb-0"><?= number_format($totalViews) ?></h2>
                        <small>All-time</small>
                    </div>
                    <i class="bi bi-eye-fill fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Platform Totals -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="text-muted">Total Users</h5>
                <h2 class="text-primary"><?= number_format($totalUsers) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="text-muted">Total Stories</h5>
                <h2 class="text-success"><?= number_format($totalStories) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="text-muted">Total Chapters</h5>
                <h2 class="text-info"><?= number_format($totalChapters) ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6>📈 Daily Story Views (Last 7 days)</h6>
            </div>
            <div class="card-body">
                <canvas id="viewsChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6>👥 User Signups (Last 7 days)</h6>
            </div>
            <div class="card-body">
                <canvas id="usersChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Additional Stats Row -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6>🔥 Trending Genres</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($topGenres)): ?>
                    <canvas id="genresChart" height="150"></canvas>
                <?php else: ?>
                    <p class="text-muted text-center">No genre data available</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6>🌍 User Countries</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($countryData)): ?>
                    <canvas id="countryChart" height="150"></canvas>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <p>Country tracking not yet enabled.</p>
                        <small>Add a 'country' column to users table to enable.</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Views Chart
const ctx1 = document.getElementById('viewsChart').getContext('2d');
new Chart(ctx1, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_values($viewsLabels)) ?>,
        datasets: [{
            label: 'Views',
            data: <?= json_encode(array_values($viewsData)) ?>,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            fill: true,
            tension: 0.3
        }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Users Chart
const ctx2 = document.getElementById('usersChart').getContext('2d');
new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_values($signupsLabels)) ?>,
        datasets: [{
            label: 'New Users',
            data: <?= json_encode(array_values($signupsData)) ?>,
            backgroundColor: '#764ba2'
        }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});

<?php if (!empty($topGenres)): ?>
// Genres Chart
const ctx3 = document.getElementById('genresChart').getContext('2d');
new Chart(ctx3, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($topGenres, 'genre')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($topGenres, 'count')) ?>,
            backgroundColor: [
                '#667eea', '#764ba2', '#f59e0b', '#10b981', '#ef4444',
                '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16', '#f97316'
            ]
        }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'right' }
        }
    }
});
<?php endif; ?>

<?php if (!empty($countryData)): ?>
// Country Chart
const ctx4 = document.getElementById('countryChart').getContext('2d');
new Chart(ctx4, {
    type: 'pie',
    data: {
        labels: <?= json_encode(array_column($countryData, 'country')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($countryData, 'count')) ?>,
            backgroundColor: [
                '#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6',
                '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1'
            ]
        }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'right' }
        }
    }
});
<?php endif; ?>

// Auto-refresh every 60 seconds
setTimeout(() => location.reload(), 60000);
</script>
