<?php
// admin/donate.php - Payment initialization with Paystack
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/inc/config.php';
$config = include __DIR__ . '/inc/config.php';
$paystack = $config['paystack'];
require_once __DIR__ . '/inc/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: donations.php');
  exit;
}

$donor_name = trim($_POST['donor_name'] ?? '');
$donor_email = trim($_POST['donor_email'] ?? '');
$amount = floatval($_POST['amount'] ?? 0);

if (!$donor_name || !$donor_email || $amount <= 0) {
  header('Location: donations.php?error=invalid');
  exit;
}

$amount_pesewas = intval($amount * 100);

// Initialize Paystack transaction
$curl = curl_init();
curl_setopt_array($curl, [
  CURLOPT_URL => "https://api.paystack.co/transaction/initialize",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => json_encode([
    'email' => $donor_email,
    'amount' => $amount_pesewas,
    'callback_url' => $paystack['callback'],
    'metadata' => [
      'donor_name' => $donor_name,
      'donor_email' => $donor_email
    ]
  ]),
  CURLOPT_HTTPHEADER => [
    "Authorization: Bearer {$paystack['secret']}",
    "Content-Type: application/json"
  ],
]);
$response = curl_exec($curl);
curl_close($curl);

$res = json_decode($response, true);

if (!$res || !isset($res['status']) || !$res['status']) {
  header('Location: donations.php?error=init_failed');
  exit;
}

$auth = $res['data'];

// Record the donation as initialized
try {
  $stmt = $pdo->prepare("
    INSERT INTO donations (donor_name, donor_email, amount, reference, status, created_at) 
    VALUES (?, ?, ?, ?, 'initialized', NOW())
  ");
  $stmt->execute([$donor_name, $donor_email, $amount, $auth['reference']]);
} catch (Exception $e) {
  error_log("Failed to record donation: " . $e->getMessage());
}

// Redirect to Paystack
header('Location: ' . $auth['authorization_url']);
exit;
?>
