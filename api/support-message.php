<?php
// api/support-message.php - Handle support messages and chat

session_status() === PHP_SESSION_NONE && session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
// Ensure support_messages exists to avoid 1146 errors when migrations not applied
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS support_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        moderator_id INT DEFAULT NULL,
        subject VARCHAR(255),
        message TEXT NOT NULL,
        sender_type ENUM('user','moderator') DEFAULT 'user',
        status ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // ignore table creation errors
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'send_message') {
    // Send support message
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($subject) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Subject and message are required']);
        exit;
    }

    // Check if this is a reply to existing thread
    $thread_id = $_POST['thread_id'] ?? null;
    $moderator_id = null;

    if ($thread_id) {
        // Fetch the thread to get moderator_id
        $stmt = $pdo->prepare("SELECT moderator_id FROM support_messages WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$thread_id, $user_id]);
        $thread = $stmt->fetch();
        if ($thread) {
            $moderator_id = $thread['moderator_id'];
        }
    }

    // Insert message
    $stmt = $pdo->prepare("
        INSERT INTO support_messages 
        (user_id, moderator_id, subject, message, sender_type, status)
        VALUES (?, ?, ?, ?, 'user', 'open')
    ");

    if ($stmt->execute([$user_id, $moderator_id, $subject, $message])) {
        $message_id = $pdo->lastInsertId();
        echo json_encode([
            'success' => true,
            'message' => 'Message sent successfully. A moderator will respond soon.',
            'message_id' => $message_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }

} elseif ($action === 'get_messages') {
    // Get all messages for current user
    $stmt = $pdo->prepare("
        SELECT sm.*, u.username, u.profile_image
        FROM support_messages sm
        LEFT JOIN users u ON sm.moderator_id = u.id
        WHERE sm.user_id = ?
        ORDER BY sm.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $messages = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);

} elseif ($action === 'get_thread') {
    // Get specific message thread
    $message_id = $_POST['message_id'] ?? 0;

    // Get main message
    $stmt = $pdo->prepare("
        SELECT sm.*, u.username as user_name, u.profile_image as user_avatar,
               mu.username as mod_name, mu.profile_image as mod_avatar
        FROM support_messages sm
        LEFT JOIN users u ON sm.user_id = u.id
        LEFT JOIN users mu ON sm.moderator_id = mu.id
        WHERE sm.id = ? AND sm.user_id = ?
    ");
    $stmt->execute([$message_id, $user_id]);
    $message = $stmt->fetch();

    if (!$message) {
        echo json_encode(['success' => false, 'message' => 'Message not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} elseif ($action === 'close_ticket') {
    // Close support ticket
    $message_id = $_POST['message_id'] ?? 0;

    $stmt = $pdo->prepare("
        UPDATE support_messages 
        SET status = 'closed' 
        WHERE id = ? AND user_id = ?
    ");

    if ($stmt->execute([$message_id, $user_id])) {
        echo json_encode(['success' => true, 'message' => 'Ticket closed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to close ticket']);
    }

} elseif ($action === 'reply_support') {
    // Admin reply to support ticket
    $message_id = $_POST['message_id'] ?? 0;
    $reply_text = trim($_POST['reply_text'] ?? '');

    if (!$reply_text) {
        echo json_encode(['success' => false, 'message' => 'Reply text is required']);
        exit;
    }

    // Check if user is admin/mod
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $admin_user = $stmt->fetch();
    
    if (!$admin_user || !$admin_user['is_admin']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // Update message with reply
    $stmt = $pdo->prepare("
        UPDATE support_messages 
        SET message = ?, moderator_id = ?, sender_type = 'moderator', status = 'in_progress'
        WHERE id = ?
    ");

    if ($stmt->execute([$reply_text, $user_id, $message_id])) {
        echo json_encode(['success' => true, 'message' => 'Reply sent successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send reply']);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
