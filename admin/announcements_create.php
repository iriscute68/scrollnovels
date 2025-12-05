<?php
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

require_admin();
?>

<div class="p-6">
  <h1 class="text-2xl font-bold text-[#D4AF37] mb-6">Create Announcement</h1>

  <form method="POST" class="max-w-2xl space-y-4">
    <div>
      <label class="text-[#D4AF37]">Title</label>
      <input type="text" name="title" class="w-full input mt-1" required>
    </div>

    <div>
      <label class="text-[#D4AF37]">Content</label>
      <textarea name="content" class="w-full input mt-1 h-40" required></textarea>
    </div>

    <div>
      <label class="text-[#D4AF37]">Level</label>
      <select name="level" class="w-full input mt-1">
        <option value="info">Info</option>
        <option value="notice">Notice</option>
        <option value="alert">Alert</option>
        <option value="system">System</option>
      </select>
    </div>

    <div class="flex gap-4">
      <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="show_on_ticker" value="1">
        <span class="text-[#C4B5A0]">Show on ticker</span>
      </label>

      <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="is_pinned" value="1">
        <span class="text-[#C4B5A0]">Pin announcement</span>
      </label>

      <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="notify_all" value="1">
        <span class="text-[#C4B5A0]">Notify all users</span>
      </label>
    </div>

    <button type="submit" class="btn btn-gold">Create Announcement</button>
  </form>
</div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = $_POST['title'] ?? '';
  $content = $_POST['content'] ?? '';
  $level = $_POST['level'] ?? 'info';
  $show_on_ticker = isset($_POST['show_on_ticker']) ? 1 : 0;
  $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
  $notify_all = isset($_POST['notify_all']) ? 1 : 0;

  $stmt = $pdo->prepare("INSERT INTO announcements (title, content, author_id, level, show_on_ticker, is_pinned) VALUES (?, ?, ?, ?, ?, ?)");
  $stmt->execute([htmlspecialchars($title), htmlspecialchars($content), current_user_id(), $level, $show_on_ticker, $is_pinned]);
  $announcementId = $pdo->lastInsertId();

  if ($notify_all) {
    $users = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $insert = $pdo->prepare("INSERT INTO notifications (user_id, actor_id, type, title, body, url, is_important) VALUES (?, ?, 'announcement', ?, ?, ?, ?)");

    foreach ($users as $uid) {
      $insert->execute([$uid, current_user_id(), htmlspecialchars($title), htmlspecialchars($content), '/announcements/' . $announcementId, $show_on_ticker]);
    }
  }

  echo "<script>alert('Announcement created!'); window.location='/admin/announcements.php';</script>";
}
?>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
