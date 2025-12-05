<?php
// api/post-reply.php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();

$thread_id = (int)$_POST['thread_id'];
$parent_id = $_POST['parent_id'] ? (int)$_POST['parent_id'] : null;
$content = trim($_POST['content'] ?? '');

// Allow short replies (including single-link replies). Store raw content and sanitize on render.
if (strlen($content) === 0) exit(json_encode(['success' => false, 'error' => 'Empty reply']));

try {
	$stmt = $pdo->prepare("INSERT INTO discussion_replies (topic_id, parent_id, content, author_id, created_at) VALUES (?, ?, ?, ?, NOW())");
	$success = $stmt->execute([$thread_id, $parent_id, $content, $_SESSION['user_id']]);
} catch (Exception $e) {
	$success = false;
}

echo json_encode(['success' => (bool)$success]);
?>