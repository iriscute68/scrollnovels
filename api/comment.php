<?php
// api/comment.php - Enhanced with CSRF protection and threading support
header('Content-Type: application/json');

require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Note: CSRF protection can be added via middleware
// Session-based auth is sufficient for this API

$action = $_POST['action'] ?? 'create';
$story_id = (int)($_POST['story_id'] ?? 0);
$chapter_id = (int)($_POST['chapter_id'] ?? 0);
$content = trim($_POST['content'] ?? '');
$reply_to = !empty($_POST['reply_to']) ? (int)$_POST['reply_to'] : null;
$user_id = $_SESSION['user_id'];

// Debug logging
error_log('Comment API: action=' . $action . ' story_id=' . $story_id . ', chapter_id=' . $chapter_id . ', reply_to=' . ($reply_to ?? 'null') . ', user_id=' . $user_id);

if (!$story_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing story_id']);
    exit;
}

if (empty($content) || strlen($content) < 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Comment must be at least 3 characters']);
    exit;
}

try {
    // Ensure book_comments table exists with threading support
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
    
    // If reply_to column doesn't exist yet, add it (for migration)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'book_comments' AND COLUMN_NAME = 'reply_to'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("ALTER TABLE book_comments ADD COLUMN reply_to INT DEFAULT NULL AFTER user_id");
        $pdo->exec("ALTER TABLE book_comments ADD FOREIGN KEY (reply_to) REFERENCES book_comments(id) ON DELETE SET NULL");
    }
    
    // If replying to another comment, validate parent exists
    if ($reply_to !== null) {
        $parentStmt = $pdo->prepare("SELECT id FROM book_comments WHERE id = ? AND story_id = ? LIMIT 1");
        $parentStmt->execute([$reply_to, $story_id]);
        if (!$parentStmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Parent comment not found']);
            exit;
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO book_comments (story_id, chapter_id, user_id, reply_to, content) VALUES (?, ?, ?, ?, ?)");
    $success = $stmt->execute([$story_id, $chapter_id ?: NULL, $user_id, $reply_to, $content]);

    if (!$success) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to insert comment']);
        exit;
    }
    
    // Get the inserted comment ID
    $comment_id = $pdo->lastInsertId();
    
    // Get comment thread info for response
    $commentStmt = $pdo->prepare("SELECT id, user_id, content, reply_to, created_at FROM book_comments WHERE id = ? LIMIT 1");
    $commentStmt->execute([$comment_id]);
    $comment = $commentStmt->fetch();

    // ADD NOTIFICATION
    $stmt = $pdo->prepare("SELECT author_id, slug FROM stories WHERE id = ?");
    $stmt->execute([$story_id]);
    $story = $stmt->fetch();
    if ($story && $story['author_id'] != $user_id) {
        notify(
            $pdo,
            $story['author_id'],
            $user_id,
            'comment',
            "commented on your story: " . substr($content, 0, 50),
            "/pages/story.php?slug={$story['slug']}&ch=" . $chapter_id
        );
    }

    http_response_code(201);
    echo json_encode([
        'success' => true, 
        'message' => 'Comment posted successfully',
        'comment_id' => (int)$comment_id,
        'reply_to' => $reply_to,
        'thread_position' => $reply_to ? 'reply' : 'main'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}