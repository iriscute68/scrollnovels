<?php
// api/user-reply-ticket.php - User reply to their support ticket
header('Content-Type: application/json');
session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$data = json_decode(file_get_contents('php://input'), true);
$ticket_id = (int)($data['ticket_id'] ?? 0);
$message = trim($data['message'] ?? '');
$user_id = $_SESSION['user_id'];

// Validate
if (!$ticket_id || empty($message) || strlen($message) < 5) {
    http_response_code(400);
    exit(json_encode([
        'success' => false,
        'error' => 'Ticket ID required and message must be at least 5 characters'
    ]));
}

try {
    // Ensure replies table exists (align with get/admin endpoints expectations)
    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        user_id INT NULL,
        admin_id INT NULL,
        message TEXT NOT NULL,
        is_admin_reply TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
        INDEX (ticket_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Verify ticket belongs to user
    $verify = $pdo->prepare("SELECT id, assigned_admin_id FROM support_tickets WHERE id = ? AND user_id = ?");
    $verify->execute([$ticket_id, $user_id]);
    $ticket = $verify->fetch();

    if (!$ticket) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'error' => 'Ticket not found or access denied']));
    }

    // Insert reply
    $stmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message, is_admin_reply) VALUES (?, ?, ?, 0)");
    $stmt->execute([$ticket_id, $user_id, $message]);

    // Update ticket status to pending
    $update = $pdo->prepare("UPDATE support_tickets SET status = 'pending', updated_at = NOW() WHERE id = ?");
    $update->execute([$ticket_id]);

    // Optional notify assigned admin via helper if available
    if (!empty($ticket['assigned_admin_id']) && function_exists('notify')) {
        notify($pdo, (int)$ticket['assigned_admin_id'], $user_id, 'ticket_reply', 'User replied to their support ticket', (string)$ticket_id, '/admin/admin.php?page=support&ticket=' . $ticket_id);
    }

    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Reply posted successfully']);

} catch (Exception $e) {
    error_log('Ticket reply error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to post reply']);
}
?>