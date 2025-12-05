<?php
$_GET['id'] = 4;
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
try {
    require 'pages/competition-details.php';
    $output = ob_get_clean();
    if (strpos($output, 'DOCTYPE') !== false) {
        echo "✓ PAGE LOADED SUCCESSFULLY - Found DOCTYPE\n";
        echo "Page size: " . strlen($output) . " bytes\n";
    } else {
        echo "✗ Page loaded but no DOCTYPE found\n";
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "✗ EXCEPTION: " . $e->getMessage() . "\n";
}
?>
