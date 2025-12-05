<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

header('Content-Type: application/json');

$uid = current_user_id();

if (isset($_POST['all'])) {
  $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id = ?")->execute([$uid]);
  $pdo->prepare("INSERT INTO notification_logs (actor_id, action) VALUES (?, 'mark_all_read')")->execute([$uid]);
} else {
  $id = intval($_POST['id'] ?? 0);
  $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id = ? AND user_id = ?")->execute([$id, $uid]);
  $pdo->prepare("INSERT INTO notification_logs (notification_id, actor_id, action) VALUES (?, ?, 'read')")->execute([$id, $uid]);
}

echo json_encode(['ok' => true]);
?>
