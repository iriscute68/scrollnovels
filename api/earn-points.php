<?php
// api/earn-points.php - Award points to users
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Must be logged in']);
    exit;
}

require_once dirname(__DIR__) . '/config/db.php';

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? null;

try {
    // Ensure tables exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_points (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        points INT DEFAULT 0,
        lifetime_points INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS point_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        points INT NOT NULL,
        description VARCHAR(255),
        type ENUM('earn','redeem') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Point values for actions
    $pointValues = [
        'publish_story' => 50,
        'publish_chapter' => 25,
        'write_review' => 10,
        'get_like' => 5,
        'complete_bio' => 15,
        'add_profile_pic' => 20,
        'get_verified' => 100,
        'add_support_links' => 30,
        'daily_login' => 2,
        'read_chapter' => 1,
    ];
    
    if (!$action || !isset($pointValues[$action])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit;
    }
    
    $points = $pointValues[$action];
    
    // Check if already earned (for one-time actions)
    if (in_array($action, ['complete_bio', 'add_profile_pic', 'add_support_links'])) {
        $stmt = $pdo->prepare("SELECT id FROM point_transactions WHERE user_id = ? AND type = 'earn' AND description LIKE ?");
        $stmt->execute([$userId, "%$action%"]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Already completed this action']);
            exit;
        }
    }
    
    // Get or create user points record
    $stmt = $pdo->prepare("SELECT id FROM user_points WHERE user_id = ?");
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) {
        $pdo->prepare("INSERT INTO user_points (user_id, points, lifetime_points) VALUES (?, ?, ?)")->execute([$userId, 0, 0]);
    }
    
    // Add points
    $pdo->prepare("
        UPDATE user_points 
        SET points = points + ?, lifetime_points = lifetime_points + ?
        WHERE user_id = ?
    ")->execute([$points, $points, $userId]);
    
    // Log transaction
    $description = ucwords(str_replace('_', ' ', $action));
    $pdo->prepare("
        INSERT INTO point_transactions (user_id, points, description, type)
        VALUES (?, ?, ?, 'earn')
    ")->execute([$userId, $points, $description]);
    
    // Get updated points
    $stmt = $pdo->prepare("SELECT points, lifetime_points FROM user_points WHERE user_id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => "Earned +$points points!",
        'points' => $result['points'],
        'lifetime_points' => $result['lifetime_points'],
        'action' => $action
    ]);
    
} catch (Exception $e) {
    error_log('Point earning error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
