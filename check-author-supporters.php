<?php
require_once __DIR__ . '/config/db.php';

$stmt = $pdo->prepare('SELECT * FROM author_supporters WHERE author_id = 1');
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "author_supporters records for author_id = 1:\n\n";
foreach ($rows as $row) {
    echo "ID: {$row['id']}\n";
    echo "  author_id: {$row['author_id']}\n";
    echo "  supporter_id: {$row['supporter_id']}\n";
    echo "  story_id: {$row['story_id']}\n";
    echo "  points_total: {$row['points_total']}\n";
    echo "  last_supported_at: {$row['last_supported_at']}\n";
    echo "  created_at: {$row['created_at']}\n";
    echo "---\n";
}

echo "\nTotal records: " . count($rows) . "\n";
echo "\nSum of all points_total: " . array_sum(array_column($rows, 'points_total')) . "\n";
