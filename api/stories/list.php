<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json');

// Get parameters from query string
$sort = $_GET['sort'] ?? 'date'; // date, views, rating, trending
$category = $_GET['category'] ?? ''; // fiction, webtoon, fanfic, etc.
$limit = min((int)($_GET['limit'] ?? 12), 100); // max 100 per request
$offset = max(0, (int)($_GET['offset'] ?? 0));
$search = $_GET['search'] ?? '';

try {
    // Build base query
    $query = "SELECT s.*, u.username as author_name, COUNT(c.id) as chapter_count FROM stories s 
              LEFT JOIN users u ON s.author_id = u.id 
              LEFT JOIN chapters c ON s.id = c.story_id 
              WHERE s.status = 'published'";
    $params = [];
    
    // Category filter
    if ($category && $category !== 'all') {
        $query .= " AND s.category = ?";
        $params[] = $category;
    }
    
    // Search filter
    if ($search) {
        $query .= " AND (s.title LIKE ? OR s.description LIKE ? OR u.username LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Group by for aggregates
    $query .= " GROUP BY s.id";
    
    // Sort options
    switch ($sort) {
        case 'views':
            $query .= " ORDER BY s.views DESC";
            break;
        case 'rating':
            $query .= " ORDER BY s.average_rating DESC";
            break;
        case 'trending':
            // Trending = views in last 7 days / (age in days + 1)
            $query .= " ORDER BY (SELECT COALESCE(SUM(view_count), 0) FROM interactions WHERE story_id = s.id AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) / (DATEDIFF(NOW(), s.created_at) + 1) DESC";
            break;
        case 'date':
        default:
            $query .= " ORDER BY s.created_at DESC";
            break;
    }
    
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(DISTINCT s.id) as total FROM stories s 
                   LEFT JOIN users u ON s.author_id = u.id 
                   LEFT JOIN chapters c ON s.id = c.story_id 
                   WHERE s.status = 'published'";
    $countParams = [];
    
    if ($category && $category !== 'all') {
        $countQuery .= " AND s.category = ?";
        $countParams[] = $category;
    }
    
    if ($search) {
        $countQuery .= " AND (s.title LIKE ? OR s.description LIKE ? OR u.username LIKE ?)";
        $searchTerm = "%{$search}%";
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
    }
    
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($countParams);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Format response
    $response = [
        'success' => true,
        'stories' => array_map(function($s) {
            return [
                'id' => $s['id'],
                'title' => htmlspecialchars($s['title']),
                'slug' => $s['slug'],
                'author' => htmlspecialchars($s['author_name']),
                'category' => $s['category'],
                'description' => substr(htmlspecialchars($s['description'] ?? ''), 0, 200),
                'cover_image' => $s['cover_image'],
                'views' => (int)$s['views'],
                'rating' => (float)($s['average_rating'] ?? 0),
                'chapters' => (int)$s['chapter_count'],
                'status' => $s['status'],
                'created_at' => $s['created_at']
            ];
        }, $stories),
        'pagination' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => $offset + $limit < $total
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
