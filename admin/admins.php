<?php
// admin/admins.php
require_once __DIR__ . '/header.php';
$activeTab = 'admins';
require_once __DIR__ . '/sidebar.php';

$admins = $pdo->query("SELECT id, username, email, role, created_at FROM admins ORDER BY created_at DESC")->fetchAll() ?? [];

// Get and clear session messages
$success = $_SESSION['admin_success'] ?? null;
$error = $_SESSION['admin_error'] ?? null;
unset($_SESSION['admin_success']);
unset($_SESSION['admin_error']);
?>

<main class="flex-1 p-6 ml-64">
  <div class="mb-6">
    <h2 class="text-2xl font-bold">Admin Management</h2>
    <p class="text-gray-400">Manage administrator accounts and permissions</p>
  </div>

  <?php if ($success): ?>
    <div class="mb-4 p-4 bg-green-900/30 border border-green-600 text-green-400 rounded-lg">✓ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  
  <?php if ($error): ?>
    <div class="mb-4 p-4 bg-red-900/30 border border-red-600 text-red-400 rounded-lg">✕ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Add New Admin Form -->
  <div class="card mb-6">
    <h3 class="text-lg font-bold mb-4">Add New Admin</h3>
    <form method="POST" action="add-admin.php" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-2">Select User</label>
          <input type="text" id="adminUserSearch" placeholder="Search username or email..." class="w-full px-3 py-2 bg-[#0f1113] border border-[#2a2d31] rounded text-white mb-2">
          <select name="user_id" id="adminUserSelect" required class="w-full px-3 py-2 border rounded-lg">
            <option value="">-- Choose a user --</option>
            <?php 
            $users = $pdo->query("SELECT id, username, email FROM users ORDER BY username")->fetchAll();
            foreach ($users as $u): 
            ?>
              <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['email'] ?? 'N/A') ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-2">Admin Role</label>
          <select name="role" required class="w-full px-3 py-2 border rounded-lg">
            <option value="moderator">Moderator</option>
            <option value="admin">Full Admin</option>
            <option value="super_admin">Super Admin</option>
          </select>
        </div>
      </div>
      <button type="submit" class="px-4 py-2 btn btn-primary">Add Admin</button>
    </form>
  </div>

  <div class="card">
    <table class="w-full table text-sm">
      <thead>
        <tr>
          <th>Username</th>
          <th>Email</th>
          <th>Role</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($admins as $a): ?>
        <tr>
          <td class="font-semibold"><?= htmlspecialchars($a['username']) ?></td>
          <td class="text-gray-400"><?= htmlspecialchars($a['email'] ?? 'N/A') ?></td>
          <td><span class="badge badge-info"><?= htmlspecialchars($a['role'] ?? 'moderator') ?></span></td>
          <td class="text-gray-400"><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
          <td>
            <button class="px-2 py-1 btn btn-secondary">Edit</button>
            <?php if($a['username'] !== $_SESSION['admin_username'] ?? ''): ?>
              <button class="px-2 py-1 btn btn-danger ml-1">Remove</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>

<script>
// Simple client-side filter for the user select
document.getElementById('adminUserSearch')?.addEventListener('input', function(e){
  const q = e.target.value.toLowerCase();
  const sel = document.getElementById('adminUserSelect');
  for (let i=0; i<sel.options.length; i++){
    const o = sel.options[i];
    const text = o.text.toLowerCase();
    if (q === '' || text.includes(q)) o.style.display = '';
    else o.style.display = 'none';
  }
});
</script>
