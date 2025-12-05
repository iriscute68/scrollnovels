<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$api_url = site_url('/api/supporters/get-top-supporters.php');
echo "API URL from site_url(): $api_url\n";
echo "Full URL with params: " . $api_url . "?author_id=1&limit=200\n";

// Test if it's reachable
$full_url = $api_url . "?author_id=1&limit=200";
echo "\nTesting: $full_url\n";
$response = @file_get_contents($full_url);

if ($response === false) {
    echo "❌ URL not reachable!\n";
} else {
    echo "✓ URL is reachable\n";
    echo "Response length: " . strlen($response) . " bytes\n";
    $data = json_decode($response, true);
    echo "Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
    echo "Total supporters: " . ($data['total'] ?? 0) . "\n";
}
