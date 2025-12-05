<?php
// api/deactivate-ad.php - Deactivate an ad
session_start();
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Check admin auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Login required']));
}

// Verify admin role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !in_array($user['role'], ['admin', 'super_admin', 'moderator'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Admin access required']));
}

// Get ad_id from POST body
$input = $_POST;
if (empty($input['ad_id'])) {
    parse_str(file_get_contents('php://input'), $input);
}
$adId = (int)($input['ad_id'] ?? 0);

if (!$adId) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Ad ID required']));
}

try {
    // Update ad status to paused
    $stmt = $pdo->prepare("UPDATE ads SET status = 'paused' WHERE id = ?");
    $stmt->execute([$adId]);
    
    // Update sponsored_books if exists
    try {
        $stmt = $pdo->prepare("UPDATE sponsored_books SET status = 'paused' WHERE ad_id = ?");
        $stmt->execute([$adId]);
    } catch (Exception $e) {}
    
    echo json_encode(['success' => true, 'message' => 'Ad deactivated successfully']);
    
} catch (Exception $e) {
    error_log('Deactivate ad error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
