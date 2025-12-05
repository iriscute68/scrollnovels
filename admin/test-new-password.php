<?php
// Test the new password works
require_once __DIR__ . '/../config.php';

echo "=== Testing Admin Login with New Password ===\n\n";

$username = 'admin';
$password = 'aa0246776376';

try {
    $stmt = $pdo->prepare("SELECT id, username, email, password, role FROM users WHERE (username = ? OR email = ?) LIMIT 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ User not found\n";
        exit;
    }
    
    echo "User found:\n";
    echo "  ID: {$user['id']}\n";
    echo "  Username: {$user['username']}\n";
    echo "  Email: {$user['email']}\n";
    echo "  Role: {$user['role']}\n\n";
    
    if (!password_verify($password, $user['password'])) {
        echo "❌ Password verification FAILED\n";
        exit;
    }
    
    echo "✅ Password verification PASSED\n\n";
    
    if (!in_array($user['role'], ['admin', 'super_admin', 'moderator'])) {
        echo "❌ User doesn't have admin role\n";
        exit;
    }
    
    echo "✅ Admin role confirmed\n\n";
    
    echo "🎉 LOGIN TEST SUCCESSFUL!\n";
    echo "You can now login to http://localhost/admin/ with:\n";
    echo "  Username: admin\n";
    echo "  Email: admin@scrollnovels.com\n";
    echo "  Password: aa0246776376\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
