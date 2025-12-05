<?php
// Update admin password
require_once __DIR__ . '/../config.php';

echo "=== Updating Admin Password ===\n\n";

$new_password = 'aa0246776376';
$username = 'admin';
$email = 'admin@scrollnovels.com';

try {
    // Hash the new password
    $hashed = password_hash($new_password, PASSWORD_BCRYPT);
    
    echo "Old password: admin123\n";
    echo "New password: $new_password\n";
    echo "Hashed: " . substr($hashed, 0, 20) . "...\n\n";
    
    // Update the password in the database
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE (username = ? OR email = ?) AND role IN ('admin', 'super_admin', 'moderator')");
    $result = $stmt->execute([$hashed, $username, $email]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo "✅ Password updated successfully!\n";
        echo "Updated " . $stmt->rowCount() . " admin user(s)\n\n";
        
        // Verify the update
        $verify = $pdo->prepare("SELECT id, username, email, role FROM users WHERE username = ? AND role IN ('admin', 'super_admin', 'moderator')");
        $verify->execute([$username]);
        $admin = $verify->fetch();
        
        if ($admin) {
            echo "Verified user:\n";
            echo "  ID: {$admin['id']}\n";
            echo "  Username: {$admin['username']}\n";
            echo "  Email: {$admin['email']}\n";
            echo "  Role: {$admin['role']}\n\n";
            
            // Test the new password
            $test = $pdo->prepare("SELECT password FROM users WHERE username = ?");
            $test->execute([$username]);
            $pwd_row = $test->fetch();
            
            if (password_verify($new_password, $pwd_row['password'])) {
                echo "✅ Password verification successful!\n";
                echo "\nYou can now login with:\n";
                echo "  Username: admin\n";
                echo "  Email: admin@scrollnovels.com\n";
                echo "  Password: $new_password\n";
            } else {
                echo "❌ Password verification failed!\n";
            }
        }
    } else {
        echo "❌ No admin users updated. Check username/email.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
