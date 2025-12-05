<?php
// api/approve-story.php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();
if (!hasRole('admin')) exit;

$story_id = (int)$_POST['story_id'];
$pdo->prepare("UPDATE stories SET status = 'published' WHERE id = ?")->execute([$story_id]);
header('Location: ' . rtrim(SITE_URL, '/') . '/admin/admin.php#stories');
?>