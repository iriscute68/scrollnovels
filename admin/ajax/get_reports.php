<?php
// /admin/ajax/get_reports.php - List moderation reports
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

if (!in_array($_SESSION['role'] ?? '', ['admin','super_admin','moderator'])) {
    http_response_code(403);
    exit(json_encode(['reports'=>[], 'error' => 'Forbidden']));
}

header('Content-Type: application/json');
session_start();

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$type = trim($_GET['type'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = max(10, min(100, intval($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $per_page;

try {
  $where = [];
  $params = [];

  if ($q !== '') {
    $where[] = "(r.reason LIKE ?)";
    $like = "%$q%";
    $params[] = $like;
  }

  if ($status !== '') {
    $where[] = "r.status = ?";
    $params[] = $status;
  }

  if ($type !== '') {
    $where[] = "r.type = ?";
    $params[] = $type;
  }

  $where_sql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

  // Count total
  $total_stmt = $pdo->prepare("SELECT COUNT(*) FROM reports r $where_sql");
  $total_stmt->execute($params);
  $total = intval($total_stmt->fetchColumn());

  // Fetch reports with user info
  $sql = "SELECT r.*, u.username AS reporter_name FROM reports r 
          LEFT JOIN users u ON u.id = r.reporter_id 
          $where_sql 
          ORDER BY r.created_at DESC 
          LIMIT ? OFFSET ?";
  $params[] = $per_page;
  $params[] = $offset;
  
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    'reports' => $reports,
    'total' => $total,
    'page' => $page,
    'per_page' => $per_page
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['reports'=>[], 'error' => $e->getMessage()]);
}
?>
