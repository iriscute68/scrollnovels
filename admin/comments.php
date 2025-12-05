<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';

$comments = [];
try {
    $stmt = $pdo->query("SELECT c.*, u.username FROM comments c LEFT JOIN users u ON c.user_id = u.id ORDER BY c.created_at DESC LIMIT 200");
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $comments = [];
}
?>
<main class="admin-main">
    <?php require __DIR__ . '/topbar.php'; ?>
    <h1 class="text-2xl font-bold mb-4">Comments Moderation</h1>
    <div class="space-y-4">
        <?php foreach($comments as $c): ?>
            <div class="card p-4">
                <div class="flex justify-between items-start">
                    <div>
                        <strong><?= htmlspecialchars($c['username'] ?? 'Anonymous') ?></strong>
                        <div class="text-sm text-gray-400"><?= date('M j, Y H:i', strtotime($c['created_at'])) ?></div>
                        <p class="mt-2 text-sm"><?= nl2br(htmlspecialchars($c['content'])) ?></p>
                    </div>
                    <div class="space-y-2 text-right">
                        <form method="POST" action="comments_action.php">
                            <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
                            <button name="action" value="delete" class="btn btn-secondary">Delete</button>
                            <button name="action" value="warn" class="btn btn-primary">Warn</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>
<?php
// admin/comments.php - simple comments moderation list
require_once 'inc/header.php';
$activeTab = 'comments';
require_once 'inc/sidebar.php';
require_once 'inc/db.php';

// Fetch recent comments
try {
    $comments = $pdo->query("SELECT c.id, c.user_id, c.story_id, c.chapter_id, c.content, c.created_at, u.username
        FROM comments c LEFT JOIN users u ON u.id = c.user_id
        ORDER BY c.created_at DESC LIMIT 200")->fetchAll() ?? [];
} catch (Exception $e) {
    $comments = [];
}
?>

<main class="flex-1 p-6 ml-64">
  <div class="mb-6">
    <h2 class="text-2xl font-bold">💬 Comments Moderation</h2>
    <p class="text-gray-400">Review and moderate user comments</p>
  </div>

  <div class="card">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-[#1f2937]">
          <th class="p-3 text-left">User</th>
          <th class="p-3 text-left">Comment</th>
          <th class="p-3 text-left">Target</th>
          <th class="p-3 text-left">Date</th>
          <th class="p-3 text-left">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($comments)): ?>
          <tr><td colspan="5" class="p-3 text-center text-gray-500">No comments found</td></tr>
        <?php else: foreach ($comments as $c): ?>
          <tr class="border-b border-[#1f2937] hover:bg-[#0f1113]">
            <td class="p-3 font-semibold"><?= htmlspecialchars($c['username'] ?? 'Guest') ?></td>
            <td class="p-3 text-gray-300"><?= nl2br(htmlspecialchars(substr($c['content'],0,300))) ?></td>
            <td class="p-3 text-gray-400">Story #<?= $c['story_id'] ?? 'N/A' ?> <?= $c['chapter_id'] ? (' / Chapter #' . $c['chapter_id']) : '' ?></td>
            <td class="p-3 text-gray-400"><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
            <td class="p-3">
              <form method="POST" action="comments_action.php" style="display:inline;">
                <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                <button type="submit" name="action" value="delete" class="px-2 py-1 btn btn-danger" onclick="return confirm('Delete comment?')">Delete</button>
                <button type="submit" name="action" value="warn" class="px-2 py-1 btn btn-warning ml-2">Warn User</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</main>

<?php require_once 'inc/footer.php'; ?>
