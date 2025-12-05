<?php
require_once __DIR__ . '/config.php';

// Get tags from database
$stmt = $pdo->prepare("SELECT id, name FROM tags WHERE id IN (SELECT DISTINCT tag_id FROM story_tags)");
$stmt->execute();
$dbTags = $stmt->fetchAll();

// Simulate API response
$fallbackGenres = [
    ['id'=>1,'name'=>'Action','emoji'=>'⚔️'],
    ['id'=>2,'name'=>'Adventure','emoji'=>'🗺️'],
];

// Simulate what the API returns for tags
$apiTagsData = [
    'tag' => [
        ['id'=>1,'name'=>'Action'],
        ['id'=>2,'name'=>'Adventure'],
        ['id'=>3,'name'=>'Immortal'],
        ['id'=>4,'name'=>'NonHuman'],
    ],
    'warning' => [
        ['id'=>100,'name'=>'Violence'],
        ['id'=>101,'name'=>'War'],
    ]
];

// Get story 8 tags
$stmt = $pdo->prepare("SELECT tags FROM stories WHERE id = 8");
$stmt->execute();
$story = $stmt->fetch();
$storyTags = array_filter(array_map('trim', explode(',', $story['tags'] ?? '')));

echo "<h2>Tag Matching Debug</h2>";
echo "<h3>Story 8 Tags (from DB):</h3>";
echo json_encode($storyTags, JSON_PRETTY_PRINT) . "\n";

echo "<h3>Available Tags (from API):</h3>";
$allTags = array_merge($apiTagsData['tag'], $apiTagsData['warning']);
foreach($allTags as $t) {
    echo "- ID: " . $t['id'] . ", Name: " . $t['name'] . "\n";
}

echo "<h3>Matching Results:</h3>";
foreach($storyTags as $storyTagName) {
    $found = null;
    foreach($allTags as $t) {
        if ($t['name'].toLowerCase() === storyTagName.toLowerCase()) {
            $found = $t;
            break;
        }
    }
    if ($found) {
        echo "✓ '$storyTagName' → ID: " . $found['id'] . "\n";
    } else {
        echo "✗ '$storyTagName' → NOT FOUND (will have id: null)\n";
    }
}
?>
