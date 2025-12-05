<?php
// Shim for API scripts that require ../includes/functions.php
$candidates = [
    __DIR__ . '/../../includes/functions.php',
    __DIR__ . '/../../inc/functions.php',
    __DIR__ . '/../../includes/inc/functions.php',
];
foreach ($candidates as $cand) {
    if (file_exists($cand)) {
        require_once $cand;
        return;
    }
}
trigger_error('api/includes/functions.php shim could not locate real functions include', E_USER_WARNING);
