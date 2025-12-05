<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

require_admin();

$title = isset($_POST["title"]) ? $_POST["title"] : "";
$slug = isset($_POST["slug"]) ? $_POST["slug"] : "";
$content = isset($_POST["content"]) ? $_POST["content"] : "";
$author = current_user_id();

$thumbnail = null;

if (!empty($_FILES["thumbnail"]["name"])) {
    $target = __DIR__ . "/../uploads/blogs/" . time() . "_" . basename($_FILES["thumbnail"]["name"]);
    if (!is_dir(dirname($target))) mkdir(dirname($target), 0755, true);
    move_uploaded_file($_FILES["thumbnail"]["tmp_name"], $target);
    $thumbnail = "/uploads/blogs/" . time() . "_" . basename($_FILES["thumbnail"]["name"]);
}

$stmt = $pdo->prepare("INSERT INTO posts (title, slug, content, cover_image, user_id, status) VALUES (?, ?, ?, ?, ?, 'draft')");
$stmt->execute([$title, $slug, $content, $thumbnail, $author]);

header("Location: blog_list_new.php");
exit;
?>
