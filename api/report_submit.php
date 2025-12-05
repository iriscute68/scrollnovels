<?php
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

session_start();
$uid = $_SESSION['user_id'] ?? null;

if (!$uid) {
  http_response_code(401);
  echo json_encode(['error' => 'Not authenticated']);
  exit;
}

$target_type = $_POST['target_type'] ?? '';
$target_id = intval($_POST['target_id'] ?? 0);
$reason_code = $_POST['reason_code'] ?? '';
$details = $_POST['details'] ?? '';

if (!$target_type || !$target_id) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing parameters']);
  exit;
}

$stmt = $pdo->prepare("INSERT INTO reports (reporter_id, target_type, target_id, reason_code, details, status, priority) VALUES (?, ?, ?, ?, ?, 'open', 2)");
$stmt->execute([$uid, $target_type, $target_id, $reason_code, $details]);

$report_id = $pdo->lastInsertId();

// Notify moderators
$admins = $pdo->query("SELECT id FROM users WHERE role IN ('moderator', 'admin', 'super_admin')")->fetchAll(PDO::FETCH_COLUMN);
$notif = $pdo->prepare("INSERT INTO notifications (user_id, actor_id, type, title, body, url, is_important) VALUES (?, ?, 'report', ?, ?, ?, 1)");

foreach ($admins as $admin_id) {
  $notif->execute([$admin_id, $uid, "New Report #$report_id", $reason_code, "/admin/moderation.php?id=$report_id"]);
}

echo json_encode(['ok' => true, 'report_id' => $report_id]);
?>
