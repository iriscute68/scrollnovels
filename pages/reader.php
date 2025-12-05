<?php
// pages/reader.php - simple chapter reader
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';

$chapter_id = isset($_GET['chapter_id']) ? (int)$_GET['chapter_id'] : 0;
if (!$chapter_id) {
    echo "<main class='max-w-4xl mx-auto p-6'><p>Chapter not found.</p></main>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT c.*, s.title as story_title, s.id as story_id, u.username as author_name FROM chapters c LEFT JOIN stories s ON c.story_id = s.id LEFT JOIN users u ON s.author_id = u.id WHERE c.id = ? LIMIT 1");
$stmt->execute([$chapter_id]);
$ch = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ch) {
    echo "<main class='max-w-4xl mx-auto p-6'><p>Chapter not found.</p></main>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

?>
<main class="max-w-4xl mx-auto p-6">
    <article class="prose dark:prose-dark">
        <h1><?= htmlspecialchars($ch['title']) ?></h1>
        <p class="muted small">From <a href="<?= site_url('/pages/book.php?id=' . (int)$ch['story_id']) ?>"><?= htmlspecialchars($ch['story_title']) ?></a> — By <?= htmlspecialchars($ch['author_name']) ?></p>
        <hr>
        <div class="chapter-content">
            <?= nl2br(htmlspecialchars($ch['content'] ?? '')) ?>
        </div>
    </article>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>

