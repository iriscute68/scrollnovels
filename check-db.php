<?php
require_once __DIR__ . '/config.php';

$stmt = $pdo->prepare('SELECT id, title, tags, content_warnings, genres FROM stories WHERE id = 8');
$stmt->execute();
$story = $stmt->fetch();

echo "Story 8:\n";
echo "Title: " . $story['title'] . "\n";
echo "Tags: " . ($story['tags'] ?? 'NULL') . "\n";
echo "Warnings: " . ($story['content_warnings'] ?? 'NULL') . "\n";
echo "Genres: " . ($story['genres'] ?? 'NULL') . "\n";
?>
