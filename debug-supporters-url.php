<?php
// Debug what the actual book.php page is generating

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$bookId = 4;
$stmt = $pdo->prepare("SELECT author_id FROM stories WHERE id = ?");
$stmt->execute([$bookId]);
$story = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Book ID: $bookId\n";
echo "Author ID: " . ($story['author_id'] ?? 'NULL') . "\n";
echo "\n";

// Test what site_url generates
$url = site_url('/api/supporters/get-top-supporters.php');
echo "site_url output: $url\n";
echo "\n";

// Test what the JavaScript would see
echo "In book.php, this line would be:\n";
echo 'const url = \'' . $url . '?author_id=' . ($story['author_id'] ?? 'null') . '&limit=200\';' . "\n";
echo "\n";

// Test the actual API
$authorId = $story['author_id'] ?? null;
if ($authorId) {
    $apiUrl = site_url('/api/supporters/get-top-supporters.php') . '?author_id=' . $authorId . '&limit=200';
    echo "Testing API: $apiUrl\n";
    $response = file_get_contents($apiUrl);
    $data = json_decode($response, true);
    echo "API Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
    echo "Data count: " . (is_array($data['data']) ? count($data['data']) : '0') . "\n";
    echo "Response preview: " . substr($response, 0, 200) . "\n";
}
