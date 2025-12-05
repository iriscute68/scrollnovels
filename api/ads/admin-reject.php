<?php
// api/ads/admin-reject.php - Admin rejects ad

session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/discord-webhook.php';

header('Content-Type: application/json');

// Verify admin is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $ad_id = (int)($_GET['id'] ?? $_POST['ad_id'] ?? 0);
    $reason = $_POST['reason'] ?? 'No reason provided';

    if (!$ad_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Ad ID required']);
        exit;
    }

    // Get ad details
    $stmt = $pdo->prepare("
        SELECT a.*, u.username, u.email, s.title
        FROM ads a 
        JOIN users u ON u.id = a.user_id 
        JOIN stories s ON s.id = a.book_id
        WHERE a.id = ?
    ");
    $stmt->execute([$ad_id]);
    $ad = $stmt->fetch();

    if (!$ad) {
        http_response_code(404);
        echo json_encode(['error' => 'Ad not found']);
        exit;
    }

    // Update ad to rejected
    $stmt = $pdo->prepare("
        UPDATE ads 
        SET payment_status = 'rejected', admin_verified = false, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$ad_id]);

    // Create admin message with rejection reason
    $stmt = $pdo->prepare("
        INSERT INTO ad_messages (ad_id, sender, message, created_at)
        VALUES (?, 'admin', ?, NOW())
    ");
    $stmt->execute([$ad_id, "Rejected: {$reason}"]);

    // Send Discord notification
    notifyDiscordAdRejected(
        $ad,
        ['username' => $ad['username']],
        ['title' => $ad['title']],
        $reason
    );

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Ad rejected'
    ]);

} catch (Exception $e) {
    error_log("Ad rejection error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
