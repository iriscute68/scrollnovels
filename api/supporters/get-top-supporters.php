<?php
// api/supporters/get-top-supporters.php - Get top supporters for an author (both money and points)
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';

$author_id = (int)($_GET['author_id'] ?? 0);
$limit = min((int)($_GET['limit'] ?? 20), 200);

if (!$author_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Author ID is required']);
    exit;
}

try {
    // Create supporters table if it doesn't exist (for monetary support)
    $pdo->exec("CREATE TABLE IF NOT EXISTS supporters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supporter_id INT NOT NULL,
        author_id INT NOT NULL,
        tip_amount DECIMAL(10, 2) DEFAULT 0,
        patreon_tier VARCHAR(100),
        kofi_reference VARCHAR(255),
        patreon_pledge_id VARCHAR(255),
        status ENUM('active', 'cancelled', 'pending') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_support (supporter_id, author_id),
        INDEX idx_author (author_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Create author_supporters table if it doesn't exist (for points support)
    $pdo->exec("CREATE TABLE IF NOT EXISTS author_supporters (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        author_id INT UNSIGNED NOT NULL,
        supporter_id INT UNSIGNED NOT NULL,
        story_id INT UNSIGNED DEFAULT 0,
        points_total INT DEFAULT 0,
        last_supported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_support (author_id, supporter_id),
        INDEX idx_author (author_id),
        INDEX idx_supporter (supporter_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
        LIMIT ?
    ");
    $stmt->execute([$author_id, $limit]);
    $moneySupporters = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        LIMIT ?
    ");
    $stmt2->execute([$author_id, $limit]);
    $pointSupporters = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    // Also check story_support table for any points not yet migrated to author_supporters
    $storySupporters = [];
    try {
        $stmt3 = $pdo->prepare("
            SELECT 
                ss.supporter_id,
                u.username,
                u.profile_image,
                0 as tip_amount,
                NULL as patreon_tier,
                'active' as status,
                ss.created_at,
                COALESCE(SUM(ss.points_amount), 0) as points_total
            FROM story_support ss
            JOIN users u ON ss.supporter_id = u.id
            WHERE ss.author_id = ? AND ss.supporter_id NOT IN (SELECT DISTINCT supporter_id FROM author_supporters WHERE author_id = ?)
            GROUP BY ss.supporter_id
            ORDER BY points_total DESC, ss.created_at DESC
            LIMIT ?
        ");
        $stmt3->execute([$author_id, $author_id, $limit]);
        $storySupporters = $stmt3->fetchAll(PDO::FETCH_ASSOC) ?? [];
    } catch (Exception $e) {
        // story_support table doesn't exist or other error - ignore
        $storySupporters = [];
    }
    
    // Merge story_support into point supporters
    $pointSupporters = array_merge($pointSupporters, $storySupporters);

    // Merge both lists - combine if same supporter appears in both
    $byId = [];
    
    // Add monetary supporters first
    foreach ($moneySupporters as $s) {
        $byId[$s['supporter_id']] = $s;
    }
    
    // Add/merge points supporters
    foreach ($pointSupporters as $p) {
        if (isset($byId[$p['supporter_id']])) {
            // Merge: already have this supporter as monetary, add their points
            $byId[$p['supporter_id']]['points_total'] = (int)$p['points_total'];
        } else {
            // New supporter who only gave points
            $byId[$p['supporter_id']] = $p;
        }
    }

    // Sort combined list by tip_amount (descending) then by points_total (descending)
    $supporters = array_values($byId);
    usort($supporters, function($a, $b) {
        $ta = (float)($a['tip_amount'] ?? 0);
        $tb = (float)($b['tip_amount'] ?? 0);
        
        if ($ta != $tb) {
            return $tb <=> $ta;  // Sort by money first
        }
        
        // If money is equal, sort by points
        $pa = (int)($a['points_total'] ?? 0);
        $pb = (int)($b['points_total'] ?? 0);
        return $pb <=> $pa;
    });
    
    // Limit final result
    $supporters = array_slice($supporters, 0, $limit);
    
    // Debug info
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM author_supporters WHERE author_id = ?");
    $stmt->execute([$author_id]);
    $authorSupportCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // story_support might not exist, wrap in try-catch
    $storySupportCount = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM story_support WHERE author_id = ?");
        $stmt->execute([$author_id]);
        $storySupportCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $storySupportCount = 0;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $supporters,
        'total' => count($supporters),
        '_debug' => [
            'author_id' => $author_id,
            'author_supporters_records' => $authorSupportCount,
            'story_support_records' => $storySupportCount,
            'money_supporters_found' => count($moneySupporters),
            'point_supporters_found' => count($pointSupporters),
            'final_merged_count' => count($byId)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
