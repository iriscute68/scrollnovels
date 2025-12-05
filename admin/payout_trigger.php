<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../api/paystack_helper.php';

require_admin();

header('Content-Type: application/json');

$comp_id = intval($_POST['comp_id'] ?? 0);

// Get all pending payouts for this competition
$stmt = $pdo->prepare("SELECT cp.*, u.email FROM competition_payouts cp 
  JOIN users u ON u.id = cp.user_id
  WHERE cp.competition_id = ? AND cp.status = 'pending'");
$stmt->execute([$comp_id]);
$payouts = $stmt->fetchAll();

$successful = 0;
$failed = 0;

foreach ($payouts as $payout) {
  // Ensure recipient code exists
  $rc = $pdo->prepare("SELECT recipient_code FROM paystack_recipients WHERE user_id = ?");
  $rc->execute([$payout['user_id']]);
  if (!$rcRow = $rc->fetch()) {
    $failed++;
    continue;
  }

  $recipient_code = $rcRow['recipient_code'];
  $amount_kobo = intval($payout['amount'] * 100);

  $transfer_data = [
    'source' => 'balance',
    'amount' => $amount_kobo,
    'recipient' => $recipient_code,
    'reason' => 'Competition prize - Entry #' . $payout['entry_id'],
    'reference' => 'COMP-PAY-' . uniqid()
  ];

  $res = paystack_api_request('transfer', $transfer_data);
  if ($res && isset($res['status']) && $res['status'] == true) {
    $pdo->prepare("UPDATE competition_payouts SET status='processing', reference=?, updated_at=NOW() WHERE id=?")
        ->execute([$res['data']['reference'], $payout['id']]);
    $successful++;
  } else {
    $failed++;
  }
}

echo json_encode(['ok' => true, 'message' => "Initiated: $successful successful, $failed failed"]);
?>
