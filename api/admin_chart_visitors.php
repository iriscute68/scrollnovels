<?php
// api/admin_chart_visitors.php
header('Content-Type: application/json');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();

// Example: total visits last 30 days from book_stats or visits table
$stmt = $pdo->query("SELECT DATE(recorded_at) AS day, SUM(views) as views FROM book_stats WHERE recorded_at > DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(recorded_at) ORDER BY day ASC");
$data = $stmt->fetchAll();

$labels = [];
$values = [];
foreach ($data as $row) {
  $labels[] = $row['day'];
  $values[] = (int)$row['views'];
}

echo json_encode(['labels' => $labels, 'values' => $values, 'total' => array_sum($values)]);
