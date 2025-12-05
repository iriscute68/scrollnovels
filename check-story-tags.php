<?php
require_once __DIR__ . '/config.php';

// Check story_tags table
$result = $pdo->query("DESCRIBE story_tags");
echo "story_tags columns:\n";
while($row = $result->fetch()) {
    echo $row['Field'] . "\n";
}

// Check if it's using tags table for tags storage
echo "\nChecking stories table tags column:\n";
$result = $pdo->query("SELECT tags FROM stories LIMIT 1");
echo "Tags are stored as: " . ($result->fetch()['tags'] ? "STRING" : "NULL") . "\n";
?>
