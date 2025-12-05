<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Read JSON body
$body = json_decode(file_get_contents('php://input'), true);
if (!$body || empty(trim($body['title'] ?? ''))) {
    echo json_encode(['success' => false, 'error' => 'Title required']);
    exit;
}

$title = trim($body['title']);
$body_text = trim($body['body'] ?? trim($body['message'] ?? trim($body['summary'] ?? '')));

try {
    $stmt = $pdo->prepare("INSERT INTO proclamations (user_id, title, body) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $title, $body_text]);

    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    error_log('proclamations_create error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
