<?php
// admin/ajax/suspend_user.php - Suspend/unsuspend user
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['admin_user'])) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'message' => 'Unauthorized']));
}

$user_id = intval($_GET['id'] ?? 0);
if (!$user_id) {
    exit(json_encode(['ok' => false, 'message' => 'Invalid user ID']));
}

try {
    // Check current status
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $new_status = ($user['status'] ?? 'active') === 'suspended' ? 'active' : 'suspended';

    $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_status, $user_id]);

    $pdo->prepare("
        INSERT INTO admin_activity_logs (admin_id, action, details, created_at)
        VALUES (?, ?, ?, NOW())
    ")->execute([
        $_SESSION['admin_user']['id'],
        'user_suspend',
        json_encode(['user_id' => $user_id, 'new_status' => $new_status])
    ]);

    exit(json_encode(['ok' => true, 'status' => $new_status]));
} catch (Exception $e) {
    exit(json_encode(['ok' => false, 'message' => $e->getMessage()]));
}
?>
