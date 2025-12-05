<?php
// api/like.php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/functions.php';  // ADD THIS

if (!isLoggedIn()) exit;

$input = json_decode(file_get_contents('php://input'), true);
$story_id = (int)($input['story_id'] ?? 0);

if ($story_id) {
    $stmt = $pdo->prepare("SELECT 1 FROM interactions WHERE type='like' AND target_id=? AND target_type='story' AND user_id=?");
    $stmt->execute([$story_id, $_SESSION['user_id']]);
    $exists = $stmt->fetch();

    if ($exists) {
        $pdo->prepare("DELETE FROM interactions WHERE type='like' AND target_id=? AND target_type='story' AND user_id=?")
            ->execute([$story_id, $_SESSION['user_id']]);
        $liked = false;
    } else {
        $pdo->prepare("INSERT INTO interactions (type, target_id, target_type, user_id) VALUES ('like', ?, 'story', ?)")
            ->execute([$story_id, $_SESSION['user_id']]);
        $liked = true;

        // ADD NOTIFICATION
        $stmt = $pdo->prepare("SELECT author_id, title FROM stories WHERE id = ?");
        $stmt->execute([$story_id]);
        $story = $stmt->fetch();
        if ($story && $story['author_id'] != $_SESSION['user_id']) {
            notify(
                $pdo,
                $story['author_id'],
                $_SESSION['user_id'],
                'like',
                "liked your story: " . $story['title'],
                "/pages/story.php?slug=" . getStorySlug($pdo, $story_id)
            );
        }
    }

    $count = $pdo->query("SELECT COUNT(*) FROM interactions WHERE type='like' AND target_id=$story_id AND target_type='story'")->fetchColumn();
    echo json_encode(['liked' => $liked, 'count' => $count]);
}

// Helper to get slug
function getStorySlug($pdo, $story_id) {
    $stmt = $pdo->prepare("SELECT slug FROM stories WHERE id = ?");
    $stmt->execute([$story_id]);
    return $stmt->fetchColumn();
}
?>