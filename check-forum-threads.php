<?php
require 'config/db.php';

// Get first forum thread
$stmt = $pdo->query("SELECT id, title, status FROM forum_topics LIMIT 1");
$thread = $stmt->fetch();

if ($thread) {
    echo "Found thread:\n";
    echo "  ID: " . $thread['id'] . "\n";
    echo "  Title: " . $thread['title'] . "\n";
    echo "  Status: " . $thread['status'] . "\n";
} else {
    echo "No forum threads found\n";
}
?>
