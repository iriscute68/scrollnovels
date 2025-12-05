<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

require_admin();

header('Content-Type: application/json');

$comp_id = intval($_POST['comp_id'] ?? 0);
$entry_id = intval($_POST['entry_id'] ?? 0);
$user_id = intval($_POST['user_id'] ?? 0);
$amount = floatval($_POST['amount'] ?? 0);

if (!$comp_id || !$entry_id || !$user_id || $amount <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid parameters']);
  exit;
}

// Create payout record
$stmt = $pdo->prepare("INSERT INTO competition_payouts (competition_id, entry_id, user_id, amount, status) VALUES (?, ?, ?, ?, 'pending')");
$stmt->execute([$comp_id, $entry_id, $user_id, $amount]);

echo json_encode(['ok' => true, 'payout_id' => $pdo->lastInsertId()]);
?>
