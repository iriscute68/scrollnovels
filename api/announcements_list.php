<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../inc/db.php';

$profileUserId = intval($_GET['user_id'] ?? 0);
$limit = intval($_GET['limit'] ?? 100);
if ($limit <= 0 || $limit > 500) $limit = 100;

try {
  if ($profileUserId) {
    $stmt = $pdo->prepare("SELECT id, title, summary, message, author_id, created_at, is_active FROM announcements WHERE author_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bindValue(1, $profileUserId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
  } else {
    $stmt = $pdo->prepare("SELECT id, title, summary, message, author_id, created_at, is_active FROM announcements ORDER BY created_at DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
  }

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['success' => true, 'announcements' => $rows]);
} catch (Exception $e) {
  http_response_code(500);
  error_log('announcements_list error: ' . $e->getMessage());
  echo json_encode(['success' => false, 'error' => 'server_error']);
}
