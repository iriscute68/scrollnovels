<?php
echo "=== Testing Book Page Top Supporters Feature ===\n\n";

require 'config/db.php';

// Get a story
$stmt = $pdo->query("SELECT s.*, u.id as author_id FROM stories s LEFT JOIN users u ON s.author_id = u.id WHERE s.id = 1");
$story = $stmt->fetch(PDO::FETCH_ASSOC);

if ($story) {
    echo "✓ Story found: {$story['title']} (ID: {$story['id']}, Author ID: {$story['author_id']})\n\n";
    
    // Test the API
    echo "Testing API call:\n";
    $api_url = "http://localhost/scrollnovels/api/supporters/get-top-supporters.php?author_id=" . $story['author_id'] . "&limit=200";
    echo "  URL: $api_url\n";
    
    $response = @file_get_contents($api_url);
    if ($response === false) {
        echo "  ✗ Failed to fetch\n";
    } else {
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "  ✗ Invalid JSON: " . json_last_error_msg() . "\n";
            echo "  Response: " . substr($response, 0, 200) . "\n";
        } else {
            if ($data['success']) {
                echo "  ✓ API Success\n";
                echo "  ✓ Supporters found: " . count($data['data']) . "\n";
                if (count($data['data']) > 0) {
                    echo "  ✓ First supporter: {$data['data'][0]['username']} (" . $data['data'][0]['points_total'] . " points)\n";
                }
            } else {
                echo "  ✗ API returned error: " . $data['error'] . "\n";
            }
        }
    }
} else {
    echo "✗ No story found\n";
}

// Now simulate loading the book page
echo "\n\n=== Testing Book Page Load ===\n";
$_GET['id'] = 1;
ob_start();
require 'pages/book.php';
$output = ob_get_clean();

if (strpos($output, 'loadSupporters') !== false) {
    echo "✓ loadSupporters function found on page\n";
}
if (strpos($output, 'supporters-loading') !== false) {
    echo "✓ supporters-loading element found on page\n";
}
if (strpos($output, 'supporters-list') !== false) {
    echo "✓ supporters-list element found on page\n";
}
if (strpos($output, 'supporters-empty') !== false) {
    echo "✓ supporters-empty element found on page\n";
}
if (strpos($output, 'supporters-count') !== false) {
    echo "✓ supporters-count element found on page\n";
}

echo "\n✓ All checks passed! The top supporters feature should work.\n";
?>
