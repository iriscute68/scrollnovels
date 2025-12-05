<?php
// api/follow.php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();

$user_id = (int)$_POST['user_id'];
if ($user_id == $_SESSION['user_id']) exit;

$stmt = $pdo->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ?");
$stmt->execute([$_SESSION['user_id'], $user_id]);
$exists = $stmt->fetch();

if ($exists) {
    $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?")
        ->execute([$_SESSION['user_id'], $user_id]);
    $following = false;
} else {
    $pdo->prepare("INSERT INTO follows (follower_id, following_id) VALUES (?, ?)")
        ->execute([$_SESSION['user_id'], $user_id]);
    $following = true;

    // Notify
    require_once '../includes/functions.php';
    notify($pdo, $user_id, $_SESSION['user_id'], 'follow', "started following you!", "/profile.php?user=" . $_SESSION['username']);
}

echo json_encode(['following' => $following]);
?>