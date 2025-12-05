<?php
// Debug admin login issue
require_once __DIR__ . '/../config/db.php';

echo "=== Admin Login Debug ===\n\n";

// Check if users table exists
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Database tables found: " . count($tables) . "\n";
    if (!in_array('users', $tables)) {
        echo "ERROR: 'users' table does not exist!\n";
        exit;
    }
    echo "✓ 'users' table exists\n\n";
} catch (Exception $e) {
    echo "ERROR checking tables: " . $e->getMessage() . "\n";
    exit;
}

// Check admin users
echo "Checking for admin users...\n";
try {
    $stmt = $pdo->query("SELECT id, username, email, role, password FROM users WHERE role IN ('admin', 'super_admin', 'moderator') LIMIT 10");
    $admins = $stmt->fetchAll();
    
    if (empty($admins)) {
        echo "❌ No admin users found!\n\n";
        echo "Creating admin user now...\n";
        
        $hashed = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
        $result = $stmt->execute(['admin', 'admin@scrollnovels.com', $hashed, 'super_admin']);
        
        if ($result) {
            echo "✓ Admin user created successfully!\n";
            echo "  Username: admin\n";
            echo "  Email: admin@scrollnovels.com\n";
            echo "  Password: admin123\n";
            echo "  Role: super_admin\n";
        } else {
            echo "ERROR: Failed to create admin user\n";
        }
    } else {
        echo "✓ Found " . count($admins) . " admin user(s):\n\n";
        foreach ($admins as $admin) {
            echo "  ID: {$admin['id']}\n";
            echo "  Username: {$admin['username']}\n";
            echo "  Email: {$admin['email']}\n";
            echo "  Role: {$admin['role']}\n";
            echo "  Password hash exists: " . (!empty($admin['password']) ? "Yes" : "No") . "\n";
            
            // Test password
            if (!empty($admin['password'])) {
                $test_pass = password_verify('admin123', $admin['password']);
                echo "  Password 'admin123' matches: " . ($test_pass ? "Yes ✓" : "No ✗") . "\n";
            }
            echo "\n";
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Test the login query
echo "\nTesting login query with 'admin'...\n";
try {
    $stmt = $pdo->prepare("SELECT id, username, email, password, role FROM users WHERE (username = ? OR email = ?) LIMIT 1");
    $stmt->execute(['admin', 'admin']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "✓ User found\n";
        echo "  ID: {$user['id']}\n";
        echo "  Username: {$user['username']}\n";
        echo "  Email: {$user['email']}\n";
        echo "  Role: {$user['role']}\n";
    } else {
        echo "❌ User not found with username 'admin'\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Debug Complete ===\n";
?>
