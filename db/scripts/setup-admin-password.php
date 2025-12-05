<?php
require __DIR__ . '/../../config/db.php';
$hash = password_hash('admin123', PASSWORD_BCRYPT);
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = 5 OR username = 'admin'");
$stmt->execute([$hash]);
echo "Admin password updated successfully";

