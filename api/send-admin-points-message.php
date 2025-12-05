<?php
// api/send-admin-points-message.php - Admin send message in points chat

session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Login required']));
}

// Verify admin role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

if (!$admin || !in_array($admin['role'] ?? '', ['admin', 'superadmin', 'super_admin'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Access denied']));
}

$user_id = $_SESSION['user_id'];
$request_id = (int)($_POST['request_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if (!$request_id || !$message) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Request ID and message required']));
}

try {
    // Insert message
    $stmt = $pdo->prepare("
        INSERT INTO point_purchase_messages (request_id, user_id, message, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$request_id, $user_id, $message]);
    
    // Notify user
    $stmt = $pdo->prepare("SELECT user_id FROM point_purchase_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();
    
    if ($request) {
        $pdo->prepare("
            INSERT INTO notifications (user_id, actor_id, type, message, url, created_at)
            VALUES (?, ?, 'points_message', 'Admin replied on your points purchase', ?, NOW())
        ")->execute([
            $request['user_id'],
            $user_id,
            '/scrollnovels/pages/points-purchase.php?package=1'
        ]);
    }
    
    exit(json_encode([
        'success' => true,
        'message' => 'Message sent successfully'
    ]));
    
} catch (Exception $e) {
    error_log('Admin points message error: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['success' => false, 'error' => 'Server error']));
}
?>
