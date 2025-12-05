<?php
require_once __DIR__ . '/config.php';

$story_id = (int)($_GET['id'] ?? 8);
$user_id = 1;

// Get the story
$stmt = $pdo->prepare('SELECT * FROM stories WHERE id = ? AND author_id = ?');
$stmt->execute([$story_id, $user_id]);
$story = $stmt->fetch();

// Get available tags
$stmt = $pdo->prepare("SELECT * FROM tags WHERE id IN (SELECT DISTINCT tag_id FROM story_tags)");
$stmt->execute();
$allTags = $stmt->fetchAll();

echo "<h2>Debug Info for Story #" . $story['id'] . "</h2>";

echo "<h3>Database Values:</h3>";
echo "<pre>";
echo "Tags in DB: " . htmlspecialchars($story['tags']) . "\n";
echo "Warnings in DB: " . htmlspecialchars($story['content_warnings']) . "\n";
echo "Genres in DB: " . htmlspecialchars($story['genres']) . "\n";
echo "</pre>";

echo "<h3>What JavaScript will receive from PHP:</h3>";
echo "<pre>";
echo "const existingTagsStr = '" . addslashes($story['tags'] ?? '') . "';\n";
echo "const existingWarningsStr = '" . addslashes($story['content_warnings'] ?? '') . "';\n";
echo "const existingGenresStr = '" . addslashes($story['genres'] ?? '') . "';\n";
echo "</pre>";

echo "<h3>After split:</h3>";
echo "<pre>";
$tags = array_filter(array_map('trim', explode(',', $story['tags'] ?? '')));
$warnings = array_filter(array_map('trim', explode(',', $story['content_warnings'] ?? '')));
$genres = array_filter(array_map('trim', explode(',', $story['genres'] ?? '')));
echo "Tags: " . json_encode($tags) . "\n";
echo "Warnings: " . json_encode($warnings) . "\n";
echo "Genres: " . json_encode($genres) . "\n";
echo "</pre>";

echo "<h3>All available tags from API:</h3>";
echo "<pre>";
foreach($allTags as $tag) {
    echo "- ID: " . $tag['id'] . ", Name: " . $tag['name'] . "\n";
}
echo "</pre>";

echo "<h3>Step-by-step matching:</h3>";
foreach($tags as $tagName) {
    $stmt = $pdo->prepare("SELECT id, name FROM tags WHERE name = ?");
    $stmt->execute([$tagName]);
    $found = $stmt->fetch();
    if ($found) {
        echo "✓ Tag '$tagName' found: ID=" . $found['id'] . "\n";
    } else {
        echo "✗ Tag '$tagName' NOT FOUND\n";
    }
}
?>
