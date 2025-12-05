<?php
// Simple wrapper so api scripts that call require_once('../config.php') will work
// when executed from the api/ directory.
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} elseif (file_exists(__DIR__ . '/../../config.php')) {
    require_once __DIR__ . '/../../config.php';
} else {
    trigger_error('api/config.php wrapper could not find project config.php', E_USER_WARNING);
}

// When running scripts from CLI or non-web contexts, ensure REQUEST_METHOD is defined to avoid notices
if (php_sapi_name() === 'cli' || !isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

