<?php
// Test staff management APIs
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';

// Set up test session for admin user (ID 5)
$_SESSION['user_id'] = 5;

echo "=== Testing Staff Management APIs ===\n\n";

// Test 1: Get admin data
echo "1. Testing GET-ADMIN API (id=5):\n";
$ch = curl_init('http://localhost/scrollnovels/api/admin/get-admin.php?id=5');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP Code: $http_code\n";
echo "Response: $response\n\n";

// Test 2: List admins from database
echo "2. Current admins in database:\n";
$admins = $pdo->query("SELECT id, username, email, role FROM users WHERE role IN ('admin', 'super_admin', 'moderator') ORDER BY id DESC")->fetchAll();
foreach ($admins as $admin) {
    echo "- ID: {$admin['id']}, Username: {$admin['username']}, Email: {$admin['email']}, Role: {$admin['role']}\n";
}
echo "\n";

// Test 3: Check permission for current user
echo "3. Checking permission for current user (ID 5):\n";
$stmt = $pdo->prepare("SELECT admin_level, role FROM users WHERE id = ?");
$stmt->execute([5]);
$user = $stmt->fetch();
echo "User ID 5 data: " . json_encode($user) . "\n";

$is_admin = false;
if ($user) {
    if ((isset($user['admin_level']) && $user['admin_level'] >= 2) ||
        (isset($user['role']) && in_array($user['role'], ['admin', 'super_admin', 'moderator']))) {
        $is_admin = true;
    }
}
echo "Is admin: " . ($is_admin ? "YES" : "NO") . "\n";
?>
