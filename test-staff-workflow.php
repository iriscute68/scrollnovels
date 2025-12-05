<?php
// Test staff management with proper session
session_start();
$_SESSION['user_id'] = 5;  // Super admin

require 'config/db.php';

echo "=== Staff Management Workflow Test ===\n\n";

// Step 1: Check permission as admin
echo "Step 1: Permission Check (User ID 5 as super_admin)\n";
$stmt = $pdo->prepare("SELECT is_admin, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

$is_admin = false;
if ($current_user) {
    if ((isset($current_user['is_admin']) && $current_user['is_admin'] == 1) ||
        (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'super_admin', 'moderator']))) {
        $is_admin = true;
    }
}
echo "  Permission: " . ($is_admin ? "✓ PASS" : "✗ FAIL") . "\n\n";

if (!$is_admin) {
    echo "ERROR: Cannot proceed!\n";
    exit(1);
}

// Step 2: Get list of current admins
echo "Step 2: Get Current Admins\n";
$admins = $pdo->query("
    SELECT u.id, u.username, u.email, u.role,
           (SELECT COUNT(*) FROM admin_action_logs WHERE actor_id = u.id) as action_count
    FROM users u
    WHERE u.role IN ('admin', 'super_admin', 'moderator')
    ORDER BY u.created_at DESC
")->fetchAll();
echo "  Found " . count($admins) . " admin(s):\n";
foreach ($admins as $admin) {
    echo "    - ID: " . $admin['id'] . ", Username: " . $admin['username'] . ", Role: " . $admin['role'] . "\n";
}
echo "\n";

// Step 3: Search for regular users
echo "Step 3: Search Users\n";
$q = 'test';
$search = '%' . $q . '%';
$stmt = $pdo->prepare("
    SELECT id, username, email 
    FROM users 
    WHERE (username LIKE ? OR email LIKE ?)
    AND role = 'user'
    LIMIT 10
");
$stmt->execute([$search, $search]);
$users = $stmt->fetchAll();
echo "  Search for 'test': Found " . count($users) . " user(s)\n";
foreach ($users as $u) {
    echo "    - ID: " . $u['id'] . ", Username: " . $u['username'] . ", Email: " . $u['email'] . "\n";
}
echo "\n";

// Step 4: Fetch specific admin data (GET)
echo "Step 4: Get Admin Data (ID 5)\n";
$id = 5;
$stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
$stmt->execute([$id]);
$admin = $stmt->fetch();
if ($admin) {
    echo "  ✓ Found Admin:\n";
    echo "    - ID: " . $admin['id'] . "\n";
    echo "    - Username: " . $admin['username'] . "\n";
    echo "    - Email: " . $admin['email'] . "\n";
    echo "    - Role: " . $admin['role'] . "\n";
} else {
    echo "  ✗ Admin not found\n";
}
echo "\n";

// Step 5: Try to promote a user (SAVE operation)
echo "Step 5: Promote User to Admin\n";
$user_id = 1;
$new_role = 'admin';

// First check if user exists
$stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_to_promote = $stmt->fetch();

if (!$user_to_promote) {
    echo "  ✗ User not found\n";
} else {
    echo "  Current: ID=" . $user_to_promote['id'] . ", Username=" . $user_to_promote['username'] . ", Role=" . $user_to_promote['role'] . "\n";
    
    // Perform update
    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->execute([$new_role, $user_id]);
    
    // Verify
    $stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $updated = $stmt->fetch();
    echo "  Updated: Username=" . $updated['username'] . ", Role=" . $updated['role'] . "\n";
    echo "  ✓ Promotion successful\n";
}
echo "\n";

// Step 6: List all admins again
echo "Step 6: List All Admins After Promotion\n";
$admins = $pdo->query("
    SELECT u.id, u.username, u.role
    FROM users u
    WHERE u.role IN ('admin', 'super_admin', 'moderator')
    ORDER BY u.id
")->fetchAll();
echo "  Total admins: " . count($admins) . "\n";
foreach ($admins as $admin) {
    echo "    - ID: " . $admin['id'] . ", Username: " . $admin['username'] . ", Role: " . $admin['role'] . "\n";
}
echo "\n";

// Step 7: Try to remove admin role (REMOVE operation)
echo "Step 7: Remove Admin Role\n";
$admin_id = 1;

// Check if user is admin
$stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$user_check = $stmt->fetch();

if (!$user_check) {
    echo "  ✗ User not found\n";
} elseif (!in_array($user_check['role'], ['admin', 'super_admin', 'moderator'])) {
    echo "  ✗ User is not an admin\n";
} else {
    // Remove admin role
    $stmt = $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?");
    $stmt->execute([$admin_id]);
    
    // Verify
    $stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $updated = $stmt->fetch();
    echo "  Updated: Username=" . $updated['username'] . ", Role=" . $updated['role'] . "\n";
    echo "  ✓ Removal successful\n";
}
echo "\n";

// Step 8: Final admin list
echo "Step 8: Final Admin List\n";
$admins = $pdo->query("
    SELECT u.id, u.username, u.role
    FROM users u
    WHERE u.role IN ('admin', 'super_admin', 'moderator')
    ORDER BY u.id
")->fetchAll();
echo "  Total admins: " . count($admins) . "\n";
foreach ($admins as $admin) {
    echo "    - ID: " . $admin['id'] . ", Username: " . $admin['username'] . ", Role: " . $admin['role'] . "\n";
}

echo "\n=== All Tests Complete ===\n";
?>
