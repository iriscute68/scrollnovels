<?php
require 'config/db.php';
$result = $pdo->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='role'");
$row = $result->fetch();
echo "Role column definition: " . $row['COLUMN_TYPE'] . "\n";
?>
