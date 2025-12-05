<?php
// api/chat/send.php - Unified send (merged; PDO, auth, status)
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

requireLogin();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$conv_id = (int)($input['conv_id'] ?? 0);
$content = trim($input['content'] ?? '');
$type = in_array($input['type'] ?? 'text', ['text', 'image']) ? $input['type'] : 'text';

if (!$conv_id || empty($content)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing conv_id or content']);
    exit;
}

// Verify user in convo
$stmt = $pdo->prepare('SELECT participants FROM conversations WHERE id = ?');
$stmt->execute([$conv_id]);
$conv = $stmt->fetch();
if (!$conv || !in_array($_SESSION['user_id'], json_decode($conv['participants'] ?? '[]', true))) {
    http_response_code(403);
    echo json_encode(['error' => 'Not in conversation']);
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO messages (conv_id, user_id, content, type) VALUES (?, ?, ?, ?)');
    $stmt->execute([$conv_id, $_SESSION['user_id'], $content, $type]);
    $msg_id = $pdo->lastInsertId();

    // Update conv timestamp
    $pdo->prepare('UPDATE conversations SET updated_at = NOW() WHERE id = ?')->execute([$conv_id]);

    // Trigger handles 'delivered'/'read' auto

    echo json_encode([
        'ok' => true,
        'id' => $msg_id,
        'status' => 'sent',
        'timestamp' => date('c')
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Send failed: ' . $e->getMessage()]);
}
?>