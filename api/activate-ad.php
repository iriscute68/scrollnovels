<?php
// api/activate-ad.php - Activate an ad and create sponsored book entry
session_start();
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Check admin auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Login required']));
}

// Verify admin role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !in_array($user['role'], ['admin', 'super_admin', 'moderator'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Admin access required']));
}

// Get ad_id from POST body
$input = $_POST;
if (empty($input['ad_id'])) {
    parse_str(file_get_contents('php://input'), $input);
}
$adId = (int)($input['ad_id'] ?? 0);

if (!$adId) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Ad ID required']));
}

try {
    // Get ad details
    $stmt = $pdo->prepare("SELECT * FROM ads WHERE id = ?");
    $stmt->execute([$adId]);
    $ad = $stmt->fetch();
    
    if (!$ad) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'error' => 'Ad not found']));
    }
    
    // Update ad status to active
    $stmt = $pdo->prepare("UPDATE ads SET status = 'active', approved_at = NOW() WHERE id = ?");
    $stmt->execute([$adId]);
    
    // If ad has a story_id, add it to sponsored_books
    if (!empty($ad['story_id'])) {
        // Create sponsored_books table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS sponsored_books (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ad_id INT NOT NULL,
            story_id INT NOT NULL,
            placement VARCHAR(50) DEFAULT 'homepage',
            priority INT DEFAULT 0,
            start_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            end_date DATETIME NULL,
            status ENUM('active','paused','ended') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_placement (placement)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Insert into sponsored_books or update existing
        $stmt = $pdo->prepare("INSERT INTO sponsored_books (ad_id, story_id, placement, status) VALUES (?, ?, ?, 'active')");
        try {
            $stmt->execute([$adId, $ad['story_id'], $ad['placement'] ?? 'homepage']);
        } catch (Exception $e) {
            // May already exist, update instead
            $stmt = $pdo->prepare("UPDATE sponsored_books SET status = 'active' WHERE ad_id = ?");
            $stmt->execute([$adId]);
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Ad activated successfully']);
    
} catch (Exception $e) {
    error_log('Activate ad error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>