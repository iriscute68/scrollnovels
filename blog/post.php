<?php
// blog/post.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';

$slug = isset($_GET['slug']) ? $_GET['slug'] : '';
if (!$slug) { header('Location:/blog'); exit; }

$stmt = $pdo->prepare("SELECT p.*, u.username FROM posts p JOIN users u ON u.id = p.user_id WHERE p.slug = ? AND p.status = 'published' LIMIT 1");
$stmt->execute([$slug]);
$post = $stmt->fetch();
if (!$post) { http_response_code(404); echo "Not found"; exit; }

// increment views (simple)
$pdo->prepare("UPDATE posts SET views = views + 1 WHERE id = ?")->execute([$post['id']]);

$blocks = json_decode($post['blocks'], true);
?>
<main class="max-w-3xl mx-auto p-6">
  <div class="mb-4">
    <h1 class="text-4xl"><?= htmlspecialchars($post['title']) ?></h1>
    <div class="muted">By <?= htmlspecialchars($post['username']) ?> • <?= htmlspecialchars($post['category']) ?> • <?= date('M j, Y', strtotime($post['published_at'])) ?></div>
  </div>

  <?php if (!empty($post['cover_image'])): ?>
    <div class="mb-6"><img src="<?= htmlspecialchars($post['cover_image']) ?>" style="width:100%;height:auto"></div>
  <?php endif; ?>

  <div class="prose">
    <?php foreach ($blocks as $b): ?>
      <?php if ($b['type'] === 'text'): ?>
        <?php
           $html = htmlspecialchars($b['content']);
           $html = preg_replace('/\n## (.+)\n?/', '<h2>$1</h2>', $html);
           $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
           $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
           $html = nl2br($html);
        ?>
        <div><?= $html ?></div>
      <?php elseif ($b['type'] === 'image'): ?>
        <figure>
          <img src="<?= htmlspecialchars($b['url']) ?>" style="max-width:100%;height:auto">
          <?php if (!empty($b['caption'])): ?><figcaption class="muted"><?= htmlspecialchars($b['caption']) ?></figcaption><?php endif; ?>
        </figure>
      <?php elseif ($b['type'] === 'link'): ?>
        <p><a href="<?= htmlspecialchars($b['url']) ?>"><?= htmlspecialchars($b['label'] ?: $b['url']) ?></a></p>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <!-- Comments / Engagement hook (simple) -->
  <div class="mt-10">
    <h3>Comments</h3>
    <!-- integrate existing comments system -->
  </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

