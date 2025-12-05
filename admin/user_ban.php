<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

require_admin();

$id = isset($_POST["id"]) ? intval($_POST["id"]) : 0;
if ($id) {
    $stmt = $pdo->prepare("UPDATE users SET status = 'banned' WHERE id = ?");
    $stmt->execute([$id]);
}
echo "OK";
?>
