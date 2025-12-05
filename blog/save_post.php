<?php
// blog/save_post.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'POST only']);
  exit;
}

$userId = current_user_id();
if (!$userId) {
  http_response_code(401);
  echo json_encode(['error' => 'Login required']);
  exit;
}

$title = trim($_POST['title'] ?? '');
$category = $_POST['category'] ?? 'Update';
$tags = trim($_POST['tags'] ?? '');
$excerpt = trim($_POST['excerpt'] ?? '');
$cover = trim($_POST['cover_image'] ?? '');
$status = $_POST['status'] ?? 'draft';
$blocks = $_POST['blocks'] ?? null;
$post_id = intval($_POST['post_id'] ?? 0);

if (!$title || !$blocks) {
  http_response_code(400);
  echo json_encode(['error' => 'Title and blocks required']);
  exit;
}

// validate JSON
$blocksDecoded = json_decode($blocks, true);
if ($blocksDecoded === null) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid blocks JSON']);
  exit;
}

// create slug (ensure uniqueness)
$slug = slugify($title);
$baseSlug = $slug;
$attempt = 1;
while (true) {
  $q = $pdo->prepare("SELECT id FROM posts WHERE slug = ? " . ($post_id ? " AND id != ? " : ""));
  $params = $post_id ? [$slug, $post_id] : [$slug];
  $q->execute($params);
  if ($q->fetch()) {
    $slug = $baseSlug . '-' . $attempt++;
  } else {
    break;
  }
}

if ($post_id) {
  $stmt = $pdo->prepare("UPDATE posts SET title=?, slug=?, category=?, tags=?, excerpt=?, cover_image=?, status=?, blocks=?, updated_at=NOW(), published_at = CASE WHEN status='published' THEN NOW() ELSE published_at END WHERE id = ? AND user_id = ?");
  $stmt->execute([$title, $slug, $category, $tags, $excerpt, $cover, $status, json_encode($blocksDecoded), $post_id, $userId]);
  echo json_encode(['success' => true, 'post_id' => $post_id, 'slug' => $slug]);
  exit;
} else {
  $stmt = $pdo->prepare("INSERT INTO posts (user_id, title, slug, category, tags, excerpt, cover_image, status, blocks, created_at, updated_at, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), CASE WHEN ?='published' THEN NOW() ELSE NULL END)");
  $stmt->execute([$userId, $title, $slug, $category, $tags, $excerpt, $cover, $status, json_encode($blocksDecoded), $status]);
  $newId = $pdo->lastInsertId();
  echo json_encode(['success' => true, 'post_id' => $newId, 'slug' => $slug]);
  exit;
}
