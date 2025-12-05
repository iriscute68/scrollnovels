<?php
session_start();
require_once 'config/db.php';

$bookId = (int)($_GET['book_id'] ?? $_GET['id'] ?? 0);
if (!$bookId) {
    die("Please provide ?book_id=X or ?id=X");
}

echo "<h2>Debugging Top Supporters for Book ID: $bookId</h2>";

// Get the story
$stmt = $pdo->prepare("SELECT id, author_id, title FROM stories WHERE id = ?");
$stmt->execute([$bookId]);
$story = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$story) {
    die("Book not found");
}

echo "<p><strong>Story:</strong> " . htmlspecialchars($story['title']) . "</p>";
echo "<p><strong>Author ID:</strong> " . ($story['author_id'] ?? 'NULL') . "</p>";

if (!$story['author_id']) {
    echo "<p style='color: red;'><strong>⚠️ ERROR:</strong> Author ID is NULL! Stories should have an author_id.</p>";
    exit;
}

$authorId = $story['author_id'];

// Check author_supporters table
echo "<h3>Author Supporters Table (author_id = $authorId):</h3>";
$stmt = $pdo->prepare("SELECT a.*, u.username FROM author_supporters a LEFT JOIN users u ON a.supporter_id = u.id WHERE a.author_id = ? ORDER BY a.points_total DESC");
$stmt->execute([$authorId]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<p>Records found: " . count($records) . "</p>";
if (count($records) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Supporter ID</th><th>Username</th><th>Points Total</th><th>Created</th></tr>";
    foreach ($records as $r) {
        echo "<tr><td>" . $r['id'] . "</td><td>" . $r['supporter_id'] . "</td><td>" . ($r['username'] ?? 'N/A') . "</td><td>" . $r['points_total'] . "</td><td>" . $r['created_at'] . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No records found in author_supporters table for this author.</p>";
}

// Check story_support table
echo "<h3>Story Support Table (author_id = $authorId):</h3>";
$stmt = $pdo->prepare("SELECT s.*, u.username FROM story_support s LEFT JOIN users u ON s.supporter_id = u.id WHERE s.author_id = ? ORDER BY s.points_amount DESC");
$stmt->execute([$authorId]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<p>Records found: " . count($records) . "</p>";
if (count($records) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Supporter ID</th><th>Username</th><th>Points</th><th>Method</th><th>Created</th></tr>";
    foreach ($records as $r) {
        echo "<tr><td>" . $r['id'] . "</td><td>" . $r['supporter_id'] . "</td><td>" . ($r['username'] ?? 'N/A') . "</td><td>" . $r['points_amount'] . "</td><td>" . $r['method'] . "</td><td>" . $r['created_at'] . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No records found in story_support table for this author.</p>";
}

// Test the API directly
echo "<h3>Test API Call:</h3>";
$apiUrl = "/scrollnovels/api/supporters/get-top-supporters.php?author_id=$authorId&limit=20";
echo "<p><strong>URL:</strong> $apiUrl</p>";
echo "<p><a href='$apiUrl' target='_blank'>Click to test API</a> (opens in new tab)</p>";

// Try calling the API via file_get_contents
echo "<p><strong>Direct API Test:</strong></p>";
$response = @file_get_contents("http://localhost" . $apiUrl);
if ($response) {
    $data = json_decode($response, true);
    echo "<pre>";
    print_r($data);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>Failed to call API</p>";
}

?>
