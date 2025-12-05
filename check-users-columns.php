<?php
require_once 'config/db.php';
$stmt = $pdo->query('DESCRIBE users');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Current users table columns:\n";
foreach ($columns as $col) {
    echo '- ' . $col['Field'] . ' (' . $col['Type'] . ")\n";
}
?>
