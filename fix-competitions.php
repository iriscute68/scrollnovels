<?php
require_once __DIR__ . '/config/db.php';

try {
    $pdo->exec("ALTER TABLE competitions ADD COLUMN max_entries INT DEFAULT 0");
    echo "Added max_entries column\n";
} catch (Exception $e) {
    echo "Column may already exist or error: " . $e->getMessage() . "\n";
}

// List all columns
$cols = $pdo->query("DESCRIBE competitions")->fetchAll(PDO::FETCH_COLUMN);
echo "Columns: " . implode(", ", $cols) . "\n";
