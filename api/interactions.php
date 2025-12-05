<?php
// api/interactions.php - Handle interactions (likes, reading status, etc.)
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_REQUEST;
$action = $input['action'] ?? '';
$story_id = (int)($input['story_id'] ?? 0);
$review_id = (int)($input['id'] ?? 0);
$status = $input['status'] ?? '';

try {
    if ($action === 'set_reading_status' && $story_id && $status) {
        // Create table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_list_status (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            story_id INT NOT NULL,
            status ENUM('reading', 'planned', 'completed', 'abandoned') DEFAULT 'reading',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_story (user_id, story_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $stmt = $pdo->prepare("INSERT INTO user_list_status (user_id, story_id, status) VALUES (?, ?, ?) 
                              ON DUPLICATE KEY UPDATE status = ?");
        $stmt->execute([$_SESSION['user_id'], $story_id, $status, $status]);
        echo json_encode(['success' => true]);
    } 
    elseif ($action === 'like_review' && $review_id) {
        // Create table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS review_interactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            review_id INT NOT NULL,
            user_id INT NOT NULL,
            type ENUM('like', 'dislike') DEFAULT 'like',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_review_type (user_id, review_id, type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $stmt = $pdo->prepare("INSERT INTO review_interactions (review_id, user_id, type) VALUES (?, ?, 'like')
                              ON DUPLICATE KEY UPDATE type = 'like'");
        $stmt->execute([$review_id, $_SESSION['user_id']]);
        
        // Get updated like/dislike counts
        $stmt = $pdo->prepare("SELECT 
            (SELECT COUNT(*) FROM review_interactions WHERE review_id = ? AND type = 'like') as likes,
            (SELECT COUNT(*) FROM review_interactions WHERE review_id = ? AND type = 'dislike') as dislikes");
        $stmt->execute([$review_id, $review_id]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'likes' => $counts['likes'], 'dislikes' => $counts['dislikes']]);
    }
    elseif ($action === 'dislike_review' && $review_id) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS review_interactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            review_id INT NOT NULL,
            user_id INT NOT NULL,
            type ENUM('like', 'dislike') DEFAULT 'like',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_review_type (user_id, review_id, type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $stmt = $pdo->prepare("INSERT INTO review_interactions (review_id, user_id, type) VALUES (?, ?, 'dislike')
                              ON DUPLICATE KEY UPDATE type = 'dislike'");
        $stmt->execute([$review_id, $_SESSION['user_id']]);
        
        // Get updated like/dislike counts
        $stmt = $pdo->prepare("SELECT 
            (SELECT COUNT(*) FROM review_interactions WHERE review_id = ? AND type = 'like') as likes,
            (SELECT COUNT(*) FROM review_interactions WHERE review_id = ? AND type = 'dislike') as dislikes");
        $stmt->execute([$review_id, $review_id]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'likes' => $counts['likes'], 'dislikes' => $counts['dislikes']]);
    }
    elseif ($action === 'get_review_counts' && $review_id) {
        // Ensure table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS review_interactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            review_id INT NOT NULL,
            user_id INT NOT NULL,
            type ENUM('like', 'dislike') DEFAULT 'like',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_review_type (user_id, review_id, type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Get like/dislike counts for a review
        $stmt = $pdo->prepare("SELECT 
            (SELECT COUNT(*) FROM review_interactions WHERE review_id = ? AND type = 'like') as likes,
            (SELECT COUNT(*) FROM review_interactions WHERE review_id = ? AND type = 'dislike') as dislikes");
        $stmt->execute([$review_id, $review_id]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'likes' => (int)$counts['likes'], 'dislikes' => (int)$counts['dislikes']]);
    }
    else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log('Interactions API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}