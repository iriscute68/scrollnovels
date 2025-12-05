<?php
// Test admin login flow
session_start();
require_once __DIR__ . '/../config/db.php';

echo "=== Testing Admin Login Flow ===\n\n";

// Step 1: Try to find admin user
echo "Step 1: Looking for admin user...\n";
try {
    $stmt = $pdo->prepare("SELECT id, username, email, password, role FROM users WHERE username = ? LIMIT 1");
    $stmt->execute(['admin']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ Admin user not found!\n";
        exit;
    }
    
    echo "✓ Admin user found:\n";
    echo "  ID: {$user['id']}\n";
    echo "  Username: {$user['username']}\n";
    echo "  Email: {$user['email']}\n";
    echo "  Role: {$user['role']}\n\n";
} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    exit;
}

// Step 2: Verify password
echo "Step 2: Testing password verification...\n";
$test_password = 'admin123';
if (!password_verify($test_password, $user['password'])) {
    echo "❌ Password verification failed!\n";
    exit;
}
echo "✓ Password verification successful\n\n";

// Step 3: Check role
echo "Step 3: Checking admin role...\n";
if (!in_array($user['role'], ['admin', 'super_admin', 'moderator'])) {
    echo "❌ User does not have admin role!\n";
    exit;
}
echo "✓ User has admin role: {$user['role']}\n\n";

// Step 4: Simulate session creation
echo "Step 4: Setting session variables...\n";
$_SESSION['admin_id'] = $user['id'];
$_SESSION['admin_username'] = $user['username'];
$_SESSION['admin_role'] = $user['role'];
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['username'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['roles'] = json_encode([$user['role']]);
$_SESSION['logged_in'] = true;

echo "✓ Session variables set:\n";
foreach ($_SESSION as $key => $value) {
    echo "  \$_SESSION['$key'] = " . (is_array($value) ? json_encode($value) : $value) . "\n";
}
echo "\n";

// Step 5: Verify session
echo "Step 5: Verifying session...\n";
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    echo "❌ Session verification failed!\n";
    exit;
}
echo "✓ Session is valid\n\n";

// Step 6: Re-verify from database
echo "Step 6: Re-verifying from database...\n";
$stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ? AND role IN ('admin', 'super_admin', 'moderator')");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();

if (!$admin) {
    echo "❌ User not found or doesn't have admin role!\n";
    exit;
}
echo "✓ User verified in database\n\n";

echo "=== ALL TESTS PASSED ===\n";
echo "Login flow is working correctly!\n";
echo "\nYou can now login with:\n";
echo "  Username: admin\n";
echo "  Email: admin@scrollnovels.com\n";
echo "  Password: admin123\n";
?>
