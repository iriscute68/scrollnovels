<?php
// /admin/ajax/get_users.php - User listing
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';

if (!isApprovedAdmin()) { http_response_code(403); exit(json_encode(['error' => 'Forbidden'])); }

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = max(10, min(100, intval($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $per_page;

try {
  global $pdo;

  $where = [];
  $params = [];

  if ($q !== '') {
    $where[] = "(username LIKE ? OR email LIKE ?)";
    $like = "%$q%";
    $params[] = $like;
    $params[] = $like;
  }

  $where_sql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

  $total_stmt = $pdo->prepare("SELECT COUNT(*) FROM users $where_sql");
  $total_stmt->execute($params);
  $total = intval($total_stmt->fetchColumn());

  $sql = "SELECT id, username, email, JSON_EXTRACT(roles, '$[0]') as role, status, created_at, last_login
          FROM users
          $where_sql
          ORDER BY id DESC
          LIMIT $per_page OFFSET $offset";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    'users' => $rows,
    'total' => $total,
    'page' => $page,
    'per_page' => $per_page
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
