<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Check admin auth - only admins can create announcements
if (!isLoggedIn() || !isRole('admin')) {
    echo json_encode(['success' => false, 'error' => 'Only admins can create announcements']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['title'])) {
    echo json_encode(['success' => false, 'error' => 'Title required']);
    exit;
}

try {
    // detect optional columns to avoid failing on older schemas
    $cols = [];
    $res = $pdo->query("SHOW COLUMNS FROM announcements")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($res as $c) $cols[] = $c;

    $fields = ['title', 'summary', 'message', 'author_id', 'is_active', 'created_at', 'updated_at'];
    $placeholders = ['?', '?', '?', '?', '1', 'NOW()', 'NOW()'];
    $values = [$data['title'], $data['summary'] ?? '', $data['message'] ?? $data['summary'] ?? '', $_SESSION['user_id']];

    // optional image_url and link
    if (in_array('image_url', $cols) && !empty($data['image_url'])) {
        // insert before created_at
        array_splice($fields, 3, 0, 'image_url');
        array_splice($placeholders, 3, 0, '?');
        array_splice($values, 3, 0, $data['image_url']);
    }
    if (in_array('link', $cols) && !empty($data['link'])) {
        array_splice($fields, 4, 0, 'link');
        array_splice($placeholders, 4, 0, '?');
        array_splice($values, 4, 0, $data['link']);
    }

    $sql = "INSERT INTO announcements (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    error_log('announcements_create error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
