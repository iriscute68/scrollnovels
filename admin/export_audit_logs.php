<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

require_admin();

$limit = intval($_GET['limit'] ?? 50);

$stmt = $pdo->prepare("SELECT aal.*, u.username FROM admin_action_logs aal 
  LEFT JOIN users u ON u.id = aal.actor_id
  ORDER BY aal.created_at DESC LIMIT ?");
$stmt->execute([$limit]);
$logs = $stmt->fetchAll();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="admin_logs_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['actor', 'action', 'target_type', 'target_id', 'data', 'timestamp']);

foreach ($logs as $log) {
  fputcsv($out, [
    $log['username'] ?? 'System',
    $log['action_type'],
    $log['target_type'],
    $log['target_id'],
    $log['data'],
    $log['created_at']
  ]);
}

fclose($out);
?>
