<?php
// api/unread-notifications.php - Get unread notification count
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'unread' => 0]);
    exit;
}

require_once dirname(__DIR__) . '/config/db.php';

try {
    // Create notifications table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50),
        title VARCHAR(255),
        message LONGTEXT,
        related_id INT,
        is_read BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_read (user_id, is_read),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Count unread notifications
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'unread' => $result['unread_count'] ?? 0
    ]);
    
} catch (Exception $e) {
    error_log('Notification error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'unread' => 0, 'error' => $e->getMessage()]);
}
?>
