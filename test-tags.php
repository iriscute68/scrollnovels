<?php
require_once 'config/db.php';

echo "<h2>Testing Tags in Database</h2>";

try {
    $stmt = $pdo->query('SELECT id, title, tags FROM stories LIMIT 5');
    $stories = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Story ID</th><th>Title</th><th>Tags</th></tr>";
    
    foreach ($stories as $row) {
        $tags = $row['tags'] ?? 'NULL';
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['title'] . "</td>";
        echo "<td>" . htmlspecialchars($tags) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "<hr><h3>Check if tags column exists:</h3>";
try {
    $colCheck = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stories' AND COLUMN_NAME = 'tags'");
    $colCheck->execute();
    $hasTagsColumn = $colCheck->fetchColumn();
    echo $hasTagsColumn ? "✅ Tags column EXISTS" : "❌ Tags column MISSING";
} catch (Exception $e) {
    echo "Error checking column: " . $e->getMessage();
}
?>
