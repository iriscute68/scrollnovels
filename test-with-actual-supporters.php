<?php
require_once __DIR__ . '/config/db.php';

// Find a book by author_id 1
$stmt = $pdo->prepare("SELECT s.id, s.author_id, u.username FROM stories s JOIN users u ON s.author_id = u.id WHERE s.author_id = 1 LIMIT 1");
$stmt->execute();
$story = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$story) {
    die("No story by author ID 1 found");
}

echo "✓ Story by author with supporters:\n";
echo "  Story ID: {$story['id']}\n";
echo "  Author ID: {$story['author_id']}\n";
echo "  Author: {$story['username']}\n\n";

// Check API for this author
$api_url = "http://localhost/scrollnovels/api/supporters/get-top-supporters.php?author_id={$story['author_id']}&limit=200";
$response = @file_get_contents($api_url);
if ($response === false) {
    die("API request failed");
}

$data = json_decode($response, true);
echo "API Response for Author ID {$story['author_id']}:\n";
echo "  Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
echo "  Total supporters: " . ($data['total'] ?? 0) . "\n";

if ($data['data']) {
    echo "  Supporters:\n";
    foreach ($data['data'] as $supporter) {
        echo "    - {$supporter['username']}: {$supporter['points_total']} points\n";
    }
}

echo "\n📌 TESTING URL:\n";
echo "Open this in your browser to test the supporters feature:\n";
echo "http://localhost/scrollnovels/pages/book.php?id={$story['id']}\n";
echo "\nThen click the '🏆 Top Supporters' tab to see the supporters load!\n";
