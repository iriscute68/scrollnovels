<?php
// api/supporters/get-author-links.php - Get author's Ko-fi and Patreon links
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/../config/db.php';

$author_id = (int)($_GET['author_id'] ?? 0);

if (!$author_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Author ID is required']);
    exit;
}

try {
    // First try to get from author_links table if it exists
    $links_data = [
        'kofi' => null,
        'patreon' => null,
        'paypal' => null,
        'points_url' => null
    ];
    
    try {
        $stmt = $pdo->prepare("SELECT link_type, link_url FROM author_links WHERE author_id = ? AND is_verified = 1");
        $stmt->execute([$author_id]);
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($links as $link) {
            $links_data[$link['link_type']] = $link['link_url'];
        }
    } catch (Exception $e) {
        // author_links table might not exist, fall back to users table
    }
    
    // Also check users table for patreon and kofi columns
    $stmt = $pdo->prepare("SELECT patreon, kofi FROM users WHERE id = ?");
    $stmt->execute([$author_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        if ($user['patreon'] && !$links_data['patreon']) {
            $links_data['patreon'] = $user['patreon'];
        }
        if ($user['kofi'] && !$links_data['kofi']) {
            $links_data['kofi'] = $user['kofi'];
        }
    }
    
    // Always add points dashboard link
    $links_data['points_url'] = 'pages/points-dashboard.php';
    
    echo json_encode(['success' => true, 'data' => $links_data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
