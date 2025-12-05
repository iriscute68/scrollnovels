<?php
// admin/ajax/assign_report.php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');
session_start();

if (!isApprovedAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    global $pdo;
    
    $reportId = $_POST['report_id'] ?? 0;
    $assigneeId = $_POST['assignee_id'] ?? 0;
    
    if (!$reportId || !$assigneeId) {
        echo json_encode(['error' => 'Missing report_id or assignee_id']);
        exit;
    }
    
    // Verify assignee is admin/moderator
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? AND role IN ('admin', 'moderator', 'content_manager')");
    $stmt->execute([$assigneeId]);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'Invalid assignee - must be admin, moderator, or content_manager']);
        exit;
    }
    
    // Assign report
    $stmt = $pdo->prepare("UPDATE reports SET assignee_id = ?, assigned_at = NOW(), status = 'in_review' WHERE id = ?");
    $stmt->execute([$assigneeId, $reportId]);
    
    // Log assignment
    $stmt = $pdo->prepare("INSERT INTO moderation_logs (admin_id, action, report_id, reason, created_at) VALUES (?, 'assign', ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $reportId, "Assigned to user ID: $assigneeId"]);
    
    echo json_encode(['ok' => true, 'message' => 'Report assigned successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
