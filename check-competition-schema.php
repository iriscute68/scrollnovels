<?php
// Check competition image functionality
require 'config/db.php';

echo "=== Checking Competition Schema ===\n\n";

// Check if banner_image column exists
try {
    $stmt = $pdo->query("DESCRIBE competitions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Competition columns:\n";
    $hasBanner = false;
    foreach ($columns as $col) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
        if ($col['Field'] === 'banner_image') {
            $hasBanner = true;
        }
    }
    
    if (!$hasBanner) {
        echo "\n⚠️ banner_image column NOT FOUND - Adding it...\n";
        $pdo->exec("ALTER TABLE competitions ADD COLUMN banner_image VARCHAR(500) AFTER description");
        echo "✓ banner_image column added\n\n";
    } else {
        echo "\n✓ banner_image column already exists\n\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Check if competitions have test data
echo "=== Competition Data ===\n";
try {
    $stmt = $pdo->query("SELECT id, title, banner_image FROM competitions LIMIT 3");
    $rows = $stmt->fetchAll();
    if (count($rows) > 0) {
        foreach ($rows as $row) {
            echo "  ID: " . $row['id'] . " | Title: " . $row['title'] . " | Image: " . ($row['banner_image'] ? 'SET' : 'NOT SET') . "\n";
        }
    } else {
        echo "  No competitions found\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nDone!\n";
?>
