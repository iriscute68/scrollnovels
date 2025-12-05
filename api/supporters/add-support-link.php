<?php
// api/supporters/add-support-link.php - Add Ko-fi or Patreon link
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$link_type = trim($data['link_type'] ?? '');
$link_url = trim($data['link_url'] ?? '');
$author_id = (int)($_SESSION['user_id']);

if (!in_array($link_type, ['kofi', 'patreon', 'paypal'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid link type']);
    exit;
}

if (empty($link_url)) {
    echo json_encode(['success' => false, 'error' => 'Link URL is required']);
    exit;
}

// Validate URL format
if (!filter_var($link_url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid URL format']);
    exit;
}

try {
    // Create tables if they don't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS author_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        author_id INT NOT NULL,
        link_type ENUM('kofi', 'patreon', 'paypal') NOT NULL,
        link_url VARCHAR(500) NOT NULL,
        patreon_access_token VARCHAR(500),
        patreon_refresh_token VARCHAR(500),
        patreon_expires_at TIMESTAMP NULL,
        is_verified TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_author_type (author_id, link_type),
        INDEX idx_author (author_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Insert or update the link
    $stmt = $pdo->prepare("
        INSERT INTO author_links (author_id, link_type, link_url, is_verified)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE 
            link_url = VALUES(link_url),
            updated_at = NOW()
    ");
    
    if ($stmt->execute([$author_id, $link_type, $link_url])) {
        echo json_encode(['success' => true, 'message' => ucfirst($link_type) . ' link added successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save link']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
