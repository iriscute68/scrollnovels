<?php
// ... inside the capture success block
if ($httpcode === 201) {
    $data = json_decode($response, true);
    $amount = $data['purchase_units'][0]['payments']['captures'][0]['amount']['value'];

    $stmt = $pdo->prepare("INSERT INTO donations (user_id, amount, method, status) VALUES (?, ?, 'paypal', 'completed')");
    $stmt->execute([$_SESSION['user_id'], $amount]);

    // ADD NOTIFICATION TO USER
    require_once '../includes/functions.php';  // ADD THIS
    notify(
        $pdo,
        $_SESSION['user_id'],
        null,
        'donation',
        "Thank you for donating $$amount!",
        "/donate.php"
    );

    // Optional: Notify admin
    $admin_id = 1; // or fetch admin
    notify($pdo, $admin_id, $_SESSION['user_id'], 'donation', "New donation: $$amount from user", rtrim(SITE_URL, '/') . '/admin/admin.php#donations');

    echo json_encode(['success' => true]);
}
?>