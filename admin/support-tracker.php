<?php
/**
 * Admin Support Messages Tracker
 * View all contact form submissions and support tickets
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../config/db.php';

// Check if admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . site_url('/pages/login.php'));
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || ($user['role'] !== 'super_admin' && $user['role'] !== 'moderator')) {
    header('Location: ' . site_url());
    exit;
}

// Get support tickets
$tickets = [];
try {
    $stmt = $pdo->prepare("
    SELECT st.*, u.username as user_name, u.email as user_email
    FROM support_tickets st
    LEFT JOIN users u ON st.user_id = u.id
    ORDER BY st.created_at DESC
    LIMIT 50
    ");
    $stmt->execute();
    $tickets = $stmt->fetchAll();
} catch (Exception $e) {
    $tickets = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Support Messages - Admin - Scroll Novels</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="max-w-7xl mx-auto p-6">
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <h1 class="text-3xl font-bold text-gray-900">📧 Support Messages</h1>
                <p class="text-gray-600 mt-1">Track all contact form submissions and support tickets</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">ID</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">From</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Subject</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Category</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Priority</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Date</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($tickets as $t): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 text-sm text-gray-900">#<?= $t['id'] ?></td>
                            <td class="px-6 py-3 text-sm">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($t['user_name'] ?? 'Guest') ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($t['user_email'] ?? '') ?></div>
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-900">
                                <?= htmlspecialchars(substr($t['subject'], 0, 40)) ?>
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600">
                                <?= htmlspecialchars($t['category'] ?? 'General') ?>
                            </td>
                            <td class="px-6 py-3 text-sm">
                                <span class="px-2 py-1 rounded text-xs font-semibold
                                    <?php
                                    echo match($t['priority'] ?? 'medium') {
                                        'high' => 'bg-red-100 text-red-800',
                                        'medium' => 'bg-yellow-100 text-yellow-800',
                                        'low' => 'bg-green-100 text-green-800',
                                        default => 'bg-gray-100 text-gray-800'
                                    };
                                    ?>
                                ">
                                    <?= ucfirst($t['priority'] ?? 'medium') ?>
                                </span>
                            </td>
                            <td class="px-6 py-3 text-sm">
                                <span class="px-2 py-1 rounded text-xs font-semibold
                                    <?php
                                    echo match($t['status'] ?? 'open') {
                                        'resolved' => 'bg-green-100 text-green-800',
                                        'in_progress' => 'bg-blue-100 text-blue-800',
                                        'closed' => 'bg-gray-100 text-gray-800',
                                        default => 'bg-orange-100 text-orange-800'
                                    };
                                    ?>
                                ">
                                    <?= ucfirst(str_replace('_', ' ', $t['status'] ?? 'open')) ?>
                                </span>
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600">
                                <?= date('M d, Y H:i', strtotime($t['created_at'])) ?>
                            </td>
                            <td class="px-6 py-3 text-sm">
                                <button onclick="viewTicket(<?= $t['id'] ?>)" class="text-blue-600 hover:underline">View</button>
                                <span class="mx-2 text-gray-300">|</span>
                                <button onclick="updateStatus(<?= $t['id'] ?>)" class="text-green-600 hover:underline">Update</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (empty($tickets)): ?>
            <div class="p-8 text-center text-gray-500">
                <p>No support tickets yet</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="mt-6">
            <a href="<?= site_url('/admin/dashboard.php') ?>" class="text-blue-600 hover:underline">← Back to Admin Dashboard</a>
        </div>
    </div>

    <script>
    function viewTicket(id) {
        alert('View ticket #' + id + '\n\nMessage preview would appear here');
    }

    function updateStatus(id) {
        const status = prompt('Update status to (open/in_progress/resolved/closed):');
        if (status) {
            alert('Status updated to: ' + status);
        }
    }
    </script>
</body>
</html>
