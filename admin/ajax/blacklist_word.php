<?php
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
    
    $word = $_POST['word'] ?? '';
    $action = $_POST['action'] ?? 'add'; // add or remove
    
    if (!$word || strlen($word) < 2) {
        echo json_encode(['error' => 'Word must be at least 2 characters']);
        exit;
    }
    
    $word = strtolower(trim($word));
    
    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO blacklist_words (word, created_at) VALUES (?, NOW())");
        $stmt->execute([$word]);
        $message = "Word '$word' added to blacklist";
    } else {
        $stmt = $pdo->prepare("DELETE FROM blacklist_words WHERE word = ?");
        $stmt->execute([$word]);
        $message = "Word '$word' removed from blacklist";
    }
    
    $stmt = $pdo->prepare("INSERT INTO moderation_logs (admin_id, action, reason, created_at) VALUES (?, 'blacklist_word', ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], "$action blacklist: $word"]);
    
    echo json_encode(['ok' => true, 'message' => $message]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
