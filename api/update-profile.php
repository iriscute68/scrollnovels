<?php
// api/update-profile.php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();

$bio = trim($_POST['bio']);
$website = trim($_POST['website']);
$avatar_path = $user['avatar'] ?? null;

if ($_FILES['avatar']['size'] > 0) {
    $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
    $avatar_path = "uploads/avatars/" . $_SESSION['user_id'] . ".$ext";
    move_uploaded_file($_FILES['avatar']['tmp_name'], "../$avatar_path");
}

$pdo->prepare("UPDATE users SET bio = ?, website = ?, avatar = ? WHERE id = ?")
    ->execute([$bio, $website, $avatar_path, $_SESSION['user_id']]);

header("Location: /profile.php?user=" . $_SESSION['username']);
?>