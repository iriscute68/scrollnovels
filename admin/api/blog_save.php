<?php
// admin/api/blog_save.php - Save/Update blog post
session_start();
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $id = intval($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $category = trim($_POST['category'] ?? 'Update');
    $tags = trim($_POST['tags'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = $_POST['content'] ?? '';
    $status = $_POST['status'] ?? 'draft';

    if (!$title || !$content) {
        echo json_encode(['success' => false, 'error' => 'Title and content are required']);
        exit;
    }

    // Handle image upload
    $cover_image = null;
    if (!empty($_FILES['cover_image']['name'])) {
        $upload_dir = __DIR__ . '/../../uploads/blog/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $ext = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $target = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $target)) {
            $cover_image = '/uploads/blog/' . $filename;
        }
    }

    if ($id) {
        // Update existing post
        $stmt = $pdo->prepare("
            UPDATE posts SET 
                title = ?,
                slug = ?,
                category = ?,
                tags = ?,
                excerpt = ?,
                content = ?,
                status = ?,
                updated_at = NOW()
                " . ($cover_image ? ", cover_image = ?" : "") . "
            WHERE id = ?
        ");

        $params = [$title, $slug, $category, $tags, $excerpt, $content, $status];
        if ($cover_image) $params[] = $cover_image;
        $params[] = $id;

        $stmt->execute($params);
        echo json_encode(['success' => true, 'message' => 'Blog post updated']);
    } else {
        // Create new post
        $stmt = $pdo->prepare("
            INSERT INTO posts (title, slug, category, tags, excerpt, content, cover_image, user_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([$title, $slug, $category, $tags, $excerpt, $content, $cover_image, $_SESSION['admin_id'], $status]);
        echo json_encode(['success' => true, 'message' => 'Blog post created']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
