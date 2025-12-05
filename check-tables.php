<?php
require_once 'config/db.php';

echo "=== Chapters Table Structure ===\n";
$cols = $pdo->query("DESCRIBE chapters")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "  - {$c['Field']} ({$c['Type']})\n";
}

echo "\n=== Verification Requests Table Structure ===\n";
try {
    $cols = $pdo->query("DESCRIBE verification_requests")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "  - {$c['Field']} ({$c['Type']})\n";
    }
} catch (Exception $e) {
    echo "Table doesn't exist: " . $e->getMessage() . "\n";
}
