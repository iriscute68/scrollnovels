<?php
/**
 * Paystack Card Management Config
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/config/db.php';

// Ensure user is authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . site_url('/pages/login.php'));
    exit;
}

$PAYSTACK_PUBLIC = getenv('PAYSTACK_PUBLIC') ?: 'pk_live_xxxxxxxx'; // Set in your .env or here
$PAYSTACK_SECRET = getenv('PAYSTACK_SECRET') ?: 'sk_live_xxxxxxxx';

// Helper to get current user email
function getCurrentUserEmail() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) return '';
    $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ? $user['email'] : '';
}

// CSRF token helper
function csrf_token() {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_check() {
    if (!isset($_POST['_csrf'])) return false;
    if (!isset($_SESSION['_csrf_token'])) return false;
    return hash_equals($_SESSION['_csrf_token'], $_POST['_csrf']);
}

?>
