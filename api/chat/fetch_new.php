<?php
// api/chat/fetch_new.php - Poll since last_id (merged; PDO, auth, unread count)
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$last_id = (int)($_GET['last_id'] ?? 0);
$conv_id = (int)($_GET['conv_id'] ?? 0);  // Optional: Single convo
$user_id = $_SESSION['user_id'];

$where = 'WHERE m.id > ? AND c.participants LIKE ?';
$params = [$last_id, "%$user_id%"];
if ($conv_id) {
    $where = 'WHERE m.conv_id = ? AND m.id > ? AND c.participants LIKE ?';
    $params = [$conv_id, $last_id, "%$user_id%"];
}

try {
    $stmt = $pdo->prepare("
        SELECT m.*, u.username, 
               (SELECT COUNT(*) FROM messages mm WHERE mm.conv_id = m.conv_id AND mm.user_id != ? AND mm.status != 'read') as unread_count
        FROM messages m 
        JOIN conversations c ON m.conv_id = c.id 
        JOIN users u ON m.user_id = u.id 
        $where 
        ORDER BY m.created_at ASC
    ");
    $params[] = $user_id;  // For unread subquery
    $stmt->execute($params);
    $messages = $stmt->fetchAll();

    // Mark as read (trigger helps, but explicit for perf)
    if (!empty($messages) && $conv_id) {
        $pdo->prepare('UPDATE messages SET status = "read" WHERE conv_id = ? AND user_id != ? AND status != "read"')
            ->execute([$conv_id, $user_id]);
    }

    echo json_encode([
        'ok' => true,
        'messages' => $messages,
        'last_id' => end($messages)['id'] ?? $last_id,
        'unread' => array_sum(array_column($messages, 'unread_count')) ?? 0
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>