<?php
// api/get-messages.php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();

$conv_id = (int)($_GET['conv'] ?? 0);
$since = (int)($_GET['since'] ?? 0);

$stmt = $pdo->prepare("
    SELECT m.*, u.username,
           DATE_FORMAT(m.created_at, '%l:%i %p') AS time
    FROM chat_messages m
    JOIN users u ON m.user_id = u.id
    WHERE m.conv_id = ? AND m.id > ?
    ORDER BY m.created_at ASC
");
$stmt->execute([$conv_id, $since]);
$messages = $stmt->fetchAll();

$unread = $pdo->query("SELECT COUNT(*) FROM chat_messages WHERE conv_id = $conv_id AND user_id != {$_SESSION['user_id']} AND status != 'read'")->fetchColumn();

echo json_encode(['messages' => $messages, 'unread' => $unread]);
?>