<?php
require_once __DIR__ . '/../config/db.php';
try {
    $res = $pdo->query("SHOW CREATE TABLE support_tickets")->fetchAll();
    if (!$res) {
        echo "support_tickets table not found\n";
        exit(1);
    }
    print_r($res[0]);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>