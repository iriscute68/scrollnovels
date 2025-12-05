<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
session_start();
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin','super_admin','moderator'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

$id = intval($_POST['chapter_id'] ?? 0);
if (!$id) { echo json_encode(['ok' => false, 'message' => 'Missing id']); exit; }

try {
    $stmt = $pdo->prepare('UPDATE chapters SET is_paid = NOT COALESCE(is_paid, 0) WHERE id = ?');
    $stmt->execute([$id]);

    // log
    try {
        $log = $pdo->prepare('INSERT INTO admin_activity_logs (admin_id, action, target_type, target_id, created_at) VALUES (?, ?, ?, ?, NOW())');
        $log->execute([$_SESSION['user_id'] ?? null, 'toggle_paywall', 'chapter', $id]);
    } catch (Exception $e) {}

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}

?>
