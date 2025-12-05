<?php
require_once 'config/db.php';

echo "=== Recent Blog Posts ===\n\n";
$stmt = $pdo->query("SELECT id, title, status, author_id, announcement_id, created_at FROM blog_posts ORDER BY created_at DESC LIMIT 10");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($posts)) {
    echo "No blog posts found!\n";
} else {
    foreach ($posts as $post) {
        echo "ID: {$post['id']}\n";
        echo "Title: {$post['title']}\n";
        echo "Status: {$post['status']}\n";
        echo "Author ID: {$post['author_id']}\n";
        echo "Announcement ID: " . ($post['announcement_id'] ?? 'null') . "\n";
        echo "Created: {$post['created_at']}\n";
        echo "---\n";
    }
}

echo "\n=== Recent Announcements ===\n\n";
$stmt2 = $pdo->query("SELECT id, title, created_at FROM announcements ORDER BY created_at DESC LIMIT 5");
$anns = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($anns)) {
    echo "No announcements found!\n";
} else {
    foreach ($anns as $ann) {
        echo "ID: {$ann['id']}, Title: {$ann['title']}, Created: {$ann['created_at']}\n";
    }
}
