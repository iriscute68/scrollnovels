<?php
require_once 'config/db.php';
$cols = $pdo->query('DESCRIBE ticket_replies')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['Field'] . ' - ' . $c['Type'] . PHP_EOL;
}
