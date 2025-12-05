<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/header.php';

$slug = isset($_GET['slug']) ? $_GET['slug'] : '';
if (!$slug) { header('Location:/stories'); exit; }

$stmt = $pdo->prepare("SELECT s.*, u.username FROM stories s JOIN users u ON u.id = s.user_id WHERE s.slug = ? AND s.status = 'published' LIMIT 1");
$stmt->execute([$slug]);
$story = $stmt->fetch();
if (!$story) { http_response_code(404); echo "Not found"; exit; }

// increment views
$pdo->prepare("UPDATE stories SET views = views + 1 WHERE id = ?")->execute([$story['id']]);

// Get chapters (compat: try `number` then `sequence`)
$chapters = $pdo->query("SELECT *, COALESCE(number, sequence) AS chapter_order FROM chapters WHERE story_id = " . $story['id'] . " AND status='published' ORDER BY COALESCE(number, sequence)")->fetchAll();

// Get ratings
$stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_votes FROM story_ratings WHERE story_id = ?");
$stmt->execute([$story['id']]);
$ratings = $stmt->fetch();
?>

<main class="max-w-4xl mx-auto p-6">
  <div class="mb-6">
    <?php if ($story['cover_image']): ?>
      <img src="<?= htmlspecialchars($story['cover_image']) ?>" alt="<?= htmlspecialchars($story['title']) ?>" class="w-full h-64 object-cover rounded mb-6">
    <?php endif; ?>

    <h1 class="text-4xl font-bold"><?= htmlspecialchars($story['title']) ?></h1>
    <p class="text-lg text-gray-400">By <?= htmlspecialchars($story['username']) ?> • <?= htmlspecialchars($story['genre']) ?></p>
    
    <div class="mt-4 flex gap-6">
      <div>📖 <?= count($chapters) ?> Chapters</div>
      <div>👁️ <?= number_format($story['views'] ?? 0) ?> Views</div>
      <?php if ($ratings['avg_rating']): ?>
        <div>⭐ <?= round($ratings['avg_rating'], 1) ?> (<?= $ratings['total_votes'] ?> votes)</div>
      <?php endif; ?>
    </div>

    <p class="text-gray-300 mt-4"><?= htmlspecialchars($story['synopsis']) ?></p>
  </div>

  <!-- Table of Contents -->
  <div class="mt-8 mb-8">
    <h2 class="text-2xl font-bold mb-4">Chapters</h2>
    <div class="space-y-2">
      <?php foreach ($chapters as $ch): ?>
        <a href="/stories/chapter.php?id=<?= $ch['id'] ?>" class="block p-3 card hover:shadow-lg transition">
          <span class="font-bold"><?= htmlspecialchars($ch['title']) ?></span>
          <span class="text-sm text-gray-400 float-right"><?= number_format($ch['word_count']) ?> words</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
