<?php
// admin/forum-moderation.php - Moderate forum posts and comments
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireLogin();

if (!hasRole('admin')) {
    header("Location: ../pages/dashboard.php");
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $type = $_POST['type'] ?? 'thread'; // 'thread' or 'comment'
    
    if ($action === 'delete') {
        try {
            if ($type === 'thread') {
                $stmt = $pdo->prepare("DELETE FROM forum_topics WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("DELETE FROM forum_comments WHERE id = ?");
            }
            $stmt->execute([$id]);
        } catch (Exception $e) {}
    } elseif ($action === 'pin') {
        try {
            $stmt = $pdo->prepare("UPDATE forum_topics SET is_pinned = 1 WHERE id = ?");
            $stmt->execute([$id]);
        } catch (Exception $e) {}
    } elseif ($action === 'unpin') {
        try {
            $stmt = $pdo->prepare("UPDATE forum_topics SET is_pinned = 0 WHERE id = ?");
            $stmt->execute([$id]);
        } catch (Exception $e) {}
    } elseif ($action === 'approve_comment') {
        try {
            $stmt = $pdo->prepare("UPDATE forum_comments SET status = 'approved' WHERE id = ?");
            $stmt->execute([$id]);
        } catch (Exception $e) {}
    } elseif ($action === 'reject_comment') {
        try {
            $stmt = $pdo->prepare("UPDATE forum_comments SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$id]);
        } catch (Exception $e) {}
    }
    
    header("Location: forum-moderation.php");
    exit;
}

// Get tab
$tab = $_GET['tab'] ?? 'threads';

// Ensure tables exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS forum_topics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content LONGTEXT NOT NULL,
        author_id INT NOT NULL,
        category VARCHAR(100),
        is_pinned BOOLEAN DEFAULT 0,
        views INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (is_pinned),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS forum_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        topic_id INT NOT NULL,
        author_id INT NOT NULL,
        content LONGTEXT NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (topic_id) REFERENCES forum_topics(id) ON DELETE CASCADE,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (status),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {}

// Fetch data
$threads = [];
$pending_comments = [];
$flagged_content = [];

try {
    if ($tab === 'threads' || $tab === 'all') {
        $threads = $pdo->query("
            SELECT ft.*, u.username as author_name, u.email as author_email,
                   (SELECT COUNT(*) FROM forum_comments WHERE topic_id = ft.id) as comment_count
            FROM forum_topics ft
            JOIN users u ON ft.author_id = u.id
            ORDER BY ft.is_pinned DESC, ft.created_at DESC
            LIMIT 100
        ")->fetchAll();
    }
    
    if ($tab === 'comments' || $tab === 'all') {
        $pending_comments = $pdo->query("
            SELECT fc.*, ft.title as topic_title, ft.id as topic_id,
                   u.username as author_name, u.email as author_email
            FROM forum_comments fc
            JOIN forum_topics ft ON fc.topic_id = ft.id
            JOIN users u ON fc.author_id = u.id
            WHERE fc.status IN ('pending', 'rejected')
            ORDER BY fc.created_at DESC
            LIMIT 100
        ")->fetchAll();
    }
} catch (Exception $e) {}

// Get counts
try {
    $total_threads = $pdo->query("SELECT COUNT(*) FROM forum_topics")->fetchColumn();
    $pinned_threads = $pdo->query("SELECT COUNT(*) FROM forum_topics WHERE is_pinned = 1")->fetchColumn();
    $pending_comment_count = $pdo->query("SELECT COUNT(*) FROM forum_comments WHERE status = 'pending'")->fetchColumn();
    $total_comments = $pdo->query("SELECT COUNT(*) FROM forum_comments")->fetchColumn();
} catch (Exception $e) {
    $total_threads = $pinned_threads = $pending_comment_count = $total_comments = 0;
}

include dirname(__DIR__) . '/includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Forum Moderation</h2>
        </div>
        <div class="col-md-6 text-end">
            <a href="admin.php" class="btn btn-secondary">Back to Admin</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h5><?= $total_threads ?></h5>
                    <small>Total Threads</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h5><?= $pinned_threads ?></h5>
                    <small>Pinned</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-danger">
                <div class="card-body">
                    <h5><?= $pending_comment_count ?></h5>
                    <small>Pending Comments</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5><?= $total_comments ?></h5>
                    <small>Total Comments</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'threads' ? 'active' : '' ?>" href="?tab=threads">Threads</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'comments' ? 'active' : '' ?>" href="?tab=comments">Comments</a>
        </li>
    </ul>

    <!-- Threads Tab -->
    <?php if ($tab === 'threads'): ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Comments</th>
                    <th>Views</th>
                    <th>Created</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($threads as $t): ?>
                <tr>
                    <td><?= $t['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars(substr($t['title'], 0, 40)) ?></strong>
                        <?php if ($t['is_pinned']): ?><span class="badge bg-warning">PINNED</span><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($t['author_name']) ?></td>
                    <td><?= $t['comment_count'] ?></td>
                    <td><?= $t['views'] ?></td>
                    <td><small><?= date('M d, Y', strtotime($t['created_at'])) ?></small></td>
                    <td>
                        <?php if ($t['is_pinned']): ?>
                            <span class="badge bg-warning">Pinned</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Active</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="type" value="thread">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <?php if ($t['is_pinned']): ?>
                                <button name="action" value="unpin" class="btn btn-sm btn-warning">Unpin</button>
                            <?php else: ?>
                                <button name="action" value="pin" class="btn btn-sm btn-info">Pin</button>
                            <?php endif; ?>
                            <button name="action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('Delete this thread?')">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Comments Tab -->
    <?php if ($tab === 'comments'): ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Thread</th>
                    <th>Author</th>
                    <th>Content Preview</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_comments as $c): ?>
                <tr>
                    <td><?= $c['id'] ?></td>
                    <td><strong><?= htmlspecialchars(substr($c['topic_title'], 0, 30)) ?></strong></td>
                    <td><?= htmlspecialchars($c['author_name']) ?> <br><small><?= htmlspecialchars($c['author_email']) ?></small></td>
                    <td><small><?= htmlspecialchars(substr($c['content'], 0, 50)) ?>...</small></td>
                    <td>
                        <span class="badge bg-<?= $c['status'] === 'pending' ? 'warning' : 'danger' ?>">
                            <?= ucfirst($c['status']) ?>
                        </span>
                    </td>
                    <td><small><?= date('M d, Y H:i', strtotime($c['created_at'])) ?></small></td>
                    <td>
                        <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#commentModal" onclick="viewComment(<?= json_encode($c) ?>)">View</button>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="type" value="comment">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button name="action" value="approve_comment" class="btn btn-sm btn-success">Approve</button>
                            <button name="action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (empty($threads) && empty($pending_comments)): ?>
    <div class="alert alert-info">No content to moderate.</div>
    <?php endif; ?>
</div>

<!-- Comment Detail Modal -->
<div class="modal fade" id="commentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Comment #<span id="commentId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label"><strong>Thread:</strong></label>
                    <p id="commentThread"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Author:</strong></label>
                    <p id="commentAuthor"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Content:</strong></label>
                    <div class="border p-3 bg-light" id="commentContent" style="max-height: 300px; overflow-y: auto;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" onclick="alert('Delete via table actions')">Delete</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function viewComment(comment) {
    document.getElementById('commentId').textContent = comment.id;
    document.getElementById('commentThread').textContent = comment.topic_title;
    document.getElementById('commentAuthor').innerHTML = comment.author_name + ' <br><a href="mailto:' + comment.author_email + '">' + comment.author_email + '</a>';
    document.getElementById('commentContent').textContent = comment.content;
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
