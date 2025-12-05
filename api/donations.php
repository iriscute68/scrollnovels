<?php
// api/donations.php - Handle donation recording
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

require_once dirname(__DIR__) . '/config/db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['amount']) || !isset($input['reference'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO donations (user_id, book_id, amount, reference, message, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'success', NOW())
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $input['book_id'] ?? null,
        $input['amount'],
        $input['reference'],
        $input['message'] ?? null
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
