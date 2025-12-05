<?php
require_once 'config/db.php';

echo "=== Checking blog_posts columns ===\n";
try {
    $cols = $pdo->query("DESCRIBE blog_posts")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "  - {$c['Field']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Checking post_comments table ===\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'post_comments'");
    if ($result->rowCount() > 0) {
        echo "post_comments table EXISTS\n";
    } else {
        echo "post_comments table DOES NOT EXIST\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Testing blog query ===\n";
try {
    $stmt = $pdo->query("
        SELECT bp.id, bp.title, bp.status
        FROM blog_posts bp 
        WHERE bp.status = 'published' 
        ORDER BY bp.created_at DESC
        LIMIT 5
    ");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($posts) . " published posts:\n";
    foreach ($posts as $p) {
        echo "  - [{$p['id']}] {$p['title']} ({$p['status']})\n";
    }
} catch (Exception $e) {
    echo "Query error: " . $e->getMessage() . "\n";
}
