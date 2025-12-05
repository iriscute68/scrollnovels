<?php
// api/pin-thread.php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();
if (!hasRole('admin')) exit;

$thread_id = (int)$_POST['thread_id'];
$stmt = $pdo->prepare("UPDATE forum_topics SET pinned = NOT pinned WHERE id = ?");
$stmt->execute([$thread_id]);
header("Location: thread.php?id=$thread_id");
?>