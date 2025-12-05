<?php
require_once 'config/db.php';

echo "=== DATABASE VERIFICATION ===\n\n";

// Test review system tables
$review_tables = ['reviews', 'review_reports'];
echo "Review System Tables:\n";
foreach ($review_tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "✓ Table '$table' has " . count($cols) . " columns\n";
    } catch (Exception $e) {
        echo "✗ Table '$table' error: " . $e->getMessage() . "\n";
    }
}

echo "\nNotification System Tables:\n";
// Test notification system tables
$notif_tables = ['follows', 'notifications', 'user_notification_settings'];
foreach ($notif_tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "✓ Table '$table' has " . count($cols) . " columns\n";
    } catch (Exception $e) {
        echo "✗ Table '$table' error: " . $e->getMessage() . "\n";
    }
}

echo "\nUser Profile Tables:\n";
// Test profile columns
try {
    $stmt = $pdo->query("DESCRIBE users");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $col_names = array_map(function($c) { return $c['Field']; }, $cols);
    $required = ['country', 'age', 'favorite_categories', 'patreon', 'kofi'];
    
    foreach ($required as $col) {
        if (in_array($col, $col_names)) {
            echo "✓ Column 'users.$col' exists\n";
        } else {
            echo "✗ Column 'users.$col' missing\n";
        }
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== VERIFICATION COMPLETE ===\n";
?>
