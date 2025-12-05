<?php
$rules_content = file_get_contents('pages/website-rules.php');
echo "Checking for 'HIGHLY RECOMMENDED': " . (strpos($rules_content, 'HIGHLY RECOMMENDED') !== false ? 'YES' : 'NO') . "\n";
echo "Checking for 'Female protagonist': " . (strpos($rules_content, 'Female protagonist') !== false ? 'YES' : 'NO') . "\n";
echo "Checking for 'LGBTQ+': " . (strpos($rules_content, 'LGBTQ+') !== false ? 'YES' : 'NO') . "\n";

// Find where HIGHLY RECOMMENDED is
$pos = strpos($rules_content, 'HIGHLY RECOMMENDED');
if ($pos !== false) {
    echo "\nFound at position: $pos\n";
    echo "Context: " . substr($rules_content, $pos - 50, 150) . "\n";
} else {
    echo "\nNOT FOUND\n";
}

// Check line count
$lines = count(file('pages/website-rules.php'));
echo "\nTotal lines: $lines\n";
echo "File size: " . filesize('pages/website-rules.php') . " bytes\n";
?>
