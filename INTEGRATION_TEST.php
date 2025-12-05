<?php
/**
 * FINAL INTEGRATION TEST
 * Test all recently implemented features
 */

require_once 'config/db.php';

echo "=== FINAL INTEGRATION TEST ===\n\n";

$tests = [];

// Test 1: Check moderation tables
echo "Test 1: Moderation Tables Exist\n";
try {
    $pdo->query("SELECT 1 FROM user_mutes LIMIT 1");
    echo "  ✓ user_mutes table exists\n";
    $tests[] = true;
} catch (Exception $e) {
    echo "  ✗ user_mutes table missing: " . $e->getMessage() . "\n";
    $tests[] = false;
}

// Test 2: Check suspension_until column
echo "\nTest 2: User Suspension Column\n";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'suspension_until'");
    $result = $stmt->fetch();
    if ($result) {
        echo "  ✓ suspension_until column exists\n";
        $tests[] = true;
    } else {
        echo "  ✗ suspension_until column not found\n";
        $tests[] = false;
    }
} catch (Exception $e) {
    echo "  ✗ Error checking suspension_until: " . $e->getMessage() . "\n";
    $tests[] = false;
}

// Test 3: Check API files exist
echo "\nTest 3: API Files Exist\n";
$apis = [
    'api/admin/moderate-user.php',
    'api/admin/get-admin.php',
    'api/admin/save-admin.php',
    'api/admin/remove-admin.php',
    'api/admin/get-achievement.php',
    'api/admin/save-achievement.php',
    'api/admin/delete-achievement.php',
    'api/admin/search-users.php'
];

foreach ($apis as $api) {
    if (file_exists($api)) {
        echo "  ✓ $api\n";
        $tests[] = true;
    } else {
        echo "  ✗ $api MISSING\n";
        $tests[] = false;
    }
}

// Test 4: Check admin page files
echo "\nTest 4: Admin Page Files Exist\n";
$admin_pages = [
    'admin/pages/users.php',
    'admin/pages/staff.php',
    'admin/pages/achievements.php'
];

foreach ($admin_pages as $page) {
    if (file_exists($page)) {
        echo "  ✓ $page\n";
        $tests[] = true;
    } else {
        echo "  ✗ $page MISSING\n";
        $tests[] = false;
    }
}

// Test 5: Check website rules updated
echo "\nTest 5: Website Rules Updated\n";
$rules_content = file_get_contents('pages/website-rules.php');
if (strpos($rules_content, 'HIGHLY RECOMMENDED') !== false && 
    strpos($rules_content, 'female protagonists') !== false &&
    strpos($rules_content, 'LGBTQ+') !== false) {
    echo "  ✓ Website rules contain recommended content section\n";
    $tests[] = true;
} else {
    echo "  ✗ Website rules missing recommended content section\n";
    $tests[] = false;
}

// Test 6: Database connectivity
echo "\nTest 6: Database Connectivity\n";
try {
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "  ✓ Connected to database - Users: $count\n";
    $tests[] = true;
} catch (Exception $e) {
    echo "  ✗ Database error: " . $e->getMessage() . "\n";
    $tests[] = false;
}

// Summary
echo "\n=== TEST SUMMARY ===\n";
$passed = array_sum($tests);
$total = count($tests);
echo "Passed: $passed / $total tests\n";

if ($passed === $total) {
    echo "\n✓ ALL TESTS PASSED - System is ready!\n";
} else {
    echo "\n✗ Some tests failed - Please review\n";
}
?>
