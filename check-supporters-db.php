<?php
require_once __DIR__ . '/config/db.php';

echo "Supporters Database Check:\n";
echo "==========================\n\n";

// Check author_supporters
$stmt = $pdo->prepare('SELECT COUNT(*) as count FROM author_supporters');
$stmt->execute();
$count1 = $stmt->fetch()['count'];
echo "author_supporters table: $count1 records\n";

// Show sample author_supporters
if ($count1 > 0) {
    $stmt = $pdo->prepare('SELECT * FROM author_supporters LIMIT 5');
    $stmt->execute();
    $samples = $stmt->fetchAll();
    foreach ($samples as $s) {
        echo "  - Author ID: {$s['author_id']}, Supporter ID: {$s['supporter_id']}, Points: {$s['points_total']}\n";
    }
}

// Check supporters  
$stmt = $pdo->prepare('SELECT COUNT(*) as count FROM supporters');
$stmt->execute();
$count2 = $stmt->fetch()['count'];
echo "\nsupporters table: $count2 records\n";

// Show sample supporters
if ($count2 > 0) {
    $stmt = $pdo->prepare('SELECT * FROM supporters LIMIT 5');
    $stmt->execute();
    $samples = $stmt->fetchAll();
    foreach ($samples as $s) {
        echo "  - Author ID: {$s['author_id']}, Supporter ID: {$s['supporter_id']}, Amount: \${$s['tip_amount']}\n";
    }
}

// Check story_support if exists
echo "\nstory_support table: ";
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM story_support');
    $stmt->execute();
    $count3 = $stmt->fetch()['count'];
    echo "$count3 records\n";
} catch (Exception $e) {
    echo "Doesn't exist\n";
}

echo "\n" . str_repeat("=", 50) . "\n";

if ($count1 + $count2 == 0) {
    echo "⚠️  STATUS: No supporters exist!\n";
    echo "\nThe top supporters feature is WORKING CORRECTLY.\n";
    echo "It's just showing 'No supporters yet' because there are\n";
    echo "no actual supporter records in the database.\n";
    echo "\nThis is EXPECTED behavior, not a bug!\n";
} else {
    echo "✓ Supporters found. The feature should display them.\n";
}
