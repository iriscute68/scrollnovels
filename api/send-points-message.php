<?php
// api/send-points-message.php - Send message in points purchase chat

session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Login required']));
}

$user_id = $_SESSION['user_id'];
$request_id = (int)($_POST['request_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if (!$request_id) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Request ID required']));
}

// Verify ownership
try {
    $stmt = $pdo->prepare("SELECT user_id FROM point_purchase_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();
    
    if (!$request || $request['user_id'] != $user_id) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
    }
    
    // Handle image upload
    $image_url = null;
    if (!empty($_FILES['image']['tmp_name'])) {
        $upload_dir = dirname(__DIR__) . '/uploads/point-proofs/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'proof_' . $request_id . '_' . time() . '.' . $file_ext;
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
            $image_url = '/scrollnovels/uploads/point-proofs/' . $filename;
        }
    }
    
    // Insert message
    $stmt = $pdo->prepare("
        INSERT INTO point_purchase_messages (request_id, user_id, message, image_url, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$request_id, $user_id, $message ?: null, $image_url]);
    
    // Notify admins
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role IN ('admin', 'superadmin') LIMIT 5");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($admins as $admin_id) {
        $pdo->prepare("
            INSERT INTO notifications (user_id, actor_id, type, message, url, created_at)
            VALUES (?, ?, 'points_message', ?, ?, NOW())
        ")->execute([
            $admin_id,
            $user_id,
            'New message on points purchase request #' . $request_id,
            '/scrollnovels/admin/pages/points-purchase.php?id=' . $request_id
        ]);
    }
    
    exit(json_encode([
        'success' => true,
        'message' => 'Message sent successfully'
    ]));
    
} catch (Exception $e) {
    error_log('Points message error: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['success' => false, 'error' => 'Server error']));
}
?>
