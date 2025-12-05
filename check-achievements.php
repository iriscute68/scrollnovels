<?php
require_once __DIR__ . '/config.php';

$result = $pdo->query("DESCRIBE achievements");
echo "achievements columns:\n";
while($row = $result->fetch()) {
    echo $row['Field'] . "\n";
}
?>
