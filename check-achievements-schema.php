<?php
require 'config/db.php';

// Verify achievements table structure
$stmt = $pdo->query("DESCRIBE achievements");
echo "Achievements table columns:\n";
while ($row = $stmt->fetch()) {
    echo "  - {$row['Field']}: {$row['Type']}\n";
}
?>
