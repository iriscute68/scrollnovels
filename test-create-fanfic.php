<?php
require 'config/db.php';

// Create a test fanfic story
$stmt = $pdo->prepare("
    INSERT INTO stories (author_id, title, slug, synopsis, genre, is_fanfiction, fanfic_source, tags, status, created_at, published_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'published', NOW(), NOW())
");

$stmt->execute([
    1, // Assume user_id 1 exists
    'Test Fanfiction Story',
    'test-fanfiction-story',
    'This is a test fanfiction story to verify the fanfic.php page is working',
    'Fantasy',
    1, // is_fanfiction = true
    'Original Universe',
    'fanfic,fantasy,test',
]);

$newId = $pdo->lastInsertId();
echo "Created test fanfic story with ID: $newId\n";

// Now test the fanfic query
$stmt = $pdo->query("SELECT s.id, s.title FROM stories s WHERE (s.is_fanfiction = 1 OR s.content_type = 'fanfic' OR LOWER(s.tags) LIKE '%fanfic%' OR LOWER(s.tags) LIKE '%fanfiction%') LIMIT 5");
$stories = $stmt->fetchAll();
echo "Stories from fanfic query: " . count($stories) . "\n";
foreach ($stories as $s) {
    echo "  - " . $s['id'] . ": " . $s['title'] . "\n";
}
?>
