<?php
// api/upload-competition-image.php - Upload competition cover image
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Verify admin access
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'POST only']));
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'No image or upload error']));
}

$allowed = ['image/jpeg', 'image/png', 'image/webp'];
$file = $_FILES['image'];

// Validate MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowed)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Invalid image type: ' . $mimeType]));
}

// Check file size (max 10MB for competition banners)
if ($file['size'] > 10 * 1024 * 1024) {
    http_response_code(413);
    exit(json_encode(['success' => false, 'error' => 'File too large (max 10MB)']));
}

// Create upload directory
$uploadDir = dirname(__DIR__) . '/uploads/competitions/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'comp_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$targetPath = $uploadDir . $filename;

// Move file
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // Return relative path for database storage
    $relativePath = '/uploads/competitions/' . $filename;
    http_response_code(200);
    exit(json_encode([
        'success' => true,
        'filename' => $filename,
        'path' => $relativePath
    ]));
} else {
    http_response_code(500);
    exit(json_encode(['success' => false, 'error' => 'Upload failed']));
}
?>
