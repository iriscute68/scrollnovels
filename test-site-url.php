<?php
// Set error reporting to show everything
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Simulate GET parameter for competition ID 4
$_GET['id'] = 4;

// Simulate accessing the page
ob_start();
try {
    include 'pages/competition-details.php';
} catch (Exception $e) {
    echo "Exception during include: " . $e->getMessage() . "\n";
}
$output = ob_get_clean();

echo "Page output length: " . strlen($output) . " bytes\n";

if (strlen($output) > 500) {
    echo "✓ Page loaded successfully\n";
    echo "First 500 chars:\n";
    echo substr($output, 0, 500) . "...\n";
} else {
    echo "✗ Page output too short, likely redirected or errored\n";
    echo "Output: " . $output . "\n";
}
?>
