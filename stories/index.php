<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/header.php';

// list stories with filters by genre, popularity, new, updated, trending
$genre = isset($_GET['genre']) ? $_GET['genre'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'new';

$sql = "SELECT s.*, u.username FROM stories s JOIN users u ON u.id = s.user_id WHERE s.status='published'";
$params = [];

if ($genre) {
  $sql .= " AND s.genre = ?";
  $params[] = $genre;
}

if ($sort === 'trending') {
  $sql .= " ORDER BY s.views DESC";
} elseif ($sort === 'updated') {
  $sql .= " ORDER BY s.updated_at DESC";
} else {
  $sql .= " ORDER BY s.created_at DESC";
}

$sql .= " LIMIT 20";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stories = $stmt->fetchAll();

// Get genres for filter
$genres = $pdo->query("SELECT DISTINCT genre FROM stories WHERE status='published' ORDER BY genre")->fetchAll();
?>

<div class="max-w-7xl mx-auto p-6">
  <h1 class="text-4xl mb-6">Browse Stories</h1>

  <div class="mb-6 flex gap-4">
    <select onchange="window.location.href='/stories/index.php?genre=' + this.value + '&sort=<?= htmlspecialchars($sort) ?>'">
      <option value="">All Genres</option>
      <?php foreach ($genres as $g): ?>
        <option value="<?= htmlspecialchars($g['genre']) ?>" <?= ($genre === $g['genre']) ? 'selected' : '' ?>><?= htmlspecialchars($g['genre']) ?></option>
      <?php endforeach; ?>
    </select>

    <select onchange="window.location.href='/stories/index.php?genre=<?= htmlspecialchars($genre) ?>&sort=' + this.value">
      <option value="new" <?= ($sort === 'new') ? 'selected' : '' ?>>Newest</option>
      <option value="updated" <?= ($sort === 'updated') ? 'selected' : '' ?>>Recently Updated</option>
      <option value="trending" <?= ($sort === 'trending') ? 'selected' : '' ?>>Trending</option>
    </select>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($stories as $s): ?>
      <a href="/stories/view.php?slug=<?= htmlspecialchars($s['slug']) ?>" class="card p-4 hover:shadow-lg transition">
        <?php if ($s['cover_image']): ?>
          <img src="<?= htmlspecialchars($s['cover_image']) ?>" alt="<?= htmlspecialchars($s['title']) ?>" class="w-full h-48 object-cover rounded mb-3">
        <?php endif; ?>
        <h3 class="text-lg font-bold"><?= htmlspecialchars($s['title']) ?></h3>
        <p class="text-sm text-gray-400">By <?= htmlspecialchars($s['username']) ?></p>
        <p class="text-sm text-gray-300 mt-2"><?= htmlspecialchars(substr($s['synopsis'], 0, 100)) ?>...</p>
        <p class="text-xs text-gray-500 mt-2"><?= htmlspecialchars($s['genre']) ?></p>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
