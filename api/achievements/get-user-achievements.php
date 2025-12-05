<?php
/**
 * Get all achievements for a user with earned status
 * GET /api/achievements/get-user-achievements.php?user_id=1
 */

require_once dirname(__FILE__) . '/../../database-config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiResponse(false, null, 'Invalid request method', 405);
}

$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

if (!$userId) {
    apiResponse(false, null, 'User ID required', 400);
}

try {
    // Sample achievements (in production, query from database)
    $allAchievements = [
        ['id' => 1, 'code' => 'first_comment', 'name' => 'First Comment', 'icon' => '💬', 'description' => 'Leave your first comment', 'points_reward' => 50],
        ['id' => 2, 'code' => 'comment_king', 'name' => 'Comment King', 'icon' => '👑', 'description' => 'Leave 100 comments', 'points_reward' => 200],
        ['id' => 3, 'code' => 'reviewer_1', 'name' => 'Reviewer I', 'icon' => '⭐', 'description' => 'Leave your first review', 'points_reward' => 50],
        ['id' => 4, 'code' => 'first_support', 'name' => 'First Supporter', 'icon' => '💝', 'description' => 'Support a book with 50 points', 'points_reward' => 100],
        ['id' => 5, 'code' => 'prolific_author', 'name' => 'Prolific Author', 'icon' => '✍️', 'description' => 'Publish 20 chapters', 'points_reward' => 500],
    ];

    // Mock user points
    $userPoints = [
        'balance' => 2850,
        'total_earned' => 5420,
    ];

    // Mock earned achievements
    $earnedAchievements = [
        ['achievement_id' => 1, 'earned_at' => '2024-01-15'],
        ['achievement_id' => 3, 'earned_at' => '2024-02-20'],
    ];

    $achievements = [];
    foreach ($allAchievements as $achievement) {
        $earned = false;
        $earned_at = null;
        
        foreach ($earnedAchievements as $e) {
            if ($e['achievement_id'] == $achievement['id']) {
                $earned = true;
                $earned_at = $e['earned_at'];
                break;
            }
        }
        
        $achievements[] = array_merge($achievement, [
            'earned' => $earned,
            'earned_at' => $earned_at,
        ]);
    }

    apiResponse(true, [
        'achievements' => $achievements,
        'user_points' => $userPoints,
        'total_achievements' => count($allAchievements),
        'earned_count' => count($earnedAchievements),
    ], 'Achievements retrieved successfully');

} catch (Exception $e) {
    apiResponse(false, null, 'Error retrieving achievements: ' . $e->getMessage(), 500);
}
?>
