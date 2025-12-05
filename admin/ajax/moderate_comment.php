<?php
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
    
    $commentId = $_POST['comment_id'] ?? 0;
    $action = $_POST['action'] ?? 'hide'; // hide, show, pin, unpin
    
    if (!$commentId) {
        echo json_encode(['error' => 'Missing comment_id']);
        exit;
    }
    
    if ($action === 'hide') {
        $stmt = $pdo->prepare("UPDATE comments SET is_deleted = 1, moderated_by = ?, moderated_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $commentId]);
        $message = 'Comment hidden';
    } elseif ($action === 'show') {
        $stmt = $pdo->prepare("UPDATE comments SET is_deleted = 0, moderated_by = ?, moderated_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $commentId]);
        $message = 'Comment unhidden';
    } elseif ($action === 'pin') {
        $stmt = $pdo->prepare("UPDATE comments SET is_pinned = 1 WHERE id = ?");
        $stmt->execute([$commentId]);
        $message = 'Comment pinned';
    } elseif ($action === 'unpin') {
        $stmt = $pdo->prepare("UPDATE comments SET is_pinned = 0 WHERE id = ?");
        $stmt->execute([$commentId]);
        $message = 'Comment unpinned';
    } else {
        echo json_encode(['error' => 'Invalid action']);
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO moderation_logs (admin_id, action, reason, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], "moderate_comment_$action", "Comment $commentId"]);
    
    echo json_encode(['ok' => true, 'message' => $message]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
