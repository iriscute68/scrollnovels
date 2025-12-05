<?php
require 'config/db.php';

// Check structure of forum_topics
$stmt = $pdo->query("DESCRIBE forum_topics");
echo "forum_topics columns:\n";
while ($row = $stmt->fetch()) {
    echo "  - {$row['Field']}: {$row['Type']}\n";
}
?>
