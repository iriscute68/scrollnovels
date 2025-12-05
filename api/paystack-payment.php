<?php
// api/paystack-payment.php
header('Content-Type: application/json');
session_start();
require_once dirname(__DIR__) . '/config/db.php';

$reference = $_GET['reference'] ?? null;
if (!$reference) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'No reference provided']);
  exit;
}

// Paystack keys
$paystack_secret = 'sk_live_YOUR_SECRET_KEY_HERE';

// Verify with Paystack
$curl = curl_init();
curl_setopt_array($curl, [
  CURLOPT_URL => "https://api.paystack.co/transaction/verify/{$reference}",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    "Authorization: Bearer {$paystack_secret}",
    "Content-Type: application/json"
  ],
]);
$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($http_code !== 200) {
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => 'Verification failed']);
  exit;
}

$res = json_decode($response, true);

if (!$res || !isset($res['status']) || !$res['status']) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'Payment not successful']);
  exit;
}

$data = $res['data'];
$trans_status = $data['status']; // "success" or "failed"
$amount = $data['amount'] / 100;
$email = $data['customer']['email'] ?? 'unknown';
$donor_name = $data['metadata']['donor_name'] ?? 'Anonymous';

try {
  // Get input
  $input = json_decode(file_get_contents('php://input'), true) ?? [];
  $donor_name = $input['donor_name'] ?? $donor_name;
  $donor_email = $input['donor_email'] ?? $email;

  // Check if donation already exists
  $stmt = $pdo->prepare("SELECT id FROM donations WHERE reference = ?");
  $stmt->execute([$reference]);
  $existing = $stmt->fetch();

  if ($existing) {
    echo json_encode(['status' => 'success', 'message' => 'Donation already recorded']);
    exit;
  }

  // Record the donation
  $stmt = $pdo->prepare("
    INSERT INTO donations (donor_name, donor_email, amount, reference, status, created_at) 
    VALUES (?, ?, ?, ?, ?, NOW())
  ");
  $stmt->execute([$donor_name, $donor_email, $amount, $reference, $trans_status]);

  // Calculate points (1 point per GHS)
  $points = intval($amount);
  
  if ($trans_status === 'success') {
    // Try to find user and add points
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$donor_email]);
    $user = $stmt->fetch();
    
    if ($user) {
      // Update points if table exists
      try {
        $stmt = $pdo->prepare("UPDATE users SET coins = IFNULL(coins, 0) + ? WHERE id = ?");
        $stmt->execute([$points, $user['id']]);
      } catch (Exception $e) {
        // coins column might not exist, ignore
      }
    }
  }

  echo json_encode(['status' => 'success', 'message' => 'Payment recorded', 'amount' => $amount]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
