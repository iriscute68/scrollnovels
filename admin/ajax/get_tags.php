<?php
// /admin/ajax/get_tags.php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';

if (!isApprovedAdmin()) { http_response_code(403); exit(json_encode(['error' => 'Forbidden'])); }

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');

try {
  global $pdo;

  $where = $q ? "WHERE name LIKE ?" : "";
  $params = $q ? ["%$q%"] : [];

  $sql = "SELECT id, name, slug
          FROM tags
          $where
          ORDER BY name ASC
          LIMIT 1000";

  if ($q) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
  } else {
    $stmt = $pdo->query($sql);
  }

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Add count for each tag
  foreach ($rows as &$r) {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM story_tags WHERE tag_id = ?");
    $count_stmt->execute([$r['id']]);
    $r['count'] = intval($count_stmt->fetchColumn());
  }

  echo json_encode(['tags' => $rows]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
