<?php
// ajax/unlock_achievement.php - Unlock a user achievement
require_once __DIR__ . '/../config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$achievement_id = intval($_POST['achievement_id'] ?? 0);

if (!$achievement_id) {
    echo json_encode(['ok' => false, 'message' => 'Missing achievement_id']);
    exit;
}

try {
    // Check if already unlocked
    $check = $pdo->prepare("
        SELECT id FROM user_achievements 
        WHERE user_id = ? AND achievement_id = ? AND unlocked_at IS NOT NULL
    ");
    $check->execute([$user_id, $achievement_id]);
    
    if ($check->rowCount() > 0) {
        echo json_encode(['ok' => false, 'message' => 'Already unlocked']);
        exit;
    }

    // Get achievement details
    $ach = $pdo->prepare("SELECT * FROM achievements WHERE id = ?");
    $ach->execute([$achievement_id]);
    $achievement = $ach->fetch(PDO::FETCH_ASSOC);

    if (!$achievement) {
        echo json_encode(['ok' => false, 'message' => 'Achievement not found']);
        exit;
    }

    // Unlock or create record
    $stmt = $pdo->prepare("
        INSERT INTO user_achievements (user_id, achievement_id, unlocked_at, created_at)
        VALUES (?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE unlocked_at = NOW()
    ");
    $stmt->execute([$user_id, $achievement_id]);

    // Log the unlock
    $pdo->prepare("
        INSERT INTO admin_activity_logs (admin_id, action, created_at)
        VALUES (?, ?, NOW())
    ")->execute([$user_id, "Unlocked achievement: {$achievement['name']}"]);

    echo json_encode([
        'ok' => true,
        'message' => 'Achievement unlocked!',
        'achievement' => [
            'id' => $achievement['id'],
            'name' => $achievement['name'],
            'icon' => $achievement['icon']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
