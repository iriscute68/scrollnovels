<?php
/**
 * api/messages/edit.php - Edit an existing message
 */
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

requireLogin();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$message_id = (int)($input['message_id'] ?? 0);
$new_content = trim($input['content'] ?? '');
$user_id = $_SESSION['user_id'];

if (!$message_id || empty($new_content)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing message_id or content']);
    exit;
}

try {
    // Verify user owns the message
    $stmt = $pdo->prepare("SELECT user_id FROM chat_messages WHERE id = ?");
    $stmt->execute([$message_id]);
    $message = $stmt->fetch();

    if (!$message) {
        http_response_code(404);
        echo json_encode(['error' => 'Message not found']);
        exit;
    }

    if ($message['user_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    // Update message
    $stmt = $pdo->prepare("
        UPDATE chat_messages 
        SET content = ?, edited_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$new_content, $message_id]);

    echo json_encode([
        'ok' => true,
        'id' => $message_id,
        'content' => $new_content,
        'edited' => true,
        'timestamp' => date('c')
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
