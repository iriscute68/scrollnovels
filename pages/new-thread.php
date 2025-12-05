<?php
// new-thread.php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireLogin();

$categories = $pdo->query("SELECT id, name FROM forum_categories")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $category_id = (int)$_POST['category_id'];

    if (strlen($title) < 5 || strlen($content) < 10) {
        $error = "Title or content too short";
    } else {
        $slug = strtolower(preg_replace('/[^a-z0-9-]+/', '-', $title));
        $slug = trim($slug, '-');

        $stmt = $pdo->prepare("INSERT INTO forum_topics (title, slug, content, category_id, author_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $slug, $content, $category_id, $_SESSION['user_id']]);
        $thread_id = $pdo->lastInsertId();

        header("Location: thread.php?id=$thread_id");
        exit;
    }
}
?>

<?php
    $page_title = 'New Thread - Scroll Novels';
    $page_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
    require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <h2>Create New Thread</h2>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="card p-4">
        <div class="mb-3">
            <label>Title</label>
            <input type="text" name="title" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Category</label>
            <select name="category_id" class="form-select" required>
                <option value="">-- Select --</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label>Content</label>
            <textarea name="content" class="form-control" rows="8" required></textarea>
        </div>
        <button type="submit" class="btn btn-success">Post Thread</button>
        <a href="forum.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
