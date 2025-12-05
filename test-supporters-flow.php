<?php
// test-supporters-flow.php - Diagnostic script to verify supporters system

require_once __DIR__ . '/config/db.php';

echo "<h1>Supporters System Diagnostic</h1>";

// Check if we have test data
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 2; // Test user
$author_id = isset($_GET['author_id']) ? (int)$_GET['author_id'] : 1; // Test author

echo "<h2>Test Parameters</h2>";
echo "Supporter ID: $user_id<br>";
echo "Author ID: $author_id<br>";

// Check user exists
echo "<h2>1. Checking Users Exist</h2>";
try {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id IN (?, ?)");
    $stmt->execute([$user_id, $author_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($users);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check user_points
echo "<h2>2. Checking user_points Table</h2>";
try {
    $stmt = $pdo->prepare("SELECT user_id, points FROM user_points WHERE user_id IN (?, ?)");
    $stmt->execute([$user_id, $author_id]);
    $points = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($points);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check point_transactions
echo "<h2>3. Checking point_transactions Table</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT user_id, type, points, description, created_at 
        FROM point_transactions 
        WHERE user_id IN (?, ?) 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id, $author_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($transactions);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check author_supporters
echo "<h2>4. Checking author_supporters Table</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT id, author_id, supporter_id, story_id, points_total, last_supported_at, created_at 
        FROM author_supporters 
        WHERE author_id = ? 
        ORDER BY points_total DESC
    ");
    $stmt->execute([$author_id]);
    $supporters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($supporters);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Test API endpoint
echo "<h2>5. Testing get-top-supporters.php API</h2>";
$api_url = "http://localhost/scrollnovels/api/supporters/get-top-supporters.php?author_id=$author_id&limit=20";
echo "Testing: $api_url<br>";
try {
    $response = file_get_contents($api_url);
    $data = json_decode($response, true);
    echo "<pre>";
    print_r($data);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check specific supporter record
echo "<h2>6. Checking Specific Supporter Record</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT * FROM author_supporters 
        WHERE author_id = ? AND supporter_id = ?
    ");
    $stmt->execute([$author_id, $user_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($record);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "<hr>";
echo "<p>Run the give-points API first, then reload this page to see updated data.</p>";
echo "<p><a href='?user_id=$user_id&author_id=$author_id'>Refresh</a></p>";
?>
