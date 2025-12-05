<?php
// api/admin/search-stories.php - Search stories for admin
session_start();
header('Content-Type: application/json');

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check if admin
try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
}

$query = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

if (empty($query)) {
    echo json_encode(['success' => false, 'error' => 'Search query required']);
    exit;
}

try {
    $searchTerm = '%' . $query . '%';
    
    $sql = "
        SELECT s.id, s.title, s.slug, s.status, s.views, s.rating,
               u.username as author, u.id as author_id,
               COUNT(c.id) as chapter_count,
               s.created_at
        FROM stories s
        JOIN users u ON s.author_id = u.id
        LEFT JOIN chapters c ON c.story_id = s.id
        WHERE (s.title LIKE ? OR u.username LIKE ?)
    ";
    
    $params = [$searchTerm, $searchTerm];
    
    if (!empty($status)) {
        $sql .= " AND s.status = ?";
        $params[] = $status;
    }
    
    $sql .= " GROUP BY s.id ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stories = $stmt->fetchAll();
    
    // Get total count
    $countSql = "
        SELECT COUNT(DISTINCT s.id) as total
        FROM stories s
        JOIN users u ON s.author_id = u.id
        WHERE (s.title LIKE ? OR u.username LIKE ?)
    ";
    $countParams = [$searchTerm, $searchTerm];
    
    if (!empty($status)) {
        $countSql .= " AND s.status = ?";
        $countParams[] = $status;
    }
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total = $countStmt->fetch()['total'];
    
    echo json_encode([
        'success' => true,
        'data' => $stories,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Story search error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Search failed']);
}
?>
