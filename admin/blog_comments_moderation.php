<?php
// admin/blog_comments_moderation.php - Moderate blog comments
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/topbar.php';

if (!isset($_SESSION['admin_user'])) {
    header('Location: /pages/login.php');
    exit;
}

require_once __DIR__ . '/../config.php';

// Get pending and recent comments
$stmt = $pdo->prepare("
    SELECT bc.*, a.title as blog_title, u.username, u.id as user_id
    FROM blog_comments bc
    JOIN announcements a ON bc.blog_post_id = a.id
    JOIN users u ON bc.user_id = u.id
    ORDER BY bc.is_approved ASC, bc.created_at DESC
    LIMIT 100
");
$stmt->execute();
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pending = array_filter($comments, fn($c) => !$c['is_approved']);
$approved = array_filter($comments, fn($c) => $c['is_approved']);
?>

<main class="flex-1 overflow-auto bg-background text-foreground">
    <div class="max-w-6xl mx-auto p-6 space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">💬 Blog Comments Moderation</h1>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 border-b border-border">
            <button onclick="showTab('pending')" class="tab-btn tab-active" id="tab-pending">
                ⏳ Pending (<?= count($pending) ?>)
            </button>
            <button onclick="showTab('approved')" class="tab-btn" id="tab-approved">
                ✓ Approved (<?= count($approved) ?>)
            </button>
        </div>

        <!-- Pending Comments -->
        <div id="pending-tab" class="tab-content space-y-4">
            <?php if (empty($pending)): ?>
                <div class="card p-8 text-center text-muted-foreground">
                    <p>No pending comments</p>
                </div>
            <?php else: ?>
                <?php foreach ($pending as $comment): ?>
                    <div class="card p-4 border-l-4 border-warning">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <p class="font-semibold text-sm">
                                    <a href="/blog_post.php?id=<?= $comment['blog_post_id'] ?>" class="text-primary hover:underline">
                                        <?= htmlspecialchars($comment['blog_title']) ?>
                                    </a>
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    By <a href="/profile.php?id=<?= $comment['user_id'] ?>" class="text-primary">
                                        <?= htmlspecialchars($comment['username']) ?>
                                    </a> • <?= date('M d, Y g:i A', strtotime($comment['created_at'])) ?>
                                </p>
                            </div>
                            <span class="badge badge-warning">Pending</span>
                        </div>

                        <p class="text-sm mb-4 p-3 bg-muted/50 rounded">
                            <?= htmlspecialchars($comment['comment_text']) ?>
                        </p>

                        <div class="flex gap-2">
                            <button onclick="approveComment(<?= $comment['id'] ?>)" class="btn btn-sm btn-success">
                                ✓ Approve
                            </button>
                            <button onclick="rejectComment(<?= $comment['id'] ?>)" class="btn btn-sm btn-error">
                                ✗ Reject
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Approved Comments -->
        <div id="approved-tab" class="tab-content hidden space-y-4">
            <?php if (empty($approved)): ?>
                <div class="card p-8 text-center text-muted-foreground">
                    <p>No approved comments</p>
                </div>
            <?php else: ?>
                <?php foreach ($approved as $comment): ?>
                    <div class="card p-4 border-l-4 border-success">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <p class="font-semibold text-sm">
                                    <a href="/blog_post.php?id=<?= $comment['blog_post_id'] ?>" class="text-primary hover:underline">
                                        <?= htmlspecialchars($comment['blog_title']) ?>
                                    </a>
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    By <a href="/profile.php?id=<?= $comment['user_id'] ?>" class="text-primary">
                                        <?= htmlspecialchars($comment['username']) ?>
                                    </a> • <?= date('M d, Y g:i A', strtotime($comment['created_at'])) ?>
                                </p>
                            </div>
                            <span class="badge badge-success">Approved</span>
                        </div>

                        <p class="text-sm mb-4 p-3 bg-muted/50 rounded">
                            <?= htmlspecialchars($comment['comment_text']) ?>
                        </p>

                        <button onclick="rejectComment(<?= $comment['id'] ?>)" class="btn btn-sm btn-ghost">
                            Remove
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.tab-btn {
    padding: 0.75rem 1.5rem;
    border: none;
    background: transparent;
    color: var(--muted-foreground);
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.tab-btn.tab-active {
    color: var(--foreground);
    font-weight: 600;
}

.tab-btn.tab-active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--gold) 0%, var(--light-gold) 100%);
}

.tab-content.hidden {
    display: none;
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-warning {
    background: rgba(245, 158, 11, 0.2);
    color: #fcd34d;
}

.badge-success {
    background: rgba(16, 185, 129, 0.2);
    color: #86efac;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    border: 1px solid transparent;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
}

.btn-error {
    background: #ef4444;
    color: white;
}

.btn-error:hover {
    background: #dc2626;
}

.btn-ghost {
    background: transparent;
    color: var(--foreground);
}

.btn-ghost:hover {
    background: rgba(212, 175, 55, 0.1);
}
</style>

<script>
function showTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('tab-active'));
    
    document.getElementById(tab + '-tab').classList.remove('hidden');
    document.getElementById('tab-' + tab).classList.add('tab-active');
}

async function approveComment(id) {
    const res = await fetch('/admin/ajax/approve_blog_comment.php?id=' + id, {
        method: 'POST',
        credentials: 'same-origin'
    });

    const result = await res.json();
    if (result.ok) {
        alert('Comment approved');
        location.reload();
    } else {
        alert('Error: ' + result.message);
    }
}

async function rejectComment(id) {
    if (!confirm('Delete this comment?')) return;

    const res = await fetch('/admin/ajax/delete_blog_comment.php?id=' + id, {
        method: 'POST',
        credentials: 'same-origin'
    });

    const result = await res.json();
    if (result.ok) {
        alert('Comment deleted');
        location.reload();
    } else {
        alert('Error: ' + result.message);
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
