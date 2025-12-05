<?php
// api/save_post.php - Save blog post (new or edit)
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
    exit;
}

$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$category = trim($_POST['category'] ?? 'Update');
$tags = trim($_POST['tags'] ?? '');
$excerpt = trim($_POST['excerpt'] ?? '');
$coverImage = trim($_POST['cover_image'] ?? '');
$status = trim($_POST['status'] ?? 'draft');
$postId = intval($_POST['post_id'] ?? 0);

if (!$title || !$content) {
    http_response_code(400);
    echo json_encode(['error' => 'Title and content required']);
    exit;
}

// Generate slug
function slugify($str) {
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9]+/', '-', $str);
    return trim($str, '-');
}

$baseSlug = slugify($title);
$slug = $baseSlug;
$attempt = 1;

// Ensure unique slug
while (true) {
    $stmt = $pdo->prepare("SELECT id FROM posts WHERE slug = ? AND id != ?");
    $stmt->execute([$slug, $postId]);
    if (!$stmt->fetchColumn()) break;
    $slug = $baseSlug . '-' . (++$attempt);
}

try {
    if ($postId) {
        // Update existing post
        $stmt = $pdo->prepare("
            UPDATE posts 
            SET title = ?, slug = ?, content = ?, category = ?, tags = ?, excerpt = ?, 
                cover_image = ?, status = ?, updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$title, $slug, $content, $category, $tags, $excerpt, $coverImage, $status, $postId, $userId]);
        echo json_encode(['success' => true, 'id' => $postId, 'slug' => $slug]);
    } else {
        // Create new post
        $stmt = $pdo->prepare("
            INSERT INTO posts (title, slug, content, category, tags, excerpt, cover_image, status, user_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$title, $slug, $content, $category, $tags, $excerpt, $coverImage, $status, $userId]);
        $newId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newId, 'slug' => $slug]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
