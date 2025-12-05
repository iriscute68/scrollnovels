<?php
// admin/chapters.php - Manage story chapters
require_once 'inc/header.php';
$activeTab = 'chapters';
require_once 'inc/sidebar.php';

// Fetch chapters with story info
try {
    $chapters = $pdo->query("
        SELECT c.*, s.title as story_title, u.username as author 
        FROM chapters c
        LEFT JOIN stories s ON c.story_id = s.id
        LEFT JOIN users u ON s.author_id = u.id
        ORDER BY c.story_id DESC, c.sequence DESC
        LIMIT 100
    ")->fetchAll() ?? [];
} catch (Exception $e) {
    $chapters = [];
}

// Handle chapter deletion/approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $chapter_id = (int)($_POST['chapter_id'] ?? 0);
    
    if ($chapter_id) {
        try {
            if ($action === 'delete') {
                $pdo->prepare("DELETE FROM chapters WHERE id = ?")->execute([$chapter_id]);
                $success = "Chapter deleted";
            } elseif ($action === 'approve') {
                $pdo->prepare("UPDATE chapters SET status = 'published' WHERE id = ?")->execute([$chapter_id]);
                $success = "Chapter approved";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<main class="flex-1 p-6 ml-64">
  <div class="mb-6">
    <h2 class="text-2xl font-bold">📖 Chapter Management</h2>
    <p class="text-gray-400">Manage story chapters and approve new submissions</p>
  </div>

  <?php if (isset($success)): ?>
    <div class="mb-4 p-4 bg-green-900/30 border border-green-600 text-green-400 rounded-lg">✓ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  
  <?php if (isset($error)): ?>
    <div class="mb-4 p-4 bg-red-900/30 border border-red-600 text-red-400 rounded-lg">✕ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-[#1f2937]">
            <th class="text-left p-3">Chapter Title</th>
            <th class="text-left p-3">Story</th>
            <th class="text-left p-3">Author</th>
            <th class="text-left p-3">Status</th>
            <th class="text-left p-3">Created</th>
            <th class="text-left p-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($chapters)): ?>
            <tr>
              <td colspan="6" class="p-3 text-center text-gray-500">No chapters yet</td>
            </tr>
          <?php else: ?>
            <?php foreach ($chapters as $ch): ?>
              <tr class="border-b border-[#1f2937] hover:bg-[#0f1113]">
                <td class="p-3 font-semibold"><?= htmlspecialchars(substr($ch['title'] ?? 'Untitled', 0, 40)) ?></td>
                <td class="p-3 text-gray-400"><?= htmlspecialchars(substr($ch['story_title'] ?? 'Unknown', 0, 30)) ?></td>
                <td class="p-3 text-gray-400"><?= htmlspecialchars($ch['author'] ?? 'Unknown') ?></td>
                <td class="p-3">
                  <?php 
                  $status = $ch['status'] ?? 'draft';
                  $badge_class = $status === 'published' ? 'bg-green-600/30 text-green-300' : 'bg-yellow-600/30 text-yellow-300';
                  ?>
                  <span class="px-2 py-1 rounded text-xs <?= $badge_class ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                </td>
                <td class="p-3 text-gray-400"><?= date('M d, Y', strtotime($ch['created_at'])) ?></td>
                <td class="p-3 space-x-1">
                  <?php if (($ch['status'] ?? 'draft') !== 'published'): ?>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="chapter_id" value="<?= $ch['id'] ?>">
                      <button type="submit" class="px-2 py-1 btn btn-success text-xs">Approve</button>
                    </form>
                  <?php endif; ?>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="chapter_id" value="<?= $ch['id'] ?>">
                    <button type="submit" class="px-2 py-1 btn btn-danger text-xs" onclick="return confirm('Delete this chapter?')">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<?php require_once 'inc/footer.php'; ?>
