<?php
require 'config/db.php';

echo "=== blog_comments ===\n";
$result = $pdo->query('DESC blog_comments');
foreach($result->fetchAll() as $row) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\n=== blog_comment_replies ===\n";
$result = $pdo->query('DESC blog_comment_replies');
foreach($result->fetchAll() as $row) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\n=== competition_entries ===\n";
try {
    $result = $pdo->query('DESC competition_entries');
    foreach($result->fetchAll() as $row) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
