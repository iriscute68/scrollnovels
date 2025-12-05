<?php
// admin/ajax/approve_payout.php - Approve payout
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['admin_user'])) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'message' => 'Unauthorized']));
}

$payout_id = intval($_GET['id'] ?? 0);
if (!$payout_id) {
    exit(json_encode(['ok' => false, 'message' => 'Invalid payout ID']));
}

try {
    // Update payout status
    $stmt = $pdo->prepare("UPDATE payouts SET status = 'completed', completed_at = NOW() WHERE id = ?");
    $stmt->execute([$payout_id]);

    $pdo->prepare("
        INSERT INTO admin_activity_logs (admin_id, action, details, created_at)
        VALUES (?, ?, ?, NOW())
    ")->execute([
        $_SESSION['admin_user']['id'],
        'payout_approve',
        json_encode(['payout_id' => $payout_id])
    ]);

    exit(json_encode(['ok' => true]));
} catch (Exception $e) {
    exit(json_encode(['ok' => false, 'message' => $e->getMessage()]));
}
?>
