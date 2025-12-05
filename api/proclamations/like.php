<?php
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(dirname(__DIR__)) . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$proclamationId = (int)($input['proclamation_id'] ?? 0);

if (!$proclamationId) {
    echo json_encode(['success' => false, 'error' => 'Proclamation ID required']);
    exit;
}

$userId = $_SESSION['user_id'];

// Create proclamation_likes table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS proclamation_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proclamation_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (proclamation_id, user_id),
        FOREIGN KEY (proclamation_id) REFERENCES proclamations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Table already exists or error
}

try {
    // Check if already liked
    $stmt = $pdo->prepare("SELECT id FROM proclamation_likes WHERE proclamation_id = ? AND user_id = ?");
    $stmt->execute([$proclamationId, $userId]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Unlike
        $stmt = $pdo->prepare("DELETE FROM proclamation_likes WHERE proclamation_id = ? AND user_id = ?");
        $stmt->execute([$proclamationId, $userId]);
        echo json_encode(['success' => true, 'action' => 'unliked']);
    } else {
        // Like
        $stmt = $pdo->prepare("INSERT INTO proclamation_likes (proclamation_id, user_id) VALUES (?, ?)");
        $stmt->execute([$proclamationId, $userId]);
        echo json_encode(['success' => true, 'action' => 'liked']);
    }
} catch (Exception $e) {
    error_log('Error toggling like: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
