<?php
error_log("===== BOOK.PHP DEBUG START =====");
error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
error_log("QUERY_STRING: " . $_SERVER['QUERY_STRING']);
error_log("\$_GET id: " . ($_GET['id'] ?? 'NULL'));

$bookId = (int)($_GET['id'] ?? 0);
error_log("Parsed bookId: " . $bookId);

if (!$bookId) {
    error_log("REDIRECT TRIGGERED: NO BOOK ID");
} else {
    error_log("Book ID is valid, will attempt to fetch from DB");
}

// Try to fetch the story
require_once dirname(__DIR__) . '/config/db.php';
$stmt = $pdo->prepare("SELECT id, title FROM stories WHERE id = ?");
$stmt->execute([$bookId]);
$story = $stmt->fetch();

if ($story) {
    error_log("Story found: " . $story['title']);
} else {
    error_log("Story NOT found for id=" . $bookId);
    error_log("REDIRECT WOULD BE TRIGGERED");
}

echo "Check error log for full debug output";
?>

