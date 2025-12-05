<?php
// admin/index.php - Admin entry redirect
session_start();

// Use main site database connection
require_once __DIR__ . '/../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: login.php', true, 302);
    exit;
}

// Verify session is still valid in database
try {
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ? AND role IN ('admin', 'super_admin', 'moderator')");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        session_destroy();
        header('Location: login.php', true, 302);
        exit;
    }
} catch (Exception $e) {
    error_log('Session verification error: ' . $e->getMessage());
    session_destroy();
    header('Location: login.php', true, 302);
    exit;
}

// Redirect to new admin dashboard
header('Location: admin.php?page=dashboard', true, 302);
exit;
?>
