<?php
// api/notifications/follow-story.php - Follow or unfollow a story
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/../config/db.php';
require_once dirname(__FILE__) . '/helpers.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$story_id = (int)($_POST['story_id'] ?? $_GET['story_id'] ?? 0);
$action = $_POST['action'] ?? $_GET['action'] ?? 'toggle';

if (!$story_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Story ID required']);
    exit;
}

try {
    $is_following = isFollowing($user_id, $story_id);
    
    if ($action === 'toggle') {
        if ($is_following) {
            unfollowStory($user_id, $story_id);
            echo json_encode(['success' => true, 'action' => 'unfollowed', 'is_following' => false]);
        } else {
            followStory($user_id, $story_id);
            echo json_encode(['success' => true, 'action' => 'followed', 'is_following' => true]);
        }
    } elseif ($action === 'follow') {
        if (!$is_following) {
            followStory($user_id, $story_id);
        }
        echo json_encode(['success' => true, 'action' => 'followed', 'is_following' => true]);
    } elseif ($action === 'unfollow') {
        if ($is_following) {
            unfollowStory($user_id, $story_id);
        }
        echo json_encode(['success' => true, 'action' => 'unfollowed', 'is_following' => false]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
