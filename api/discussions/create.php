<?php
// api/discussions/create.php - New thread (merged; PDO insert, auth)
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$category = trim($_POST['category'] ?? 'general');

if (empty($title) || empty($content)) {
    http_response_code(400);
    echo json_encode(['error' => 'Title and content required']);
    exit;
}

try {
    $slug = slugify($title);
    // Check dup slug
    $check = $pdo->prepare('SELECT id FROM discussions WHERE slug = ?');
    $check->execute([$slug]);
    if ($check->fetch()) $slug .= '-' . time();

    $stmt = $pdo->prepare('INSERT INTO discussions (title, slug, content, category, author_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$title, $slug, $content, $category, $_SESSION['user_id']]);
    $id = $pdo->lastInsertId();

    echo json_encode(['ok' => true, 'id' => $id, 'slug' => $slug]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Create failed']);
}
?>