<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$data = json_decode(file_get_contents('php://input'), true) ?? [];

// Create chat table if it doesn't exist
try {
    $pdo->query("CREATE TABLE IF NOT EXISTS chat_conversations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user1_id INT NOT NULL,
        user2_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user1_id) REFERENCES users(id),
        FOREIGN KEY (user2_id) REFERENCES users(id)
    )");
    
    $pdo->query("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        conversation_id INT NOT NULL,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
} catch (Exception $e) {
    // Tables may already exist
}

if ($action === 'create') {
    $other_user_id = $data['other_user_id'] ?? null;
    
    if (!$other_user_id) {
        echo json_encode(['success' => false, 'error' => 'Other user ID required']);
        exit;
    }
    
    try {
        // Check if conversation already exists
        $stmt = $pdo->prepare("SELECT id FROM chat_conversations WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
        $stmt->execute([$_SESSION['user_id'], $other_user_id, $other_user_id, $_SESSION['user_id']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            echo json_encode(['success' => true, 'message' => 'Conversation already exists', 'id' => $existing['id']]);
            exit;
        }
        
        // Create new conversation
        $stmt = $pdo->prepare("INSERT INTO chat_conversations (user1_id, user2_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $other_user_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Conversation created',
            'id' => $pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
elseif ($action === 'send_message') {
    $conversation_id = $data['conversation_id'] ?? null;
    $message = $data['message'] ?? null;
    
    if (!$conversation_id || !$message) {
        echo json_encode(['success' => false, 'error' => 'Conversation ID and message required']);
        exit;
    }
    
    try {
        // Verify user is part of this conversation
        $stmt = $pdo->prepare("SELECT id FROM chat_conversations WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
        $stmt->execute([$conversation_id, $_SESSION['user_id'], $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Not authorized for this conversation']);
            exit;
        }
        
        // Insert message
        $stmt = $pdo->prepare("INSERT INTO chat_messages (conversation_id, user_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$conversation_id, $_SESSION['user_id'], $message]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Message sent',
            'id' => $pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
elseif ($action === 'get_messages') {
    $conversation_id = $_GET['conversation_id'] ?? null;
    
    if (!$conversation_id) {
        echo json_encode(['success' => false, 'error' => 'Conversation ID required']);
        exit;
    }
    
    try {
        // Verify user is part of this conversation
        $stmt = $pdo->prepare("SELECT id FROM chat_conversations WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
        $stmt->execute([$conversation_id, $_SESSION['user_id'], $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Not authorized']);
            exit;
        }
        
        // Get messages
        $stmt = $pdo->prepare("
            SELECT cm.id, cm.user_id, u.username, cm.message, cm.created_at 
            FROM chat_messages cm
            LEFT JOIN users u ON cm.user_id = u.id
            WHERE cm.conversation_id = ?
            ORDER BY cm.created_at ASC
        ");
        $stmt->execute([$conversation_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $messages]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
elseif ($action === 'get_conversations') {
    try {
        $stmt = $pdo->prepare("
            SELECT cc.id, cc.created_at,
                   CASE 
                       WHEN cc.user1_id = ? THEN u2.username
                       ELSE u1.username
                   END as other_user,
                   CASE 
                       WHEN cc.user1_id = ? THEN cc.user2_id
                       ELSE cc.user1_id
                   END as other_user_id
            FROM chat_conversations cc
            LEFT JOIN users u1 ON cc.user1_id = u1.id
            LEFT JOIN users u2 ON cc.user2_id = u2.id
            WHERE cc.user1_id = ? OR cc.user2_id = ?
            ORDER BY cc.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $conversations]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
