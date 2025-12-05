<?php
// api/proclamation_replies.php - Handle proclamation replies
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

try {
    // Create table if doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS proclamation_replies (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        proclamation_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        reply_text LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (proclamation_id) REFERENCES proclamations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (proclamation_id),
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Get replies for a proclamation
    if ($method === 'GET' && $action === 'list') {
        $proclamation_id = (int)($_GET['proclamation_id'] ?? 0);
        if (!$proclamation_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing proclamation_id']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT r.id, r.reply_text, r.created_at, u.id as user_id, u.username, u.profile_image
            FROM proclamation_replies r
            JOIN users u ON r.user_id = u.id
            WHERE r.proclamation_id = ?
            ORDER BY r.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$proclamation_id]);
        $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'replies' => $replies]);
        exit;
    }

    // Add reply (requires login)
    if ($method === 'POST' && $action === 'add') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $proclamation_id = (int)($_POST['proclamation_id'] ?? 0);
        $reply_text = trim($_POST['reply_text'] ?? '');

        if (!$proclamation_id || empty($reply_text) || strlen($reply_text) > 1000) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid reply']);
            exit;
        }

        // Verify proclamation exists
        $check = $pdo->prepare("SELECT id FROM proclamations WHERE id = ?")->execute([$proclamation_id]);
        if (!$check) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Proclamation not found']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO proclamation_replies (proclamation_id, user_id, reply_text) VALUES (?, ?, ?)");
        $stmt->execute([$proclamation_id, $_SESSION['user_id'], $reply_text]);

        echo json_encode(['success' => true, 'message' => 'Reply posted']);
        exit;
    }

    // Delete reply (owner or admin only)
    if ($method === 'POST' && $action === 'delete') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $reply_id = (int)($_POST['reply_id'] ?? 0);
        if (!$reply_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid reply']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM proclamation_replies WHERE id = ? AND user_id = ?");
        $result = $stmt->execute([$reply_id, $_SESSION['user_id']]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Reply deleted']);
        } else {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You cannot delete this reply']);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);

} catch (Exception $e) {
    http_response_code(500);
    error_log('proclamation_replies error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
