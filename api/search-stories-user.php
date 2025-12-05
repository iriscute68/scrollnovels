<?php
// api/search-stories-user.php - User story search
session_start();
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config/db.php';

$query = trim($_GET['q'] ?? '');
$genre = $_GET['genre'] ?? '';
$sort = $_GET['sort'] ?? 'latest';
$page = (int)($_GET['page'] ?? 1);
$limit = 12;
$offset = ($page - 1) * $limit;

if (empty($query) && empty($genre)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Search query or genre required']);
    exit;
}

try {
    $sql = "
        SELECT s.*, u.username as author_name,
               COUNT(c.id) as chapter_count,
               COALESCE(AVG(r.rating), 0) as avg_rating,
               COUNT(r.id) as review_count
        FROM stories s
        JOIN users u ON s.author_id = u.id
        LEFT JOIN chapters c ON c.story_id = s.id
        LEFT JOIN reviews r ON r.story_id = s.id
        WHERE s.status = 'active'
    ";
    
    $params = [];
    
    if (!empty($query)) {
        $searchTerm = '%' . $query . '%';
        $sql .= " AND (s.title LIKE ? OR s.description LIKE ? OR u.username LIKE ?)";
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    if (!empty($genre)) {
        $sql .= " AND (s.genres LIKE ? OR s.tags LIKE ?)";
        $params[] = '%' . $genre . '%';
        $params[] = '%' . $genre . '%';
    }
    
    $sql .= " GROUP BY s.id";
    
    // Sorting
    if ($sort === 'popular') {
        $sql .= " ORDER BY s.views DESC";
    } elseif ($sort === 'rating') {
        $sql .= " ORDER BY avg_rating DESC";
    } elseif ($sort === 'newest') {
        $sql .= " ORDER BY s.created_at DESC";
    } else {
        $sql .= " ORDER BY s.created_at DESC";
    }
    
    $sql .= " LIMIT ? OFFSET ?";
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
        WHERE s.status = 'active'
    ";
    
    $countParams = [];
    if (!empty($query)) {
        $searchTerm = '%' . $query . '%';
        $countSql .= " AND (s.title LIKE ? OR s.description LIKE ? OR u.username LIKE ?)";
        $countParams = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    if (!empty($genre)) {
        $countSql .= " AND (s.genres LIKE ? OR s.tags LIKE ?)";
        $countParams[] = '%' . $genre . '%';
        $countParams[] = '%' . $genre . '%';
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
