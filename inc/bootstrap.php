<?php
declare(strict_types=1);
// inc/bootstrap.php
// Central bootstrap: sessions, error handling, app root, CSRF helpers, escaping.

// Error reporting: development may set display_errors=1; production should log.
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Path constants
if (!defined('APP_ROOT')) {
    // If this file lives in APP_ROOT/inc/
    define('APP_ROOT', realpath(__DIR__ . '/..'));
}

// Secure session settings before start
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'] ?? 0,
    'path' => $cookieParams['path'] ?? '/',
    'domain' => $cookieParams['domain'] ?? '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Basic autoload / include fallback helper
function safe_require(string $relPath): bool {
    $p = APP_ROOT . '/' . ltrim($relPath, '/');
    if (file_exists($p)) {
        require_once $p;
        return true;
    }
    error_log("Missing include: {$p}");
    return false;
}

// Load config if available (not fatal)
safe_require('config/db.php');

// CSRF helpers
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

function csrf_token(): string {
    return (string)($_SESSION['csrf_token'] ?? '');
}

function check_csrf($token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

// Helper to escape output (XSS prevention)
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Session regeneration helper for login
function secure_login_session_regenerate(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

// Check if user is logged in
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Require login, redirect if not
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . site_url('/pages/login.php'));
        exit;
    }
}

// Safe exec placeholder: only run whitelisted commands defined in code
function safe_exec_placeholder(string $which, string $args='') {
    // No commands allowed by default. If you need actions, explicitly map them here.
    $allowed = [
        // 'flush_cache' => '/usr/bin/redis-cli FLUSHALL',
    ];
    // args are ignored unless you add explicit handling
    if (isset($allowed[$args])) {
        $cmd = $allowed[$args];
    } else {
        error_log("safe_exec_placeholder blocked: {$which} {$args}");
        return false;
    }
    $descriptorSpec = [['pipe','r'], ['pipe','w'], ['pipe','w']];
    $process = proc_open($cmd, $descriptorSpec, $pipes);
    if (!is_resource($process)) return false;
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    proc_close($process);
    return $output;
}

// End bootstrap
?>
