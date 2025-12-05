<?php
// admin/ajax/get_genres.php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');
session_start();

if (!isApprovedAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    global $pdo;
    
    // Check if genres table exists, if not return demo data
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'genres'");
    $tableExists = $stmt->fetchColumn() > 0;
    
    if ($tableExists) {
        $stmt = $pdo->query("SELECT id, name FROM genres ORDER BY name ASC");
        $genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Demo genres
        $genres = [
            ['id' => 1, 'name' => 'Romance'],
            ['id' => 2, 'name' => 'Fantasy'],
            ['id' => 3, 'name' => 'Science Fiction'],
            ['id' => 4, 'name' => 'Mystery'],
            ['id' => 5, 'name' => 'Thriller'],
            ['id' => 6, 'name' => 'Historical'],
            ['id' => 7, 'name' => 'Adventure'],
            ['id' => 8, 'name' => 'Drama'],
        ];
    }
    
    echo json_encode(['ok' => true, 'genres' => $genres]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
