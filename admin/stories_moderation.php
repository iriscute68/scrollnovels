<?php
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

require_admin();

$pending_stories = $pdo->query("SELECT s.*, u.username FROM stories s JOIN users u ON u.id = s.user_id WHERE s.status = 'pending_review' ORDER BY s.created_at DESC")->fetchAll();
?>

<div class="p-6">
  <h1 class="text-3xl font-bold mb-6">Story Moderation</h1>

  <?php if (empty($pending_stories)): ?>
    <p class="text-gray-400">No stories pending review.</p>
  <?php else: ?>
    <div class="space-y-4">
      <?php foreach ($pending_stories as $s): ?>
        <div class="bg-gray-800 p-4 rounded-lg">
          <h3 class="text-xl font-bold"><?= htmlspecialchars($s['title']) ?></h3>
          <p class="text-sm text-gray-400">By <?= htmlspecialchars($s['username']) ?> • <?= date('M d, Y', strtotime($s['created_at'])) ?></p>
          <p class="text-gray-300 mt-2"><?= htmlspecialchars(substr($s['synopsis'], 0, 150)) ?>...</p>
          
          <div class="flex gap-2 mt-4">
            <form method="post" action="story_approve.php" style="display:inline;">
              <input type="hidden" name="id" value="<?= $s['id'] ?>">
              <input type="hidden" name="action" value="approve">
              <button class="px-3 py-1 bg-green-600 text-white rounded">Approve</button>
            </form>
            <form method="post" action="story_approve.php" style="display:inline;">
              <input type="hidden" name="id" value="<?= $s['id'] ?>">
              <input type="hidden" name="action" value="reject">
              <button class="px-3 py-1 bg-red-600 text-white rounded">Reject</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
