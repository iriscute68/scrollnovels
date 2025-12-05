<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Handle both form POST and AJAX JSON
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Not logged in']));
}

$userId = $_SESSION['user_id'];

// Get data from POST or JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['content'])) {
        // Form submission
        $postId = intval($_POST['post_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $replyToId = intval($_POST['reply_to_id'] ?? 0);
        $isJson = false;
    } else {
        // AJAX JSON submission
        $input = json_decode(file_get_contents('php://input'), true);
        $postId = intval($input['post_id'] ?? 0);
        $content = trim($input['content'] ?? '');
        $replyToId = intval($input['reply_to_id'] ?? 0);
        $isJson = true;
    }
} else {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid request']));
}

if (!$postId || empty($content)) {
    if ($isJson) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Post ID and content required']));
    } else {
        header('Location: ' . SITE_URL . '/pages/community.php');
        exit;
    }
}

try {
    // Ensure community_replies table exists with reply_to_id support
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        author_id INT NOT NULL,
        reply_to_id INT,
        content LONGTEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reply_to_id) REFERENCES community_replies(id) ON DELETE CASCADE,
        INDEX (post_id),
        INDEX (reply_to_id),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Add reply_to_id column if it doesn't exist (for backwards compatibility)
    try {
        $pdo->exec("ALTER TABLE community_replies ADD COLUMN reply_to_id INT DEFAULT NULL");
    } catch (Exception $e) {
        // Column might already exist
    }
    
    // Validate that the reply_to_id (if provided) exists and belongs to the same post
    if ($replyToId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM community_replies WHERE id = ? AND post_id = ?");
        $stmt->execute([$replyToId, $postId]);
        if (!$stmt->fetch()) {
            if ($isJson) {
                http_response_code(400);
                exit(json_encode(['success' => false, 'message' => 'Invalid reply_to_id']));
            } else {
                $replyToId = 0; // Fall back to top-level reply
            }
        }
    }
    
    // Insert the reply
    if ($replyToId > 0) {
        $stmt = $pdo->prepare("INSERT INTO community_replies (post_id, author_id, reply_to_id, content, created_at) 
            VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$postId, $userId, $replyToId, $content]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO community_replies (post_id, author_id, content, created_at) 
            VALUES (?, ?, ?, NOW())");
        $stmt->execute([$postId, $userId, $content]);
    }
    
    if ($isJson) {
        exit(json_encode(['success' => true, 'message' => 'Reply posted successfully']));
    } else {
        // Redirect on form submission success
        header('Location: ' . SITE_URL . '/pages/community-thread.php?id=' . $postId);
        exit;
    }
} catch (Exception $e) {
    error_log('Community reply error: ' . $e->getMessage());
    if ($isJson) {
        http_response_code(500);
        exit(json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]));
    } else {
        header('Location: ' . SITE_URL . '/pages/community.php');
        exit;
    }
}
