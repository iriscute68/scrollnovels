<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

requireLogin();
if (!isApprovedAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf($csrf)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$title = $_POST['title'] ?? '';
$slug = $_POST['slug'] ?? null;
$content = $_POST['content'] ?? '';
$link = $_POST['link'] ?? null;
$id = isset($_POST['id']) ? intval($_POST['id']) : null;

if (!$title || !$content) {
    http_response_code(400);
    echo json_encode(['error' => 'title and content required']);
    exit;
}

if ($id) {
    $stmt = $pdo->prepare("UPDATE announcements SET title = ?, slug = ?, content = ?, link = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$title, $slug, $content, $link, $id]);
    echo json_encode(['status' => 'updated']);
    exit;
} else {
    $stmt = $pdo->prepare("INSERT INTO announcements (title, slug, content, link) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $slug, $content, $link]);
    echo json_encode(['status' => 'created', 'id' => $pdo->lastInsertId()]);
    exit;
}
