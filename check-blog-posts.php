<?php
require_once 'config/db.php';

echo "=== Blog Posts Table Structure ===\n\n";
try {
    $cols = $pdo->query("DESCRIBE blog_posts")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo $col['Field'] . " - " . $col['Type'] . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
