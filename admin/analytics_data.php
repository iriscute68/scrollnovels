<?php
// admin/analytics_data.php - returns JSON analytics via PDO
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$range = $_GET['range'] ?? 'daily';
try {
    // DAILY STORY VIEWS (last 30 days default)
    $viewsStmt = $pdo->prepare("SELECT DATE(viewed_at) as day, COUNT(*) as views FROM story_views WHERE viewed_at >= DATE(NOW() - INTERVAL 30 DAY) GROUP BY DATE(viewed_at) ORDER BY day ASC");
    $viewsStmt->execute();
    $dailyViews = $viewsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $dailyViews = [];
}

try {
    $donStmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as total FROM donations WHERE created_at >= DATE(NOW() - INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC");
    $donStmt->execute();
    $donations = $donStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $donations = [];
}

try {
    $userStmt = $pdo->query("SELECT country, COUNT(*) as total FROM users GROUP BY country ORDER BY total DESC LIMIT 50");
    $userDistribution = $userStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $userDistribution = [];
}

try {
    $statusStmt = $pdo->query("SELECT status, COUNT(*) as total FROM stories GROUP BY status");
    $storyStatus = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $storyStatus = [];
}

// Top supporters fallback
try {
    $supStmt = $pdo->query("SELECT u.id, u.username, s.total_amount FROM supporters s INNER JOIN users u ON s.user_id = u.id ORDER BY s.total_amount DESC LIMIT 10");
    $topSupporters = $supStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $topSupporters = [];
}

echo json_encode([
    'dailyViews' => $dailyViews,
    'donations' => $donations,
    'users' => $userDistribution,
    'storyStatus' => $storyStatus,
    'supporters' => $topSupporters
]);
