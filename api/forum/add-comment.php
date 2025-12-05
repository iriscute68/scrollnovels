<?php
/**
 * Add a comment to a forum post
 * POST /api/forum/add-comment.php
 */

require_once dirname(__FILE__) . '/../../database-config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiResponse(false, null, 'Invalid request method', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['post_id']) || !isset($input['user_id']) || !isset($input['content'])) {
    apiResponse(false, null, 'Missing required fields', 400);
}

$postId = intval($input['post_id']);
$userId = intval($input['user_id']);
$userName = $input['user_name'] ?? 'Anonymous';
$content = trim($input['content']);

if (strlen($content) < 2) {
    apiResponse(false, null, 'Comment must be at least 2 characters', 400);
}

if (strlen($content) > 5000) {
    apiResponse(false, null, 'Comment exceeds 5000 characters', 400);
}

try {
    // Simulate comment creation (in production, save to database)
    $commentId = rand(1000, 9999);

    apiResponse(true, [
        'comment_id' => $commentId,
        'points_added' => 5,
    ], 'Comment added successfully', 201);

} catch (Exception $e) {
    apiResponse(false, null, 'Error adding comment: ' . $e->getMessage(), 500);
}
?>
