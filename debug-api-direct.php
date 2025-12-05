<?php
// Direct test of API response
require_once __DIR__ . '/config/db.php';

echo "Testing API endpoint directly...\n\n";

// Test for Author ID 1 (which has supporters)
$author_id = 1;

// Simulate what the API does
header('Content-Type: application/json');

$supporters = [];

// Get monetary supporters
$stmt = $pdo->prepare("
    SELECT 
        s.supporter_id,
        u.username,
        u.profile_image,
        s.tip_amount,
        s.patreon_tier,
        s.status
    FROM supporters s
    LEFT JOIN users u ON s.supporter_id = u.id
    WHERE s.author_id = ?
    ORDER BY s.tip_amount DESC
");
$stmt->execute([$author_id]);
$moneySupporters = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Money supporters found: " . count($moneySupporters) . "\n";

// Get point supporters
$stmt = $pdo->prepare("
    SELECT 
        au.supporter_id,
        u.username,
        u.profile_image,
        au.points_total
    FROM author_supporters au
    LEFT JOIN users u ON au.supporter_id = u.id
    WHERE au.author_id = ?
    ORDER BY au.points_total DESC
");
$stmt->execute([$author_id]);
$pointSupporters = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Point supporters found: " . count($pointSupporters) . "\n\n";

// Build array indexed by supporter_id
$byId = [];

foreach ($moneySupporters as $m) {
    $byId[$m['supporter_id']] = $m;
}

foreach ($pointSupporters as $p) {
    if (isset($byId[$p['supporter_id']])) {
        $byId[$p['supporter_id']]['points_total'] = (int)$p['points_total'];
    } else {
        $byId[$p['supporter_id']] = $p;
    }
}

$supporters = array_values($byId);

echo "Total merged: " . count($supporters) . "\n\n";

echo "Supporters data:\n";
foreach ($supporters as $s) {
    echo json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

// Now test the actual API call
echo "\n" . str_repeat("=", 60) . "\n";
echo "Testing actual API endpoint:\n\n";

$api_response = file_get_contents("http://localhost/scrollnovels/api/supporters/get-top-supporters.php?author_id=1");
$api_data = json_decode($api_response, true);

echo "API Success: " . ($api_data['success'] ? 'YES' : 'NO') . "\n";
echo "Total in API response: " . ($api_data['total'] ?? 0) . "\n";
echo "Data items: " . (count($api_data['data'] ?? []) ?? 0) . "\n\n";

if ($api_data['data']) {
    echo "Supporters from API:\n";
    foreach ($api_data['data'] as $s) {
        echo "- {$s['username']}: {$s['points_total']} pts, Status: " . ($s['status'] ?? 'N/A') . "\n";
    }
}
