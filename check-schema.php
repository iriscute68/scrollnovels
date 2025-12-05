<?php
require_once 'config/db.php';

// Check stories table structure
$stmt = $pdo->query("DESCRIBE stories");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Stories Table Structure:</h2>";
foreach ($columns as $col) {
    echo "Column: " . $col['Field'] . " | Type: " . $col['Type'] . " | Null: " . $col['Null'] . "\n<br>";
}

// Check sample data
echo "\n<h2>Sample Stories Data:</h2>";
$stmt = $pdo->query("SELECT id, title, genre, genres, tags FROM stories LIMIT 3");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['id'] . " | Title: " . $row['title'] . "\n<br>";
    echo "  genre: " . ($row['genre'] ?? 'NULL') . "\n<br>";
    echo "  genres: " . ($row['genres'] ?? 'NULL') . "\n<br>";
    echo "  tags: " . ($row['tags'] ?? 'NULL') . "\n<br><br>";
}
?>
