<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="comments_export_' . date('Y-m-d_H-i-s') . '.csv"');
session_start();

if (!isApprovedAdmin()) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

try {
    global $pdo;
    
    $chapterId = $_GET['chapter_id'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 1000), 10000);
    
    $query = "SELECT c.id, c.content, u.username, c.rating, c.created_at FROM comments c LEFT JOIN users u ON c.user_id = u.id";
    $params = [];
    
    if ($chapterId) {
        $query .= " WHERE c.chapter_id = ?";
        $params[] = $chapterId;
    }
    
    $query .= " ORDER BY c.created_at DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Comment ID', 'Content', 'Author', 'Rating', 'Created']);
    
    foreach ($comments as $comment) {
        fputcsv($output, [
            $comment['id'],
            substr($comment['content'], 0, 200),
            $comment['username'],
            $comment['rating'],
            $comment['created_at']
        ]);
    }
    fclose($output);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
exit;
?>
