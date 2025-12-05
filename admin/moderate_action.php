<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

require_admin();

header('Content-Type: application/json');

$uid = current_user_id();
$report_id = intval($_POST['report_id'] ?? 0);
$action = $_POST['action'] ?? '';
$reason = $_POST['reason'] ?? '';
$duration_hours = intval($_POST['duration_hours'] ?? 72);

$stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ?");
$stmt->execute([$report_id]);
$report = $stmt->fetch();

if (!$report) {
  http_response_code(404);
  echo json_encode(['error' => 'Report not found']);
  exit;
}

$target_type = $report['target_type'];
$target_id = $report['target_id'];

switch ($action) {
  case 'dismiss':
    $pdo->prepare("UPDATE reports SET status='dismissed', assigned_moderator=?, updated_at=NOW() WHERE id=?")
        ->execute([$uid, $report_id]);
    break;

  case 'soft_delete':
    $pdo->prepare("UPDATE " . htmlspecialchars($target_type) . " SET is_deleted=1, deleted_at=NOW() WHERE id=?")
        ->execute([$target_id]);
    $pdo->prepare("UPDATE reports SET status='resolved', assigned_moderator=?, updated_at=NOW() WHERE id=?")
        ->execute([$uid, $report_id]);
    break;

  case 'suspend':
    $endAt = date('Y-m-d H:i:s', time() + $duration_hours * 3600);
    $pdo->prepare("INSERT INTO sanctions (user_id, actor_id, type, reason, metadata, start_at, end_at, active) VALUES (?, ?, 'suspension', ?, ?, NOW(), ?, 1)")
        ->execute([$target_id, $uid, $reason, json_encode(['report_id' => $report_id]), $endAt]);
    $pdo->prepare("UPDATE reports SET status='resolved', assigned_moderator=?, updated_at=NOW() WHERE id=?")
        ->execute([$uid, $report_id]);
    break;

  default:
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

$pdo->prepare("INSERT INTO admin_action_logs (actor_id, action_type, target_type, target_id, data) VALUES (?, ?, ?, ?, ?)")
    ->execute([$uid, $action, $target_type, $target_id, json_encode(['report_id' => $report_id, 'reason' => $reason])]);

echo json_encode(['ok' => true]);
?>
