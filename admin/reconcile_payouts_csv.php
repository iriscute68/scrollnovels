<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

require_admin();

// Paystack reconciliation report
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="payout_reconciliation_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['payout_id', 'user_id', 'username', 'amount', 'reference', 'local_status', 'updated_at']);

$stmt = $pdo->prepare("SELECT cp.id, cp.user_id, u.username, cp.amount, cp.reference, cp.status, cp.updated_at
  FROM competition_payouts cp
  JOIN users u ON u.id = cp.user_id
  WHERE cp.status IN ('processing', 'completed', 'failed')
  ORDER BY cp.updated_at DESC");

$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  fputcsv($out, [
    $row['id'],
    $row['user_id'],
    $row['username'],
    $row['amount'],
    $row['reference'],
    $row['status'],
    $row['updated_at']
  ]);
}

fclose($out);
?>
