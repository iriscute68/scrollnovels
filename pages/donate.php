<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/paystack.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
$bookId = (int)($_GET['book_id'] ?? 0);
$user = null;
$donations = [];
$topDonors = [];

if (!$isLoggedIn) {
    header('Location: ' . site_url('/pages/login.php'));
    exit;
}

// Get user info
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    $user = [];
}

if (!$user) {
    header('Location: ' . site_url('/pages/login.php'));
    exit;
}

// Get user's donation history
try {
    $stmt = $pdo->prepare("SELECT * FROM donations WHERE donor_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$userId]);
    $donations = $stmt->fetchAll();
} catch (Exception $e) {
    $donations = [];
}

// Get top donors
try {
    $stmt = $pdo->query("SELECT donor_id, SUM(amount) as total, COUNT(*) as count FROM donations WHERE status='success' GROUP BY donor_id ORDER BY total DESC LIMIT 5");
    $topDonors = $stmt->fetchAll();
} catch (Exception $e) {
    $topDonors = [];
}

$currentPage = 'donate';
?>
<?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

<main class="min-h-screen py-12">
    <div class="max-w-6xl mx-auto px-4">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Donation area: site support (Ko-fi / Patreon) or story support when book_id is provided -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8">
                    <?php if ($bookId): ?>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">?? Support This Story</h1>
                        <p class="text-gray-600 dark:text-gray-400 mb-6">Donate directly to support the author of this story.</p>
                        <!-- Keep legacy Paystack flow for story donations -->
                        <form id="donation-form" class="space-y-6">
                            <input type="hidden" id="book_id" name="book_id" value="<?= $bookId ?>">
                            <input type="hidden" id="user_id" name="user_id" value="<?= $userId ?>">
                            <div>
                                <label class="block font-semibold text-gray-900 dark:text-white mb-2">Amount (USD)</label>
                                <input type="number" id="amount" name="amount" min="1" step="1" class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white" required>
                            </div>
                            <div>
                                <label class="block font-semibold text-gray-900 dark:text-white mb-2">Message (Optional)</label>
                                <textarea name="message" rows="3" placeholder="Send a message to the author..." class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"></textarea>
                            </div>
                            <button type="button" id="payBtn" class="w-full px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg">?? Donate to Author</button>
                        </form>
                    <?php else: ?>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Support the Website</h1>
                        <p class="text-gray-600 dark:text-gray-400 mb-6">Help keep Scroll Novels online and maintained. Choose your preferred platform:</p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="p-6 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-900 text-center">
                                <h3 class="font-semibold text-amber-700 dark:text-amber-300 mb-3">Ko-fi</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">One-off support or monthly membership via Ko-fi.</p>
                                <a href="https://ko-fi.com/yourusername" target="_blank" rel="noopener" class="inline-block px-6 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-medium">? Support on Ko-fi</a>
                            </div>

                            <div class="p-6 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg border border-indigo-200 dark:border-indigo-900 text-center">
                                <h3 class="font-semibold text-indigo-700 dark:text-indigo-300 mb-3">Patreon</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Become a patron for exclusive perks and early access.</p>
                                <a href="https://www.patreon.com/Zakielvtuber" target="_blank" rel="noopener" class="inline-block px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium">?? Support on Patreon</a>
                            </div>
                        </div>

                        <div class="mt-8 text-xs text-gray-600 dark:text-gray-400">
                            <p>Note: Ko-fi and Patreon are third-party services. Links open in a new tab and donations are processed on their platforms.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Leaderboard & History -->
            <div class="space-y-8">
                <!-- Top Donors -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">?? Top Supporters</h3>
                    <?php if (count($topDonors) > 0): ?>
                        <div class="space-y-3">
                            <?php $i = 1; foreach ($topDonors as $donor): ?>
                                <div class="flex items-center justify-between p-3 bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-gray-700 dark:to-gray-600 rounded-lg">
                                    <div>
                                        <div class="font-bold text-lg">
                                            <?= $i == 1 ? '??' : ($i == 2 ? '??' : '??') ?> #<?= $i ?>
                                        </div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            <?= $donor['count'] ?> donation(s)
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold text-lg text-gray-900 dark:text-white">
                                            $<?= number_format($donor['total']) ?>
                                        </div>
                                    </div>
                                </div>
                                <?php $i++; endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-600 dark:text-gray-400 text-center py-4">No donations yet</p>
                    <?php endif; ?>
                </div>

                <!-- Your Donations -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">?? Your Donations</h3>
                    <?php if (count($donations) > 0): ?>
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            <?php foreach ($donations as $donation): ?>
                                <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border-l-4 border-pink-600">
                                    <div class="flex justify-between items-start mb-1">
                                        <div class="font-semibold text-gray-900 dark:text-white">
                                            $<?= number_format($donation['amount']) ?>
                                        </div>
                                        <span class="text-xs px-2 py-1 bg-<?= $donation['status'] == 'success' ? 'green' : 'yellow' ?>-100 text-<?= $donation['status'] == 'success' ? 'green' : 'yellow' ?>-800 rounded">
                                            <?= ucfirst($donation['status']) ?>
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                        <?= date('M d, Y H:i', strtotime($donation['created_at'])) ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-600 dark:text-gray-400 text-center py-8">You haven't donated yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</main>

<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
// Load saved payment methods
async function loadPaymentMethods() {
    try {
        const response = await fetch('<?= site_url('/api/get-payment-methods.php') ?>');
        const data = await response.json();
        
        const container = document.getElementById('payment-methods');
        container.innerHTML = '';
        
        if (data.success && data.methods.length > 0) {
            data.methods.forEach((method, index) => {
                const div = document.createElement('div');
                div.className = 'p-3 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-pink-600 hover:bg-pink-50 dark:hover:bg-pink-900/20 transition';
                div.innerHTML = `
                    <label class="flex items-center gap-3">
                        <input type="radio" name="payment_method_id" value="${method.id}" ${index === 0 ? 'checked' : ''}>
                        <span class="text-gray-900 dark:text-white">
                            ?? ${method.card_brand.toUpperCase()} ending in ${method.last_four}
                        </span>
                    </label>
                `;
                container.appendChild(div);
            });
        }
    } catch (error) {
        console.error('Error loading payment methods:', error);
    }
}

// Add card modal (simplified - will open Paystack card form)
document.getElementById('add-card-btn').addEventListener('click', function() {
    // Simply uncheck any selected payment method so user provides new card info
    document.querySelectorAll('input[name="payment_method_id"]').forEach(radio => radio.checked = false);
    
    // Auto-check the save card checkbox to encourage saving
    document.getElementById('save-card').checked = true;
    
    alert('? You can add a new card by proceeding with payment.\n\nAfter successful payment, you can save this card for future donations.');
});

// Preset amount buttons
document.querySelectorAll('.preset-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('amount').value = this.dataset.amount;
        document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('border-pink-600', 'bg-pink-50'));
        this.classList.add('border-pink-600', 'bg-pink-50');
    });
});

// Paystack payment
document.getElementById('payBtn').addEventListener('click', async function() {
    const amount = parseInt(document.getElementById('amount').value);
    const bookId = <?= $bookId ?>;
    const message = document.querySelector('textarea[name="message"]').value;
    const saveCard = document.getElementById('save-card').checked;
    const paymentMethodId = document.querySelector('input[name="payment_method_id"]:checked')?.value || null;
    
    if (!amount || amount < 5) {
        alert('Please enter a valid amount (minimum $5)');
        return;
    }

    // Disable button to prevent double submission
    this.disabled = true;
    this.textContent = '? Processing...';

    try {
        // Initialize payment
        const initResponse = await fetch('<?= site_url('/api/paystack-donate.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                amount: amount,
                book_id: bookId,
                message: message,
                save_card: saveCard,
                payment_method_id: paymentMethodId
            })
        });

        const initData = await initResponse.json();

        if (!initData.success) {
            alert('Error: ' + (initData.error || 'Failed to initialize payment'));
            this.disabled = false;
            this.textContent = '?? Donate';
            return;
        }

        // Open Paystack payment modal
        const handler = PaystackPop.setup({
            key: '<?= PAYSTACK_PUBLIC_KEY ?>',
            email: '<?= htmlspecialchars($user['email'] ?? '') ?>',
            amount: amount * 100,
            ref: initData.reference,
            onClose: function() {
                document.getElementById('payBtn').disabled = false;
                document.getElementById('payBtn').textContent = '?? Donate';
                alert('Payment window closed.');
            },
            onSuccess: async function(response) {
                // Verify payment
                const verifyResponse = await fetch('<?= site_url('/api/paystack-verify.php') ?>?reference=' + response.reference + '&donation_id=' + initData.donation_id);
                const verifyData = await verifyResponse.json();

                if (verifyData.success) {
                    alert('? Thank you for your donation! Your payment has been confirmed.');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    alert('?? Payment completed but verification pending. We\'ll confirm shortly.');
                    setTimeout(() => location.reload(), 2000);
                }
            }
        });
        handler.openIframe();
    } catch (error) {
        console.error('Error:', error);
        alert('Error processing payment. Please try again.');
        this.disabled = false;
        this.textContent = '?? Donate';
    }
});

// Load payment methods on page load
document.addEventListener('DOMContentLoaded', loadPaymentMethods);
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
</body>
</html>
</body>
</html>

