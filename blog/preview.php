<?php
// blog/preview.php
require_once __DIR__ . '/../includes/header.php';

$title = isset($_POST['title']) ? $_POST['title'] : '';
$category = isset($_POST['category']) ? $_POST['category'] : '';
$tags = isset($_POST['tags']) ? $_POST['tags'] : '';
$excerpt = isset($_POST['excerpt']) ? $_POST['excerpt'] : '';
$cover = isset($_POST['cover_image']) ? $_POST['cover_image'] : '';
$blocks = json_decode(isset($_POST['blocks']) ? $_POST['blocks'] : '[]', true);

?>
<div class="max-w-3xl mx-auto p-6">
  <div class="mb-6">
    <h1 class="text-4xl"><?= htmlspecialchars($title) ?></h1>
    <div class="muted"><?= htmlspecialchars($category) ?> • <?= htmlspecialchars($tags) ?></div>
  </div>

  <?php if ($cover): ?>
    <div class="mb-4"><img src="<?= htmlspecialchars($cover) ?>" style="width:100%;height:auto"></div>
  <?php endif; ?>

  <div class="prose">
    <?php foreach ($blocks as $b): ?>
      <?php if ($b['type'] === 'text'): ?>
        <?php
           // Very small markdown-like renderer: convert ## headers and **bold**, *italic*
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

