<?php
/**
 * API: Mark community post or reply as helpful
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in first']);
    exit;
}

$userId = $_SESSION['user_id'];
$type = $_POST['type'] ?? '';
$id = intval($_POST['id'] ?? 0);

if (!in_array($type, ['post', 'reply']) || !$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    // Create helpful_votes table if doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS helpful_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content_type ENUM('post', 'reply') NOT NULL,
        content_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_vote (user_id, content_type, content_id),
        INDEX (content_type, content_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Check if user already voted
    $stmt = $pdo->prepare("SELECT id FROM helpful_votes WHERE user_id = ? AND content_type = ? AND content_id = ?");
    $stmt->execute([$userId, $type, $id]);
    
    if ($stmt->fetch()) {
        // Remove vote (toggle off)
        $stmt = $pdo->prepare("DELETE FROM helpful_votes WHERE user_id = ? AND content_type = ? AND content_id = ?");
        $stmt->execute([$userId, $type, $id]);
        
        // Get new count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM helpful_votes WHERE content_type = ? AND content_id = ?");
        $stmt->execute([$type, $id]);
        $count = $stmt->fetchColumn();
        
        echo json_encode(['success' => true, 'action' => 'removed', 'count' => $count]);
    } else {
        // Add vote
        $stmt = $pdo->prepare("INSERT INTO helpful_votes (user_id, content_type, content_id) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $type, $id]);
        
        // Get new count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM helpful_votes WHERE content_type = ? AND content_id = ?");
        $stmt->execute([$type, $id]);
        $count = $stmt->fetchColumn();
        
        echo json_encode(['success' => true, 'action' => 'added', 'count' => $count]);
    }
} catch (Exception $e) {
    error_log('Helpful vote error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
