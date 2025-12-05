<?php
require 'config/db.php';
$stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE id = 1');
$stmt->execute([]);
$user = $stmt->fetch();
echo "User 1: ";
var_dump($user);
?>
