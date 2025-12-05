<?php
// Quick verification script
require_once __DIR__ . '/config.php';

echo "=== Schema Verification ===\n\n";

// Check tables exist
$tables = ['competitions', 'announcements', 'saved_stories', 'reading_progress', 'guide_pages', 'blog_posts'];
foreach ($tables as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "✓ $table exists ($count rows)\n";
    } catch (Exception $e) {
        echo "✗ $table missing or error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Competitions Schema ===\n";
try {
    $cols = $pdo->query("DESCRIBE competitions")->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns: " . implode(', ', $cols) . "\n";
    $required = ['title', 'rules', 'description', 'cover_image', 'start_date', 'end_date'];
    foreach ($required as $col) {
        echo (in_array($col, $cols) ? "✓" : "✗") . " $col\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Blog Posts Schema ===\n";
try {
    $cols = $pdo->query("DESCRIBE blog_posts")->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns: " . implode(', ', $cols) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
