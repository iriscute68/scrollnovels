<?php
// /admin/ajax/ban_user.php - Ban/suspend user
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';
session_start();

if (!isApprovedAdmin()) { http_response_code(403); exit(json_encode(['ok' => false, 'message' => 'Forbidden'])); }

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
$id = intval($body['id'] ?? 0);
$action = $body['action'] ?? 'ban';

if (!$id) { echo json_encode(['ok' => false, 'message' => 'Missing user ID']); exit; }

try {
  global $pdo;

  if ($action === 'suspend') {
    $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?")->execute([$id]);
    $msg = 'User suspended';
  } elseif ($action === 'ban') {
    $pdo->prepare("UPDATE users SET status = 'banned' WHERE id = ?")->execute([$id]);
    $msg = 'User banned';
  } elseif ($action === 'unban') {
    $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$id]);
    $msg = 'User unbanned';
  } else {
    echo json_encode(['ok' => false, 'message' => 'Unknown action']);
    exit;
  }

  echo json_encode(['ok' => true, 'message' => $msg]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
