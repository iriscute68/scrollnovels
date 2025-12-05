<?php
// forum.php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';

$search = trim($_GET['q'] ?? '');
$category_filter = $_GET['cat'] ?? '';

// Fetch categories
$categories = $pdo->query("SELECT id, name, description FROM forum_categories")->fetchAll();

// Fetch topics
$where = [];
$params = [];

if ($search) {
    $where[] = "(t.title LIKE ? OR t.content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($category_filter) {
    $where[] = "t.category_id = ?";
    $params[] = $category_filter;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT t.*, u.username, c.name as cat_name,
           (SELECT COUNT(*) FROM discussion_replies WHERE topic_id = t.id) AS reply_count,
           (SELECT created_at FROM discussion_replies WHERE topic_id = t.id ORDER BY created_at DESC LIMIT 1) AS last_reply
    FROM forum_topics t
    JOIN users u ON t.author_id = u.id
    LEFT JOIN forum_categories c ON t.category_id = c.id
    $where_sql
    ORDER BY t.pinned DESC, last_reply DESC, t.created_at DESC
    LIMIT 50
");
$stmt->execute($params);
$topics = $stmt->fetchAll();
?>
<?php
    $page_title = 'Forum - Scroll Novels';
    $page_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">'
        . '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';
    require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <!-- Categories -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-header"><strong>Categories</strong></div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item <?= !$category_filter ? 'active' : '' ?>">
                        <a href="forum.php" class="text-decoration-none">All Topics</a>
                    </li>
                    <?php foreach ($categories as $cat): ?>
                        <li class="list-group-item <?= $category_filter == $cat['id'] ? 'active' : '' ?>">
                            <a href="?cat=<?= $cat['id'] ?>" class="text-decoration-none">
                                <?= htmlspecialchars($cat['name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Topics -->
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Community Forum</h3>
                <a href="new-thread.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Thread</a>
            </div>

            <!-- Search -->
            <form class="mb-3">
                <div class="input-group">
                    <input type="text" name="q" class="form-control" placeholder="Search threads..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-outline-secondary"><i class="fas fa-search"></i></button>
                </div>
            </form>

            <!-- Topics List -->
            <?php if ($topics): ?>
                <div class="list-group">
                    <?php foreach ($topics as $t): ?>
                        <a href="thread.php?id=<?= $t['id'] ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">
                                    <?php if ($t['pinned']): ?><i class="fas fa-thumbtack text-warning"></i> <?php endif; ?>
                                    <?= htmlspecialchars($t['title']) ?>
                                </h5>
                                <small><?= date('M j, Y', strtotime($t['created_at'])) ?></small>
                            </div>
                            <p class="mb-1 text-muted">
                                by <strong><?= htmlspecialchars($t['username']) ?></strong>
                                in <em><?= htmlspecialchars($t['cat_name'] ?? 'General') ?></em>
                            </p>
                            <small>
                                <?= $t['reply_count'] ?> replies
                                <?php if ($t['last_reply']): ?>
                                    • Last: <?= date('g:i A', strtotime($t['last_reply'])) ?>
                                <?php endif; ?>
                            </small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-muted">No threads found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
