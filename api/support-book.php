<?php
// api/support-book.php - Support a book with points

session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$bookId = (int)($_POST['book_id'] ?? $_POST['story_id'] ?? 0);
$pointsSpent = (int)($_POST['points'] ?? 0);
$pointType = $_POST['point_type'] ?? 'free'; // free, premium, patreon

// Validate input
if ($bookId <= 0 || $pointsSpent <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid book_id or points']);
    exit;
}

// Allowed point options
$allowedPoints = [10, 50, 100, 500, 1000];
if (!in_array($pointsSpent, $allowedPoints)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid points amount. Allowed: 10, 50, 100, 500, 1000']);
    exit;
}

// Validate point type
if (!in_array($pointType, ['free', 'premium', 'patreon'])) {
    $pointType = 'free';
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Check user's points balance
    $stmt = $pdo->prepare("SELECT * FROM user_points WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userPoints = $stmt->fetch();

    if (!$userPoints) {
        // Create points record if doesn't exist
        $stmt = $pdo->prepare("INSERT INTO user_points (user_id, free_points, premium_points, patreon_points, total_points) VALUES (?, 0, 0, 0, 0)");
        $stmt->execute([$userId]);
        $userPoints = ['free_points' => 0, 'premium_points' => 0, 'patreon_points' => 0];
    }

    // Get available points based on type
    $availablePoints = (int)$userPoints["{$pointType}_points"];
    if ($availablePoints < $pointsSpent) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'error' => "Insufficient {$pointType} points",
            'available' => $availablePoints,
            'required' => $pointsSpent
        ]);
        exit;
    }

    // Determine multiplier based on point type
    $multiplier = 1.0;
    if ($pointType === 'premium') $multiplier = 2.0;
    if ($pointType === 'patreon') $multiplier = 3.0;

    // Record the support transaction
    $stmt = $pdo->prepare("
        INSERT INTO book_support (user_id, book_id, points_spent, point_type, multiplier)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $bookId, $pointsSpent, $pointType, $multiplier]);

    // Deduct points from user
    $stmt = $pdo->prepare("
        UPDATE user_points 
        SET {$pointType}_points = {$pointType}_points - ?,
            total_points = total_points - ?
        WHERE user_id = ?
    ");
    $stmt->execute([$pointsSpent, $pointsSpent, $userId]);

    // Record transaction
    $stmt = $pdo->prepare("
        INSERT INTO points_transactions (user_id, points, type, source)
        VALUES (?, ?, ?, 'support')
    ");
    $stmt->execute([$userId, -$pointsSpent, $pointType]);

    // Recalculate rankings
    recalculateBookRankings($bookId, $pdo);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "✓ Supported with {$pointsSpent} {$pointType} points!",
        'points_remaining' => [
            'free' => (int)$userPoints['free_points'] - ($pointType === 'free' ? $pointsSpent : 0),
            'premium' => (int)$userPoints['premium_points'] - ($pointType === 'premium' ? $pointsSpent : 0),
            'patreon' => (int)$userPoints['patreon_points'] - ($pointType === 'patreon' ? $pointsSpent : 0),
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

/**
 * Recalculate book rankings for daily/weekly/monthly/all-time
 */
function recalculateBookRankings($bookId, $pdo) {
    $now = new DateTime();
    $timestamps = [
        'daily' => $now->modify('-1 day')->getTimestamp(),
        'weekly' => $now->modify('-7 days')->getTimestamp(),
        'monthly' => $now->modify('-30 days')->getTimestamp(),
        'all_time' => 0
    ];

    foreach ($timestamps as $rankType => $timestamp) {
        $sql = "
            SELECT 
                COALESCE(SUM(effective_points), 0) as total_points,
                COUNT(DISTINCT user_id) as supporter_count
            FROM book_support
            WHERE book_id = ?
        ";
        $params = [$bookId];

        if ($timestamp > 0) {
            $sql .= " AND created_at >= FROM_UNIXTIME(?)";
            $params[] = $timestamp;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();

        $stmt = $pdo->prepare("
            INSERT INTO book_rankings (book_id, rank_type, total_support_points, supporter_count, rank_position, calculated_at)
            VALUES (?, ?, ?, ?, NULL, NOW())
            ON DUPLICATE KEY UPDATE 
                total_support_points = VALUES(total_support_points),
                supporter_count = VALUES(supporter_count),
                calculated_at = NOW()
        ");
        $stmt->execute([$bookId, $rankType, $result['total_points'] ?? 0, $result['supporter_count'] ?? 0]);
    }
}
?>
