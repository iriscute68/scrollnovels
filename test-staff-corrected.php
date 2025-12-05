<?php
// Test staff management with correct enum roles
session_start();
$_SESSION['user_id'] = 5;  // Super admin

require 'config/db.php';

echo "=== Staff Management - Corrected for Enum Roles ===\n\n";

// Permission check
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
echo "1. Permission check: " . ($is_admin ? "✓ PASS" : "✗ FAIL") . "\n\n";

// List current admins
echo "2. Current Admins:\n";
$admins = $pdo->query("SELECT id, username, role FROM users WHERE role IN ('moderator', 'super_admin')")->fetchAll();
foreach ($admins as $a) {
    echo "   - ID: " . $a['id'] . ", Username: " . $a['username'] . ", Role: " . $a['role'] . "\n";
}
echo "\n";

// Search for users to promote
echo "3. Search for user 'test' to promote:\n";
$search = '%test%';
$stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE (username LIKE ? OR email LIKE ?) AND role NOT IN ('moderator', 'super_admin') LIMIT 10");
$stmt->execute([$search, $search]);
$users = $stmt->fetchAll();
foreach ($users as $u) {
    echo "   - ID: " . $u['id'] . ", Username: " . $u['username'] . ", Email: " . $u['email'] . "\n";
}
echo "\n";

// Promote user 2 to moderator
echo "4. Promote user 2 (testauthor) to moderator:\n";
$stmt = $pdo->prepare("UPDATE users SET role = 'moderator' WHERE id = 2");
$stmt->execute([]);
$stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = 2");
$stmt->execute([]);
$updated = $stmt->fetch();
echo "   ✓ Updated to: Username=" . $updated['username'] . ", Role=" . $updated['role'] . "\n\n";

// List admins again
echo "5. Current Admins After Promotion:\n";
$admins = $pdo->query("SELECT id, username, role FROM users WHERE role IN ('moderator', 'super_admin')")->fetchAll();
foreach ($admins as $a) {
    echo "   - ID: " . $a['id'] . ", Username: " . $a['username'] . ", Role: " . $a['role'] . "\n";
}
echo "\n";

// Remove user 2 from admin
echo "6. Remove user 2 from admin (set to reader):\n";
$stmt = $pdo->prepare("UPDATE users SET role = 'reader' WHERE id = 2");
$stmt->execute([]);
$stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = 2");
$stmt->execute([]);
$updated = $stmt->fetch();
echo "   ✓ Updated to: Username=" . $updated['username'] . ", Role=" . $updated['role'] . "\n\n";

// Final admin list
echo "7. Final Admin List:\n";
$admins = $pdo->query("SELECT id, username, role FROM users WHERE role IN ('moderator', 'super_admin')")->fetchAll();
foreach ($admins as $a) {
    echo "   - ID: " . $a['id'] . ", Username: " . $a['username'] . ", Role: " . $a['role'] . "\n";
}

echo "\n=== Test Complete ===\n";
?>
