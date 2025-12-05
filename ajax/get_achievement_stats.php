<?php
// admin/ajax/get_achievement_stats.php - Get user achievement statistics
require_once __DIR__ . '/../../config.php';
session_start();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode([
        'stats' => [
            'unlocked' => 0,
            'total' => 0,
            'progress_percent' => 0
        ]
    ]);
    exit;
}

try {
    // Get total achievements
    $total_stmt = $pdo->query("SELECT COUNT(*) as total FROM achievements");
    $total = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Get unlocked achievements for user
    $unlocked_stmt = $pdo->prepare("
        SELECT COUNT(*) as unlocked 
        FROM user_achievements 
        WHERE user_id = ? AND unlocked_at IS NOT NULL
    ");
    $unlocked_stmt->execute([$user_id]);
    $unlocked = $unlocked_stmt->fetch(PDO::FETCH_ASSOC)['unlocked'] ?? 0;

    // Calculate progress
    $progress_percent = $total > 0 ? round(($unlocked / $total) * 100) : 0;

    echo json_encode([
        'stats' => [
            'unlocked' => $unlocked,
            'total' => $total,
            'progress_percent' => $progress_percent
        ],
        'user_id' => $user_id
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch stats']);
}
?>
