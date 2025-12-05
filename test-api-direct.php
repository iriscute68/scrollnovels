<?php
// Simulate an API call to get-admin.php
session_start();
$_SESSION['user_id'] = 5;

require 'config/db.php';
require 'includes/auth.php';

header('Content-Type: application/json');

echo "=== Testing get-admin.php directly ===\n\n";

// Check if user is logged in
if (!isLoggedIn()) {
    echo "❌ User not logged in\n";
    exit;
}
echo "✅ User is logged in\n";

// Check if user is admin
if (!hasRole('admin')) {
    echo "❌ User is not admin\n";
    exit;
}
echo "✅ User has admin role\n";

$id = (int)5;

if (!$id) {
    echo "❌ No admin ID provided\n";
    exit;
}
echo "✅ Admin ID provided: $id\n";

try {
    $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        echo "❌ Admin not found in database\n";
        exit;
    }
    
    echo "✅ Admin found in database\n";
    echo "Response:\n";
    echo json_encode($admin) . "\n";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>
