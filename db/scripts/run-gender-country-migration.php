<?php
require __DIR__ . '/../../config/db.php';

try {
    // Add gender column
    $pdo->exec("ALTER TABLE users ADD COLUMN gender ENUM('woman', 'man', 'trans', 'other') DEFAULT NULL AFTER discord");
    echo "✓ Added gender column to users table\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "ℹ Gender column already exists\n";
    } else {
        echo "✗ Error adding gender: " . $e->getMessage() . "\n";
    }
}

try {
    // Add country column
    $pdo->exec("ALTER TABLE users ADD COLUMN country VARCHAR(100) DEFAULT NULL AFTER gender");
    echo "✓ Added country column to users table\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "ℹ Country column already exists\n";
    } else {
        echo "✗ Error adding country: " . $e->getMessage() . "\n";
    }
}

// Verify columns were added
$result = $pdo->query("DESCRIBE users");
$columns = $result->fetchAll(PDO::FETCH_COLUMN, 0);
echo "\n=== Current users table columns ===\n";
echo implode("\n", $columns) . "\n";
?>

