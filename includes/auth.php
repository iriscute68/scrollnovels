<?php
// includes/auth.php
// Only start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Ensure SITE_URL and DB config are available when auth is included
if (!defined('SITE_URL')) {
    if (file_exists(__DIR__ . '/../config/config.php')) {
        require_once __DIR__ . '/../config/config.php';
    } elseif (file_exists(__DIR__ . '/../config.php')) {
        require_once __DIR__ . '/../config.php';
    }
}
// Load DB connection; prefer config/db.php
if (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
} elseif (file_exists(__DIR__ . '/../config.php')) {
    // config.php may already load DB; nothing to do
} else {
    // Last resort: try top-level config in config/ folder
    @require_once __DIR__ . '/../config/db.php';
}

// Define minimal URL helpers early so pages that call them before header won't fatal
if (!function_exists('site_url')) {
    function site_url($path = '') {
        if (!defined('SITE_URL')) define('SITE_URL', 'http://localhost/scrollnovels');
        if (empty($path)) return rtrim(SITE_URL, '/');
        return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
    }
}
if (!function_exists('asset_url')) {
    function asset_url($path) {
        if (!defined('SITE_URL')) define('SITE_URL', 'http://localhost/scrollnovels');
        return rtrim(SITE_URL, '/') . '/assets/' . ltrim($path, '/');
    }
}
// CSRF helpers fallback
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf_token'];
    }
}
if (!function_exists('verify_csrf')) {
    function verify_csrf($token) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return !empty($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . rtrim(SITE_URL, '/') . '/pages/login.php');
        exit;
    }
}

function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function hasRole($role) {
    global $pdo;
    if (!isLoggedIn()) return false;
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ? AND r.name = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id'], $role]);
        return (bool) $stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

function isRole($role) {
    return hasRole($role);
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        http_response_code(403);
        exit('Forbidden: insufficient permissions');
    }
}

function requireAdmin() {
    requireRole('admin');
}

function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        header('Location: ' . rtrim(SITE_URL, '/') . '/pages/dashboard.php');
        exit;
    }
}
?>