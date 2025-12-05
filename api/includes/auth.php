<?php
// Shim for API scripts that require ../includes/auth.php
// Try several common locations for the real auth include.
$candidates = [
    __DIR__ . '/../../includes/auth.php',
    __DIR__ . '/../../inc/auth.php',
    __DIR__ . '/../../includes/inc/auth.php',
];
foreach ($candidates as $cand) {
    if (file_exists($cand)) {
        require_once $cand;
        return;
    }
}
// If nothing found, raise a helpful error during CLI runs.
trigger_error('api/includes/auth.php shim could not locate real auth include', E_USER_WARNING);
