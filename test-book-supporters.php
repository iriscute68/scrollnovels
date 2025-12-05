<?php
/**
 * Test: Book Page - Supporters Tab
 * Checks if the supporters tab and loading works on book page
 */

require_once __DIR__ . '/config/db.php';

// Find a book with an author
$stmt = $pdo->prepare("SELECT s.id, s.author_id, u.username FROM stories s JOIN users u ON s.author_id = u.id LIMIT 1");
$stmt->execute();
$story = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$story) {
    die("❌ No stories found in database");
}

echo "✓ Testing book page: /pages/book.php?id=" . $story['id'] . "\n";
echo "  Story ID: " . $story['id'] . "\n";
echo "  Author ID: " . $story['author_id'] . "\n";
echo "  Author: " . $story['username'] . "\n\n";

// Check if API returns data for this author
$url = "http://localhost/scrollnovels/api/supporters/get-top-supporters.php?author_id=" . $story['author_id'];
echo "Testing API: $url\n";

$response = @file_get_contents($url);
if ($response === false) {
    echo "❌ API request failed\n";
} else {
    $data = json_decode($response, true);
    echo "✓ API response received\n";
    echo "  Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
    echo "  Total supporters found: " . ($data['total'] ?? 0) . "\n";
    if ($data['_debug']) {
        echo "  Debug info:\n";
        foreach ($data['_debug'] as $key => $value) {
            echo "    - $key: $value\n";
        }
    }
}

// Check if book page loads
echo "\nChecking if book page loads...\n";
$book_url = "http://localhost/scrollnovels/pages/book.php?id=" . $story['id'];
$book_response = @file_get_contents($book_url);
if ($book_response === false) {
    echo "❌ Book page request failed\n";
} else {
    echo "✓ Book page loads (" . strlen($book_response) . " bytes)\n";
    
    // Check for loadSupporters function
    if (strpos($book_response, 'function loadSupporters()') !== false) {
        echo "  ✓ loadSupporters() function found in HTML\n";
    } else {
        echo "  ❌ loadSupporters() function NOT found in HTML\n";
    }
    
    // Check for supporters tab button
    if (strpos($book_response, "onclick=\"switchTab('supporters')\"") !== false) {
        echo "  ✓ Supporters tab button found\n";
    } else {
        echo "  ❌ Supporters tab button NOT found\n";
    }
    
    // Check for supporters content div
    if (strpos($book_response, "id=\"supporters-content\"") !== false) {
        echo "  ✓ supporters-content div found\n";
    } else {
        echo "  ❌ supporters-content div NOT found\n";
    }
    
    // Check for supporters-loading
    if (strpos($book_response, "id=\"supporters-loading\"") !== false) {
        echo "  ✓ supporters-loading element found\n";
    } else {
        echo "  ❌ supporters-loading element NOT found\n";
    }
}

echo "\nTo manually test:\n";
echo "1. Open: http://localhost/scrollnovels/pages/book.php?id=" . $story['id'] . "\n";
echo "2. Click the '🏆 Top Supporters' tab\n";
echo "3. Open browser DevTools (F12) → Console tab\n";
echo "4. Look for console logs starting with '=== loadSupporters() CALLED ==='\n";
echo "5. Check if supporters load or if there's an error message\n";
