<?php
require __DIR__ . '/../../config/db.php';
$hash = '$2y$10$PPNVf6fvR1Ki5.RB4vbj7O/hOCxg6S4Up2UdoYcend5IVmZThVONC';
$stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE username = 'Zakielenvt'");
$stmt->execute([$hash]);
echo "[✓] Admin password updated successfully!\n";
echo "Username: Zakielenvt\n";
echo "Password: aa0246776376\n";
echo "Email: abakaherica1@gmail.com\n";
echo "Role: superadmin\n";
?>

