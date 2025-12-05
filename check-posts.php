<?php
require_once __DIR__ . '/config/db.php';

echo "=== Announcements ===\n";
$rows = $pdo->query("SELECT id, title, created_at FROM announcements ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "ID: {$r['id']} | {$r['title']} | {$r['created_at']}\n";
}

echo "\n=== Blog Posts ===\n";
$rows = $pdo->query("SELECT id, title, created_at FROM blog_posts ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "ID: {$r['id']} | {$r['title']} | {$r['created_at']}\n";
}

echo "\nDone!\n";
