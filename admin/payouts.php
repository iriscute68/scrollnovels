<?php
// admin/payouts.php - Manage payments and payouts
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/topbar.php';

if (!isset($_SESSION['admin_user'])) {
    header('Location: /pages/login.php');
    exit;
}

require_once __DIR__ . '/../config.php';

// Get all payments
$stmt = $pdo->prepare("
    SELECT p.*, u.username 
    FROM payments p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC
    LIMIT 100
");
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all payouts
$stmt = $pdo->prepare("
    SELECT po.*, u.username 
    FROM payouts po
    JOIN users u ON po.user_id = u.id
    ORDER BY po.created_at DESC
    LIMIT 100
");
$stmt->execute();
$payouts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary stats
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'");
$stmt->execute();
$total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM payouts WHERE status = 'completed'");
$stmt->execute();
$total_payouts = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>

<main class="flex-1 overflow-auto bg-background text-foreground">
    <div class="max-w-6xl mx-auto p-6 space-y-6">
        <h1 class="text-2xl font-bold">💰 Payments & Payouts</h1>

        <!-- Summary Cards -->
        <div class="grid grid-cols-3 gap-4">
            <div class="card p-4">
                <p class="text-muted-foreground text-sm">Total Revenue</p>
                <p class="text-3xl font-bold text-gold">$<?= number_format($total_revenue, 2) ?></p>
            </div>
            <div class="card p-4">
                <p class="text-muted-foreground text-sm">Total Payouts</p>
                <p class="text-3xl font-bold text-primary">$<?= number_format($total_payouts, 2) ?></p>
            </div>
            <div class="card p-4">
                <p class="text-muted-foreground text-sm">Net (30% fee)</p>
                <p class="text-3xl font-bold">$<?= number_format(($total_revenue - $total_payouts) * 0.7, 2) ?></p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 border-b border-border">
            <button onclick="showTab('payments')" class="tab-btn tab-active" id="tab-payments">
                💳 Payments (<?= count($payments) ?>)
            </button>
            <button onclick="showTab('payouts')" class="tab-btn" id="tab-payouts">
                🏦 Payouts (<?= count($payouts) ?>)
            </button>
        </div>

        <!-- Payments Tab -->
        <div id="payments-tab" class="tab-content space-y-4">
            <div class="card overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-muted">
                        <tr>
                            <th class="px-4 py-3 text-left">User</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-left">Method</th>
                            <th class="px-4 py-3 text-left">Gateway</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php foreach ($payments as $p): ?>
                            <tr class="hover:bg-muted/50">
                                <td class="px-4 py-3 font-medium"><?= htmlspecialchars($p['username']) ?></td>
                                <td class="px-4 py-3 text-right font-bold">$<?= number_format($p['amount'], 2) ?></td>
                                <td class="px-4 py-3 text-sm"><?= ucfirst($p['method']) ?></td>
                                <td class="px-4 py-3 text-sm"><?= ucfirst($p['gateway']) ?></td>
                                <td class="px-4 py-3">
                                    <span class="badge <?= $p['status'] === 'completed' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-xs text-muted-foreground">
                                    <?= date('M d, Y', strtotime($p['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payouts Tab -->
        <div id="payouts-tab" class="tab-content hidden space-y-4">
            <div class="flex gap-3 mb-4">
                <button onclick="showPayoutForm()" class="btn btn-primary">+ New Payout</button>
            </div>

            <div class="card overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-muted">
                        <tr>
                            <th class="px-4 py-3 text-left">Author</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-left">Method</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right">Date</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php foreach ($payouts as $po): ?>
                            <tr class="hover:bg-muted/50">
                                <td class="px-4 py-3 font-medium"><?= htmlspecialchars($po['username']) ?></td>
                                <td class="px-4 py-3 text-right font-bold">$<?= number_format($po['amount'], 2) ?></td>
                                <td class="px-4 py-3 text-sm"><?= ucfirst($po['method']) ?></td>
                                <td class="px-4 py-3">
                                    <span class="badge <?= $po['status'] === 'completed' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= ucfirst($po['status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-xs text-muted-foreground">
                                    <?= date('M d, Y', strtotime($po['created_at'])) ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <?php if ($po['status'] === 'pending'): ?>
                                        <button onclick="approvePayout(<?= $po['id'] ?>)" class="btn btn-sm btn-success">Approve</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<style>
.tab-btn {
    padding: 0.75rem 1.5rem;
    border: none;
    background: transparent;
    color: var(--muted-foreground);
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.tab-btn.tab-active {
    color: var(--foreground);
    font-weight: 600;
}

.tab-btn.tab-active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #d4af37 0%, #f0e68c 100%);
}

.tab-content.hidden {
    display: none;
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-success {
    background: rgba(16, 185, 129, 0.2);
    color: #86efac;
}

.badge-warning {
    background: rgba(245, 158, 11, 0.2);
    color: #fcd34d;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    border: 1px solid transparent;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

.btn-primary {
    background: #d4af37;
    color: #120a2a;
}

.btn-primary:hover {
    background: #f0e68c;
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
}

.card {
    background: rgba(18, 10, 42, 0.5);
    border: 1px solid #d4af37;
    border-radius: 0.5rem;
}

.grid {
    display: grid;
}

.grid-cols-3 {
    grid-template-columns: repeat(3, 1fr);
}

.gap-4 {
    gap: 1rem;
}

.text-gold {
    color: #d4af37;
}
</style>

<script>
function showTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('tab-active'));
    
    document.getElementById(tab + '-tab').classList.remove('hidden');
    document.getElementById('tab-' + tab).classList.add('tab-active');
}

async function approvePayout(payoutId) {
    if (!confirm('Approve this payout?')) return;

    const res = await fetch('/admin/ajax/approve_payout.php?id=' + payoutId, {
        method: 'POST',
        credentials: 'same-origin'
    });

    const result = await res.json();
    if (result.ok) {
        location.reload();
    } else {
        alert('Error: ' + result.message);
    }
}

function showPayoutForm() {
    alert('Payout creation would open here');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
