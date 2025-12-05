<?php
require_once __DIR__ . '/config/db.php';

echo "Testing API for Book 4 (Author ID 1):\n";
echo "======================================\n\n";

$api_url = "http://localhost/scrollnovels/api/supporters/get-top-supporters.php?author_id=1&limit=200";

echo "URL: $api_url\n\n";

// Test 1: Direct file_get_contents
echo "Test 1: file_get_contents\n";
$response = @file_get_contents($api_url);
if ($response === false) {
    echo "  ❌ FAILED - URL not accessible\n";
} else {
    echo "  ✓ Response received (" . strlen($response) . " bytes)\n";
    $data = json_decode($response, true);
    if ($data) {
        echo "  ✓ Valid JSON\n";
        echo "  Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
        echo "  Total: " . $data['total'] . "\n";
        echo "  Data items: " . count($data['data']) . "\n";
    } else {
        echo "  ❌ Invalid JSON\n";
        echo "  Raw response: " . substr($response, 0, 200) . "\n";
    }
}

// Test 2: Check if API file exists
echo "\n\nTest 2: API file exists\n";
$api_file = __DIR__ . '/api/supporters/get-top-supporters.php';
if (file_exists($api_file)) {
    echo "  ✓ File exists at: $api_file\n";
} else {
    echo "  ❌ File NOT found at: $api_file\n";
}

// Test 3: Direct PHP include
echo "\n\nTest 3: Direct PHP execution\n";
$_GET['author_id'] = 1;
$_GET['limit'] = 200;
ob_start();
try {
    include($api_file);
} catch (Exception $e) {
    echo "  ❌ Exception: " . $e->getMessage() . "\n";
}
$output = ob_get_clean();
echo "  Output length: " . strlen($output) . " bytes\n";
if (strlen($output) > 0) {
    $direct_data = json_decode($output, true);
    if ($direct_data) {
        echo "  ✓ Valid JSON from direct include\n";
        echo "  Success: " . ($direct_data['success'] ? 'YES' : 'NO') . "\n";
        echo "  Total: " . $direct_data['total'] . "\n";
    } else {
        echo "  ❌ Invalid JSON\n";
        echo "  Output: " . substr($output, 0, 200) . "\n";
    }
}
