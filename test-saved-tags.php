<?php
require_once __DIR__ . '/config.php';

// Get the story being edited (from URL parameter or session)
$storyId = $_GET['story_id'] ?? 8;

$stmt = $pdo->prepare("SELECT id, title, tags, content_warnings, genres FROM stories WHERE id = ?");
$stmt->execute([$storyId]);
$story = $stmt->fetch();

echo "Story ID: " . $storyId . "<br>";
echo "Title: " . ($story['title'] ?? 'NOT FOUND') . "<br>";
echo "Tags (raw): " . var_export($story['tags'] ?? null, true) . "<br>";
echo "Tags (exploded): " . var_export(explode(',', $story['tags'] ?? ''), true) . "<br>";
echo "Warnings (raw): " . var_export($story['content_warnings'] ?? null, true) . "<br>";
echo "Warnings (exploded): " . var_export(explode(',', $story['content_warnings'] ?? ''), true) . "<br>";
echo "Genres (raw): " . var_export($story['genres'] ?? null, true) . "<br>";
echo "Genres (exploded): " . var_export(explode(',', $story['genres'] ?? ''), true) . "<br>";
?>
