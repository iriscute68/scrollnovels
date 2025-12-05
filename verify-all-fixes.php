<?php
// Final comprehensive verification script
echo "=== FINAL VERIFICATION - Session 3 Fixes ===\n\n";

require 'config/db.php';

$checks = [];

// Check 1: FK Constraint
echo "1. Checking blog_comment_replies FK constraint...\n";
try {
    $result = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME='blog_comment_replies' AND COLUMN_NAME='comment_id' AND REFERENCED_TABLE_NAME='blog_comments'")->fetch();
    if ($result) {
        echo "   ✓ FK constraint EXISTS: " . $result['CONSTRAINT_NAME'] . "\n";
        $checks['FK Constraint'] = 'PASS';
    } else {
        echo "   ✗ FK constraint NOT found\n";
        $checks['FK Constraint'] = 'FAIL';
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $checks['FK Constraint'] = 'ERROR';
}
echo "\n";

// Check 2: submitted_at column
echo "2. Checking competition_entries.submitted_at column...\n";
try {
    $result = $pdo->query("SHOW COLUMNS FROM competition_entries LIKE 'submitted_at'")->fetch();
    if ($result) {
        echo "   ✓ Column EXISTS\n";
        echo "   Type: " . $result['Type'] . "\n";
        $checks['submitted_at Column'] = 'PASS';
    } else {
        echo "   ✗ Column NOT found\n";
        $checks['submitted_at Column'] = 'FAIL';
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $checks['submitted_at Column'] = 'ERROR';
}
echo "\n";

// Check 3: author_supporters table
echo "3. Checking author_supporters table...\n";
try {
    $result = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='scroll_novels' AND TABLE_NAME='author_supporters'")->fetch();
    if ($result) {
        echo "   ✓ Table EXISTS\n";
        $count = $pdo->query("SELECT COUNT(*) as cnt FROM author_supporters")->fetch();
        echo "   Rows: " . $count['cnt'] . "\n";
        $checks['author_supporters Table'] = 'PASS';
    } else {
        echo "   ✗ Table NOT found\n";
        $checks['author_supporters Table'] = 'FAIL';
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $checks['author_supporters Table'] = 'ERROR';
}
echo "\n";

// Check 4: supporters table
echo "4. Checking supporters table...\n";
try {
    $result = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='scroll_novels' AND TABLE_NAME='supporters'")->fetch();
    if ($result) {
        echo "   ✓ Table EXISTS\n";
        $count = $pdo->query("SELECT COUNT(*) as cnt FROM supporters")->fetch();
        echo "   Rows: " . $count['cnt'] . "\n";
        $checks['supporters Table'] = 'PASS';
    } else {
        echo "   ✗ Table NOT found\n";
        $checks['supporters Table'] = 'FAIL';
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $checks['supporters Table'] = 'ERROR';
}
echo "\n";

// Check 5: Competition image display
echo "5. Checking competition image display code...\n";
try {
    $code = file_get_contents('pages/competition-details.php');
    if (strpos($code, "cover_image") !== false && strpos($code, "background-image") !== false) {
        echo "   ✓ Competition image display code PRESENT\n";
        $checks['Competition Image Display'] = 'PASS';
    } else {
        echo "   ✗ Competition image display code NOT found\n";
        $checks['Competition Image Display'] = 'FAIL';
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $checks['Competition Image Display'] = 'ERROR';
}
echo "\n";

// Check 6: Admin guides page
echo "6. Checking admin guides management page...\n";
try {
    if (file_exists('admin/pages/guides.php')) {
        echo "   ✓ Admin guides page EXISTS\n";
        $code = file_get_contents('admin/pages/guides.php');
        $hasCRUD = strpos($code, 'action') !== false && strpos($code, 'DELETE') !== false;
        if ($hasCRUD) {
            echo "   ✓ CRUD operations PRESENT\n";
            $checks['Admin Guides'] = 'PASS';
        } else {
            echo "   ✗ CRUD operations NOT found\n";
            $checks['Admin Guides'] = 'WARN';
        }
    } else {
        echo "   ✗ Admin guides page NOT found\n";
        $checks['Admin Guides'] = 'FAIL';
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $checks['Admin Guides'] = 'ERROR';
}
echo "\n";

// Check 7: Notification bell in header
echo "7. Checking notification bell in header...\n";
try {
    $code = file_get_contents('includes/header.php');
    if (strpos($code, 'notificationBell') !== false && strpos($code, '🔔') !== false) {
        echo "   ✓ Notification bell code PRESENT\n";
        $checks['Notification Bell'] = 'PASS';
    } else {
        echo "   ✗ Notification bell code NOT found\n";
        $checks['Notification Bell'] = 'FAIL';
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $checks['Notification Bell'] = 'ERROR';
}
echo "\n";

// Check 8: Database connectivity
echo "8. Checking database connectivity...\n";
try {
    $result = $pdo->query("SELECT 1")->fetch();
    if ($result) {
        echo "   ✓ Database CONNECTION OK\n";
        $checks['Database Connection'] = 'PASS';
    }
} catch (Exception $e) {
    echo "   ✗ Database connection FAILED\n";
    $checks['Database Connection'] = 'FAIL';
}
echo "\n";

// Summary
echo "=== SUMMARY ===\n";
$pass = count(array_filter($checks, fn($v) => $v === 'PASS'));
$total = count($checks);
echo "Passed: $pass/$total\n\n";

foreach ($checks as $name => $status) {
    $symbol = $status === 'PASS' ? '✓' : ($status === 'FAIL' ? '✗' : '⚠');
    echo "$symbol $name: $status\n";
}

if ($pass === $total) {
    echo "\n🎉 ALL CHECKS PASSED! System is ready to use.\n";
} else {
    echo "\n⚠️  Some checks need attention.\n";
}
?>
