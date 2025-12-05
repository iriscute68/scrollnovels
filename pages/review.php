<?php
// review.php?story=123
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
$story_id = (int)$_GET['story'];
$story = $pdo->query("SELECT title, slug FROM stories WHERE id=$story_id")->fetch();

$reviews = $pdo->query("
    SELECT r.*, u.username, u.profile_image, COALESCE(SUM(v.vote),0) as votes
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN review_votes v ON v.review_id = r.id
    WHERE r.story_id = $story_id
    GROUP BY r.id
    ORDER BY votes DESC, r.created_at DESC
")->fetchAll();
?>

<?php
    $page_title = 'Reviews - ' . htmlspecialchars($story['title']);
    $page_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
    require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <h3>Reviews for <a href="<?= rtrim(SITE_URL, '/') ?>/pages/story.php?slug=<?= urlencode($story['slug']) ?>"><?= htmlspecialchars($story['title']) ?></a></h3>

    <?php if (isLoggedIn()): ?>
    <form action="<?= rtrim(SITE_URL, '/') ?>/api/submit-review.php" method="POST" class="card p-3 mb-4">
        <input type="hidden" name="story_id" value="<?= $story_id ?>">
        <div class="mb-3">
            <label>Rating</label>
            <select name="rating" class="form-select" required>
                <option>5</option><option>4</option><option>3</option><option>2</option><option>1</option>
            </select>
        </div>
        <textarea name="content" class="form-control" rows="3" placeholder="Write your review..." required></textarea>
        <button type="submit" class="btn btn-primary mt-2">Post Review</button>
    </form>
    <?php endif; ?>

    <?php foreach ($reviews as $r): ?>
    <div class="border rounded p-3 mb-3">
        <div class="d-flex">
            <img src="<?= htmlspecialchars($r['avatar']??'/assets/default-avatar.png') ?>" width="40" class="rounded-circle">
            <div class="ms-3 flex-grow-1">
                <strong><?= htmlspecialchars($r['username']) ?></strong>
                <span class="text-warning">★<?= $r['rating'] ?></span>
                <small class="text-muted float-end"><?= date('M j', strtotime($r['created_at'])) ?></small>
                <p class="mt-1"><?= nl2br(htmlspecialchars($r['content'])) ?></p>
                <div>
                    <button class="btn btn-sm upvote" data-id="<?= $r['id'] ?>">+<?= $r['votes'] ?></button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$('.upvote').click(function() {
    const id = $(this).data('id');
    $.post('/api/vote-review.php', { review_id: id, vote: 1 }, () => location.reload());
});
</script>
</body></html>
