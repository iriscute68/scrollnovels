<?php
require_once 'config.php';

echo "<div style='max-width: 900px; margin: 40px auto; font-family: sans-serif;'>";
echo "<h1 style='color: #333;'>ScrollNovels Database Setup</h1>";

// Run SQL files
$sql_files = [
    'create-community-tables.sql' => 'Community Forum Tables',
    'create-achievements-table.sql' => 'Achievements System',
];

foreach ($sql_files as $file => $label) {
    echo "<hr style='margin: 20px 0;'>";
    echo "<h2>$label</h2>";
    
    if (!file_exists($file)) {
        echo "<div style='background: #fee; padding: 10px; border-radius: 5px; color: #c33;'>";
        echo "❌ File not found: $file";
        echo "</div>";
        continue;
    }
    
    try {
        $sql = file_get_contents($file);
        $pdo->exec($sql);
        echo "<div style='background: #efe; padding: 10px; border-radius: 5px; color: #3a3;'>";
        echo "✅ $label created successfully";
        echo "</div>";
    } catch (Exception $e) {
        echo "<div style='background: #fee; padding: 10px; border-radius: 5px; color: #c33;'>";
        echo "❌ Error: " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
}

echo "<hr style='margin: 20px 0;'>";
echo "<h2>Database Tables Status</h2>";

$tables_to_check = [
    'community_posts' => 'Community Posts',
    'community_replies' => 'Community Replies',
    'community_helpful' => 'Community Votes',
    'competition_entries' => 'Competition Entries',
    'achievements' => 'Achievements',
    'user_achievements' => 'User Achievements',
];

foreach ($tables_to_check as $table => $label) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        $icon = $exists ? '✅' : '⚠️';
        echo "<div style='padding: 8px; margin: 5px 0; background: " . ($exists ? '#efe' : '#ffe') . ";'>$icon $label</div>";
    } catch (Exception $e) {
        echo "<div style='padding: 8px; margin: 5px 0; background: #fee;'>❌ $label - Error</div>";
    }
}

echo "<hr style='margin: 20px 0;'>";
echo "<div style='background: #e8f5ff; padding: 15px; border-radius: 5px; border-left: 4px solid #2196F3;'>";
echo "<strong>✅ Setup Complete!</strong><br>";
echo "All database tables have been created. You can now:<br>";
echo "• Create community posts and discussions<br>";
echo "• Enter stories in competitions<br>";
echo "• Track user achievements<br>";
echo "<a href='/pages/community.php' style='color: #2196F3; text-decoration: none;'>> Visit Community Forum</a>";
echo "</div>";

echo "</div>";
?>

