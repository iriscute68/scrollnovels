<?php
// api/get-support-tickets.php - Get user's support tickets with replies and admin info
header('Content-Type: application/json');
session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$user_id = $_SESSION['user_id'];
$status = $_GET['status'] ?? null;
$ticket_id = (int)($_GET['ticket_id'] ?? 0);

try {
    // Get specific ticket with replies
    if ($ticket_id) {
        $stmt = $pdo->prepare("
            SELECT st.*, st.message as description,
                   (SELECT COUNT(*) FROM ticket_replies WHERE ticket_id = st.id) as reply_count,
                   u.username as assigned_admin_name
            FROM support_tickets st
            LEFT JOIN users u ON st.assigned_admin_id = u.id
            WHERE st.id = ? AND st.user_id = ?
        ");
        $stmt->execute([$ticket_id, $user_id]);
        $ticket = $stmt->fetch();
        
        if (!$ticket) {
            http_response_code(404);
            exit(json_encode(['success' => false, 'error' => 'Ticket not found']));
        }
        
        // Get replies
        $replies_stmt = $pdo->prepare("
            SELECT tr.*, 
                   CASE WHEN tr.admin_id IS NOT NULL THEN 'Admin' ELSE 'User' END as sender_type,
                   u.username,
                   u.profile_image
            FROM ticket_replies tr
            LEFT JOIN users u ON COALESCE(tr.admin_id, tr.user_id) = u.id
            WHERE tr.ticket_id = ?
            ORDER BY tr.created_at ASC
        ");
        $replies_stmt->execute([$ticket_id]);
        $replies = $replies_stmt->fetchAll();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'ticket' => $ticket,
            'replies' => $replies
        ]);
        exit;
    }
    
    // Get all tickets for user
    if ($status) {
        $stmt = $pdo->prepare("
            SELECT st.id, st.subject, st.category, st.status, st.priority, 
                   st.created_at, st.updated_at,
                   (SELECT COUNT(*) FROM ticket_replies WHERE ticket_id = st.id) as reply_count,
                   u.username as assigned_admin_name
            FROM support_tickets st
            LEFT JOIN users u ON st.assigned_admin_id = u.id
            WHERE st.user_id = ? AND st.status = ?
            ORDER BY st.updated_at DESC
        ");
        $stmt->execute([$user_id, $status]);
    } else {
        $stmt = $pdo->prepare("
            SELECT st.id, st.subject, st.category, st.status, st.priority, 
                   st.created_at, st.updated_at,
                   (SELECT COUNT(*) FROM ticket_replies WHERE ticket_id = st.id) as reply_count,
                   u.username as assigned_admin_name
            FROM support_tickets st
            LEFT JOIN users u ON st.assigned_admin_id = u.id
            WHERE st.user_id = ?
            ORDER BY st.updated_at DESC
            LIMIT 50
        ");
        $stmt->execute([$user_id]);
    }
    
    $tickets = $stmt->fetchAll();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'tickets' => $tickets,
        'count' => count($tickets)
    ]);
    
} catch (Exception $e) {
    error_log('Ticket fetch error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch tickets']);
}
?>
