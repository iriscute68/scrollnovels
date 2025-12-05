<?php
/**
 * Get all comments for a forum post with pagination
 * GET /api/forum/get-comments.php?post_id=1&page=1&limit=20
 */

require_once dirname(__FILE__) . '/../../database-config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiResponse(false, null, 'Invalid request method', 405);
}

$postId = isset($_GET['post_id']) ? intval($_GET['post_id']) : null;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? min(100, intval($_GET['limit'])) : 20;
$offset = ($page - 1) * $limit;

if (!$postId) {
    apiResponse(false, null, 'Post ID required', 400);
}

try {
    // Mock comments
    $comments = [
        [
            'id' => 1,
            'user_id' => 101,
            'user_name' => 'John Doe',
            'content' => 'This chapter was absolutely amazing! The character development is incredible.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        ],
        [
            'id' => 2,
            'user_id' => 102,
            'user_name' => 'Sarah Smith',
            'content' => "Can't wait for the next update. This story has me hooked!",
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 hours')),
        ],
    ];

    // Format response
    $comments = array_map(function($comment) {
        return [
            'id' => $comment['id'],
            'user_id' => $comment['user_id'],
            'user_name' => $comment['user_name'],
            'content' => htmlspecialchars($comment['content']),
            'created_at' => $comment['created_at'],
            'created_at_formatted' => date('M d, Y H:i', strtotime($comment['created_at'])),
        ];
    }, $comments);

    apiResponse(true, [
        'comments' => $comments,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => 1,
            'total_comments' => count($comments),
            'per_page' => $limit,
        ],
    ], 'Comments retrieved successfully');

} catch (Exception $e) {
    apiResponse(false, null, 'Error retrieving comments: ' . $e->getMessage(), 500);
}
?>
