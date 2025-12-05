<?php
// api/admin/revoke-artist.php
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
    $stmt = $pdo->prepare("DELETE ur FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ? AND r.name = 'artist'");
    $stmt->execute([$user_id]);

    if (function_exists('notify')) {
        notify($pdo, $user_id, $_SESSION['user_id'], 'verification', 'Your artist status has been revoked.', '/pages/become-verified.php');
    }

    echo json_encode(['success' => true, 'message' => 'Artist status revoked']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
