<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();

$entry_id = intval($_POST['entry_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$entry_id || !$action) {
  header('Location: ' . $_SERVER['HTTP_REFERER']);
  exit;
}

if (!in_array($action, ['approve','disqualify'])) {
  header('Location: ' . $_SERVER['HTTP_REFERER']);
  exit;
}

$status = $action === 'approve' ? 'approved' : 'disqualified';
$u = $pdo->prepare("UPDATE competition_entries SET status = ? WHERE id = ?");
$u->execute([$status, $entry_id]);

// Log
$log = $pdo->prepare("INSERT INTO admin_activity (admin_id, action, meta) VALUES (?, ?, ?)");
$log->execute([current_user_id(), 'entry_'.$action, json_encode(['entry_id'=>$entry_id])]);

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
