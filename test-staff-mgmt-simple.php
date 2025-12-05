<?php
require 'config/db.php';

echo "=== Testing Staff Management APIs ===\n\n";

// Test 1: Check users table structure
echo "Test 1: Users table columns\n";
$result = $pdo->query('DESCRIBE users');
$columns = $result->fetchAll();
foreach ($columns as $col) {
    if (in_array($col['Field'], ['id', 'username', 'email', 'role', 'is_admin'])) {
        echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
}
echo "\n";

// Test 2: Check existing admins
echo "Test 2: Existing admins in database\n";
$admins = $pdo->query("
    SELECT id, username, email, role, is_admin
    FROM users
    WHERE role IN ('admin', 'super_admin', 'moderator') OR is_admin = 1
    LIMIT 10
")->fetchAll();
echo "  Found " . count($admins) . " admins:\n";
foreach ($admins as $a) {
    echo "    - ID: " . $a['id'] . ", Username: " . $a['username'] . ", Role: " . $a['role'] . ", is_admin: " . $a['is_admin'] . "\n";
}
echo "\n";

// Test 3: Check regular users available for promotion
echo "Test 3: Regular users available for admin promotion\n";
$users = $pdo->query("
    SELECT id, username, email, role
    FROM users
    WHERE role = 'reader' OR role = 'author'
    LIMIT 5
")->fetchAll();
echo "  Found " . count($users) . " regular users:\n";
foreach ($users as $u) {
    echo "    - ID: " . $u['id'] . ", Username: " . $u['username'] . ", Email: " . $u['email'] . ", Role: " . $u['role'] . "\n";
}
echo "\n";

// Test 4: Permission check logic
echo "Test 4: Permission check logic (as if user ID 1 is admin)\n";
$stmt = $pdo->prepare("SELECT is_admin, role FROM users WHERE id = ?");
$stmt->execute([1]);
$test_user = $stmt->fetch();

$is_admin = false;
if ($test_user) {
    if ((isset($test_user['is_admin']) && $test_user['is_admin'] == 1) ||
        (isset($test_user['role']) && in_array($test_user['role'], ['admin', 'super_admin', 'moderator']))) {
        $is_admin = true;
    }
}
echo "  User ID 1: is_admin=" . ($test_user['is_admin'] ?? 'NULL') . ", role=" . ($test_user['role'] ?? 'NULL') . ", Permission: " . ($is_admin ? "PASS" : "FAIL") . "\n";
echo "\n";
?>
