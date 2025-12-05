<?php
// admin/contracts.php - Manage contracts (merged; PDO list/edit, auth)
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
if (!isApprovedAdmin()) {
    http_response_code(403);
    exit('Forbidden');
}
include __DIR__ . '/../includes/header.php';

$page_title = 'Contracts Management';
$success = $error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        $user_id = (int)($_POST['user_id'] ?? 0);
        $story_id = (int)($_POST['story_id'] ?? 0);
        $terms = trim($_POST['terms'] ?? '');
        $status = $_POST['status'] ?? 'pending';
        $total_amount = (float)($_POST['total_amount'] ?? 0);

        if ($action === 'create' || $action === 'update') {
            if (empty($terms)) {
                $error = 'Terms required.';
            } else {
                try {
                    if ($action === 'create') {
                        $stmt = $pdo->prepare('INSERT INTO contracts (user_id, story_id, terms, status, total_amount) VALUES (?, ?, ?, ?, ?)');
                        $stmt->execute([$user_id, $story_id ?: null, $terms, $status, $total_amount]);
                        $success = 'Contract created!';
                    } else {
                        $stmt = $pdo->prepare('UPDATE contracts SET user_id = ?, story_id = ?, terms = ?, status = ?, total_amount = ? WHERE id = ?');
                        $stmt->execute([$user_id, $story_id ?: null, $terms, $status, $total_amount, $id]);
                        $success = 'Contract updated!';
                    }
                } catch (PDOException $e) {
                    error_log('Contract Save Error: ' . $e->getMessage());
                    $error = 'Save failed.';
                }
            }
        } elseif ($action === 'delete' && $id) {
            try {
                $pdo->prepare('DELETE FROM contracts WHERE id = ?')->execute([$id]);
                $success = 'Contract deleted!';
            } catch (PDOException $e) {
                $error = 'Delete failed.';
            }
        } elseif ($action === 'milestone' && $id) {
            $milestone_date = $_POST['milestone_date'] ?? date('Y-m-d');
            $milestone_amount = (float)($_POST['milestone_amount'] ?? 0);
            // Stub: Log milestone to interactions or separate table; for now, update total
            $pdo->prepare('UPDATE contracts SET total_amount = total_amount + ? WHERE id = ?')->execute([$milestone_amount, $id]);
            $success = 'Milestone added: $' . $milestone_amount . ' on ' . $milestone_date;
        }
    }
}

// Fetch contracts
try {
    $stmt = $pdo->prepare('SELECT c.*, u.username, s.title as story_title FROM contracts c LEFT JOIN users u ON c.user_id = u.id LEFT JOIN stories s ON c.story_id = s.id ORDER BY c.created_at DESC');
    $stmt->execute();
    $contracts = $stmt->fetchAll();

    // Users/Stories for dropdown
    $user_stmt = $pdo->query('SELECT id, username FROM users ORDER BY username');
    $users = $user_stmt->fetchAll();
    $story_stmt = $pdo->query('SELECT id, title FROM stories WHERE status = "published" ORDER BY title');
    $stories = $story_stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Contracts Load Error: ' . $e->getMessage());
    $contracts = []; $users = []; $stories = [];
}

// Edit mode
$edit_contract = null;
if (isset($_GET['edit']) && ($edit_id = (int)$_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ?');
    $stmt->execute([$edit_id]);
    $edit_contract = $stmt->fetch();
}
?>

<link rel="stylesheet" href="<?= asset_url('css/site-theme.compiled.css') ?>">
<main class="max-w-6xl mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6 text-emerald-400">Contracts Management</h1>
    <?php if ($error): ?>
        <div class="bg-red-900/20 text-red-400 p-3 rounded mb-4 border border-red-500"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-green-900/20 text-green-400 p-3 rounded mb-4 border border-green-500"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Contract Form -->
    <form method="POST" class="bg-gray-800 p-6 rounded-lg border border-gray-700 mb-8 space-y-4">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="<?= $edit_contract ? 'update' : 'create' ?>">
        <input type="hidden" name="id" value="<?= $edit_contract['id'] ?? '' ?>">
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label for="user_id" class="block text-sm font-medium mb-2 text-gray-300">User</label>
                <select id="user_id" name="user_id" required class="w-full p-3 bg-gray-700 border rounded">
                    <option value="">Select User</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($edit_contract['user_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="story_id" class="block text-sm font-medium mb-2 text-gray-300">Linked Story (Optional)</label>
                <select id="story_id" name="story_id" class="w-full p-3 bg-gray-700 border rounded">
                    <option value="">None</option>
                    <?php foreach ($stories as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($edit_contract['story_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div>
            <label for="terms" class="block text-sm font-medium mb-2 text-gray-300">Terms</label>
            <textarea id="terms" name="terms" rows="6" required class="w-full p-3 bg-gray-700 border rounded"><?= htmlspecialchars($edit_contract['terms'] ?? '') ?></textarea>
        </div>
        <div class="grid md:grid-cols-3 gap-4">
            <div>
                <label for="status" class="block text-sm font-medium mb-2 text-gray-300">Status</label>
                <select id="status" name="status" class="w-full p-3 bg-gray-700 border rounded">
                    <option value="pending" <?= ($edit_contract['status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="signed" <?= ($edit_contract['status'] ?? '') === 'signed' ? 'selected' : '' ?>>Signed</option>
                    <option value="paid" <?= ($edit_contract['status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
                </select>
            </div>
            <div>
                <label for="total_amount" class="block text-sm font-medium mb-2 text-gray-300">Total Amount ($)</label>
                <input type="number" id="total_amount" name="total_amount" step="0.01" value="<?= $edit_contract['total_amount'] ?? 0 ?>" class="w-full p-3 bg-gray-700 border rounded">
            </div>
            <div class="flex items-end">
                <button type="button" onclick="addMilestone(<?= $edit_contract['id'] ?? 0 ?>) " class="w-full py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Add Milestone</button>
            </div>
        </div>
        <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 rounded-md font-semibold text-white transition"><?= $edit_contract ? 'Update Contract' : 'Create Contract' ?></button>
        <?php if ($edit_contract): ?>
            <a href="<?= SITE_URL ?>/admin/contracts.php" class="text-sm text-gray-400 hover:underline">Cancel</a>
        <?php endif; ?>
    </form>

    <!-- Contracts List -->
    <h2 class="text-2xl font-bold mb-4">Contracts</h2>
    <div class="overflow-x-auto">
        <table class="w-full bg-gray-800 border border-gray-700 rounded">
            <thead class="bg-gray-700">
                <tr>
                    <th class="p-3 text-left">User</th>
                    <th class="p-3 text-left">Story</th>
                    <th class="p-3 text-left">Status</th>
                    <th class="p-3 text-left">Amount</th>
                    <th class="p-3 text-left">Created</th>
                    <th class="p-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contracts as $contract): ?>
                    <tr class="border-t border-gray-700 hover:bg-gray-700">
                        <td class="p-3"><?= htmlspecialchars($contract['username'] ?? 'N/A') ?></td>
                        <td class="p-3"><?= htmlspecialchars($contract['story_title'] ?? 'General') ?></td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded text-xs <?= $contract['status'] === 'pending' ? 'bg-yellow-600' : ($contract['status'] === 'signed' ? 'bg-blue-600' : 'bg-green-600') ?>">
                                <?= ucfirst($contract['status']) ?>
                            </span>
                        </td>
                        <td class="p-3">$<?= number_format($contract['total_amount'], 2) ?></td>
                        <td class="p-3"><?= time_ago($contract['created_at']) ?></td>
                        <td class="p-3 text-center space-x-2">
                            <a href="?edit=<?= $contract['id'] ?>" class="px-2 py-1 bg-blue-600 text-white rounded text-sm">Edit</a>
                            <button onclick="deleteContract(<?= $contract['id'] ?>)" class="px-2 py-1 bg-red-600 text-white rounded text-sm">Delete</button>
                            <a href="#" onclick="exportPDF(<?= $contract['id'] ?>)" class="px-2 py-1 bg-gray-600 text-white rounded text-sm">PDF</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($contracts)): ?>
                    <tr><td colspan="6" class="p-3 text-center text-gray-400">No contracts yet. Create one above!</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
// JS: Delete + Milestone Modal (stub; from dashboard.js)
function deleteContract(id) {
    if (confirm('Delete contract?')) {
        fetch(`${SITE_URL}/admin/contracts.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=delete&id=${id}&csrf=<?= csrf_token() ?>`
        }).then(r => r.json()).then(data => {
            if (data.ok) location.reload();
            else alert(data.error);
        });
    }
}

function addMilestone(id) {
    const amount = prompt('Milestone amount ($):');
    const date = prompt('Date (YYYY-MM-DD):') || date('Y-m-d');
    if (amount) {
        fetch(`${SITE_URL}/admin/contracts.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=milestone&id=${id}&milestone_amount=${amount}&milestone_date=${date}&csrf=<?= csrf_token() ?>`
        }).then(r => r.json()).then(data => {
            if (data.ok) location.reload();
            else alert(data.error);
        });
    }
}

function exportPDF(id) {
    // Stub: Window.print() or jsPDF
    window.print();  // Simple print for now
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>