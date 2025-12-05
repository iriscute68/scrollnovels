<?php
// api/admin/approve-artist.php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/../includes/auth.php';
require_once dirname(__DIR__) . '/../config/db.php';

if (!hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = (int)($data['user_id'] ?? 0);

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Missing user_id']);
    exit;
}

try {
    // Update verification request
    $stmt = $pdo->prepare("UPDATE verification_requests SET status = 'approved', reviewed_by = ? WHERE user_id = ? AND verification_type = 'artist' AND status = 'pending'");
    $stmt->execute([$_SESSION['user_id'], $user_id]);

    // Add artist role to user
    $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE name = 'artist' ON DUPLICATE KEY UPDATE role_id = role_id");
    $stmt->execute([$user_id]);

    // Send notification
    if (function_exists('notify')) {
        notify($pdo, $user_id, $_SESSION['user_id'], 'verification', 'Your artist verification has been approved!', '/pages/become-verified.php');
    }

    echo json_encode(['success' => true, 'message' => 'Artist approved']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
