<?php
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$procId = (int)($data['proclamation_id'] ?? 0);
$content = trim($data['content'] ?? '');

if (!$procId || !$content) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO proclamation_replies (proclamation_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
    $ok = $stmt->execute([$procId, $_SESSION['user_id'], $content]);
    
    if ($ok) {
        // Get proclamation author to notify them
        $stmt = $pdo->prepare("SELECT user_id FROM proclamations WHERE id = ?");
        $stmt->execute([$procId]);
        $proc = $stmt->fetch();
        
        if ($proc && $proc['user_id'] != $_SESSION['user_id']) {
            notify($pdo, $proc['user_id'], $_SESSION['user_id'], 'proclamation_reply',
                   "replied to your proclamation",
                   "/pages/proclamations.php");
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to post reply']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
