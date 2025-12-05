<?php
// api/admin/add-ticket-reply.php - Add reply to support ticket
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// Check admin login
$admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
if (!$admin_id) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Admin access required']));
}

// Verify admin role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$user = $stmt->fetch();
if (!$user || !in_array($user['role'] ?? '', ['admin', 'super_admin', 'moderator'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Admin role required']));
}

$ticket_id = (int)($_POST['ticket_id'] ?? 0);
$reply_text = trim($_POST['reply_text'] ?? '');

if (!$ticket_id || !$reply_text) {
    exit(json_encode(['success' => false, 'message' => 'Ticket ID and reply text required']));
}

if (strlen($reply_text) > 10000) {
    exit(json_encode(['success' => false, 'message' => 'Reply too long (max 10000 characters)']));
}

try {
    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        admin_id INT,
        user_id INT,
        message LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (ticket_id),
        FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Insert reply
    $stmt = $pdo->prepare("
        INSERT INTO ticket_replies (ticket_id, admin_id, user_id, message, created_at)
        VALUES (?, ?, NULL, ?, NOW())
    ");
    $stmt->execute([$ticket_id, $admin_id, $reply_text]);
    
    // Update ticket status to in_progress if not already resolved/closed
    $pdo->prepare("
        UPDATE support_tickets 
        SET status = CASE 
            WHEN status NOT IN ('resolved', 'closed') THEN 'in_progress'
            ELSE status
        END,
        updated_at = NOW()
        WHERE id = ?
    ")->execute([$ticket_id]);
    
    // Get ticket user ID to send notification
    $stmt = $pdo->prepare("SELECT user_id, subject FROM support_tickets WHERE id = ?");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
    
    if ($ticket && $ticket['user_id']) {
        // Get admin username
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch();
        
        // Send notification to user
        $pdo->prepare("
            INSERT INTO notifications (user_id, actor_id, type, message, url, created_at)
            VALUES (?, ?, 'support_reply', ?, ?, NOW())
        ")->execute([
            $ticket['user_id'],
            $admin_id,
            'Admin replied to your support ticket: ' . htmlspecialchars(substr($ticket['subject'], 0, 50)),
            '/scrollnovels/pages/support.php#ticket-' . $ticket_id
        ]);
    }
    
    exit(json_encode([
        'success' => true,
        'message' => 'Reply sent successfully',
        'reply_id' => $pdo->lastInsertId()
    ]));
    
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]));
}
