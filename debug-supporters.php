<?php
// Debug supporters
require 'config/db.php';

echo "=== Debugging Top Supporters Issue ===\n\n";

// Check 1: author_supporters table
echo "1. author_supporters table:\n";
try {
    $result = $pdo->query("SELECT COUNT(*) as cnt FROM author_supporters")->fetch();
    echo "   Total rows: " . $result['cnt'] . "\n";
    
    if ($result['cnt'] > 0) {
        echo "   Sample data:\n";
        $samples = $pdo->query("SELECT * FROM author_supporters LIMIT 5")->fetchAll();
        foreach ($samples as $row) {
            echo "   - Author: {$row['author_id']}, Supporter: {$row['supporter_id']}, Points: {$row['points_total']}\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Check 2: supporters table
echo "2. supporters table:\n";
try {
    $result = $pdo->query("SELECT COUNT(*) as cnt FROM supporters")->fetch();
    echo "   Total rows: " . $result['cnt'] . "\n";
    
    if ($result['cnt'] > 0) {
        echo "   Sample data:\n";
        $samples = $pdo->query("SELECT * FROM supporters LIMIT 5")->fetchAll();
        foreach ($samples as $row) {
            echo "   - Supporter: {$row['supporter_id']}, Author: {$row['author_id']}, Tip: {$row['tip_amount']}\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Check 3: Get story authors
echo "3. Stories and Authors:\n";
try {
    $stories = $pdo->query("SELECT id, author_id, title FROM stories LIMIT 3")->fetchAll();
    foreach ($stories as $story) {
        echo "   Story {$story['id']} ({$story['title']}): Author {$story['author_id']}\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Check 4: Test the API query directly
echo "4. Testing API query for author_id=3:\n";
try {
    $author_id = 3;
    
    // Get monetary supporters
    $stmt = $pdo->prepare("
        SELECT 
            s.supporter_id,
            u.username,
            u.profile_image,
            COALESCE(MAX(s.tip_amount), 0) as tip_amount,
            s.patreon_tier,
            'active' as status,
            s.created_at,
            0 as points_total
        FROM supporters s
        JOIN users u ON s.supporter_id = u.id
        WHERE s.author_id = ? AND s.status = 'active'
        GROUP BY s.supporter_id
        ORDER BY tip_amount DESC
        LIMIT 20
    ");
    $stmt->execute([$author_id]);
    $moneySupporters = $stmt->fetchAll();
    echo "   Money supporters for author $author_id: " . count($moneySupporters) . "\n";
    
    // Get points supporters
    $stmt2 = $pdo->prepare("
        SELECT 
            a.supporter_id,
            u.username,
            u.profile_image,
            0 as tip_amount,
            NULL as patreon_tier,
            'active' as status,
            a.last_supported_at as created_at,
            COALESCE(SUM(a.points_total), 0) as points_total
        FROM author_supporters a
        JOIN users u ON a.supporter_id = u.id
        WHERE a.author_id = ?
        GROUP BY a.supporter_id
        ORDER BY points_total DESC, a.last_supported_at DESC
        LIMIT 20
    ");
    $stmt2->execute([$author_id]);
    $pointSupporters = $stmt2->fetchAll();
    echo "   Points supporters for author $author_id: " . count($pointSupporters) . "\n";
    
    if (count($pointSupporters) > 0) {
        echo "   First point supporter:\n";
        $p = $pointSupporters[0];
        echo "   - User: {$p['username']}, Points: {$p['points_total']}\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}
echo "\n";

echo "Done.\n";
?>
