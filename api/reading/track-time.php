<?php
// api/reading/track-time.php - Track reading time and award points

session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

header('Content-Type: application/json');

// Verify user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $user_id = (int)$_SESSION['user_id'];
    $book_id = (int)($_POST['book_id'] ?? 0);
    $chapter_id = (int)($_POST['chapter_id'] ?? 0);

    if (!$book_id || !$chapter_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Book ID and Chapter ID required']);
        exit;
    }

    // Get cache key for this reading session
    $cacheKey = 'reading_session_' . $user_id . '_' . $book_id;

    // Check if cache file exists and get elapsed time
    $cachePath = sys_get_temp_dir() . '/' . md5($cacheKey) . '.tmp';
    $minutesElapsed = 0;
    $pointsEarned = 0;

    if (file_exists($cachePath)) {
        $lastTime = (int)file_get_contents($cachePath);
        $minutesElapsed = (int)(floor((time() - $lastTime) / 60));
    }

    // Award points if 10+ minutes have passed
    if ($minutesElapsed >= 10) {
        // Calculate points: 3 points per 10 minutes
        $pointsEarned = floor($minutesElapsed / 10) * 3;

        // Create reading session record
        $stmt = $pdo->prepare("
            INSERT INTO reading_sessions (user_id, book_id, chapter_id, minutes_read, points_earned, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $book_id, $chapter_id, $minutesElapsed, $pointsEarned]);

        // Update user's total supporter points
        $stmt = $pdo->prepare("
            UPDATE users 
            SET supporter_points = supporter_points + ?
            WHERE id = ?
        ");
        $stmt->execute([$pointsEarned, $user_id]);

        // Reset cache timer
        file_put_contents($cachePath, time());
    }

    // Update or create cache timestamp
    if (!file_exists($cachePath)) {
        file_put_contents($cachePath, time());
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'minutes_read' => $minutesElapsed,
        'points_earned' => $pointsEarned,
        'message' => $pointsEarned > 0 ? "You earned {$pointsEarned} supporter points!" : 'Keep reading...'
    ]);

} catch (Exception $e) {
    error_log("Reading tracking error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
