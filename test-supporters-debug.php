<?php
require_once __DIR__ . '/config/db.php';

// Get test book ID from parameter
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 1;

echo "<h1>Supporters Debug - Book ID: $book_id</h1>";

// Get the book and author info
try {
    $stmt = $pdo->prepare("SELECT id, title, author_id FROM stories WHERE id = ?");
    $stmt->execute([$book_id]);
    $book = $stmt->fetch();
    
    if (!$book) {
        echo "<p>Book not found</p>";
        exit;
    }
    
    echo "<p><strong>Book:</strong> {$book['title']}</p>";
    echo "<p><strong>Author ID:</strong> {$book['author_id']}</p>";
    
    $author_id = $book['author_id'];
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
    exit;
}

// Test the API endpoint
echo "<h2>API Test</h2>";
$api_url = "http://localhost/scrollnovels/api/supporters/get-top-supporters.php?author_id=$author_id&limit=20";
echo "<p><a href='$api_url' target='_blank'>$api_url</a></p>";

try {
    $response = file_get_contents($api_url);
    $data = json_decode($response, true);
    echo "<pre>";
    print_r($data);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p>Error fetching API: " . $e->getMessage() . "</p>";
}

// Check author_supporters table
echo "<h2>Author Supporters Data</h2>";
try {
    $stmt = $pdo->prepare("SELECT * FROM author_supporters WHERE author_id = ? LIMIT 20");
    $stmt->execute([$author_id]);
    $supporters = $stmt->fetchAll();
    
    echo "<p>Found " . count($supporters) . " supporter records</p>";
    echo "<pre>";
    print_r($supporters);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// Check user_points table
echo "<h2>User Points Data</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM user_points LIMIT 10");
    $points = $stmt->fetchAll();
    echo "<pre>";
    print_r($points);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// Check point_transactions
echo "<h2>Point Transactions</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM point_transactions ORDER BY created_at DESC LIMIT 10");
    $transactions = $stmt->fetchAll();
    echo "<pre>";
    print_r($transactions);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
