<?php
require_once 'config.php';

try {
    $sql = file_get_contents('create-community-tables.sql');
    $pdo->exec($sql);
    echo "<div style='background:#d4edda; padding:15px; border-radius:5px; color:#155724;'>";
    echo "<strong>✅ Success!</strong> Community tables created successfully.<br>";
    echo "Tables created: community_posts, community_replies, community_helpful, competition_entries";
    echo "</div>";
} catch (Exception $e) {
    echo "<div style='background:#f8d7da; padding:15px; border-radius:5px; color:#721c24;'>";
    echo "<strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>

