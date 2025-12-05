<?php
// admin/stories.php - Story management with warn/remove instead of delete
require_once 'inc/header.php';
$activeTab = 'stories';
require_once 'inc/sidebar.php';

// Handle story actions (warn/remove)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $story_id = (int)($_POST['story_id'] ?? 0);
    $action = $_POST['action'];

    try {
        if ($action === 'warn') {
            // Mark story as warned
            $pdo->prepare("UPDATE stories SET warned = 1, warned_at = NOW() WHERE id = ?")->execute([$story_id]);
            $message = "Story warned successfully";
        } elseif ($action === 'remove') {
            // Remove from display (soft delete by marking as removed)
            $pdo->prepare("UPDATE stories SET removed = 1, removed_at = NOW(), removed_reason = ? WHERE id = ?")->execute([$_POST['reason'] ?? 'Removed by admin', $story_id]);
            $message = "Story removed successfully";
        } elseif ($action === 'restore') {
            // Restore a removed story
            $pdo->prepare("UPDATE stories SET removed = 0, removed_at = NULL WHERE id = ?")->execute([$story_id]);
            $message = "Story restored successfully";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Ensure status column exists
try {
    $pdo->exec("ALTER TABLE stories ADD COLUMN warned TINYINT(1) DEFAULT 0, ADD COLUMN warned_at TIMESTAMP NULL, ADD COLUMN removed TINYINT(1) DEFAULT 0, ADD COLUMN removed_at TIMESTAMP NULL, ADD COLUMN removed_reason VARCHAR(255) NULL");
} catch (Exception $e) {
    // columns may already exist
}

// Fetch stories - show all active stories
try {
    $stories = $pdo->query("
        SELECT s.id, s.title, u.username, s.author_id, s.created_at, s.views, 
               COALESCE(s.warned, 0) as warned, COALESCE(s.removed, 0) as removed, s.removed_reason
        FROM stories s 
        LEFT JOIN users u ON u.id = s.author_id 
        ORDER BY s.created_at DESC 
        LIMIT 100
    ")->fetchAll();
} catch (Exception $e) {
    $stories = [];
}
?>

<main class="flex-1 p-6 ml-64">
  <div class="mb-6">
    <h2 class="text-2xl font-bold">📚 Story Management</h2>
    <p class="text-gray-400">Manage user stories - warn or remove inappropriate content</p>
  </div>

  <?php if (isset($message)): ?>
    <div class="mb-4 p-4 bg-green-900/30 border border-green-600 text-green-400 rounded-lg">✓ <?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if (isset($error)): ?>
    <div class="mb-4 p-4 bg-red-900/30 border border-red-600 text-red-400 rounded-lg">✕ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-[#1f2937]">
            <th class="text-left p-3">Title</th>
            <th class="text-left p-3">Author</th>
            <th class="text-left p-3">Views</th>
            <th class="text-left p-3">Created</th>
            <th class="text-left p-3">Status</th>
            <th class="text-left p-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($stories as $s): ?>
          <tr class="border-b border-[#1f2937] hover:bg-[#0f1113]">
            <td class="p-3 font-semibold"><?= htmlspecialchars(substr($s['title'], 0, 50)) ?></td>
            <td class="p-3 text-gray-400"><?= htmlspecialchars($s['username'] ?? 'Unknown') ?></td>
            <td class="p-3"><?= number_format($s['views'] ?? 0) ?></td>
            <td class="p-3 text-gray-400"><?= date('M d, Y', strtotime($s['created_at'])) ?></td>
            <td class="p-3">
              <?php if ($s['removed']): ?>
                <span class="px-2 py-1 bg-red-600/30 text-red-300 rounded text-xs">Removed: <?= htmlspecialchars($s['removed_reason'] ?? 'No reason') ?></span>
              <?php elseif ($s['warned']): ?>
                <span class="px-2 py-1 bg-yellow-600/30 text-yellow-300 rounded text-xs">Warned</span>
              <?php else: ?>
                <span class="px-2 py-1 bg-green-600/30 text-green-300 rounded text-xs">Active</span>
              <?php endif; ?>
            </td>
            <td class="p-3 space-x-2">
              <?php if ($s['removed']): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="story_id" value="<?= $s['id'] ?>">
                  <input type="hidden" name="action" value="restore">
                  <button class="px-2 py-1 btn btn-success text-xs">Restore</button>
                </form>
              <?php else: ?>
                <button class="px-2 py-1 btn btn-warning text-xs" onclick="openWarnModal(<?= $s['id'] ?>, '<?= htmlspecialchars($s['title']) ?>')">Warn</button>
                <button class="px-2 py-1 btn btn-danger text-xs" onclick="openRemoveModal(<?= $s['id'] ?>, '<?= htmlspecialchars($s['title']) ?>')">Remove</button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- Warn Modal -->
<div id="warnModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-[#17191b] p-6 rounded-lg shadow-lg border border-[#2a2d31] w-96">
    <h3 class="text-lg font-bold mb-4">⚠️ Warn Author</h3>
    <p id="warnTitle" class="text-gray-400 mb-4"></p>
    <form method="POST" id="warnForm">
      <input type="hidden" name="action" value="warn">
      <input type="hidden" name="story_id" id="warnStoryId">
      <p class="text-sm text-gray-400 mb-4">The author will be notified that their story has been flagged.</p>
      <div class="flex gap-2">
        <button type="submit" class="flex-1 px-4 py-2 btn btn-warning">Send Warning</button>
        <button type="button" class="flex-1 px-4 py-2 btn btn-secondary" onclick="closeWarnModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Remove Modal -->
<div id="removeModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-[#17191b] p-6 rounded-lg shadow-lg border border-[#2a2d31] w-96">
    <h3 class="text-lg font-bold mb-4">🚫 Remove Story</h3>
    <p id="removeTitle" class="text-gray-400 mb-4"></p>
    <form method="POST" id="removeForm">
      <input type="hidden" name="action" value="remove">
      <input type="hidden" name="story_id" id="removeStoryId">
      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">Reason for removal</label>
        <select name="reason" required class="w-full px-3 py-2 bg-[#0f1113] border border-[#2a2d31] rounded text-white">
          <option value="Violates content policy">Violates content policy</option>
          <option value="Inappropriate content">Inappropriate content</option>
          <option value="Copyright violation">Copyright violation</option>
          <option value="Spam">Spam</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <p class="text-xs text-gray-400 mb-4">The story will be hidden but not permanently deleted. It can be restored.</p>
      <div class="flex gap-2">
        <button type="submit" class="flex-1 px-4 py-2 btn btn-danger">Remove Story</button>
        <button type="button" class="flex-1 px-4 py-2 btn btn-secondary" onclick="closeRemoveModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openWarnModal(storyId, title) {
  document.getElementById('warnStoryId').value = storyId;
  document.getElementById('warnTitle').textContent = 'Title: ' + title;
  document.getElementById('warnModal').classList.remove('hidden');
}

function closeWarnModal() {
  document.getElementById('warnModal').classList.add('hidden');
}

function openRemoveModal(storyId, title) {
  document.getElementById('removeStoryId').value = storyId;
  document.getElementById('removeTitle').textContent = 'Title: ' + title;
  document.getElementById('removeModal').classList.remove('hidden');
}

function closeRemoveModal() {
  document.getElementById('removeModal').classList.add('hidden');
}
</script>

<?php require_once 'inc/footer.php'; ?>
