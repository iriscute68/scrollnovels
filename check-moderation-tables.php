<?php
require 'config/db.php';

// Check if required moderation tables exist
$tables_needed = ['user_mutes'];

echo "=== Checking Moderation Tables ===\n\n";

foreach ($tables_needed as $table) {
    $result = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
    if ($result) {
        echo "✓ Table '$table' exists\n";
    } else {
        echo "✗ Table '$table' NOT FOUND - needs to be created\n";
    }
}

// Check users table has suspension fields
echo "\n=== Checking User Table Columns ===\n";
$result = $pdo->query("DESCRIBE users");
$columns = $result->fetchAll();
$col_names = array_column($columns, 'Field');

$required_cols = ['status', 'suspension_until'];
foreach ($required_cols as $col) {
    if (in_array($col, $col_names)) {
        echo "✓ Column '$col' exists\n";
    } else {
        echo "✗ Column '$col' NOT FOUND\n";
    }
}

echo "\nCurrent user columns:\n";
foreach ($columns as $col) {
    echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
}
?>
