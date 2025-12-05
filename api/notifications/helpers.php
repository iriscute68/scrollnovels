<?php
// api/notifications/helpers.php - Helper functions for creating notifications
require_once dirname(__DIR__) . '/../config/db.php';

/**
 * Create a notification for a user
 * @param int $user_id Target user
 * @param string $type Notification type (new_chapter, comment, review, etc.)
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $link URL to relevant page
 * @param string $icon Icon name
 * @param array $data Additional JSON data
 */
function createNotification($user_id, $type, $title, $message, $link, $icon = '', $data = []) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, icon, data)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $json_data = json_encode($data);
        return $stmt->execute([$user_id, $type, $title, $message, $link, $icon, $json_data]);
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Create notification for all followers of a story
 * @param int $story_id Story ID
 * @param string $type Notification type
 * @param string $title Title
 * @param string $message Message
 * @param string $link Link
 * @param array $data Additional data
 */
function notifyFollowers($story_id, $type, $title, $message, $link, $data = []) {
    global $pdo;
    
    try {
        // Get all followers
        $stmt = $pdo->prepare("SELECT user_id FROM follows WHERE story_id = ?");
        $stmt->execute([$story_id]);
        $followers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $success_count = 0;
        foreach ($followers as $follower) {
            if (createNotification($follower['user_id'], $type, $title, $message, $link, '', $data)) {
                $success_count++;
            }
        }
        
        return $success_count;
    } catch (Exception $e) {
        error_log("Error notifying followers: " . $e->getMessage());
        return 0;
    }
}

/**
 * Check if user has notification setting enabled
 * @param int $user_id User ID
 * @param string $setting Setting name (new_chapter, comment, review, etc.)
 */
function isNotificationEnabled($user_id, $setting) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT $setting FROM user_notification_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        
        return $result && $result[$setting] == 1;
    } catch (Exception $e) {
        return true; // Default to enabled if error
    }
}

/**
 * Follow a story
 * @param int $user_id User ID
 * @param int $story_id Story ID
 */
function followStory($user_id, $story_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO follows (user_id, story_id) VALUES (?, ?)");
        return $stmt->execute([$user_id, $story_id]);
    } catch (Exception $e) {
        error_log("Error following story: " . $e->getMessage());
        return false;
    }
}

/**
 * Unfollow a story
 * @param int $user_id User ID
 * @param int $story_id Story ID
 */
function unfollowStory($user_id, $story_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM follows WHERE user_id = ? AND story_id = ?");
        return $stmt->execute([$user_id, $story_id]);
    } catch (Exception $e) {
        error_log("Error unfollowing story: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user is following a story
 * @param int $user_id User ID
 * @param int $story_id Story ID
 */
function isFollowing($user_id, $story_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM follows WHERE user_id = ? AND story_id = ?");
        $stmt->execute([$user_id, $story_id]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}
?>
