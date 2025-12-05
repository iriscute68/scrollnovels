<?php
/**
 * Add Card - Opens Paystack popup to save a card
 */
require_once __DIR__ . '/config.php';

$userEmail = getCurrentUserEmail();
if (!$userEmail) {
    die('Unable to retrieve user email');
}
?>
<?php
    $page_title = 'Add Card - Scroll Novels';
    $page_head = '';
    require_once __DIR__ . '/../../includes/header.php';
?>

    <div class="max-w-3xl mx-auto p-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h1 class="text-3xl font-bold mb-4 text-emerald-600">Add a Payment Card</h1>
            <p class="text-gray-600 mb-6">Securely add a card to your account using Paystack. Your card details are encrypted and never stored on our servers.</p>

            <div class="mb-6">
                <button id="addCardBtn" class="px-6 py-3 bg-emerald-600 text-white font-semibold rounded-lg hover:bg-emerald-700 transition">
                    Click to Add Card
                </button>
            </div>

            <p class="text-sm text-gray-500">You'll be redirected to Paystack's secure payment page. A small verification amount (e.g., GHS 0.01) will be used to validate your card and will be refunded.</p>

            <hr class="my-6">

            <div class="mt-6">
                <a href="<?= site_url('/pages/cards/list_cards.php') ?>" class="text-emerald-600 hover:underline">← Back to My Cards</a>
            </div>
        </div>
    </div>

    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
    document.getElementById('addCardBtn').addEventListener('click', function() {
        var handler = PaystackPop.setup({
            key: '<?php echo htmlspecialchars($PAYSTACK_PUBLIC, ENT_QUOTES); ?>',
            email: '<?php echo htmlspecialchars($userEmail, ENT_QUOTES); ?>',
            amount: 50, // GHS 0.50 - minimal amount for verification
            currency: 'GHS',
            channels: ['card'],
            metadata: {
                action: 'save_card',
                user_id: <?php echo $_SESSION['user_id']; ?>
            },
            callback: function(response) {
                // Send reference to backend for verification and storage
                const form = new FormData();
                form.append('reference', response.reference);
                form.append('_csrf', '<?php echo csrf_token(); ?>');

                fetch('<?= site_url('/pages/cards/save_card.php') ?>', {
                    method: 'POST',
                    body: form
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('✓ Card saved successfully!');
                        window.location.href = '<?= site_url('/pages/cards/list_cards.php') ?>';
                    } else {
                        alert('✗ Error: ' + (data.message || 'Failed to save card'));
                    }
                })
                .catch(err => {
                    alert('✗ Error: ' + err.message);
                });
            },
            onClose: function() {
                console.log('Paystack popup closed');
            }
        });
        handler.openIframe();
    });
    </script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
