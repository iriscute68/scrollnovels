<?php
// ipn.php
require_once dirname(__DIR__) . '/config/db.php';

// Read POST data
$req = 'cmd=_notify-validate';
foreach ($_POST as $key => $value) {
    $value = urlencode(stripslashes($value));
    $req .= "&$key=$value";
}

// Post back to PayPal
$ch = curl_init('https://ipnpb.paypal.com/cgi-bin/webscr');
curl_setopt_array($ch, [
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_POST => 1,
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_POSTFIELDS => $req,
    CURLOPT_SSL_VERIFYPEER => 1,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HTTPHEADER => ['Connection: Close']
]);
$res = curl_exec($ch);
curl_close($ch);

if (strcmp($res, "VERIFIED") == 0) {
    $payment_status = $_POST['payment_status'];
    $txn_id = $_POST['txn_id'];
    $amount = $_POST['mc_gross'];
    $payer_email = $_POST['payer_email'];

    if ($payment_status == 'Completed') {
        // Find pending donation
        $stmt = $pdo->prepare("UPDATE donations SET status = 'completed', method = 'paypal' WHERE txn_id = ? AND status = 'pending'");
        $stmt->execute([$txn_id]);

        if ($stmt->rowCount() == 0) {
            // Insert new
            $pdo->prepare("INSERT INTO donations (user_id, amount, method, status) VALUES ((SELECT id FROM users WHERE email = ?), ?, 'paypal', 'completed')")
                ->execute([$payer_email, $amount]);
        }
    }
}
?>
