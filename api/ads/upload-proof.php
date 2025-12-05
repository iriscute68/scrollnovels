<?php
// api/ads/upload-proof.php - Upload payment proof image and message

session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/discord-webhook.php';

header('Content-Type: application/json');

// Verify user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $ad_id = (int)($_GET['id'] ?? $_POST['ad_id'] ?? 0);
    $user_id = (int)$_SESSION['user_id'];
    $message = $_POST['message'] ?? 'Payment proof uploaded';

    if (!$ad_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Ad ID required']);
        exit;
    }

    // Verify ad exists and belongs to user
    $stmt = $pdo->prepare("SELECT id, user_id, book_id FROM ads WHERE id = ? AND user_id = ?");
    $stmt->execute([$ad_id, $user_id]);
    $ad = $stmt->fetch();
    
    if (!$ad) {
        http_response_code(403);
        echo json_encode(['error' => 'Ad not found or not yours']);
        exit;
    }

    $imagePath = null;

    // Handle file upload if provided
    if (isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['proof'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid image type. Allowed: JPG, PNG, WebP, GIF']);
            exit;
        }

        // Check file size (10MB max)
        if ($file['size'] > 10 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['error' => 'File too large (max 10MB)']);
            exit;
        }

        // Create upload directory
        $uploadDir = __DIR__ . '/../../uploads/ad_proofs';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $ext = strtolower($ext ?: (strpos($mime, 'png') !== false ? 'png' : (strpos($mime, 'jpeg') !== false ? 'jpg' : 'webp')));
        $filename = 'ad_' . $ad_id . '_' . time() . '.' . $ext;
        $filePath = $uploadDir . '/' . $filename;
        $imagePath = 'uploads/ad_proofs/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save file']);
            exit;
        }

        // Update ad with proof image
        $stmt = $pdo->prepare("UPDATE ads SET proof_image = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$imagePath, $ad_id]);
    }

    // Create message record
    $stmt = $pdo->prepare("
        INSERT INTO ad_messages (ad_id, sender, message, image, created_at)
        VALUES (?, 'user', ?, ?, NOW())
    ");
    $stmt->execute([$ad_id, $message, $imagePath]);
    $messageId = $pdo->lastInsertId();

    // Send Discord notification to admin
    $adStmt = $pdo->prepare("SELECT a.*, u.username, u.email, s.title FROM ads a 
                            JOIN users u ON u.id = a.user_id 
                            JOIN stories s ON s.id = a.book_id
                            WHERE a.id = ?");
    $adStmt->execute([$ad_id]);
    $adData = $adStmt->fetch();
    
    if ($adData && !empty($imagePath)) {
        $imageUrl = site_url('/' . $imagePath);
        notifyDiscordAdProof(
            $adData,
            ['username' => $adData['username'], 'email' => $adData['email']],
            ['title' => $adData['title']],
            $imageUrl
        );
    }

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message_id' => $messageId,
        'image' => $imagePath
    ]);

} catch (Exception $e) {
    error_log("Proof upload error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
