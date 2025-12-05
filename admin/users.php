<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';

// Simple users admin view
$q = $pdo->query("SELECT id, username, email, created_at FROM users ORDER BY created_at DESC LIMIT 200");
$users = $q->fetchAll(PDO::FETCH_ASSOC);
?>
<main class="admin-main">
    <?php require __DIR__ . '/topbar.php'; ?>
    <h1 class="text-2xl font-bold mb-4">Users</h1>
    <div class="mb-4">
        <input id="userSearch" placeholder="Search users..." class="input" />
    </div>
    <table class="table w-full">
        <thead><tr><th>Username</th><th>Email</th><th>Joined</th><th>Action</th></tr></thead>
        <tbody id="usersTable">
            <?php foreach($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                    <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <form method="POST" action="add-admin.php" style="display:inline">
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <select name="role">
                                <option value="moderator">Moderator</option>
                                <option value="admin">Admin</option>
                            </select>
                            <button class="btn btn-primary" type="submit">Grant</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>
<?php
// admin/users.php - Manage users
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/topbar.php';

if (!isset($_SESSION['admin_user'])) {
    header('Location: /pages/login.php');
    exit;
}

require_once __DIR__ . '/../config.php';

// Get all users with stats
$stmt = $pdo->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM stories WHERE user_id = u.id) as story_count,
           (SELECT COUNT(*) FROM chapters WHERE user_id = u.id AND is_published = 1) as chapter_count
    FROM users u
    ORDER BY u.created_at DESC
    LIMIT 100
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="flex-1 overflow-auto bg-background text-foreground">
    <div class="max-w-6xl mx-auto p-6 space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">👥 Users Management</h1>
        </div>

        <!-- Search & Filter -->
        <div class="card p-4 space-y-4">
            <div class="flex gap-3 flex-wrap">
                <input type="text" id="searchInput" class="input-field flex-1 min-w-[200px]" 
                    placeholder="Search by username or email...">
                <select id="filterRole" class="input-field">
                    <option value="">All Roles</option>
                    <option value="user">👤 User</option>
                    <option value="moderator">🛡️ Moderator</option>
                    <option value="editor">✏️ Editor</option>
                    <option value="admin">🔑 Admin</option>
                </select>
                <select id="filterStatus" class="input-field">
                    <option value="">All Statuses</option>
                    <option value="active">✓ Active</option>
                    <option value="suspended">⏸️ Suspended</option>
                    <option value="banned">🚫 Banned</option>
                </select>
                <button onclick="filterUsers()" class="btn btn-ghost">Filter</button>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Username</th>
                        <th class="px-4 py-3 text-left font-medium">Email</th>
                        <th class="px-4 py-3 text-center font-medium">Stories</th>
                        <th class="px-4 py-3 text-center font-medium">Chapters</th>
                        <th class="px-4 py-3 text-left font-medium">Role</th>
                        <th class="px-4 py-3 text-left font-medium">Status</th>
                        <th class="px-4 py-3 text-right font-medium">Joined</th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTable" class="divide-y divide-border">
                    <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-muted/50 transition-colors user-row" 
                            data-role="<?= $user['role'] ?? 'user' ?>" 
                            data-status="<?= $user['status'] ?? 'active' ?>">
                            <td class="px-4 py-3 font-medium"><?= htmlspecialchars($user['username']) ?></td>
                            <td class="px-4 py-3 text-sm"><?= htmlspecialchars($user['email']) ?></td>
                            <td class="px-4 py-3 text-center"><?= $user['story_count'] ?? 0 ?></td>
                            <td class="px-4 py-3 text-center"><?= $user['chapter_count'] ?? 0 ?></td>
                            <td class="px-4 py-3">
                                <select class="input-field py-1" onchange="updateUserRole(<?= $user['id'] ?>, this.value)">
                                    <option value="user" <?= ($user['role'] ?? 'user') === 'user' ? 'selected' : '' ?>>User</option>
                                    <option value="moderator" <?= ($user['role'] ?? 'user') === 'moderator' ? 'selected' : '' ?>>Moderator</option>
                                    <option value="editor" <?= ($user['role'] ?? 'user') === 'editor' ? 'selected' : '' ?>>Editor</option>
                                    <option value="admin" <?= ($user['role'] ?? 'user') === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <span class="badge <?= ($user['status'] ?? 'active') === 'banned' ? 'badge-error' : (($user['status'] ?? 'active') === 'suspended' ? 'badge-warning' : 'badge-success') ?>">
                                    <?= ucfirst($user['status'] ?? 'active') ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-muted-foreground">
                                <?= date('M d, Y', strtotime($user['created_at'])) ?>
                            </td>
                            <td class="px-4 py-3 text-right space-x-1">
                                <button onclick="suspendUser(<?= $user['id'] ?>)" class="btn btn-sm btn-warning">Suspend</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<style>
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

.badge-error {
    background: rgba(239, 68, 68, 0.2);
    color: #fca5a5;
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

.btn-ghost {
    background: transparent;
    color: var(--foreground);
    border-color: var(--border);
}

.btn-ghost:hover {
    background: rgba(212, 175, 55, 0.1);
}

.btn-warning {
    background: #f59e0b;
    color: #fff;
}

.btn-warning:hover {
    background: #d97706;
}

.input-field {
    background: rgba(18, 10, 42, 0.8);
    border: 1px solid #d4af37;
    color: #fff;
    padding: 0.5rem;
    border-radius: 0.375rem;
}

.card {
    background: rgba(18, 10, 42, 0.5);
    border: 1px solid #d4af37;
    border-radius: 0.5rem;
}
</style>

<script>
function filterUsers() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const role = document.getElementById('filterRole').value;
    const status = document.getElementById('filterStatus').value;
    const rows = document.querySelectorAll('.user-row');

    rows.forEach(row => {
        const username = row.textContent.toLowerCase();
        const userRole = row.dataset.role;
        const userStatus = row.dataset.status;
        
        const matchSearch = username.includes(search);
        const matchRole = !role || userRole === role;
        const matchStatus = !status || userStatus === status;
        
        row.style.display = (matchSearch && matchRole && matchStatus) ? '' : 'none';
    });
}

document.getElementById('searchInput').addEventListener('input', filterUsers);

async function updateUserRole(userId, newRole) {
    const res = await fetch('/admin/ajax/update_user_role.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, role: newRole })
    });

    const result = await res.json();
    if (!result.ok) {
        alert('Error: ' + result.message);
        location.reload();
    }
}

async function suspendUser(userId) {
    if (!confirm('Suspend this user?')) return;

    const res = await fetch('/admin/ajax/suspend_user.php?id=' + userId, {
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
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

    <div id="userTable"></div>
</div>

<script src="users.js"></script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
