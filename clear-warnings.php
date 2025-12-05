<?php
require_once 'config/db.php';

$pdo->exec("UPDATE stories SET content_warnings = NULL WHERE id = 8");
echo "Cleared content_warnings for story 8";
?>
