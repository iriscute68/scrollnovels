<?php
require_once 'config/db.php';

$stmt = $pdo->query('SELECT id, title, tags, genres, content_warnings FROM stories WHERE id = 8');
$story = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Story ID 8: " . $story['title'] . "\n";
echo "Tags: " . ($story['tags'] ?? 'NULL') . "\n";
echo "Genres: " . ($story['genres'] ?? 'NULL') . "\n";
echo "Warnings: " . ($story['content_warnings'] ?? 'NULL') . "\n";
?>
