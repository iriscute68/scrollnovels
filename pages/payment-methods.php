<?php
// pages/payment-methods.php - Manage payment & bank details
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Create tables if they don't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_payment_cards (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        card_holder_name VARCHAR(255) NOT NULL,
        card_number_masked VARCHAR(20) NOT NULL,
        card_last_four VARCHAR(4) NOT NULL,
        expiry_month INT,
        expiry_year INT,
        card_brand VARCHAR(50),
        is_default BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_bank_details (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        bank_name VARCHAR(255) NOT NULL,
        account_holder_name VARCHAR(255) NOT NULL,
        account_number_masked VARCHAR(20) NOT NULL,
        account_last_four VARCHAR(4) NOT NULL,
        routing_number_masked VARCHAR(20),
        country VARCHAR(100),
        currency VARCHAR(10) DEFAULT 'USD',
        is_default BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    // Tables already exist
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // Add card
    if ($action === 'add_card') {
        $name = trim($_POST['card_holder_name'] ?? '');
        $number = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
        $month = (int)($_POST['expiry_month'] ?? 0);
        $year = (int)($_POST['expiry_year'] ?? 0);
        $brand = ucfirst(strtolower($_POST['card_brand'] ?? 'visa'));

        if (empty($name) || !preg_match('/^\d{13,19}$/', $number)) {
            echo json_encode(['success' => false, 'error' => 'Invalid card details']);
            exit;
        }

        $masked = 'XXXX-XXXX-XXXX-' . substr($number, -4);
        $last_four = substr($number, -4);

        try {
            $stmt = $pdo->prepare("INSERT INTO user_payment_cards (user_id, card_holder_name, card_number_masked, card_last_four, expiry_month, expiry_year, card_brand) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $name, $masked, $last_four, $month, $year, $brand]);
            echo json_encode(['success' => true, 'message' => 'Card added successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to save card']);
        }
        exit;
    }

    // Delete card
    if ($action === 'delete_card') {
        $card_id = (int)($_POST['card_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM user_payment_cards WHERE id = ? AND user_id = ?");
            $stmt->execute([$card_id, $user_id]);
            echo json_encode(['success' => true, 'message' => 'Card deleted successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to delete card']);
        }
        exit;
    }

    // Set default card
    if ($action === 'set_default_card') {
        $card_id = (int)($_POST['card_id'] ?? 0);
        try {
            $pdo->prepare("UPDATE user_payment_cards SET is_default = FALSE WHERE user_id = ?")->execute([$user_id]);
            $pdo->prepare("UPDATE user_payment_cards SET is_default = TRUE WHERE id = ? AND user_id = ?")->execute([$card_id, $user_id]);
            echo json_encode(['success' => true, 'message' => 'Default card updated']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to update default card']);
        }
        exit;
    }

    // Add bank
    if ($action === 'add_bank') {
        $bank_name = trim($_POST['bank_name'] ?? '');
        $account_holder = trim($_POST['account_holder_name'] ?? '');
        $account_number = preg_replace('/\s+/', '', $_POST['account_number'] ?? '');
        $routing = preg_replace('/\s+/', '', $_POST['routing_number'] ?? '');
        $country = trim($_POST['country'] ?? 'US');

        if (empty($bank_name) || empty($account_holder) || empty($account_number)) {
            echo json_encode(['success' => false, 'error' => 'Missing required bank details']);
            exit;
        }

        $masked = 'XXXX' . substr($account_number, -4);
        $last_four = substr($account_number, -4);
        $routing_masked = !empty($routing) ? 'XXXX' . substr($routing, -3) : null;

        try {
            $stmt = $pdo->prepare("INSERT INTO user_bank_details (user_id, bank_name, account_holder_name, account_number_masked, account_last_four, routing_number_masked, country) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $bank_name, $account_holder, $masked, $last_four, $routing_masked, $country]);
            echo json_encode(['success' => true, 'message' => 'Bank account added successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to save bank details']);
        }
        exit;
    }

    // Delete bank
    if ($action === 'delete_bank') {
        $bank_id = (int)($_POST['bank_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM user_bank_details WHERE id = ? AND user_id = ?");
            $stmt->execute([$bank_id, $user_id]);
            echo json_encode(['success' => true, 'message' => 'Bank account deleted successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to delete bank account']);
        }
        exit;
    }

    // Set default bank
    if ($action === 'set_default_bank') {
        $bank_id = (int)($_POST['bank_id'] ?? 0);
        try {
            $pdo->prepare("UPDATE user_bank_details SET is_default = FALSE WHERE user_id = ?")->execute([$user_id]);
            $pdo->prepare("UPDATE user_bank_details SET is_default = TRUE WHERE id = ? AND user_id = ?")->execute([$bank_id, $user_id]);
            echo json_encode(['success' => true, 'message' => 'Default bank account updated']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to update default bank account']);
        }
        exit;
    }
}

// Fetch user's cards and banks
$cardStmt = $pdo->prepare("SELECT * FROM user_payment_cards WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$cardStmt->execute([$user_id]);
$cards = $cardStmt->fetchAll(PDO::FETCH_ASSOC);

$bankStmt = $pdo->prepare("SELECT * FROM user_bank_details WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$bankStmt->execute([$user_id]);
$banks = $bankStmt->fetchAll(PDO::FETCH_ASSOC);

$userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
?>
<?php
    $page_title = 'Payment Methods - Scroll Novels';
    $page_head = '';
    require_once __DIR__ . '/../includes/header.php';
?>
<!-- Main Content -->
<main class="flex-1">
    <div class="max-w-4xl mx-auto px-4 py-12">
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-emerald-700 dark:text-emerald-400 mb-2">💳 Payment Methods</h1>
            <p class="text-gray-600 dark:text-gray-400">Manage your payment cards and bank accounts</p>
        </div>

        <!-- Payment Cards Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-8 shadow border border-emerald-200 dark:border-emerald-900 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">💳 Payment Cards</h2>
                <button onclick="showCardForm()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors">+ Add Card</button>
            </div>

            <!-- Card Form (Hidden) -->
            <form id="cardForm" class="hidden mb-6 p-6 bg-emerald-50 dark:bg-gray-700 rounded-lg border border-emerald-200 dark:border-emerald-700 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="text" id="card_holder_name" placeholder="Cardholder Name" class="px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-emerald-600" required>
                    <input type="text" id="card_number" placeholder="Card Number" maxlength="19" class="px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-emerald-600" required>
                </div>
                <div class="grid grid-cols-3 md:grid-cols-3 gap-4">
                    <select id="expiry_month" class="px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                        <option value="">Month</option>
                        <?php for ($m = 1; $m <= 12; $m++) echo "<option value='$m'>" . str_pad($m, 2, '0', STR_PAD_LEFT) . "</option>"; ?>
                    </select>
                    <select id="expiry_year" class="px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                        <option value="">Year</option>
                        <?php for ($y = date('Y'); $y <= date('Y') + 20; $y++) echo "<option value='$y'>$y</option>"; ?>
                    </select>
                    <select id="card_brand" class="px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                        <option value="Visa">Visa</option>
                        <option value="Mastercard">Mastercard</option>
                        <option value="Amex">Amex</option>
                        <option value="Discover">Discover</option>
                    </select>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="saveCard()" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors">Save Card</button>
                    <button type="button" onclick="hideCardForm()" class="flex-1 px-4 py-2 border-2 border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20 font-medium transition-colors">Cancel</button>
                </div>
            </form>

            <!-- Cards List -->
            <div class="space-y-3">
                <?php if (empty($cards)): ?>
                    <p class="text-gray-600 dark:text-gray-400 py-6 text-center">No payment cards added yet</p>
                <?php else: ?>
                    <?php foreach ($cards as $card): ?>
                        <div class="flex items-center justify-between p-4 bg-emerald-50 dark:bg-gray-700 rounded-lg border border-emerald-200 dark:border-emerald-700">
                            <div>
                                <p class="font-semibold text-emerald-700 dark:text-emerald-400"><?= htmlspecialchars($card['card_holder_name']) ?></p>
                                <p class="text-sm text-gray-600 dark:text-gray-400"><?= $card['card_brand'] ?> • <?= $card['card_last_four'] ?> • Expires <?= str_pad($card['expiry_month'], 2, '0', STR_PAD_LEFT) ?>/<?= $card['expiry_year'] ?></p>
                                <?php if ($card['is_default']): ?>
                                    <span class="inline-block mt-1 px-2 py-1 text-xs bg-emerald-200 dark:bg-emerald-900 text-emerald-800 dark:text-emerald-300 rounded">Default</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-2">
                                <?php if (!$card['is_default']): ?>
                                    <button onclick="setDefaultCard(<?= $card['id'] ?>)" class="px-3 py-1 text-sm border border-emerald-300 dark:border-emerald-700 text-emerald-600 dark:text-emerald-400 rounded hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors">Set Default</button>
                                <?php endif; ?>
                                <button onclick="deleteCard(<?= $card['id'] ?>)" class="px-3 py-1 text-sm border border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">Delete</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bank Details Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-8 shadow border border-emerald-200 dark:border-emerald-900">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">🏦 Bank Accounts</h2>
                <button onclick="showBankForm()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors">+ Add Bank</button>
            </div>

            <!-- Bank Form (Hidden) -->
            <form id="bankForm" class="hidden mb-6 p-6 bg-emerald-50 dark:bg-gray-700 rounded-lg border border-emerald-200 dark:border-emerald-700 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="text" id="bank_name" placeholder="Bank Name" class="px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-emerald-600" required>
                    <input type="text" id="account_holder_name" placeholder="Account Holder Name" class="px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-emerald-600" required>
                </div>
                <input type="text" id="account_number" placeholder="Account Number" class="w-full px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-emerald-600" required>
                <input type="text" id="routing_number" placeholder="Routing Number (US only)" class="w-full px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                <select id="country" class="w-full px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                    <option value="US">United States (USD)</option>
                    <option value="CA">Canada (CAD)</option>
                    <option value="GB">United Kingdom (GBP)</option>
                    <option value="AU">Australia (AUD)</option>
                    <option value="EUR">Europe (EUR)</option>
                </select>
                <div class="flex gap-3">
                    <button type="button" onclick="saveBank()" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors">Save Bank</button>
                    <button type="button" onclick="hideBankForm()" class="flex-1 px-4 py-2 border-2 border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20 font-medium transition-colors">Cancel</button>
                </div>
            </form>

            <!-- Banks List -->
            <div class="space-y-3">
                <?php if (empty($banks)): ?>
                    <p class="text-gray-600 dark:text-gray-400 py-6 text-center">No bank accounts added yet</p>
                <?php else: ?>
                    <?php foreach ($banks as $bank): ?>
                        <div class="flex items-center justify-between p-4 bg-emerald-50 dark:bg-gray-700 rounded-lg border border-emerald-200 dark:border-emerald-700">
                            <div>
                                <p class="font-semibold text-emerald-700 dark:text-emerald-400"><?= htmlspecialchars($bank['bank_name']) ?></p>
                                <p class="text-sm text-gray-600 dark:text-gray-400"><?= htmlspecialchars($bank['account_holder_name']) ?> • <?= $bank['account_last_four'] ?> • <?= $bank['country'] ?></p>
                                <?php if ($bank['is_default']): ?>
                                    <span class="inline-block mt-1 px-2 py-1 text-xs bg-emerald-200 dark:bg-emerald-900 text-emerald-800 dark:text-emerald-300 rounded">Default</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-2">
                                <?php if (!$bank['is_default']): ?>
                                    <button onclick="setDefaultBank(<?= $bank['id'] ?>)" class="px-3 py-1 text-sm border border-emerald-300 dark:border-emerald-700 text-emerald-600 dark:text-emerald-400 rounded hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors">Set Default</button>
                                <?php endif; ?>
                                <button onclick="deleteBank(<?= $bank['id'] ?>)" class="px-3 py-1 text-sm border border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">Delete</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Back Button -->
        <div class="mt-8">
            <a href="<?= site_url('/pages/profile.php') ?>" class="px-6 py-2 border-2 border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20 font-medium transition-colors">← Back to Profile</a>
        </div>
    </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

<script>
// Card management
function showCardForm() {
    document.getElementById('cardForm').classList.remove('hidden');
}

function hideCardForm() {
    document.getElementById('cardForm').classList.add('hidden');
    document.getElementById('cardForm').reset();
}

async function saveCard() {
    const data = new FormData();
    data.append('action', 'add_card');
    data.append('card_holder_name', document.getElementById('card_holder_name').value);
    data.append('card_number', document.getElementById('card_number').value);
    data.append('expiry_month', document.getElementById('expiry_month').value);
    data.append('expiry_year', document.getElementById('expiry_year').value);
    data.append('card_brand', document.getElementById('card_brand').value);

    const response = await fetch(location.href, { method: 'POST', body: data });
    const result = await response.json();
    
    if (result.success) {
        alert(result.message);
        location.reload();
    } else {
        alert('Error: ' + result.error);
    }
}

async function deleteCard(cardId) {
    if (!confirm('Delete this card?')) return;
    
    const data = new FormData();
    data.append('action', 'delete_card');
    data.append('card_id', cardId);

    const response = await fetch(location.href, { method: 'POST', body: data });
    const result = await response.json();
    
    if (result.success) {
        alert(result.message);
        location.reload();
    } else {
        alert('Error: ' + result.error);
    }
}

async function setDefaultCard(cardId) {
    const data = new FormData();
    data.append('action', 'set_default_card');
    data.append('card_id', cardId);

    const response = await fetch(location.href, { method: 'POST', body: data });
    const result = await response.json();
    
    if (result.success) {
        location.reload();
    } else {
        alert('Error: ' + result.error);
    }
}

// Bank management
function showBankForm() {
    document.getElementById('bankForm').classList.remove('hidden');
}

function hideBankForm() {
    document.getElementById('bankForm').classList.add('hidden');
    document.getElementById('bankForm').reset();
}

async function saveBank() {
    const data = new FormData();
    data.append('action', 'add_bank');
    data.append('bank_name', document.getElementById('bank_name').value);
    data.append('account_holder_name', document.getElementById('account_holder_name').value);
    data.append('account_number', document.getElementById('account_number').value);
    data.append('routing_number', document.getElementById('routing_number').value);
    data.append('country', document.getElementById('country').value);

    const response = await fetch(location.href, { method: 'POST', body: data });
    const result = await response.json();
    
    if (result.success) {
        alert(result.message);
        location.reload();
    } else {
        alert('Error: ' + result.error);
    }
}

async function deleteBank(bankId) {
    if (!confirm('Delete this bank account?')) return;
    
    const data = new FormData();
    data.append('action', 'delete_bank');
    data.append('bank_id', bankId);

    const response = await fetch(location.href, { method: 'POST', body: data });
    const result = await response.json();
    
    if (result.success) {
        alert(result.message);
        location.reload();
    } else {
        alert('Error: ' + result.error);
    }
}

async function setDefaultBank(bankId) {
    const data = new FormData();
    data.append('action', 'set_default_bank');
    data.append('bank_id', bankId);

    const response = await fetch(location.href, { method: 'POST', body: data });
    const result = await response.json();
    
    if (result.success) {
        location.reload();
    } else {
        alert('Error: ' + result.error);
    }
}
</script>

</body>
</html>

