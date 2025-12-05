<?php
/**
 * api/check-achievements.php - Check and unlock achievements based on user actions
 */
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    // Get user stats
    $userStmt = $pdo->prepare("SELECT id, created_at, username FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    $unlockedCount = 0;
    $newUnlocks = [];
    
    // Get all achievements
    $achStmt = $pdo->query('SELECT id, title FROM achievements');
    $achievements = $achStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Check which are already unlocked
    $alreadyUnlockedStmt = $pdo->prepare('SELECT achievement_id FROM user_achievements WHERE user_id = ?');
    $alreadyUnlockedStmt->execute([$userId]);
    $alreadyUnlocked = $alreadyUnlockedStmt->fetchAll(PDO::FETCH_COLUMN);
    $alreadyUnlocked = array_flip($alreadyUnlocked);
    
    // Check achievement conditions
    foreach ($achievements as $achId => $achTitle) {
        // Skip if already unlocked
        if (isset($alreadyUnlocked[$achId])) {
            $unlockedCount++;
            continue;
        }
        
        $unlocked = false;
        
        // Check conditions based on achievement title
        switch ($achTitle) {
            case 'Follower':
                // Get first follower
                $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM followers WHERE user_id = ?');
                $stmt->execute([$userId]);
                if ($stmt->fetch()['cnt'] > 0) $unlocked = true;
                break;
                
            case 'First Story':
                // Published at least 1 story
                $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM stories WHERE author_id = ? AND status = "published"');
                $stmt->execute([$userId]);
                if ($stmt->fetch()['cnt'] > 0) $unlocked = true;
                break;
                
            case 'Chapter Writer':
                // Written at least 5 chapters
                $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM chapters WHERE created_at >= (SELECT created_at FROM users WHERE id = ?) AND story_id IN (SELECT id FROM stories WHERE author_id = ?)');
                $stmt->execute([$userId, $userId]);
                if ($stmt->fetch()['cnt'] >= 5) $unlocked = true;
                break;
                
            case 'Book Completed':
                // Completed a story with 20+ chapters
                $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM stories WHERE author_id = ? AND (SELECT COUNT(*) FROM chapters WHERE story_id = stories.id) >= 20');
                $stmt->execute([$userId]);
                if ($stmt->fetch()['cnt'] > 0) $unlocked = true;
                break;
                
            case 'Popular Author':
                // Get 100 story views
                $stmt = $pdo->prepare('SELECT SUM(views) as total_views FROM stories WHERE author_id = ?');
                $stmt->execute([$userId]);
                $result = $stmt->fetch();
                if (($result['total_views'] ?? 0) >= 100) $unlocked = true;
                break;
                
            case 'Viral Post':
                // Get 1000 story views
                $stmt = $pdo->prepare('SELECT SUM(views) as total_views FROM stories WHERE author_id = ?');
                $stmt->execute([$userId]);
                $result = $stmt->fetch();
                if (($result['total_views'] ?? 0) >= 1000) $unlocked = true;
                break;
                
            case 'Critic':
                // Written 10 reviews
                $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM reviews WHERE user_id = ?');
                $stmt->execute([$userId]);
                if ($stmt->fetch()['cnt'] >= 10) $unlocked = true;
                break;
                
            case 'Community Leader':
                // Got 100 followers
                $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM followers WHERE user_id = ?');
                $stmt->execute([$userId]);
                if ($stmt->fetch()['cnt'] >= 100) $unlocked = true;
                break;
                
            case 'Social Butterfly':
                // Following 50 authors
                $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM followers WHERE follower_id = ?');
                $stmt->execute([$userId]);
                if ($stmt->fetch()['cnt'] >= 50) $unlocked = true;
                break;
                
            case 'Early Bird':
                // Joined within first month
                $earlyDate = date('Y-m-d', strtotime('-30 days'));
                if ($user['created_at'] > $earlyDate) $unlocked = true;
                break;
                
            case 'Collector':
                // Saved 10 stories to library
                $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM library WHERE user_id = ?');
                $stmt->execute([$userId]);
                if ($stmt->fetch()['cnt'] >= 10) $unlocked = true;
                break;
        }
        
        // Unlock the achievement if conditions met
        if ($unlocked) {
            $insertStmt = $pdo->prepare('INSERT INTO user_achievements (user_id, achievement_id, unlocked_at) VALUES (?, ?, NOW())');
            $insertStmt->execute([$userId, $achId]);
            $newUnlocks[] = $achTitle;
            $unlockedCount++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'unlocked_count' => $unlockedCount,
        'total_achievements' => count($achievements),
        'new_unlocks' => $newUnlocks,
        'message' => count($newUnlocks) > 0 ? 'You unlocked ' . count($newUnlocks) . ' achievement(s)!' : 'No new achievements unlocked'
    ]);
    
} catch (Exception $e) {
    error_log('Achievement check error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
