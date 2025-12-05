<?php
// api/unread-count.php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$count->execute([$_SESSION['user_id']]);
echo $count->fetchColumn();
?>