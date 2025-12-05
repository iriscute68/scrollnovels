<?php
require_once __DIR__ . '/config/db.php';

echo "point_transactions table columns:\n";
$stmt = $pdo->query('DESCRIBE point_transactions');
while ($row = $stmt->fetch()) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}
