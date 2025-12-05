<?php
require_once 'config/db.php';

$migrations = [
    'country' => 'ALTER TABLE users ADD COLUMN country VARCHAR(100) NULL',
    'age' => 'ALTER TABLE users ADD COLUMN age INT NULL',
    'favorite_categories' => 'ALTER TABLE users ADD COLUMN favorite_categories JSON NULL'
];

foreach ($migrations as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "✓ Added $name column\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "✓ $name column already exists\n";
        } else {
            echo "✗ Error: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n✓ Users table migration complete!\n";
?>
