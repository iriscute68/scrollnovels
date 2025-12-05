<?php
// admin/comments_action.php - handle delete or warn for comments
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/../inc/roles_permissions.php';

if (!isAdminLoggedIn()) {
    http_response_code(403);
    exit('Forbidden');
}

$action = $_POST['action'] ?? '';
$comment_id = (int)($_POST['comment_id'] ?? 0);

if (!$comment_id) {
    $_SESSION['admin_error'] = 'Missing comment id';
    header('Location: comments.php'); exit;
}

try {
    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM comments WHERE id = ?')->execute([$comment_id]);
        $_SESSION['admin_success'] = 'Comment deleted';
    } elseif ($action === 'warn') {
        // Fetch comment and user
        $c = $pdo->prepare('SELECT user_id FROM comments WHERE id = ? LIMIT 1');
        $c->execute([$comment_id]);
        $uid = $c->fetchColumn();
        if ($uid) {
            // create user_warnings table if missing
            $pdo->exec("CREATE TABLE IF NOT EXISTS user_warnings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED,
                admin_id INT UNSIGNED,
                reason TEXT,
                issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $note = 'Warning issued for comment id ' . $comment_id;
            $admin_id = $_SESSION['admin_id'] ?? null;
            $pdo->prepare('INSERT INTO user_warnings (user_id, admin_id, reason) VALUES (?, ?, ?)')->execute([$uid, $admin_id, $note]);
            $_SESSION['admin_success'] = 'User warned and warning recorded';
        } else {
            $_SESSION['admin_error'] = 'Unable to find user for comment';
        }
    }
} catch (Exception $e) {
    $_SESSION['admin_error'] = 'Error: ' . $e->getMessage();
}

header('Location: comments.php');
exit;
