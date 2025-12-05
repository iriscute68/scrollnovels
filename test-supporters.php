<?php
// test-supporters.php - Test supporters data and API
session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$bookId = (int)($_GET['book_id'] ?? 0);
if (!$bookId) {
    die("No book_id provided");
}

// Get story/book details
$stmt = $pdo->prepare("SELECT author_id FROM stories WHERE id = ?");
$stmt->execute([$bookId]);
$story = $stmt->fetch();

if (!$story) {
    die("Book not found");
}

$author_id = $story['author_id'];
echo "<h2>Testing Supporters for Book ID: $bookId (Author ID: $author_id)</h2>";

// Check what tables exist
echo "<h3>Database Tables Check:</h3>";

// Check supporters table
$stmt = $pdo->prepare("SELECT COUNT(*) FROM supporters WHERE author_id = ?");
$stmt->execute([$author_id]);
$supporterCount = $stmt->fetchColumn();
echo "<p><strong>Supporters table:</strong> $supporterCount records for this author</p>";

if ($supporterCount > 0) {
    echo "<pre>";
    $stmt = $pdo->prepare("SELECT s.*, u.username FROM supporters s LEFT JOIN users u ON s.supporter_id = u.id WHERE s.author_id = ? ORDER BY s.tip_amount DESC LIMIT 5");
    $stmt->execute([$author_id]);
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
}

// Check author_supporters table
$stmt = $pdo->prepare("SELECT COUNT(*) FROM author_supporters WHERE author_id = ?");
$stmt->execute([$author_id]);
$authorSupportCount = $stmt->fetchColumn();
echo "<p><strong>Author_supporters table:</strong> $authorSupportCount records for this author</p>";

if ($authorSupportCount > 0) {
    echo "<pre>";
    $stmt = $pdo->prepare("SELECT a.*, u.username FROM author_supporters a LEFT JOIN users u ON a.supporter_id = u.id WHERE a.author_id = ? ORDER BY a.points_total DESC LIMIT 5");
    $stmt->execute([$author_id]);
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
}

// Check story_support table
$stmt = $pdo->prepare("SELECT COUNT(*) FROM story_support WHERE author_id = ?");
$stmt->execute([$author_id]);
$storySupportCount = $stmt->fetchColumn();
echo "<p><strong>Story_support table:</strong> $storySupportCount records for this author</p>";

if ($storySupportCount > 0) {
    echo "<pre>";
    $stmt = $pdo->prepare("SELECT s.*, u.username FROM story_support s LEFT JOIN users u ON s.supporter_id = u.id WHERE s.author_id = ? ORDER BY s.points_amount DESC LIMIT 5");
    $stmt->execute([$author_id]);
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
}

// Test the actual API
echo "<h3>API Test:</h3>";
$apiUrl = site_url('/api/supporters/get-top-supporters.php?author_id=' . $author_id . '&limit=20');
echo "<p><strong>API URL:</strong> $apiUrl</p>";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
curl_close($ch);

echo "<pre>";
echo "API Response:\n";
$data = json_decode($response, true);
print_r($data);
echo "</pre>";

// Check donation records
echo "<h3>Donations Check:</h3>";
$stmt = $pdo->prepare("SELECT COUNT(*) FROM donations WHERE story_id IN (SELECT id FROM stories WHERE author_id = ?)");
$stmt->execute([$author_id]);
$donationCount = $stmt->fetchColumn();
echo "<p><strong>Donation records:</strong> $donationCount</p>";

if ($donationCount > 0) {
    echo "<pre>";
    $stmt = $pdo->prepare("SELECT d.*, u.username FROM donations d LEFT JOIN users u ON d.donor_id = u.id WHERE d.story_id IN (SELECT id FROM stories WHERE author_id = ?) ORDER BY d.amount DESC LIMIT 5");
    $stmt->execute([$author_id]);
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
}

// Check user's current points for support
if (isset($_SESSION['user_id'])) {
    echo "<h3>Your Account (User ID: " . $_SESSION['user_id'] . "):</h3>";
    $stmt = $pdo->prepare("SELECT points FROM user_points WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $points = $stmt->fetchColumn();
    echo "<p><strong>Your points balance:</strong> " . ($points ?? 0) . "</p>";
}

?>
