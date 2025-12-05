<?php
// api/supporters/get-user-points.php - Get current user's points balance
session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Ensure user_points table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_points (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL UNIQUE,
        points INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_points (points)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Table exists
}

// Get or create user points record
try {
    $stmt = $pdo->prepare("INSERT INTO user_points (user_id, points) VALUES (?, 0) ON DUPLICATE KEY UPDATE user_id=user_id");
    $stmt->execute([$user_id]);
    
    $stmt = $pdo->prepare("SELECT points FROM user_points WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    $points = $result ? (int)$result['points'] : 0;
    
    echo json_encode([
        'success' => true,
        'points' => $points
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching points: ' . $e->getMessage()
    ]);
}
