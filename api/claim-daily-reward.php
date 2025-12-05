<?php
// api/claim-daily-reward.php - Claim daily login points

session_start();
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

try {
    // Check if user already claimed daily reward today
    $stmt = $pdo->prepare("
        SELECT daily_login_claimed, last_login FROM user_points WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $points = $stmt->fetch();

    $today = date('Y-m-d');
    $lastLogin = $points['last_login'] ?? null;
    $alreadyClaimed = $points['daily_login_claimed'] ?? false;

    if ($lastLogin === $today && $alreadyClaimed) {
        http_response_code(400);
        echo json_encode(['error' => 'Daily reward already claimed today']);
        exit;
    }

    $dailyPoints = 10; // Free points for daily login

    // Ensure user has points record
    $stmt = $pdo->prepare("
        INSERT INTO user_points (user_id, free_points, total_points, last_login, daily_login_claimed)
        VALUES (?, ?, ?, ?, TRUE)
        ON DUPLICATE KEY UPDATE
            free_points = free_points + ?,
            total_points = total_points + ?,
            last_login = ?,
            daily_login_claimed = TRUE
    ");
    $stmt->execute([$userId, $dailyPoints, $dailyPoints, $today, $dailyPoints, $dailyPoints, $today]);

    // Log transaction
    $stmt = $pdo->prepare("
        INSERT INTO points_transactions (user_id, points, type, source)
        VALUES (?, ?, 'free', 'daily_login')
    ");
    $stmt->execute([$userId, $dailyPoints]);

    echo json_encode([
        'success' => true,
        'message' => "✓ Claimed $dailyPoints daily points!",
        'points_earned' => $dailyPoints,
        'total_points' => $points['total_points'] + $dailyPoints ?? $dailyPoints
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
