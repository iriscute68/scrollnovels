<?php
// api/send-support-reply.php - Send reply to support ticket
header('Content-Type: application/json');
session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Please login']));
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$ticket_id = (int)($data['ticket_id'] ?? 0);
$message = trim($data['message'] ?? '');

if (!$ticket_id || empty($message)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Ticket ID and message required']));
}

try {
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS support_replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        user_id INT NOT NULL,
        message LONGTEXT NOT NULL,
        is_admin TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (ticket_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Verify user owns ticket or is admin
    $stmt = $pdo->prepare("SELECT user_id FROM support_tickets WHERE id = ?");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'error' => 'Ticket not found']));
    }
    
    $is_admin = false;
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if ($user && in_array($user['role'] ?? '', ['admin', 'super_admin', 'moderator'])) {
        $is_admin = true;
    }
    
    // Check ownership or admin role
    if ($ticket['user_id'] !== $user_id && !$is_admin) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
    }
    
    // Insert reply
    $stmt = $pdo->prepare("
        INSERT INTO support_replies (ticket_id, user_id, message, is_admin) 
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->execute([$ticket_id, $user_id, $message, $is_admin ? 1 : 0]);
    $reply_id = $pdo->lastInsertId();
    
    // Update ticket status if admin reply
    if ($is_admin) {
        $stmt = $pdo->prepare("UPDATE support_tickets SET status = 'in_progress' WHERE id = ?");
        $stmt->execute([$ticket_id]);
        
        // Notify ticket owner
        notify($pdo, $ticket['user_id'], $user_id, 'support_reply', 'New reply to your support ticket', "/pages/support.php?ticket=" . $ticket_id);
    } else {
        $stmt = $pdo->prepare("UPDATE support_tickets SET status = 'pending' WHERE id = ?");
        $stmt->execute([$ticket_id]);
        
        // Notify admins
        $admins = $pdo->query("SELECT id FROM users WHERE role IN ('admin', 'super_admin', 'moderator')")->fetchAll();
        foreach ($admins as $admin) {
            notify($pdo, $admin['id'], $user_id, 'support_reply', 'User replied to support ticket #' . $ticket_id, "/admin/admin.php?page=support&ticket=" . $ticket_id);
        }
    }
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'reply_id' => $reply_id,
        'message' => 'Reply sent successfully'
    ]);
    
} catch (Exception $e) {
    error_log('Support reply error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to send reply: ' . $e->getMessage()]);
}
?>
