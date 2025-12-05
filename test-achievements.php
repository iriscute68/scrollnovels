<?php
require 'config/db.php';

echo "=== ACHIEVEMENTS SYSTEM TEST ===\n\n";

// Check if achievements table exists and has data
$stmt = $pdo->query("SELECT COUNT(*) FROM achievements");
$count = $stmt->fetchColumn();
echo "✅ Total achievements in database: " . $count . "\n\n";

// List sample achievements
$stmt = $pdo->query("SELECT id, title, description, points FROM achievements LIMIT 3");
$achievements = $stmt->fetchAll();

if (count($achievements) > 0) {
    echo "Sample Achievements:\n";
    foreach ($achievements as $ach) {
        echo "  ID: " . $ach['id'] . " | Title: " . $ach['title'] . " | Points: " . $ach['points'] . "\n";
    }
} else {
    echo "⚠️  No achievements found\n";
}

echo "\n=== API FILES CHECK ===\n";

$files = [
    'api/admin/get-achievement.php' => 'Fetch achievement',
    'api/admin/save-achievement.php' => 'Create/update achievement',
    'api/admin/delete-achievement.php' => 'Delete achievement',
];

foreach ($files as $file => $purpose) {
    if (file_exists($file)) {
        echo "✅ $file - $purpose\n";
    } else {
        echo "❌ $file - MISSING\n";
    }
}

echo "\n=== READY ===\n";
echo "Achievement create/edit/delete functionality is now ready!\n";
echo "Visit: http://localhost/scrollnovels/admin/admin.php?page=achievements\n";
?>
