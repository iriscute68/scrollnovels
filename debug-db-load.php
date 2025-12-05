<?php
require_once __DIR__ . '/config.php';

// Simulate what the write-story.php page does
$story_id = (int)($_GET['id'] ?? 8);
$user_id = 1; // assuming user 1

$stmt = $pdo->prepare('SELECT * FROM stories WHERE id = ? AND author_id = ?');
$stmt->execute([$story_id, $user_id]);
$story = $stmt->fetch();

if (!$story) {
    die('Story not found');
}

echo "<h2>Story #" . $story['id'] . ": " . $story['title'] . "</h2>";
echo "<p><strong>Tags from DB:</strong> " . htmlspecialchars($story['tags'] ?? '') . "</p>";
echo "<p><strong>Warnings from DB:</strong> " . htmlspecialchars($story['content_warnings'] ?? '') . "</p>";
echo "<p><strong>Genres from DB:</strong> " . htmlspecialchars($story['genres'] ?? '') . "</p>";

echo "<h3>JavaScript will receive:</h3>";
echo "<pre>";
echo "const existingTagsStr = '" . addslashes($story['tags'] ?? '') . "';\n";
echo "const existingWarningsStr = '" . addslashes($story['content_warnings'] ?? '') . "';\n";
echo "const existingGenresStr = '" . addslashes($story['genres'] ?? '') . "';\n";
echo "</pre>";
?>
