<?php
session_start();
require_once dirname(dirname(__DIR__)) . '/config/db.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? null;
$story_id = (int)($data['story_id'] ?? 0);
$author_id = (int)($data['author_id'] ?? 0);
$reason = trim($data['reason'] ?? '');

if (!$story_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid story ID']);
    exit;
}

// Verify admin access
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id']) && !isset($_SESSION['admin_user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$adminId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;

// Check role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$user = $stmt->fetch();

if (!$user || !in_array($user['role'], ['admin', 'super_admin', 'moderator'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get story info if we need author_id
if (!$author_id && $story_id) {
    $storyStmt = $pdo->prepare("SELECT author_id, title FROM stories WHERE id = ?");
    $storyStmt->execute([$story_id]);
    $storyInfo = $storyStmt->fetch();
    if ($storyInfo) {
        $author_id = $storyInfo['author_id'];
        $storyTitle = $storyInfo['title'];
    }
} else {
    $storyStmt = $pdo->prepare("SELECT title FROM stories WHERE id = ?");
    $storyStmt->execute([$story_id]);
    $storyInfo = $storyStmt->fetch();
    $storyTitle = $storyInfo['title'] ?? 'Unknown Story';
}

// Helper function to send notification
function sendModNotification($pdo, $user_id, $type, $message, $url = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, actor_id, type, message, url, created_at) VALUES (?, NULL, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $type, $message, $url]);
        return true;
    } catch (Exception $e) {
        error_log('Notification error: ' . $e->getMessage());
        return false;
    }
}

try {
    if ($action === 'delete') {
        // Send notification before deleting
        if ($author_id && $reason) {
            $message = "Your story \"{$storyTitle}\" has been removed by a moderator. Reason: {$reason}";
            sendModNotification($pdo, $author_id, 'moderation', $message, null);
        } elseif ($author_id) {
            $message = "Your story \"{$storyTitle}\" has been removed by a moderator.";
            sendModNotification($pdo, $author_id, 'moderation', $message, null);
        }
        
        $stmt = $pdo->prepare("DELETE FROM stories WHERE id = ?");
        $stmt->execute([$story_id]);
        echo json_encode(['success' => true, 'message' => 'Story deleted successfully']);
    } 
    elseif ($action === 'publish') {
        $stmt = $pdo->prepare("UPDATE stories SET status = 'published' WHERE id = ?");
        $stmt->execute([$story_id]);
        
        // Notify author of publication
        if ($author_id) {
            $message = "Your story \"{$storyTitle}\" has been published!";
            sendModNotification($pdo, $author_id, 'story', $message, "/pages/story.php?id={$story_id}");
        }
        
        echo json_encode(['success' => true, 'message' => 'Story published successfully']);
    }
    elseif ($action === 'unpublish') {
        $stmt = $pdo->prepare("UPDATE stories SET status = 'pending' WHERE id = ?");
        $stmt->execute([$story_id]);
        
        // Send notification
        if ($author_id && $reason) {
            $message = "Your story \"{$storyTitle}\" has been unpublished by a moderator. Reason: {$reason}";
            sendModNotification($pdo, $author_id, 'moderation', $message, "/pages/story.php?id={$story_id}");
        } elseif ($author_id) {
            $message = "Your story \"{$storyTitle}\" has been unpublished by a moderator.";
            sendModNotification($pdo, $author_id, 'moderation', $message, "/pages/story.php?id={$story_id}");
        }
        
        echo json_encode(['success' => true, 'message' => 'Story unpublished successfully']);
    }
    elseif ($action === 'boost') {
        // Add boost_score and set featured flag
        $stmt = $pdo->prepare("UPDATE stories SET boost_score = COALESCE(boost_score, 0) + 1, featured = 1 WHERE id = ?");
        $stmt->execute([$story_id]);
        echo json_encode(['success' => true, 'message' => 'Story boosted successfully']);
    } 
    else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
