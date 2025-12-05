<?php
require 'config/db.php';

echo "=== Checking database structure ===\n\n";

// Check stories table
echo "1. Stories table columns:\n";
$stmt = $pdo->query("DESCRIBE stories");
$cols = $stmt->fetchAll();
foreach ($cols as $col) {
    echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n2. Sample story:\n";
$stmt = $pdo->query("SELECT s.*, u.id as author_id FROM stories s LEFT JOIN users u ON s.author_id = u.id LIMIT 1");
$story = $stmt->fetch(PDO::FETCH_ASSOC);
if ($story) {
    echo "  Story ID: " . $story['id'] . "\n";
    echo "  Story Title: " . $story['title'] . "\n";
    echo "  Story author_id (from JOIN): " . $story['author_id'] . "\n";
    
    // Check supporters for this author
    echo "\n3. Checking supporters for author_id={$story['author_id']}:\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM author_supporters WHERE author_id = ?");
    $stmt->execute([$story['author_id']]);
    $result = $stmt->fetch();
    echo "  author_supporters records: " . $result['cnt'] . "\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM supporters WHERE author_id = ?");
    $stmt->execute([$story['author_id']]);
    $result = $stmt->fetch();
    echo "  supporters records: " . $result['cnt'] . "\n";
    
    // story_support table might not exist, skip checking it here
    
    // Test the API directly
    echo "\n4. Testing API call...\n";
    $ch = curl_init("http://localhost/scrollnovels/api/supporters/get-top-supporters.php?author_id=" . $story['author_id']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    echo "API Response:\n";
    echo $response . "\n";
} else {
    echo "No stories found!\n";
}
?>

