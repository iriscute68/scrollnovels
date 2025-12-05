<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

header('Content-Type: application/json');

$uid = current_user_id();
$limit = intval($_GET['limit'] ?? 10);

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
$stmt->execute([$uid, $limit]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unread->execute([$uid]);
$unreadCount = $unread->fetchColumn();

echo json_encode(['unread' => $unreadCount, 'items' => $items]);
?>
