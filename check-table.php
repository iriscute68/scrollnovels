<?php
require 'config/db.php';
$result = $pdo->query('DESCRIBE users');
$columns = $result->fetchAll();
foreach ($columns as $col) {
    echo $col['Field'] . ' (' . $col['Type'] . ')' . ($col['Null'] === 'NO' ? ' NOT NULL' : '') . "\n";
}
?>
