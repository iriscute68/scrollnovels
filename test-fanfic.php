<?php
require 'config/db.php';

$stmt = $pdo->query('SELECT COUNT(*) as cnt, SUM(CASE WHEN is_fanfiction=1 THEN 1 ELSE 0 END) as fanfic_count FROM stories');
$r = $stmt->fetch();
echo 'Total stories: ' . $r['cnt'] . "\n";
echo 'Fanfic stories: ' . $r['fanfic_count'] . "\n";

// Also check the query from fanfic.php
$stmt = $pdo->query("SELECT s.id, s.title FROM stories s WHERE (s.is_fanfiction = 1 OR s.content_type = 'fanfic' OR LOWER(s.tags) LIKE '%fanfic%' OR LOWER(s.tags) LIKE '%fanfiction%') LIMIT 5");
$stories = $stmt->fetchAll();
echo "Stories from fanfic query: " . count($stories) . "\n";
foreach ($stories as $s) {
    echo "  - " . $s['id'] . ": " . $s['title'] . "\n";
}
?>
