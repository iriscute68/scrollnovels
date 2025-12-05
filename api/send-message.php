<?php
// api/send-message.php - wrapper to send chat messages
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$conv_id = (int)($_POST['conv_id'] ?? 0);
$content = trim($_POST['content'] ?? '');

if (!$conv_id || !$content) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT 1 FROM conversations WHERE id = ? AND JSON_CONTAINS(participants, ?)");
$stmt->execute([$conv_id, json_encode($_SESSION['user_id'])]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO chat_messages (conv_id, user_id, content, status) VALUES (?, ?, ?, 'sent')");
$stmt->execute([$conv_id, $_SESSION['user_id'], $content]);

// Update conversation timestamp
$pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$conv_id]);

echo json_encode(['success' => true]);
?>
