<?php
// admin/add-admin.php — simple handler to promote a user to a role
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../inc/roles_permissions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . rtrim(defined('SITE_URL') ? SITE_URL : '/scrollnovels', '/') . '/admin/admins.php');
    exit;
}

$user_id = (int)($_POST['user_id'] ?? 0);
$role = trim($_POST['role'] ?? '');

if (!$user_id || $role === '') {
    $_SESSION['admin_error'] = 'Missing user or role';
    header('Location: ' . rtrim(defined('SITE_URL') ? SITE_URL : '/scrollnovels', '/') . '/admin/admins.php');
    exit;
}

if (promote_user_to_role($pdo, $user_id, $role)) {
    $_SESSION['admin_success'] = 'Promoted user successfully';
} else {
    $_SESSION['admin_error'] = 'Failed to promote user';
}

header('Location: ' . rtrim(defined('SITE_URL') ? SITE_URL : '/scrollnovels', '/') . '/admin/admins.php');
exit;

