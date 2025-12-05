<?php
// api/request-verification.php - Handle verification requests from users
if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// Ensure verification_requests table has required columns
try {
    $pdo->exec("ALTER TABLE verification_requests ADD COLUMN IF NOT EXISTS verification_type VARCHAR(50) DEFAULT 'artist'");
    $pdo->exec("ALTER TABLE verification_requests ADD COLUMN IF NOT EXISTS description TEXT");
    $pdo->exec("ALTER TABLE verification_requests ADD COLUMN IF NOT EXISTS proof_images TEXT");
    $pdo->exec("ALTER TABLE verification_requests ADD COLUMN IF NOT EXISTS admin_notes TEXT");
} catch (Exception $e) {
    // Columns might already exist or MySQL doesn't support IF NOT EXISTS
    try {
        // Try adding individually
        try { $pdo->exec("ALTER TABLE verification_requests ADD COLUMN verification_type VARCHAR(50) DEFAULT 'artist'"); } catch (Exception $e2) {}
        try { $pdo->exec("ALTER TABLE verification_requests ADD COLUMN description TEXT"); } catch (Exception $e2) {}
        try { $pdo->exec("ALTER TABLE verification_requests ADD COLUMN proof_images TEXT"); } catch (Exception $e2) {}
        try { $pdo->exec("ALTER TABLE verification_requests ADD COLUMN admin_notes TEXT"); } catch (Exception $e2) {}
    } catch (Exception $e3) {}
}

if ($action === 'submit_request') {
    // Submit verification request
    $verification_type = $_POST['verification_type'] ?? 'artist';
    $description = $_POST['description'] ?? '';

    // Only 'artist' verification is supported
    if (!in_array($verification_type, ['artist'])) {
        $verification_type = 'artist';
    }

    // Check for existing pending request
    try {
        $stmt = $pdo->prepare("SELECT id FROM verification_requests WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$user_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'You already have a pending verification request']);
            exit;
        }
    } catch (Exception $e) {
        // Continue if check fails
    }

    // Handle proof images upload
    $proof_images = [];
    if (isset($_FILES['proof_images']) && !empty($_FILES['proof_images']['name'][0])) {
        $upload_dir = dirname(__DIR__) . '/uploads/verification/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        foreach ($_FILES['proof_images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['proof_images']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = uniqid('proof_') . '_' . basename($_FILES['proof_images']['name'][$key]);
                $file_path = $upload_dir . $file_name;

                // Validate file type
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                if (!in_array($_FILES['proof_images']['type'][$key], $allowed)) {
                    continue;
                }

                // Validate file size (max 5MB)
                if ($_FILES['proof_images']['size'][$key] > 5 * 1024 * 1024) {
                    continue;
                }

                if (move_uploaded_file($tmp_name, $file_path)) {
                    $proof_images[] = '/uploads/verification/' . $file_name;
                }
            }
        }
    }

    $proof_images_json = json_encode($proof_images);
    
    // Build message that includes type and description
    $message_text = "Type: {$verification_type}\n\nDescription:\n{$description}";

    // Try to insert with new columns first, fallback to old structure
    try {
        $stmt = $pdo->prepare("
            INSERT INTO verification_requests 
            (user_id, verification_type, description, proof_images, message, documents, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$user_id, $verification_type, $description, $proof_images_json, $message_text, $proof_images_json]);
    } catch (Exception $e) {
        // Fallback to basic columns
        try {
            $stmt = $pdo->prepare("
                INSERT INTO verification_requests (user_id, message, documents, status, created_at)
                VALUES (?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$user_id, $message_text, $proof_images_json]);
        } catch (Exception $e2) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e2->getMessage()]);
            exit;
        }
    }
    
    $request_id = $pdo->lastInsertId();

    // Notify admins
    try {
        $admin_stmt = $pdo->query("SELECT id FROM users WHERE role IN ('admin', 'super_admin', 'moderator') LIMIT 10");
        $admins = $admin_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($admins as $admin_id) {
            if (function_exists('notify')) {
                notify($pdo, $admin_id, $user_id, 'verification_request', "New {$verification_type} verification request", "/admin/admin.php?page=verify-artist");
            }
        }
    } catch (Exception $e) {
        // Notification failed but verification still submitted
    }

    echo json_encode([
        'success' => true,
        'message' => 'Verification request submitted successfully! Our team will review your submission within 48 hours.'
    ]);
    exit;

} elseif ($action === 'get_request_status') {
    // Get current verification request status
    $stmt = $pdo->prepare("
        SELECT * FROM verification_requests 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $request = $stmt->fetch();

    if ($request) {
        echo json_encode([
            'success' => true,
            'request' => [
                'id' => $request['id'],
                'status' => $request['status'],
                'created_at' => $request['created_at'],
                'updated_at' => $request['updated_at'] ?? null,
                'admin_notes' => $request['admin_notes'] ?? $request['message'] ?? null
            ]
        ]);
    } else {
        echo json_encode(['success' => true, 'request' => null]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
