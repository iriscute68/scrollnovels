<?php
require_once __DIR__ . '/config/db.php';

echo "Book 4 Debug:\n";
echo "=============\n\n";

// Check book 4
$stmt = $pdo->prepare('SELECT id, author_id FROM stories WHERE id = 4');
$stmt->execute();
$book = $stmt->fetch();

echo "Book ID: " . $book['id'] . "\n";
echo "Author ID: " . $book['author_id'] . "\n\n";

// Check if author 1 has supporters
$stmt = $pdo->prepare('SELECT * FROM author_supporters WHERE author_id = 1');
$stmt->execute();
$supporters = $stmt->fetchAll();

echo "Author 1 supporters: " . count($supporters) . " records\n";
foreach ($supporters as $s) {
    echo "  - Supporter " . $s['supporter_id'] . ": " . $s['points_total'] . " points\n";
}

// Try the API
echo "\n\nTesting API:\n";
$api_url = "http://localhost/scrollnovels/api/supporters/get-top-supporters.php?author_id=1&limit=200";
$response = @file_get_contents($api_url);
if ($response) {
    $data = json_decode($response, true);
    echo "API Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
    echo "Total in API: " . $data['total'] . "\n";
    echo "Data items: " . count($data['data']) . "\n";
    if ($data['data']) {
        foreach ($data['data'] as $sup) {
            echo "  - " . $sup['username'] . ": " . $sup['points_total'] . " pts\n";
        }
    }
}
