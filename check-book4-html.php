<?php
// Fetch the actual HTML rendered for book 4
$html = @file_get_contents('http://localhost/scrollnovels/pages/book.php?id=4');

// Look for the loadSupporters function
if (strpos($html, 'function loadSupporters()') !== false) {
    echo "✓ loadSupporters() function found\n";
} else {
    echo "❌ loadSupporters() function NOT found\n";
}

// Look for the supporters tab
if (strpos($html, "onclick=\"switchTab('supporters')\"") !== false) {
    echo "✓ Supporters tab button found\n";
} else {
    echo "❌ Supporters tab button NOT found\n";
}

// Look for the author_id in JavaScript
preg_match('/const authorId = (\d+|null);/', $html, $matches);
if ($matches) {
    echo "✓ Author ID in JS: " . $matches[1] . "\n";
} else {
    echo "❌ Author ID not found in JS\n";
}

// Look for supporters-content div
if (strpos($html, "id=\"supporters-content\"") !== false) {
    echo "✓ supporters-content div found\n";
} else {
    echo "❌ supporters-content div NOT found\n";
}

// Look for supporters-loading div
if (strpos($html, "id=\"supporters-loading\"") !== false) {
    echo "✓ supporters-loading div found\n";
} else {
    echo "❌ supporters-loading div NOT found\n";
}

// Extract a snippet of the loadSupporters function
if (preg_match('/function loadSupporters\(\) \{[\s\S]{0,500}/', $html, $matches)) {
    echo "\n📝 First 500 chars of loadSupporters():\n";
    echo $matches[0] . "...\n";
}
