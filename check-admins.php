<?php
require 'config/db.php';
echo "=== Current Admin Users ===\n";
$admins = $pdo->query("SELECT id, username, role, is_admin FROM users WHERE role IN ('admin', 'super_admin', 'moderator') ORDER BY id")->fetchAll();
foreach ($admins as $a) {
    echo "ID: " . $a['id'] . ", Username: " . $a['username'] . ", Role: " . $a['role'] . ", is_admin: " . $a['is_admin'] . "\n";
}
?>
