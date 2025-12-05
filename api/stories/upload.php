<?php
// api/stories/upload.php - Images + Meta (merged; PDO chapter insert, auth)
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth.php';

requireRole('author');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$story_id = (int)($_POST['story_id'] ?? 0);
$number = (int)($_POST['chapter_number'] ?? 1);
$title = trim($_POST['chapter_title'] ?? "Chapter $number");
$content = trim($_POST['content'] ?? '');  // Prose fallback
$slug = slugify($title);

if (!$story_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing story_id']);
    exit;
}

// Verify ownership
$stmt = $pdo->prepare('SELECT id FROM stories WHERE id = ? AND author_id = ?');
$stmt->execute([$story_id, $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Not your story']);
    exit;
}

$upload_dir = UPLOADS_DIR . "stories/{$story_id}/ch{$number}/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$images = [];
$files = $_FILES['images'] ?? [];
if (!empty($files['name'][0])) {  // Multi-file
    foreach ($files['name'] as $i => $name) {
        $tmp = $files['tmp_name'][$i];
        $size = $files['size'][$i];
        $type = $files['type'][$i];
        if ($size > 10 * 1024 * 1024 || !in_array($type, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) continue;

        $index = str_pad($i + 1, 3, '0', STR_PAD_LEFT);
        $ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'jpg';
        $target = $upload_dir . "{$index}.{$ext}";
        if (move_uploaded_file($tmp, $target)) {
            $images[] = "stories/{$story_id}/ch{$number}/{$index}.{$ext}";
        }
    }
}

try {
    // Insert/update chapter
    $images_json = json_encode($images);
    $stmt = $pdo->prepare('INSERT INTO chapters (story_id, title, slug, number, content, images, status) VALUES (?, ?, ?, ?, ?, ?, "published") ON DUPLICATE KEY UPDATE content = ?, images = ?');
    $stmt->execute([$story_id, $title, $slug, $number, $content, $images_json, $content, $images_json]);
    $chapter_id = $pdo->lastInsertId() ?: $pdo->lastInsertId();  // MySQL quirk

    echo json_encode([
        'ok' => true,
        'chapter_id' => $chapter_id,
        'saved' => count($images),
        'slug' => $slug,
        'images' => $images
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Upload failed: ' . $e->getMessage()]);
}
?>