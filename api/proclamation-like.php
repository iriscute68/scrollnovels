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

if (!$procId) {
    echo json_encode(['success' => false, 'error' => 'Invalid proclamation ID']);
    exit;
}

try {
    // Check if already liked
    $stmt = $pdo->prepare("SELECT id FROM proclamation_likes WHERE proclamation_id = ? AND user_id = ?");
    $stmt->execute([$procId, $_SESSION['user_id']]);
    $liked = $stmt->fetch();
    
    if ($liked) {
        // Unlike
        $stmt = $pdo->prepare("DELETE FROM proclamation_likes WHERE proclamation_id = ? AND user_id = ?");
        $stmt->execute([$procId, $_SESSION['user_id']]);
    } else {
        // Like
        $stmt = $pdo->prepare("INSERT INTO proclamation_likes (proclamation_id, user_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$procId, $_SESSION['user_id']]);
        
        // Notify proclamation author
        $stmt = $pdo->prepare("SELECT user_id FROM proclamations WHERE id = ?");
        $stmt->execute([$procId]);
        $proc = $stmt->fetch();
        
        if ($proc && $proc['user_id'] != $_SESSION['user_id']) {
            notify($pdo, $proc['user_id'], $_SESSION['user_id'], 'proclamation_like',
                   "liked your proclamation",
                   "/pages/proclamations.php");
        }
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
