<?php
require_once __DIR__ . '/config.php';

// Check admin_action_logs table
try {
    $result = $pdo->query("DESCRIBE admin_action_logs");
    echo "admin_action_logs columns:\n";
    while($row = $result->fetch()) {
        echo $row['Field'] . "\n";
    }
} catch(Exception $e) {
    echo "Table doesn't exist: " . $e->getMessage();
}
?>
