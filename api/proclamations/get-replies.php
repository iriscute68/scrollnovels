<?php
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(dirname(__DIR__)) . '/config/db.php';

$proclamationId = $_GET['proclamation_id'] ?? null;

if (!$proclamationId) {
    echo json_encode(['success' => false, 'error' => 'Proclamation ID required']);
    exit;
}

// Create proclamation_replies table if it doesn't exist
try {
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
    // Table already exists or error
}

try {
    $stmt = $pdo->prepare("
        SELECT pr.*, u.username, u.profile_image
        FROM proclamation_replies pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.proclamation_id = ?
        ORDER BY pr.created_at ASC
        LIMIT 50
    ");
    $stmt->execute([$proclamationId]);
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'replies' => $replies]);
} catch (Exception $e) {
    error_log('Error fetching replies: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error', 'replies' => []]);
}
