<?php
// admin/api/reject-flag.php
session_start();
require_once dirname(dirname(dirname(__DIR__))) . '/config/db.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing flag ID']);
    exit;
}

try {
    // Get the post_id first
    $stmt = $pdo->prepare("SELECT post_id FROM moderation_reports WHERE id = ?");
    $stmt->execute([$data['id']]);
    $report = $stmt->fetch();
    
    if ($report) {
        // Delete the post
        $stmt = $pdo->prepare("DELETE FROM community_posts WHERE id = ?");
        $stmt->execute([$report['post_id']]);
        
        // Update the flag status
        $stmt = $pdo->prepare("UPDATE moderation_reports SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$data['id']]);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
