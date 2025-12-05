<?php
require_once __DIR__ . '/config/db.php';

echo "competition_entries table columns:\n";
$stmt = $pdo->query('DESCRIBE competition_entries');
while ($row = $stmt->fetch()) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}
