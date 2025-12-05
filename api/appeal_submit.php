<?php
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

session_start();
$uid = $_SESSION['user_id'] ?? null;

if (!$uid) {
  http_response_code(401);
  exit;
}

$sanction_id = intval($_POST['sanction_id'] ?? 0);
$message = $_POST['message'] ?? '';

if (!$sanction_id || !$message) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid']);
  exit;
}

$stmt = $pdo->prepare("INSERT INTO appeals (sanction_id, user_id, message, status) VALUES (?, ?, ?, 'open')");
$stmt->execute([$sanction_id, $uid, $message]);
$appeal_id = $pdo->lastInsertId();

// Notify admins
$admins = $pdo->query("SELECT id FROM users WHERE role IN ('admin', 'super_admin')")->fetchAll(PDO::FETCH_COLUMN);
$notif = $pdo->prepare("INSERT INTO notifications (user_id, actor_id, type, title, body, url) VALUES (?, ?, 'appeal', ?, ?, ?)");

foreach ($admins as $admin_id) {
  $notif->execute([$admin_id, $uid, "New Appeal #$appeal_id", substr($message, 0, 200), "/admin/appeals.php?id=$appeal_id"]);
}

echo json_encode(['ok' => true, 'appeal_id' => $appeal_id]);
?>
