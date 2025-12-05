<?php
// config/paystack.php - Paystack Payment Configuration

// Paystack API Keys
define('PAYSTACK_PUBLIC_KEY', 'pk_live_YOUR_PUBLIC_KEY_HERE');
define('PAYSTACK_SECRET_KEY', 'sk_live_YOUR_SECRET_KEY_HERE');

// Paystack Subaccounts
define('PAYSTACK_AUTHOR_SUBACCOUNT', 'ACCT_jd64gj8rt1qu967');        // Author donations
define('PAYSTACK_WEBSITE_SUBACCOUNT', 'ACCT_txt5akppkllhdik');      // Website donations

// Paystack Subaccount Splits (percentage)
// User requirement: 80% to author, 20% to platform
define('PAYSTACK_AUTHOR_SPLIT', 80);    // 80% goes to author
define('PAYSTACK_WEBSITE_SPLIT', 20);   // 20% goes to website

// Verify donation on Paystack
function verifyPaystackPayment($reference) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer " . PAYSTACK_SECRET_KEY
        ),
    ));
    
    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        return ['status' => false, 'error' => $error];
    }
    
    return json_decode($response, true);
}

// Initialize Paystack payment
function initializePaystackPayment($email, $amount, $metadata = []) {
    $curl = curl_init();
    
    $data = [
        'email' => $email,
        'amount' => $amount * 100, // Convert to kobo
        'metadata' => $metadata
    ];
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.paystack.co/transaction/initialize",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
            "Content-Type: application/x-www-form-urlencoded",
        ),
    ));
    
    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        return ['status' => false, 'error' => $error];
    }
    
    return json_decode($response, true);
}

// Calculate transaction split
function calculatePaystackSplit($amount, $isAuthorDonation = true) {
    if ($isAuthorDonation) {
        return [
            'author_amount' => round($amount * (PAYSTACK_AUTHOR_SPLIT / 100), 2),
            'website_amount' => round($amount * (PAYSTACK_WEBSITE_SPLIT / 100), 2),
        ];
    }
    return [
        'website_amount' => $amount,
        'author_amount' => 0,
    ];
}
?>
