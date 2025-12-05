<?php
// api/chat/create.php - Start convo (merged; JSON participants, auth)
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

requireLogin();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$other_user_id = (int)($input['with'] ?? 0);  // e.g., ?with=2 for author1
if (!$other_user_id || $other_user_id === $_SESSION['user_id']) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user']);
    exit;
}

try {
    // Check if convo exists (participants sorted to avoid dups)
    $participants = json_encode(sorted([$_SESSION['user_id'], $other_user_id]));
    $stmt = $pdo->prepare('SELECT id FROM conversations WHERE participants = ?');
    $stmt->execute([$participants]);
    if ($existing = $stmt->fetch()) {
        echo json_encode(['ok' => true, 'conv_id' => $existing['id']]);
        exit;
    }

    // Create new
    $stmt = $pdo->prepare('INSERT INTO conversations (participants) VALUES (?)');
    $stmt->execute([$participants]);
    $conv_id = $pdo->lastInsertId();

    echo json_encode(['ok' => true, 'conv_id' => $conv_id]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create chat']);
}
?>