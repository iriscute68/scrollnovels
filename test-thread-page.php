<?php
// Test thread.php functionality
require 'config/db.php';

// Simulate GET request
$_GET['id'] = 1;
$_SESSION = ['user_id' => null]; // Not logged in

// Try to include thread.php and catch any errors
ob_start();
try {
    include 'pages/thread.php';
    $output = ob_get_clean();
    echo "✓ Thread page loaded successfully\n";
    echo "  Output length: " . strlen($output) . " bytes\n";
    
    // Check for key elements
    if (strpos($output, 'toggleLockThread') !== false) {
        echo "✓ Lock/unlock function found\n";
    } else {
        echo "✗ Lock/unlock function NOT found\n";
    }
    
    if (strpos($output, 'deleteThread') !== false) {
        echo "✓ Delete thread function found\n";
    } else {
        echo "✗ Delete thread function NOT found\n";
    }
    
    if (strpos($output, 'lock-thread.php') !== false) {
        echo "✓ Lock API endpoint found\n";
    } else {
        echo "✗ Lock API endpoint NOT found\n";
    }
    
    if (strpos($output, 'delete-thread.php') !== false) {
        echo "✓ Delete API endpoint found\n";
    } else {
        echo "✗ Delete API endpoint NOT found\n";
    }
    
} catch (Exception $e) {
    ob_get_clean();
    echo "✗ Error loading thread page:\n";
    echo "  " . $e->getMessage() . "\n";
}
?>
