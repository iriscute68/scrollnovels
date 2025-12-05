<?php
require_once __DIR__ . '/config/db.php';

echo "proclamations table columns:\n";
$stmt = $pdo->query('DESCRIBE proclamations');
while ($row = $stmt->fetch()) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}
