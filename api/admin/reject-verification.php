<?php
// api/admin/reject-verification.php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/../includes/auth.php';
require_once dirname(__DIR__) . '/../config/db.php';

if (!hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$request_id = (int)($data['request_id'] ?? 0);

if (!$request_id) {
    echo json_encode(['success' => false, 'error' => 'Missing request_id']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT user_id, verification_type FROM verification_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $req = $stmt->fetch();
    
    if (!$req) {
        echo json_encode(['success' => false, 'error' => 'Request not found']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE verification_requests SET status = 'rejected', reviewed_by = ? WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $request_id]);

    if (function_exists('notify')) {
        $type = $req['verification_type'];
        notify($pdo, $req['user_id'], $_SESSION['user_id'], 'verification', 'Your ' . $type . ' verification was not approved.', '/pages/become-verified.php');
    }

    echo json_encode(['success' => true, 'message' => 'Application rejected']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
