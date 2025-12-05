<?php
// Test staff management APIs
session_start();

// For testing, set session manually
$_SESSION['user_id'] = 5;  // Assuming ID 5 is an admin

require_once 'config/db.php';

echo "=== Testing Staff Management APIs ===\n\n";

// Test 1: Verify current user is admin
echo "Test 1: Check current user admin status\n";
$stmt = $pdo->prepare("SELECT id, username, email, role, admin_level FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();
echo "  User ID: " . $admin['id'] . "\n";
echo "  Username: " . $admin['username'] . "\n";
echo "  Role: " . $admin['role'] . "\n";
echo "  Admin Level: " . $admin['admin_level'] . "\n";
echo "  Is Admin: " . (($admin['admin_level'] >= 2 || in_array($admin['role'], ['admin', 'super_admin', 'moderator'])) ? "YES" : "NO") . "\n\n";

// Test 2: Fetch admin list
echo "Test 2: Fetch admin list\n";
$admins = $pdo->query("
    SELECT u.id, u.username, u.email, u.role, u.admin_level,
           (SELECT COUNT(*) FROM admin_action_logs WHERE actor_id = u.id) as action_count
    FROM users u
    WHERE u.role IN ('admin', 'super_admin', 'moderator')
    ORDER BY u.created_at DESC
")->fetchAll();
echo "  Found " . count($admins) . " admins:\n";
foreach ($admins as $a) {
    echo "    - ID: " . $a['id'] . ", Username: " . $a['username'] . ", Role: " . $a['role'] . ", Actions: " . $a['action_count'] . "\n";
}
echo "\n";

// Test 3: Search for regular users
echo "Test 3: Search for regular users\n";
$users = $pdo->query("
    SELECT id, username, email
    FROM users
    WHERE role = 'user'
    LIMIT 5
")->fetchAll();
echo "  Found " . count($users) . " regular users:\n";
foreach ($users as $u) {
    echo "    - ID: " . $u['id'] . ", Username: " . $u['username'] . ", Email: " . $u['email'] . "\n";
}
echo "\n";

// Test 4: Test permission check logic (same as API)
echo "Test 4: Test permission check logic\n";
$stmt = $pdo->prepare("SELECT admin_level, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

$is_admin = false;
if ($current_user) {
    if ((isset($current_user['admin_level']) && $current_user['admin_level'] >= 2) ||
        (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'super_admin', 'moderator']))) {
        $is_admin = true;
    }
}
echo "  Permission check result: " . ($is_admin ? "PASS" : "FAIL") . "\n";

?>
