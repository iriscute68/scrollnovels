<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$profileUserId = intval($_GET['user_id'] ?? 0);
$limit = intval($_GET['limit'] ?? 50);
if ($limit <= 0 || $limit > 500) $limit = 50;

try {
    // Ensure proclamations table exists with basic structure
    $pdo->exec("CREATE TABLE IF NOT EXISTS proclamations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        body LONGTEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if ($profileUserId) {
        $stmt = $pdo->prepare("SELECT id, title, body as summary, body as content, created_at FROM proclamations WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bindValue(1, $profileUserId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("SELECT id, title, body as summary, body as content, created_at FROM proclamations ORDER BY created_at DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'proclamations' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    error_log('proclamations_list error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'server_error: ' . $e->getMessage()]);
}
