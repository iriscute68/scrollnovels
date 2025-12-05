<?php
require 'config/db.php';

// Check table structure
$stmt = $pdo->query("DESC stories");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Stories table columns:\n";
foreach ($columns as $col) {
    echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

// Show sample story
$stmt = $pdo->query("SELECT id, title, is_fanfiction, content_type, tags FROM stories LIMIT 1");
$story = $stmt->fetch();
echo "\nSample story:\n";
echo "  Title: " . $story['title'] . "\n";
echo "  is_fanfiction: " . $story['is_fanfiction'] . "\n";
echo "  content_type: " . $story['content_type'] . "\n";
echo "  tags: " . substr($story['tags'], 0, 100) . "\n";
?>
