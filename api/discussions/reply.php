<?php
// api/discussions/reply.php - Reply + image (merged; threaded, upload)
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$disc_id = (int)($_POST['discussion_id'] ?? 0);
$parent_id = (int)($_POST['parent_id'] ?? 0);  // For nested
$content = trim($_POST['content'] ?? '');
$image = $_FILES['image'] ?? null;  // Optional

if (!$disc_id || empty($content)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing discussion or content']);
    exit;
}

// Upload image if present
$image_path = null;
if ($image && $image['error'] === UPLOAD_ERR_OK) {
    $upload_dir = UPLOADS_DIR . "forum/replies/{$disc_id}/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $ext = pathinfo($image['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $target = $upload_dir . $filename;
    if (move_uploaded_file($image['tmp_name'], $target)) {
        $image_path = "forum/replies/{$disc_id}/{$filename}";
    }
}

try {
    $stmt = $pdo->prepare('INSERT INTO replies (discussion_id, parent_id, content, image, author_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$disc_id, $parent_id ?: null, $content, $image_path, $_SESSION['user_id']]);
    $reply_id = $pdo->lastInsertId();

    // Update discussion updated_at
    $pdo->prepare('UPDATE discussions SET updated_at = NOW() WHERE id = ?')->execute([$disc_id]);

    echo json_encode(['ok' => true, 'id' => $reply_id, 'image' => $image_path]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Reply failed']);
}
?>