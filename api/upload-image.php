<?php
// api/upload-image.php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();

if (!isset($_FILES['image'])) {
    echo json_encode(['success' => false, 'error' => 'No file']);
    exit;
}

$file = $_FILES['image'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg','jpeg','png','webp'];
if (!in_array($ext, $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Invalid type']);
    exit;
}

$filename = uniqid('ch_') . '.' . $ext;
$path = __DIR__ . '/../uploads/chapters/' . $filename;

if (move_uploaded_file($file['tmp_name'], $path)) {
    echo json_encode(['success' => true, 'filename' => $filename]);
} else {
    echo json_encode(['success' => false, 'error' => 'Upload failed']);
}
?>