<?php
// Test blog setup
require_once __DIR__ . '/config/db.php';

echo "=== BLOG SYSTEM CHECK ===\n";

// 1. Check posts table
try {
    $result = $pdo->query("SHOW TABLES LIKE 'posts'");
    if ($result->rowCount() > 0) {
        echo "✓ posts table exists\n";
    } else {
        echo "✗ posts table NOT found\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking table: " . $e->getMessage() . "\n";
}

// 2. Check blog files
$files = [
    'blog/index.php',
    'blog/create.php',
    'blog/save_post.php',
    'blog/post.php',
    'blog/preview.php'
];

echo "\nBlog Files:\n";
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "✓ $file exists\n";
    } else {
        echo "✗ $file NOT found\n";
    }
}

// 3. Check required includes
$includes = [
    'includes/auth.php',
    'includes/functions.php',
    'includes/header.php',
    'includes/footer.php',
    'config/db.php'
];

echo "\nRequired Includes:\n";
foreach ($includes as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "✓ $file exists\n";
    } else {
        echo "✗ $file NOT found\n";
    }
}

// 4. Check for slugify function
if (function_exists('slugify')) {
    echo "\n✓ slugify() function available\n";
} else {
    require_once __DIR__ . '/includes/functions.php';
    if (function_exists('slugify')) {
        echo "\n✓ slugify() function available after require\n";
    } else {
        echo "\n✗ slugify() function NOT found\n";
    }
}

echo "\n=== BLOG READY ===\n";
?>
