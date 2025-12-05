<?php
// admin/ajax/handle_report.php
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
    $action = $_POST['action'] ?? ''; // warn, suspend, delete_content, escalate, resolve
    $reason = $_POST['reason'] ?? '';
    $userId = $_POST['user_id'] ?? 0;
    
    if (!$reportId || !$action) {
        echo json_encode(['error' => 'Missing report_id or action']);
        exit;
    }
    
    // Update report status
    $newStatus = ($action === 'escalate') ? 'escalated' : 'resolved';
    $stmt = $pdo->prepare("UPDATE reports SET status = ?, action_taken = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?");
    $stmt->execute([$newStatus, $action, $_SESSION['user_id'], $reportId]);
    
    // Log moderation action
    $stmt = $pdo->prepare("INSERT INTO moderation_logs (admin_id, action, user_id, report_id, reason, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $action, $userId, $reportId, $reason]);
    
    // Take specific action
    if ($action === 'warn' && $userId) {
        // Create warning notification
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$userId, 'warning', "Your content was reported. Reason: " . substr($reason, 0, 100)]);
    } elseif ($action === 'suspend' && $userId) {
        // Suspend user
        $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
        $stmt->execute([$userId]);
    } elseif ($action === 'delete_content' && isset($_POST['content_id'])) {
        // Delete specific content (story, chapter, comment)
        $contentType = $_POST['content_type'] ?? 'comment'; // comment, chapter, story
        if ($contentType === 'comment') {
            $stmt = $pdo->prepare("UPDATE comments SET is_deleted = 1 WHERE id = ?");
        } elseif ($contentType === 'chapter') {
            $stmt = $pdo->prepare("UPDATE chapters SET is_deleted = 1 WHERE id = ?");
        } elseif ($contentType === 'story') {
            $stmt = $pdo->prepare("UPDATE stories SET status = 'archived' WHERE id = ?");
        }
        $stmt->execute([$_POST['content_id']]);
    }
    
    echo json_encode(['ok' => true, 'message' => 'Report handled successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
