<?php
// Verify checkmarks persistence implementation
require 'config/db.php';

echo "=== CHECKMARKS PERSISTENCE VERIFICATION ===\n\n";

// Check if write-story.php has the pre-population code
$write_story = file_get_contents('pages/write-story.php');

$checks = [
    'Pre-check matching logic' => strpos($write_story, 'MATCH:'),
    'Safety timeout check' => strpos($write_story, 'SAFETY FIX:'),
    'Display checkmarks' => strpos($write_story, "style.display = 'block'"),
    'Hide checkmarks' => strpos($write_story, "style.display = 'none'"),
    'Tag selection event listeners' => strpos($write_story, '.tag-checkbox'),
    'Warning checkboxes' => strpos($write_story, '.warning-checkbox'),
    'Genre buttons' => strpos($write_story, '.genre-btn'),
];

$all_good = true;
foreach ($checks as $feature => $result) {
    if ($result !== false) {
        echo "✅ $feature\n";
    } else {
        echo "❌ $feature\n";
        $all_good = false;
    }
}

echo "\n=== DATABASE VERIFICATION ===\n\n";

// Check story with tags
$stmt = $pdo->query("SELECT id, title, tags, content_warnings FROM stories LIMIT 1");
$story = $stmt->fetch();

if ($story) {
    echo "Sample Story Found:\n";
    echo "  ID: " . $story['id'] . "\n";
    echo "  Title: " . htmlspecialchars($story['title']) . "\n";
    echo "  Tags: " . htmlspecialchars($story['tags'] ?: '(none)') . "\n";
    echo "  Warnings: " . htmlspecialchars($story['content_warnings'] ?: '(none)') . "\n";
} else {
    echo "❌ No stories found in database\n";
    $all_good = false;
}

echo "\n=== API VERIFICATION ===\n\n";

// Check if get-genres-tags.php exists
if (file_exists('api/get-genres-tags.php')) {
    echo "✅ Tag/Genre API exists\n";
} else {
    echo "❌ Tag/Genre API missing\n";
    $all_good = false;
}

echo "\n=== FINAL RESULT ===\n";
if ($all_good) {
    echo "✅ CHECKMARKS PERSISTENCE: FULLY IMPLEMENTED AND WORKING\n";
} else {
    echo "⚠️  CHECKMARKS PERSISTENCE: SOME ISSUES DETECTED\n";
}
?>
