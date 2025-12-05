<?php
require_once 'config/db.php';

// Test getting genres and tags dynamically
$genres = [];
$tags = [];

try {
    // Get unique genres from old genre column
    $stmt = $pdo->query("SELECT DISTINCT TRIM(genre) as genre FROM stories WHERE genre IS NOT NULL AND genre != '' ORDER BY genre");
    $genres = array_filter(array_unique(array_map('trim', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    
    // Get unique genres from new genres column (comma-separated)
    $stmt = $pdo->query("SELECT DISTINCT genres FROM stories WHERE genres IS NOT NULL AND genres != ''");
    $genresList = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($genresList as $genreString) {
        $genreItems = array_filter(array_map('trim', explode(',', $genreString)));
        $genres = array_merge($genres, $genreItems);
    }
    $genres = array_values(array_unique($genres));
    sort($genres);
    
    // Get unique tags
    $stmt = $pdo->query("SELECT DISTINCT tags FROM stories WHERE tags IS NOT NULL AND tags != ''");
    $allTagsList = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($allTagsList as $tagString) {
        $tagItems = array_filter(array_map('trim', explode(',', $tagString)));
        $tags = array_merge($tags, $tagItems);
    }
    $tags = array_values(array_unique($tags));
    sort($tags);
    
    echo "<h2>Found Genres (" . count($genres) . "):</h2>";
    echo implode(', ', $genres) . "\n<br><br>";
    
    echo "<h2>Found Tags (" . count($tags) . "):</h2>";
    echo implode(', ', $tags) . "\n<br><br>";
    
    // Test filtering
    $testTag = !empty($tags) ? $tags[0] : 'Magic';
    echo "<h2>Testing filter for: '$testTag'</h2>";
    
    $query = "SELECT id, title, tags, genres FROM stories WHERE (genre = ? OR FIND_IN_SET(?, genres) > 0 OR FIND_IN_SET(?, tags) > 0)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$testTag, $testTag, $testTag]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($results) . " stories:\n<br>";
    foreach ($results as $story) {
        echo "  - " . $story['title'] . " (tags: " . ($story['tags'] ?? 'NULL') . ")\n<br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
