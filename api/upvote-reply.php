<?php
// api/upvote-reply.php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();

$input = json_decode(file_get_contents('php://input'), true);
$reply_id = (int)($input['reply_id'] ?? 0);

$stmt = $pdo->prepare("SELECT 1 FROM discussion_replies WHERE id = ? AND topic_id IN (SELECT id FROM forum_topics)");
$stmt->execute([$reply_id]);
if (!$stmt->fetch()) exit;

$stmt = $pdo->prepare("SELECT 1 FROM interactions WHERE type='upvote' AND target_id=? AND target_type='reply' AND user_id=?");
$stmt->execute([$reply_id, $_SESSION['user_id']]);
$exists = $stmt->fetch();

if ($exists) {
    $pdo->prepare("DELETE FROM interactions WHERE type='upvote' AND target_id=? AND target_type='reply' AND user_id=?")
        ->execute([$reply_id, $_SESSION['user_id']]);
    $delta = -1;
} else {
    $pdo->prepare("INSERT INTO interactions (type, target_id, target_type, user_id) VALUES ('upvote', ?, 'reply', ?)")
        ->execute([$reply_id, $_SESSION['user_id']]);
    $delta = 1;
}

$pdo->prepare("UPDATE discussion_replies SET upvotes = upvotes + ? WHERE id = ?")->execute([$delta, $reply_id]);
$count = $pdo->query("SELECT upvotes FROM discussion_replies WHERE id = $reply_id")->fetchColumn();

echo json_encode(['success' => true, 'count' => $count]);
?>