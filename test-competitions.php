<?php
// Test competition display
require 'config/db.php';

echo "=== Testing Competition Display ===\n\n";

echo "1. Competitions in database:\n";
$comps = $pdo->query("SELECT id, title, start_date, end_date FROM competitions LIMIT 5")->fetchAll();
foreach ($comps as $c) {
    echo "   - ID {$c['id']}: {$c['title']}\n";
    echo "     Start: {$c['start_date']}, End: {$c['end_date']}\n";
}

echo "\n2. Current date: " . date('Y-m-d H:i:s') . "\n\n";

echo "3. Competition status calculation:\n";
$now = new DateTime();
foreach ($comps as $c) {
    $startDate = new DateTime($c['start_date']);
    $endDate = new DateTime($c['end_date']);
    
    if ($now < $startDate) {
        $status = 'upcoming';
    } elseif ($now > $endDate) {
        $status = 'ended';
    } else {
        $status = 'active';
    }
    
    echo "   - {$c['title']}: $status\n";
    echo "     (Start: {$startDate->format('Y-m-d H:i')}, End: {$endDate->format('Y-m-d H:i')}, Now: {$now->format('Y-m-d H:i')})\n";
}

echo "\nDone.\n";
?>
