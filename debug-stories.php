<?php
require_once 'config/db.php';

// Check stories table structure
echo "Stories table columns:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM stories");
while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (stripos($col['Field'], 'author') !== false) {
        echo $col['Field'] . ": " . $col['Type'] . "\n";
    }
}

// Check a specific story
echo "\n\nTest story query:\n";
$stmt = $pdo->prepare("SELECT id, author_id, title FROM stories LIMIT 1");
$stmt->execute();
$story = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($story);

// Check if author_id exists
echo "\n\nStories with author_id NULL check:\n";
$stmt = $pdo->query("SELECT COUNT(*) as null_count FROM stories WHERE author_id IS NULL");
echo "Stories with NULL author_id: " . $stmt->fetch(PDO::FETCH_ASSOC)['null_count'] . "\n";

// Check total stories
$stmt = $pdo->query("SELECT COUNT(*) as total FROM stories");
echo "Total stories: " . $stmt->fetch(PDO::FETCH_ASSOC)['total'] . "\n";

?>
