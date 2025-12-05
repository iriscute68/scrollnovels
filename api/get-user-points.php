<?php
// api/get-user-points.php - Get user's current points and statistics

session_start();
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

try {
    // Get user points
    $stmt = $pdo->prepare("
        SELECT * FROM user_points WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $points = $stmt->fetch();

    if (!$points) {
        $points = [
            'user_id' => $userId,
            'free_points' => 0,
            'premium_points' => 0,
            'patreon_points' => 0,
            'total_points' => 0
        ];
    }

    // Get Patreon status if linked
    $stmt = $pdo->prepare("
        SELECT tier_name, amount_cents, active FROM patreon_links WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $patreonStatus = $stmt->fetch();

    // Get recent point transactions
    $stmt = $pdo->prepare("
        SELECT type, source, points, created_at 
        FROM points_transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $transactions = $stmt->fetchAll();

    // Get books user has supported
    $stmt = $pdo->prepare("
        SELECT 
            bs.book_id,
            s.title,
            SUM(bs.points_spent) as total_points_spent,
            SUM(bs.effective_points) as effective_points,
            COUNT(*) as support_count,
            MAX(bs.created_at) as last_support
        FROM book_support bs
        JOIN stories s ON bs.book_id = s.id
        WHERE bs.user_id = ?
        GROUP BY bs.book_id, s.title
        ORDER BY last_support DESC
    ");
    $stmt->execute([$userId]);
    $supportedBooks = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'points' => $points,
        'patreon' => $patreonStatus,
        'transactions' => $transactions,
        'supported_books' => $supportedBooks
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
