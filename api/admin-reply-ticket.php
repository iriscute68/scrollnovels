<?php
// api/admin-reply-ticket.php - Admin reply to support ticket
header('Content-Type: application/json');
session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Verify admin access
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Check if user is admin
try {
    $stmt = $pdo->prepare("SELECT admin_level FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || $user['admin_level'] < 1) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'error' => 'Admin access required']));
    }
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'error' => 'Server error']));
}

$data = json_decode(file_get_contents('php://input'), true);
$ticket_id = (int)($data['ticket_id'] ?? 0);
$message = trim($data['message'] ?? '');
$admin_id = $_SESSION['user_id'];
$new_status = $data['status'] ?? null;

if (!$ticket_id || empty($message)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Ticket ID and message required']));
}

try {
    // Ensure replies table exists
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

    // Insert admin reply
    $stmt = $pdo->prepare("
        INSERT INTO ticket_replies (ticket_id, admin_id, message, is_admin_reply) 
        VALUES (?, ?, ?, TRUE)
    ");
    $stmt->execute([$ticket_id, $admin_id, $message]);
    
    // Update ticket status if provided
    if ($new_status && in_array($new_status, ['open', 'pending', 'resolved', 'closed'])) {
        $stmt = $pdo->prepare("
            UPDATE support_tickets 
            SET status = ?, assigned_admin_id = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$new_status, $admin_id, $ticket_id]);
    }
    
    // Get ticket user for notification
    $stmt = $pdo->prepare("SELECT user_id FROM support_tickets WHERE id = ?");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
    
    if ($ticket) {
        // Notify user
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, reference_id, reference_type) 
            VALUES (?, 'ticket_reply', 'Support Response', ?, ?, 'support_ticket')
        ");
        $notif_msg = "Admin replied to your support ticket";
        $stmt->execute([$ticket['user_id'], $notif_msg, $ticket_id]);
    }
    
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Reply posted successfully']);
    
} catch (Exception $e) {
    error_log('Ticket reply error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to post reply']);
}
?>
