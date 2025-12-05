<?php
// api/send-message.php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();

$conv_id = (int)$_POST['conv_id'];
$content = trim($_POST['content']);

if (!$conv_id || !$content) exit;

$stmt = $pdo->prepare("SELECT 1 FROM conversations WHERE id = ? AND JSON_CONTAINS(participants, ?)");
$stmt->execute([$conv_id, json_encode($_SESSION['user_id'])]);
if (!$stmt->fetch()) exit;

$stmt = $pdo->prepare("INSERT INTO chat_messages (conv_id, user_id, content, status) VALUES (?, ?, ?, 'sent')");
$stmt->execute([$conv_id, $_SESSION['user_id'], $content]);

// Update conversation timestamp
$pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$conv_id]);

echo json_encode(['success' => true]);
?>