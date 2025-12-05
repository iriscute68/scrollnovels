<?php
// api/claim-daily-quest.php - Claim daily quest reward
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Login required']));
}

$user_id = $_SESSION['user_id'];
$quest_type = $_POST['quest_type'] ?? '';

if (!$quest_type) {
    exit(json_encode(['success' => false, 'message' => 'Quest type required']));
}

// Define daily quests
$dailyQuests = [
    'daily_reading' => ['name' => 'Daily Reading', 'points' => 10],
    'daily_comment' => ['name' => 'Write a Comment', 'points' => 15],
    'daily_like' => ['name' => 'Give Likes', 'points' => 12],
    'daily_review' => ['name' => 'Write a Review', 'points' => 20],
    'daily_post' => ['name' => 'Post a Comment', 'points' => 18],
];

if (!isset($dailyQuests[$quest_type])) {
    exit(json_encode(['success' => false, 'message' => 'Invalid quest type']));
}

$today = date('Y-m-d');
$quest_points = $dailyQuests[$quest_type]['points'];

try {
    // Check if quest is completed and reward not yet claimed
    $stmt = $pdo->prepare("
        SELECT * FROM daily_quest_progress 
        WHERE user_id = ? AND quest_type = ? AND quest_date = ?
    ");
    $stmt->execute([$user_id, $quest_type, $today]);
    $quest = $stmt->fetch();
    
    if (!$quest) {
        exit(json_encode(['success' => false, 'message' => 'Quest not found']));
    }
    
    if (!$quest['completed']) {
        exit(json_encode(['success' => false, 'message' => 'Quest not yet completed']));
    }
    
    if ($quest['claimed']) {
        exit(json_encode(['success' => false, 'message' => 'Reward already claimed']));
    }
    
    // Mark as claimed and award points
    $pdo->prepare("
        UPDATE daily_quest_progress 
        SET claimed = 1, updated_at = NOW()
        WHERE id = ?
    ")->execute([$quest['id']]);
    
    // Award points
    $pdo->prepare("
        INSERT INTO point_transactions (user_id, points, description, type) 
        VALUES (?, ?, ?, 'earn')
    ")->execute([$user_id, $quest_points, 'Daily Quest: ' . $dailyQuests[$quest_type]['name']]);
    
    // Update user points
    $pdo->prepare("
        UPDATE user_points 
        SET points = points + ?, lifetime_points = lifetime_points + ?, updated_at = NOW()
        WHERE user_id = ?
    ")->execute([$quest_points, $quest_points, $user_id]);
    
    exit(json_encode([
        'success' => true,
        'message' => 'Reward claimed successfully',
        'points' => $quest_points
    ]));
    
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]));
}
