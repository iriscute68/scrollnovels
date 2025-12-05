<?php
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(dirname(__DIR__)) . '/config/db.php';

$userId = $_GET['user_id'] ?? null;
$currentUser = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

// Create proclamations tables if they don't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS proclamations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content LONGTEXT NOT NULL,
        images JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS proclamation_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proclamation_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (proclamation_id, user_id),
        FOREIGN KEY (proclamation_id) REFERENCES proclamations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS proclamation_replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proclamation_id INT NOT NULL,
        user_id INT NOT NULL,
        content LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (proclamation_id) REFERENCES proclamations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_proclamation (proclamation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Tables already exist or error
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.profile_image,
               (SELECT COUNT(*) FROM proclamation_likes WHERE proclamation_id = p.id) as likes_count,
               (SELECT COUNT(*) FROM proclamation_likes WHERE proclamation_id = p.id AND user_id = ?) as user_liked,
               (SELECT COUNT(*) FROM proclamation_replies WHERE proclamation_id = p.id) as replies_count
        FROM proclamations p
        JOIN users u ON p.user_id = u.id
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$currentUser ?? 0, $userId]);
    $proclamations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse images JSON
    foreach ($proclamations as &$p) {
        if ($p['images']) {
            $images = json_decode($p['images'], true);
            $p['images'] = is_array($images) && count($images) > 0 ? $images[0] : null;
        }
    }
    
    echo json_encode(['success' => true, 'proclamations' => $proclamations]);
} catch (Exception $e) {
    error_log('Error fetching proclamations: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error', 'proclamations' => []]);
}
