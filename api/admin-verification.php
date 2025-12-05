<?php
// api/admin-verification.php - Admin API for verification request management

require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in and is moderator/admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userRoles = $_SESSION['roles'] ?? [];
if (is_string($userRoles)) {
    $userRoles = json_decode($userRoles, true) ?: [];
}

if (!in_array('admin', $userRoles) && !in_array('mod', $userRoles)) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'get_request') {
    // Get specific verification request details
    $request_id = (int)($_POST['id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT vr.*, u.username, u.email, u.profile_image 
        FROM verification_requests vr
        LEFT JOIN users u ON vr.user_id = u.id
        WHERE vr.id = ?
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();

    if ($request) {
        // Parse proof_images JSON
        $proof_images = json_decode($request['proof_images'], true) ?: [];

        echo json_encode([
            'success' => true,
            'request' => [
                'id' => $request['id'],
                'user_id' => $request['user_id'],
                'username' => $request['username'],
                'email' => $request['email'],
                'avatar' => $request['avatar'],
                'verification_type' => $request['verification_type'],
                'status' => $request['status'],
                'description' => $request['description'],
                'proof_images' => $proof_images,
                'admin_notes' => $request['admin_notes'],
                'created_at' => $request['created_at'],
                'updated_at' => $request['updated_at']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
    }

} elseif ($action === 'get_support_messages') {
    // Get support messages for moderator dashboard
    $status_filter = $_POST['status'] ?? 'open';

    if ($status_filter === 'all') {
        $query = "SELECT sm.*, u.username, u.profile_image FROM support_messages sm 
                  LEFT JOIN users u ON sm.user_id = u.id 
                  ORDER BY sm.created_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
    } else {
        $query = "SELECT sm.*, u.username, u.profile_image FROM support_messages sm 
                  LEFT JOIN users u ON sm.user_id = u.id 
                  WHERE sm.status = ? 
                  ORDER BY sm.created_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$status_filter]);
    }

    $messages = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);

} elseif ($action === 'reply_support') {
    // Send reply to support message
    $message_id = (int)($_POST['message_id'] ?? 0);
    $reply = trim($_POST['message'] ?? '');

    if (empty($reply)) {
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
        exit;
    }

    // Update original message moderator_id and status
    $stmt = $pdo->prepare("
        UPDATE support_messages 
        SET moderator_id = ?, status = 'in_progress'
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $message_id]);

    // Insert reply message
    $stmt = $pdo->prepare("
        INSERT INTO support_messages 
        (user_id, moderator_id, message, sender_type, status)
        VALUES ((SELECT user_id FROM support_messages WHERE id = ?), ?, ?, 'moderator', 'in_progress')
    ");

    if ($stmt->execute([$message_id, $_SESSION['user_id'], $reply])) {
        echo json_encode([
            'success' => true,
            'message' => 'Reply sent successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send reply']);
    }

} elseif ($action === 'close_support') {
    // Close support ticket
    $message_id = (int)($_POST['message_id'] ?? 0);

    $stmt = $pdo->prepare("
        UPDATE support_messages 
        SET status = 'closed'
        WHERE id = ?
    ");

    if ($stmt->execute([$message_id])) {
        echo json_encode(['success' => true, 'message' => 'Ticket closed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to close ticket']);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
