<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

require_admin();

$comp_id = intval($_POST['comp_id'] ?? 0);
$judge_id = intval($_POST['judge_id'] ?? 0);

if (!$comp_id || !$judge_id) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing comp_id or judge_id']);
  exit;
}

// Check if judge already assigned
$stmt = $pdo->prepare("SELECT id FROM competition_judges WHERE competition_id = ? AND user_id = ?");
$stmt->execute([$comp_id, $judge_id]);

if (!$stmt->fetch()) {
  $stmt = $pdo->prepare("INSERT INTO competition_judges (competition_id, user_id) VALUES (?, ?)");
  $stmt->execute([$comp_id, $judge_id]);
}

echo json_encode(['ok' => true]);
?>
