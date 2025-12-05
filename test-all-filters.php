<?php
require_once 'config/db.php';

// Simulate clicking on different filters
$testFilters = ['Fantasy', 'Underdog', 'Historical'];

foreach ($testFilters as $filter) {
    $query = "SELECT id, title, tags, genre, genres FROM stories WHERE (genre = ? OR FIND_IN_SET(?, genres) > 0 OR FIND_IN_SET(?, tags) > 0) ORDER BY views DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$filter, $filter, $filter]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Filter: '$filter' - Found " . count($results) . " stories</h3>";
    foreach ($results as $story) {
        echo "  - " . $story['title'] . "\n<br>";
        echo "    (genre: " . ($story['genre'] ?? 'NULL') . ", genres: " . ($story['genres'] ?? 'NULL') . ", tags: " . ($story['tags'] ?? 'NULL') . ")\n<br>";
    }
    echo "<br>";
}
?>
