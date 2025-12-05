<?php
// api/delete-image.php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();

$input = json_decode(file_get_contents('php://input'), true);
$filename = $input['filename'] ?? '';

if ($filename && preg_match('/^ch_[a-z0-9]+\.(jpg|jpeg|png|webp)$/', $filename)) {
    $path = __DIR__ . '/../uploads/chapters/' . $filename;
    if (file_exists($path)) unlink($path);
}
?>