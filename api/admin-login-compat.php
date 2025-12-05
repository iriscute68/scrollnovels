<?php
/**
 * api/admin-login-compat.php - Admin authentication endpoint (compat)
 * Supports either `admins` table or `users` table with admin role and normalizes session.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/inc/db.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim($input['email'] ?? '');
$password = trim($input['password'] ?? '');

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Email and password required']);
    exit;
}

try {
    $admin = null;

    // Try users table first (preferred source)
           $stmt = $pdo->prepare("SELECT id, username, email, password, role FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fallback to admins table if present
    if (!$admin) {
        try {
            $stmt = $pdo->prepare("SELECT id, username, email, password, role FROM admins WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // admins table doesn't exist or query failed
        }
    }

    if (!$admin) {
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        exit;
    }

    // Check role
    $role = strtolower($admin['role'] ?? '');
    if (!in_array($role, ['admin', 'superadmin', 'super_admin', 'moderator', 'mod', 'owner'])) {
        echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
        exit;
    }

    // Verify password
    $hash = $admin['password'] ?? '';
    if (!password_verify($password, $hash)) {
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        exit;
    }

    // Set session variables (compatible with both site and admin area)
    $_SESSION['user_id'] = (int)$admin['id'];
    $_SESSION['username'] = $admin['username'] ?? '';
    $_SESSION['user_name'] = $admin['username'] ?? '';
    $_SESSION['email'] = $admin['email'] ?? '';
    $_SESSION['user_role'] = $role;
    $_SESSION['roles'] = json_encode([$role]);
    $_SESSION['is_admin'] = true;

    // Legacy admin session keys
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_username'] = $admin['username'] ?? '';

    echo json_encode(['success' => true, 'message' => 'Login successful', 'admin_id' => (int)$admin['id']]);
    exit;
} catch (Exception $e) {
    error_log('admin-login-compat error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
}
