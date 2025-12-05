<?php
require_once __DIR__ . '/inc/header.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

require_login();

$uid = current_user_id();
$page = intval($_GET['page'] ?? 1);
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$limit = 20;
$offset = ($page - 1) * $limit;

$where = "WHERE user_id = ?";
$params = [$uid];

if ($filter === 'unread') {
  $where .= " AND is_read = 0";
} elseif ($filter !== 'all') {
  $where .= " AND type = ?";
  $params[] = $filter;
}

$stmt = $pdo->prepare("SELECT * FROM notifications $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params, [$limit, $offset]));
$notifications = $stmt->fetchAll();

$total = $pdo->prepare("SELECT COUNT(*) FROM notifications $where");
$total->execute($params);
$totalCount = $total->fetchColumn();
$totalPages = ceil($totalCount / $limit);
?>

<div class="p-6 max-w-4xl mx-auto">
  <h1 class="text-3xl font-bold text-[#D4AF37] mb-6">Notifications</h1>

  <div class="flex gap-3 mb-6">
    <a href="?filter=all" class="px-4 py-2 <?= $filter === 'all' ? 'bg-[#D4AF37] text-[#120A2A]' : 'bg-[#1a0f3a] border border-[#D4AF37]/30' ?> rounded">All</a>
    <a href="?filter=unread" class="px-4 py-2 <?= $filter === 'unread' ? 'bg-[#D4AF37] text-[#120A2A]' : 'bg-[#1a0f3a] border border-[#D4AF37]/30' ?> rounded">Unread</a>
    <a href="?filter=announcement" class="px-4 py-2 <?= $filter === 'announcement' ? 'bg-[#D4AF37] text-[#120A2A]' : 'bg-[#1a0f3a] border border-[#D4AF37]/30' ?> rounded">Announcements</a>
    <a href="?filter=payout" class="px-4 py-2 <?= $filter === 'payout' ? 'bg-[#D4AF37] text-[#120A2A]' : 'bg-[#1a0f3a] border border-[#D4AF37]/30' ?> rounded">Payouts</a>
  </div>

  <div class="space-y-3">
    <?php foreach ($notifications as $n): ?>
      <div class="card p-4 border-l-4 border-[#D4AF37] hover:bg-[#160b2a] transition-colors">
        <div class="flex justify-between items-start">
          <div>
            <h3 class="font-bold text-[#D4AF37]"><?= htmlspecialchars($n['title']) ?></h3>
            <p class="text-[#C4B5A0] text-sm mt-1"><?= htmlspecialchars($n['body'] ?? '') ?></p>
            <p class="text-xs text-[#8B7D6B] mt-2"><?= date('M d, Y H:i', strtotime($n['created_at'])) ?></p>
          </div>
          <?php if (!$n['is_read']): ?>
            <span class="w-3 h-3 bg-[#D4AF37] rounded-full"></span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($totalPages > 1): ?>
    <div class="mt-6 flex gap-2 justify-center">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>&filter=<?= htmlspecialchars($filter) ?>" class="px-3 py-2 <?= $page === $i ? 'bg-[#D4AF37]' : 'bg-[#1a0f3a] border' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
