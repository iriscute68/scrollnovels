<?php
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

require_admin();

$limit = intval($_GET['limit'] ?? 50);

$stmt = $pdo->prepare("SELECT aal.*, u.username FROM admin_action_logs aal 
  LEFT JOIN users u ON u.id = aal.actor_id
  ORDER BY aal.created_at DESC LIMIT ?");
$stmt->execute([$limit]);
$logs = $stmt->fetchAll();
?>

<div class="p-6">
  <h1 class="text-2xl font-bold">Admin Action Logs</h1>

  <div class="space-y-2 mt-6">
    <?php foreach ($logs as $log): ?>
      <div class="card p-3">
        <div class="flex justify-between">
          <div>
            <span class="font-bold text-sm"><?= htmlspecialchars($log['username'] ?? 'System') ?></span>
            <span class="text-sm text-gray-400"><?= htmlspecialchars($log['action_type']) ?> on <?= htmlspecialchars($log['target_type']) ?></span>
          </div>
          <span class="text-xs text-gray-500"><?= date('M d H:i', strtotime($log['created_at'])) ?></span>
        </div>
        <?php if ($log['data']): ?>
          <pre class="text-xs bg-gray-900 p-2 mt-2 rounded overflow-auto max-h-40"><?= htmlspecialchars(json_encode(json_decode($log['data'], true), JSON_PRETTY_PRINT)) ?></pre>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
