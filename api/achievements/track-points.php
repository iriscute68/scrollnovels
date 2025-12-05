<?php
/**
 * Track user points for various activities
 * POST /api/achievements/track-points.php
 */

require_once dirname(__FILE__) . '/../../database-config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiResponse(false, null, 'Invalid request method', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['user_id']) || !isset($input['action'])) {
    apiResponse(false, null, 'Missing required fields', 400);
}

$userId = intval($input['user_id']);
$action = $input['action'];
$points = isset($input['points']) ? intval($input['points']) : 0;
$description = $input['description'] ?? '';

try {
    // Point values for each action
    $actionPoints = [
        'comment' => 5,
        'review' => 20,
        'reading_hour' => 10,
        'follow_author' => 3,
        'publish_chapter' => 50,
        'complete_book' => 100,
        'first_support' => 30,
        'comment_like' => 2,
    ];

    // Determine points if not specified
    if ($points === 0 && isset($actionPoints[$action])) {
        $points = $actionPoints[$action];
    }

    // Log the points transaction (in production, save to database)
    $logData = [
        'user_id' => $userId,
        'action' => $action,
        'points' => $points,
        'description' => $description,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    // Simulate achievement check
    $newAchievements = [];
    if ($action === 'comment') {
        $newAchievements[] = [
            'id' => 1,
            'name' => 'First Comment',
            'points_reward' => 50,
        ];
    }

    apiResponse(true, [
        'points_added' => $points,
        'action' => $action,
        'new_achievements' => $newAchievements,
    ], 'Points tracked successfully');

} catch (Exception $e) {
    apiResponse(false, null, 'Error tracking points: ' . $e->getMessage(), 500);
}
?>
