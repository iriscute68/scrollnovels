<?php
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';

$storyId = (int)($_GET['story_id'] ?? 0);
$chapterId = (int)($_GET['chapter_id'] ?? 0);

if (!$storyId) {
    echo json_encode(['comments' => [], 'error' => 'Missing story_id']);
    exit;
}

// Create comments table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS book_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        story_id INT NOT NULL,
        chapter_id INT DEFAULT NULL,
        user_id INT NOT NULL,
        reply_to INT DEFAULT NULL,
        content LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
        FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reply_to) REFERENCES book_comments(id) ON DELETE SET NULL,
        INDEX (story_id),
        INDEX (chapter_id),
        INDEX (user_id),
        INDEX (reply_to)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    // Table already exists
}

try {
    if ($chapterId) {
        // Get all comments (main and replies) for specific chapter
        $stmt = $pdo->prepare("
            SELECT 
                bc.id, 
                bc.content, 
                bc.created_at,
                bc.user_id,
                bc.reply_to,
                u.username,
                u.profile_image
            FROM book_comments bc
            LEFT JOIN users u ON bc.user_id = u.id
            WHERE bc.story_id = ? AND bc.chapter_id = ?
            ORDER BY bc.reply_to ASC, bc.created_at ASC
        ");
        $stmt->execute([$storyId, $chapterId]);
    } else {
        // Get all comments for story (main and replies)
        $stmt = $pdo->prepare("
            SELECT 
                bc.id, 
                bc.content, 
                bc.created_at,
                bc.user_id,
                bc.reply_to,
                u.username,
                u.profile_image
            FROM book_comments bc
            LEFT JOIN users u ON bc.user_id = u.id
            WHERE bc.story_id = ?
            ORDER BY bc.reply_to ASC, bc.created_at ASC
        ");
        $stmt->execute([$storyId]);
    }
    $allComments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize comments into threads (main comment + replies)
    $comments = [];
    $replies = [];
    
    foreach ($allComments as $comment) {
        if (empty($comment['reply_to'])) {
            // Main comment
            $comment['replies'] = [];
            $comments[] = $comment;
        } else {
            // Reply - store for linking to parent
            $replies[] = $comment;
        }
    }
    
    // Attach replies to their parent comments
    foreach ($replies as $reply) {
        foreach ($comments as &$comment) {
            if ($comment['id'] == $reply['reply_to']) {
                $comment['replies'][] = $reply;
                break;
            }
        }
    }
    
    echo json_encode(['success' => true, 'comments' => $comments]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'comments' => [], 'error' => $e->getMessage()]);
}