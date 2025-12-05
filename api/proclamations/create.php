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
$content = trim($input['content'] ?? '');

if (!$content) {
    echo json_encode(['success' => false, 'error' => 'Content is required']);
    exit;
}

$userId = $_SESSION['user_id'];

// Create proclamations table if it doesn't exist
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
} catch (Exception $e) {
    // Table already exists or error
}

try {
    $stmt = $pdo->prepare("INSERT INTO proclamations (user_id, content) VALUES (?, ?)");
    $stmt->execute([$userId, $content]);
    
    echo json_encode(['success' => true, 'proclamation_id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    error_log('Error creating proclamation: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
