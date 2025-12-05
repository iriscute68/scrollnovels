<?php
require 'config/db.php';

// Get all forum-related tables
$result = $pdo->query("SHOW TABLES LIKE 'forum%'");
$tables = $result->fetchAll(PDO::FETCH_COLUMN);

echo "Forum Tables:\n";
echo implode(", ", $tables) . "\n\n";

// Check structure of forum_posts
if (in_array('forum_posts', $tables)) {
    $stmt = $pdo->query("DESCRIBE forum_posts");
    echo "forum_posts columns:\n";
    while ($row = $stmt->fetch()) {
        echo "  - {$row['Field']}: {$row['Type']}\n";
    }
    echo "\n";
}

// Check structure of forum_threads
if (in_array('forum_threads', $tables)) {
    $stmt = $pdo->query("DESCRIBE forum_threads");
    echo "forum_threads columns:\n";
    while ($row = $stmt->fetch()) {
        echo "  - {$row['Field']}: {$row['Type']}\n";
    }
}
?>
