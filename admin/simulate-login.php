<?php
// Simulate the exact login POST request
session_start();
require_once __DIR__ . '/../config/db.php';

echo "=== Simulating Admin Login POST Request ===\n\n";

// Simulate form submission
$_POST['username'] = 'admin';
$_POST['password'] = 'admin123';

$u = $_POST['username'] ?? '';
$p = $_POST['password'] ?? '';

echo "Input:\n";
echo "  Username: $u\n";
echo "  Password: [hidden]\n\n";

if (empty($u) || empty($p)) {
    echo "❌ Error: Username and password are required\n";
    exit;
}

try {
    echo "Step 1: Querying database for user...\n";
    $stmt = $pdo->prepare("SELECT id, username, email, password, role FROM users WHERE (username = ? OR email = ?) LIMIT 1");
    $stmt->execute([$u, $u]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ User not found\n";
        exit;
    }
    echo "✓ User found (ID: {$user['id']})\n\n";
    
    echo "Step 2: Verifying password...\n";
    if (!password_verify($p, $user['password'])) {
        echo "❌ Invalid password\n";
        exit;
    }
    echo "✓ Password verified\n\n";
    
    echo "Step 3: Checking admin privileges...\n";
    if (!in_array($user['role'] ?? '', ['admin', 'super_admin', 'moderator'])) {
        echo "❌ Access denied: Admin privileges required (Role: {$user['role']})\n";
        exit;
    }
    echo "✓ User has admin role: {$user['role']}\n\n";
    
    echo "Step 4: Setting session variables...\n";
    $_SESSION['admin_id'] = $user['id'];
    $_SESSION['admin_username'] = $user['username'];
    $_SESSION['admin_role'] = $user['role'];
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['roles'] = json_encode([$user['role']]);
    $_SESSION['logged_in'] = true;
    
    echo "✓ Session variables set\n\n";
    
    echo "Step 5: Verifying session before redirect...\n";
    if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
        echo "❌ Session verification failed!\n";
        exit;
    }
    echo "✓ Session verified\n\n";
    
    echo "✅ LOGIN SUCCESSFUL!\n\n";
    echo "Session data:\n";
    foreach ($_SESSION as $key => $val) {
        if (is_array($val)) {
            echo "  \$_SESSION['$key'] = " . json_encode($val) . "\n";
        } else {
            echo "  \$_SESSION['$key'] = $val\n";
        }
    }
    echo "\nWould redirect to: dashboard.php?tab=overview\n";
    
} catch (Exception $e) {
    echo "❌ Server error: " . $e->getMessage() . "\n";
    error_log('Admin login error: ' . $e->getMessage());
}
?>
