<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="stories_export_' . date('Y-m-d_H-i-s') . '.csv"');
session_start();

if (!isApprovedAdmin()) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

try {
    global $pdo;
    
    $status = $_GET['status'] ?? '';
    $authorId = $_GET['author_id'] ?? '';
    
    $query = "SELECT s.id, s.title, s.description, u.username, s.status, s.is_featured, s.views, COUNT(c.id) as chapter_count, s.created_at FROM stories s LEFT JOIN users u ON s.author_id = u.id LEFT JOIN chapters c ON s.id = c.story_id";
    $params = [];
    
    if ($status) {
        $query .= " WHERE s.status = ?";
        $params[] = $status;
    }
    if ($authorId) {
        $query .= $status ? " AND" : " WHERE";
        $query .= " s.author_id = ?";
        $params[] = $authorId;
    }
    
    $query .= " GROUP BY s.id ORDER BY s.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Story ID', 'Title', 'Description', 'Author', 'Status', 'Featured', 'Views', 'Chapters', 'Created']);
    
    foreach ($stories as $story) {
        fputcsv($output, [
            $story['id'],
            $story['title'],
            substr($story['description'], 0, 100),
            $story['username'],
            $story['status'],
            $story['is_featured'] ? 'Yes' : 'No',
            $story['views'],
            $story['chapter_count'],
            $story['created_at']
        ]);
    }
    fclose($output);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
exit;
?>
