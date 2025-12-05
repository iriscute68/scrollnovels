<?php
// admin/paystack_callback.php
session_start();
require_once __DIR__ . '/inc/config.php';
$config = include __DIR__ . '/inc/config.php';
$paystack = $config['paystack'];
require_once __DIR__ . '/inc/db.php';

$reference = $_GET['reference'] ?? null;
if (!$reference) {
  die("No reference provided");
}

// Verify payment with Paystack
$curl = curl_init();
curl_setopt_array($curl, [
  CURLOPT_URL => "https://api.paystack.co/transaction/verify/{$reference}",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    "Authorization: Bearer {$paystack['secret']}",
    "Content-Type: application/json"
  ],
]);
$response = curl_exec($curl);
curl_close($curl);

$res = json_decode($response, true);

if ($res && isset($res['status']) && $res['status']) {
  $data = $res['data'];
  $status = $data['status']; // "success" or "failed"
  $amount = $data['amount'] / 100;
  $email = $data['customer']['email'] ?? $data['metadata']['donor_email'] ?? 'unknown';
  $donor_name = $data['metadata']['donor_name'] ?? 'Unknown';

  // Update donation record
  try {
    $stmt = $pdo->prepare("UPDATE donations SET status=?, amount=?, donor_name=?, donor_email=? WHERE reference=?");
    $stmt->execute([$status, $amount, $donor_name, $email, $reference]);

    // Log the transaction
    $stmt = $pdo->prepare("
      INSERT INTO activity_logs (admin_id, action, details, created_at) 
      VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([
      $_SESSION['admin_id'] ?? null,
      $status === 'success' ? 'Donation Received' : 'Donation Failed',
      "Ref: $reference, Amount: GHS $amount, Donor: $email"
    ]);
  } catch (Exception $e) {
    error_log("Failed to update donation: " . $e->getMessage());
  }

  ?>
  <!DOCTYPE html>
  <html>
  <head>
    <title>Payment <?= ucfirst($status) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
  </head>
  <body class="flex items-center justify-center min-h-screen" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="bg-white rounded-lg p-8 max-w-md text-center">
      <?php if($status === 'success'): ?>
        <div class="text-4xl mb-4">✅</div>
        <h2 class="text-2xl font-bold text-green-600 mb-2">Payment Successful!</h2>
        <p class="text-gray-700 mb-4">Thank you for your generous donation of <strong>GHS <?= number_format($amount, 2) ?></strong></p>
        <p class="text-gray-600 text-sm mb-4">Reference: <code><?= htmlspecialchars($reference) ?></code></p>
      <?php else: ?>
        <div class="text-4xl mb-4">❌</div>
        <h2 class="text-2xl font-bold text-red-600 mb-2">Payment Failed</h2>
        <p class="text-gray-700 mb-4">Unfortunately, your payment could not be processed.</p>
        <p class="text-gray-600 text-sm mb-4">Reference: <code><?= htmlspecialchars($reference) ?></code></p>
      <?php endif; ?>
      <a href="/admin/donations.php" class="inline-block px-6 py-2 bg-purple-600 text-white rounded-lg mt-4">Back to Donations</a>
    </div>
  </body>
  </html>
  <?php
} else {
  ?>
  <!DOCTYPE html>
  <html>
  <head>
    <title>Verification Failed</title>
    <script src="https://cdn.tailwindcss.com"></script>
  </head>
  <body class="flex items-center justify-center min-h-screen" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="bg-white rounded-lg p-8 max-w-md text-center">
      <div class="text-4xl mb-4">⚠️</div>
      <h2 class="text-2xl font-bold text-gray-800 mb-2">Verification Failed</h2>
      <p class="text-gray-700 mb-4">Unable to verify the payment. Please contact support.</p>
      <a href="/admin/donations.php" class="inline-block px-6 py-2 bg-purple-600 text-white rounded-lg">Back to Donations</a>
    </div>
  </body>
  </html>
  <?php
}
?>
