<?php
/**
 * api/admin-login.php - Admin authentication endpoint
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim($input['email'] ?? '');
$password = trim($input['password'] ?? '');

// Validate input
if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Email and password required']);
    exit;
}

try {
    // Find admin user by email or username (check both tables)
    $admin = null;
    
    // First try users table with admin/moderator role
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.password, u.role
        FROM users u
        WHERE (u.email = ? OR u.username = ?) AND u.role IN ('admin', 'super_admin', 'moderator')
        LIMIT 1
    ");
    $stmt->execute([$email, $email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        // Don't reveal if email exists
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        exit;
    }
    
    // Verify password - support both password and password_hash fields
    $pwd_field = $admin['password'] ?? $admin['password_hash'] ?? '';
    if (!$pwd_field) {
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        exit;
    }
    
    if (!password_verify($password, $pwd_field)) {
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        exit;
    }
    
    // Set session
    $_SESSION['user_id'] = $admin['id'];
    $_SESSION['username'] = $admin['username'];
    $_SESSION['user_name'] = $admin['username'];
    $_SESSION['email'] = $admin['email'];
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_role'] = $admin['role'];
    // Ensure the standard `user_role` key is set for auth helpers
    $_SESSION['user_role'] = $admin['role'];
    $_SESSION['roles'] = json_encode([$admin['role']]);
    $_SESSION['is_admin'] = true;
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    // Log admin login
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO admin_logs (admin_id, action, ip_address, timestamp)
            VALUES (?, 'login', ?, NOW())
        ");
        $logStmt->execute([$admin['id'], $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {
        // Log table might not exist, ignore
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'admin_id' => $admin['id']
    ]);
    
} catch (Exception $e) {
    error_log('Admin login error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
?>
