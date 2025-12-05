<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../inc/db.php';

// Check admin auth
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['super_admin', 'moderator', 'admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$id = intval($data['id'] ?? 0);
$title = trim($data['title'] ?? '');
$summary = trim($data['summary'] ?? '');
$message = trim($data['message'] ?? '');

if (!$id || !$title) {
    echo json_encode(['success' => false, 'error' => 'missing_parameters']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE announcements SET title = ?, summary = ?, message = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$title, $summary, $message, $id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('announcements_update error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'server_error']);
}
?>
