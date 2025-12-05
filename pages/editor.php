<?php
// editor.php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config.php';
requireLogin();
if (!hasRole('editor')) {
    // Show an access denied message instead of silently redirecting to dashboard
    require_once dirname(__DIR__) . '/includes/header.php';
    echo '<main class="max-w-4xl mx-auto p-8"><div class="bg-white dark:bg-gray-800 p-6 rounded shadow border border-emerald-200 dark:border-emerald-900"><h2 class="text-2xl font-bold text-red-600">Access denied</h2><p class="mt-3 text-gray-600">You do not have editor access. If you think this is an error, please contact support or apply for editor access.</p></div></main>';
    require_once dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$editor_id = $_SESSION['user_id'];

$assignments = $pdo->prepare("
    SELECT ea.*, s.title, s.slug, u.username as author
    FROM editor_assignments ea
    JOIN stories s ON ea.story_id = s.id
    JOIN users u ON s.author_id = u.id
    WHERE ea.editor_id = ?
    ORDER BY ea.created_at DESC
");
$assignments->execute([$editor_id]);
$assignments = $assignments->fetchAll();
?>

<div class="container mt-4">
    <h2>My Assigned Stories</h2>

    <?php foreach ($assignments as $a): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <strong><a href="<?= rtrim(SITE_URL, '/') ?>/pages/story.php?slug=<?= urlencode($a['slug']) ?>"><?= htmlspecialchars($a['title']) ?></a></strong>
                <span class="badge bg-<?= $a['status'] == 'approved' ? 'success' : ($a['status'] == 'rejected' ? 'danger' : 'warning') ?>">
                    <?= ucfirst($a['status']) ?>
                </span>
            </div>
            <div class="card-body">
                <p><strong>Author:</strong> <?= htmlspecialchars($a['author']) ?></p>
                <?php if ($a['feedback']): ?>
                    <p><strong>Your Feedback:</strong> <?= nl2br(htmlspecialchars($a['feedback'])) ?></p>
                <?php endif; ?>

                <?php if ($a['status'] == 'pending'): ?>
                    <form action="<?= rtrim(SITE_URL, '/') ?>/api/editor-feedback.php" method="POST" class="mt-3">
                        <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                        <div class="mb-3">
                            <label>Feedback</label>
                            <textarea name="feedback" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="btn-group">
                            <button type="submit" name="action" value="review" class="btn btn-warning">Submit for Review</button>
                            <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="text-muted">Status updated on <?= date('M j, Y g:i A', strtotime($a['updated_at'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty($assignments)): ?>
        <p class="text-center text-muted">No stories assigned yet.</p>
    <?php endif; ?>
</div>
</body>
</html>
