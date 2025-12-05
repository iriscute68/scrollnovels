<?php
/**
 * api/support-with-points.php - Support author with points system
 * Users can support stories using earned points or Patreon
 */

session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

// Ensure logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Must be logged in']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    // Create tables if not exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS story_support (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supporter_id INT NOT NULL,
            story_id INT NOT NULL,
            author_id INT NOT NULL,
            points_amount INT NOT NULL,
            method ENUM('points','patreon') DEFAULT 'points',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_story_id (story_id),
            KEY idx_supporter_id (supporter_id),
            KEY idx_author_id (author_id),
            FOREIGN KEY (supporter_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ============================================
    // GET USER'S POINT BALANCE
    // ============================================
    if ($action === 'get_balance') {
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(points), 0) as points
            FROM user_points
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $userPoints = $result ? (int)$result['points'] : 0;

        echo json_encode([
            'success' => true,
            'points' => $userPoints
        ]);
        exit;
    }

    // ============================================
    // GET STORY DETAILS FOR SUPPORT MODAL
    // ============================================
    if ($action === 'get_story' || $action === 'get_story_info') {
        $storyId = (int)($_GET['story_id'] ?? 0);

        if (!$storyId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'story_id required']);
            exit;
        }

        // Get story info
        $stmt = $pdo->prepare("
            SELECT s.id, s.title, s.author_id, u.username as author_name, u.profile_image
            FROM stories s
            JOIN users u ON s.author_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$storyId]);
        $story = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$story) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Story not found']);
            exit;
        }

        // Get user's current points
        $pointsStmt = $pdo->prepare("
            SELECT COALESCE(SUM(points), 0) as points
            FROM user_points
            WHERE user_id = ?
        ");
        $pointsStmt->execute([$userId]);
        $userPoints = $pointsStmt->fetch()['points'] ?? 0;

        // Get total supporters for this story
        $supportStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT supporter_id) as supporter_count,
                   SUM(points_amount) as total_points
            FROM story_support
            WHERE story_id = ?
        ");
        $supportStmt->execute([$storyId]);
        $support = $supportStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'story' => $story,
            'user_points' => $userPoints,
            'support_stats' => [
                'supporter_count' => (int)($support['supporter_count'] ?? 0),
                'total_points_received' => (int)($support['total_points'] ?? 0)
            ]
        ]);
        exit;
    }

    // ============================================
    // SUPPORT WITH POINTS
    // ============================================
    if ($action === 'support_points') {
        $storyId = (int)($_POST['story_id'] ?? 0);
        $pointsToGive = (int)($_POST['points'] ?? 0);

        if (!$storyId || $pointsToGive <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid story_id or points']);
            exit;
        }

        // Get story author
        $storyStmt = $pdo->prepare("SELECT author_id FROM stories WHERE id = ?");
        $storyStmt->execute([$storyId]);
        $story = $storyStmt->fetch();

        if (!$story) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Story not found']);
            exit;
        }

        $authorId = $story['author_id'];

        // Check user's current points
        $pointsStmt = $pdo->prepare("
            SELECT COALESCE(SUM(points), 0) as points
            FROM user_points
            WHERE user_id = ?
        ");
        $pointsStmt->execute([$userId]);
        $userPoints = (int)($pointsStmt->fetch()['points'] ?? 0);

        if ($userPoints < $pointsToGive) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Insufficient points',
                'user_points' => $userPoints,
                'required' => $pointsToGive
            ]);
            exit;
        }

        // Record support transaction
        $supportStmt = $pdo->prepare("
            INSERT INTO story_support (supporter_id, story_id, author_id, points_amount, method)
            VALUES (?, ?, ?, ?, 'points')
        ");
        $supportStmt->execute([$userId, $storyId, $authorId, $pointsToGive]);

        // Also record in author_supporters table for top supporters display
        $authorSupportStmt = $pdo->prepare("
            INSERT INTO author_supporters (author_id, supporter_id, story_id, points_total, last_supported_at, created_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                points_total = points_total + ?,
                last_supported_at = NOW()
        ");
        $authorSupportStmt->execute([$authorId, $userId, $storyId, $pointsToGive, $pointsToGive]);

        // Deduct from supporter
        $deductStmt = $pdo->prepare("
            UPDATE user_points SET points = points - ? WHERE user_id = ?
        ");
        $deductStmt->execute([$pointsToGive, $userId]);

        // Add to author
        $authorPointsCheck = $pdo->prepare("SELECT id FROM user_points WHERE user_id = ?");
        $authorPointsCheck->execute([$authorId]);
        if (!$authorPointsCheck->fetch()) {
            $pdo->prepare("INSERT INTO user_points (user_id, points, lifetime_points) VALUES (?, ?, ?)")
                ->execute([$authorId, 0, 0]);
        }

        $addStmt = $pdo->prepare("
            UPDATE user_points SET points = points + ?, lifetime_points = lifetime_points + ? WHERE user_id = ?
        ");
        $addStmt->execute([$pointsToGive, $pointsToGive, $authorId]);

        // Get updated stats
        $updatedPoints = $pdo->prepare("
            SELECT COALESCE(SUM(points), 0) as points
            FROM user_points
            WHERE user_id = ?
        ");
        $updatedPoints->execute([$userId]);
        $newBalance = (int)($updatedPoints->fetch()['points'] ?? 0);

        echo json_encode([
            'success' => true,
            'message' => "You supported this story with $pointsToGive points!",
            'new_balance' => $newBalance
        ]);
        exit;
    }

    // ============================================
    // GET TOP SUPPORTERS FOR STORY
    // ============================================
    if ($action === 'top_supporters') {
        $storyId = (int)($_GET['story_id'] ?? 0);
        $limit = (int)($_GET['limit'] ?? 10);

        if (!$storyId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'story_id required']);
            exit;
        }

        $limit = min($limit, 50);

        $stmt = $pdo->prepare("
            SELECT 
                u.id, u.username, u.profile_image,
                SUM(ss.points_amount) as total_points,
                COUNT(*) as support_count
            FROM story_support ss
            JOIN users u ON ss.supporter_id = u.id
            WHERE ss.story_id = ?
            GROUP BY ss.supporter_id
            ORDER BY total_points DESC
            LIMIT ?
        ");
        $stmt->execute([$storyId, $limit]);
        $supporters = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'supporters' => $supporters
        ]);
        exit;
    }

    // ============================================
    // GET USER'S TOTAL RECEIVED SUPPORT
    // ============================================
    if ($action === 'user_support_received') {
        $targetUserId = (int)($_GET['user_id'] ?? $userId);

        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT supporter_id) as total_supporters,
                SUM(points_amount) as total_points_received
            FROM story_support
            WHERE author_id = ?
        ");
        $stmt->execute([$targetUserId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'stats' => [
                'total_supporters' => (int)($stats['total_supporters'] ?? 0),
                'total_points_received' => (int)($stats['total_points_received'] ?? 0)
            ]
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
