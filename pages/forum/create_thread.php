<?php
// Compatibility redirect for older forum create paths
if (session_status() === PHP_SESSION_NONE) session_start();

// Simple relative redirect to the canonical new-thread page
header('Location: ../new-thread.php');
exit;

?>
