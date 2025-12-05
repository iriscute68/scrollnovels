<?php
declare(strict_types=1);
// inc/secure_upload.php - helper functions for safe uploads and serving uploaded files

function is_allowed_mime(string $mime): bool {
    $allowed = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
    ];
    return in_array($mime, $allowed, true);
}

function save_uploaded_file(array $file, string $uploadDir = ''): string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload error code: ' . $file['error']);
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!is_allowed_mime($mime)) {
        throw new RuntimeException('Disallowed file type: ' . $mime);
    }
    $maxBytes = 10 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('File too large');
    }
    if (empty($uploadDir)) {
        $uploadDir = APP_ROOT . '/uploads';
    }
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $dstName = bin2hex(random_bytes(16)) . ($ext ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '');
    $dest = rtrim($uploadDir, '/') . '/' . $dstName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Failed to move uploaded file');
    }
    chmod($dest, 0644);
    return $dstName;
}

function serve_uploaded_file(string $filename): void {
    $path = APP_ROOT . '/uploads/' . basename($filename);
    if (!file_exists($path)) {
        http_response_code(404);
        exit('Not found');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $path);
    finfo_close($finfo);
    if (!is_allowed_mime($mime)) {
        http_response_code(415);
        exit('Not allowed');
    }
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($filename) . '"');
    readfile($path);
    exit;
}
?>
