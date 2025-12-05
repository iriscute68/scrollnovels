<?php
// Test both blog and admin pages
require_once __DIR__ . '/config/db.php';
session_start();

echo "=== TESTING BLOG SYSTEM ===\n\n";

// 1. Test blog page queries
echo "1. Testing Blog Page Queries:\n";

$blogQuery = "
    SELECT a.*, 
           (SELECT COUNT(*) FROM blog_comments WHERE blog_post_id = a.id) as comment_count,
           (SELECT COUNT(*) FROM announcement_reads WHERE announcement_id = a.id) as view_count
    FROM announcements a
    WHERE a.active_from <= NOW() 
    AND (a.active_until IS NULL OR a.active_until >= NOW())
    ORDER BY a.is_pinned DESC, a.created_at DESC 
    LIMIT 50
";

try {
    $stmt = $pdo->prepare($blogQuery);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✓ Blog query successful: " . count($results) . " posts found\n";
} catch (Exception $e) {
    echo "✗ Blog query failed: " . $e->getMessage() . "\n";
}

echo "\n2. Testing Admin Page Queries:\n";

$adminQueries = [
    'total_users' => "SELECT COUNT(*) FROM users",
    'total_stories' => "SELECT COUNT(*) FROM stories",
    'total_chapters' => "SELECT COUNT(*) FROM chapters",
    'pending_stories' => "SELECT COUNT(*) FROM stories WHERE status = 'pending'",
    'pending_verification' => "SELECT COUNT(*) FROM verification_requests WHERE status = 'pending'",
    'total_donations' => "SELECT COALESCE(SUM(amount),0) FROM donations WHERE status = 'completed'",
    'active_ads' => "SELECT COUNT(*) FROM ads WHERE status = 'active'",
];

$allPassed = true;
foreach ($adminQueries as $name => $query) {
    try {
        $result = $pdo->query($query)->fetchColumn();
        echo "✓ $name: $result\n";
    } catch (Exception $e) {
        echo "✗ $name: " . $e->getMessage() . "\n";
        $allPassed = false;
    }
}

echo "\n=== VERIFICATION ===\n";
if ($allPassed) {
    echo "✓ ALL TESTS PASSED!\n";
    echo "\nYou can now access:\n";
    echo "  • Blog page: http://localhost/pages/blog.php\n";
    echo "  • Admin page: http://localhost/admin/admin.php\n";
} else {
    echo "✗ Some tests failed\n";
}
?>
