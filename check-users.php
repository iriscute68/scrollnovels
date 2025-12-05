<?php
require 'config/db.php';
$stmt = $pdo->prepare('SELECT id, username, role, is_admin FROM users WHERE id IN (1,2,5,7)');
$stmt->execute([]);
$rows = $stmt->fetchAll();
foreach ($rows as $row) {
    echo 'ID: ' . $row['id'] . ', Username: ' . $row['username'] . ', Role: ' . $row['role'] . ', is_admin: ' . $row['is_admin'] . "\n";
}
?>
