<?php
// /admin/ajax/delete_tag.php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';
session_start();

if (!isApprovedAdmin()) { http_response_code(403); exit(json_encode(['ok' => false, 'message' => 'Forbidden'])); }

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
$id = intval($body['id'] ?? 0);

if (!$id) { echo json_encode(['ok' => false, 'message' => 'Missing ID']); exit; }

try {
  global $pdo;
  $pdo->prepare("DELETE FROM story_tags WHERE tag_id = ?")->execute([$id]);
  $pdo->prepare("DELETE FROM tags WHERE id = ?")->execute([$id]);
  
  echo json_encode(['ok' => true, 'message' => 'Tag deleted']);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
