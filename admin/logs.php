<?php
// admin/logs.php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();

$limit = 200;
$stmt = $pdo->prepare("SELECT a.*, u.username FROM admin_activity a LEFT JOIN users u ON u.id = a.admin_id ORDER BY a.created_at DESC LIMIT ?");
$stmt->execute([$limit]);
$logs = $stmt->fetchAll();

require_once __DIR__ . '/../inc/header.php';
?>
<div class="container">
  <div class="card">
    <h1>Admin Activity Logs (latest <?= $limit ?>)</h1>
    <table style="width:100%">
      <thead><tr><th>Time</th><th>Admin</th><th>Action</th><th>Meta</th></tr></thead>
      <tbody>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td><?= htmlspecialchars($l['created_at']) ?></td>
            <td><?= htmlspecialchars($l['username'] ?? 'SYSTEM') ?></td>
            <td><?= htmlspecialchars($l['action']) ?></td>
            <td><pre style="white-space:pre-wrap"><?= htmlspecialchars(json_encode(json_decode($l['meta'] ?? '{}'), JSON_PRETTY_PRINT)) ?></pre></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../inc/footer.php'; ?>
