<?php
require 'config/db.php';
require 'includes/auth.php';

// Simulate getting an admin
$id = 5; // The admin we found earlier

echo "Testing get-admin.php logic:\n\n";

// This is what the API does
$stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
$stmt->execute([$id]);
$admin = $stmt->fetch();

if ($admin) {
    echo "✅ Admin found:\n";
    echo json_encode($admin, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "❌ Admin NOT found\n";
}

// Also test the hasRole function
echo "\n\nTesting hasRole function:\n";
try {
    if (function_exists('hasRole')) {
        echo "✅ hasRole function exists\n";
    } else {
        echo "❌ hasRole function NOT found\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// Check if includes/auth.php exists and is correct
echo "\n\nChecking includes/auth.php:\n";
if (file_exists('includes/auth.php')) {
    echo "✅ includes/auth.php exists\n";
    $content = file_get_contents('includes/auth.php');
    if (strpos($content, 'function hasRole') !== false) {
        echo "✅ hasRole function defined\n";
    } else {
        echo "❌ hasRole function NOT defined\n";
    }
} else {
    echo "❌ includes/auth.php NOT found\n";
}
?>
