<?php
// setup_admin_user.php
require_once __DIR__ . '/admin/inc/db.php';

try {
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
    $stmt->execute(['admin']);
    $existing = $stmt->fetch();
    
    if ($existing) {
        echo "Admin user already exists!<br>";
    } else {
        // Create admin user with password: admin123
        $hashedPassword = password_hash('admin123', PASSWORD_BCRYPT);
        
        $stmt = $pdo->prepare("INSERT INTO admins (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
        $result = $stmt->execute(['admin', 'admin@scrollnovels.local', $hashedPassword, 'admin', 'active']);
        
        if ($result) {
            echo "✅ Admin user created successfully!<br>";
            echo "Username: <strong>admin</strong><br>";
            echo "Password: <strong>admin123</strong><br>";
            echo "<br>You can now login at: <a href='/admin/login.php'>/admin/login.php</a>";
        } else {
            echo "❌ Failed to create admin user";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

