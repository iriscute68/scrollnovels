<?php
// thread.php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';

$thread_id = (int)($_GET['id'] ?? 0);
if (!$thread_id) die("Invalid thread");

// Fetch thread
$stmt = $pdo->prepare("
    SELECT t.*, u.username, u.profile_image, c.name as cat_name
    FROM forum_topics t
    JOIN users u ON t.author_id = u.id
    LEFT JOIN forum_categories c ON t.category_id = c.id
    WHERE t.id = ?
");
$stmt->execute([$thread_id]);
$thread = $stmt->fetch();
if (!$thread) die("Thread not found");

// Increment views
$pdo->prepare("UPDATE forum_topics SET views = views + 1 WHERE id = ?")->execute([$thread_id]);

// Fetch replies (recursive)
function getReplies($pdo, $parent_id = null, $thread_id, $depth = 0) {
    $sql = "SELECT r.*, u.username, u.profile_image
            FROM discussion_replies r
            JOIN users u ON r.author_id = u.id
            WHERE r.topic_id = ? AND r.parent_id " . ($parent_id === null ? "IS NULL" : "= ?") . "
            ORDER BY r.created_at ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parent_id === null ? [$thread_id] : [$thread_id, $parent_id]);
    $replies = $stmt->fetchAll();

    foreach ($replies as &$r) {
        $r['depth'] = $depth;
        $r['children'] = getReplies($pdo, $r['id'], $thread_id, $depth + 1);
    }
    return $replies;
}
$replies = getReplies($pdo, null, $thread_id);
?>

<?php
    $page_title = htmlspecialchars($thread['title']) . ' - Scroll Novels';
    // Use site-wide Tailwind styles; avoid loading Bootstrap to keep header/footer consistent
    $page_head = '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">'
        . '<style>.reply{margin-left:0;border-left:0;padding-left:0}.upvote-btn{cursor:pointer}.upvote-btn.voted{color:#e74c3c}</style>';

    require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-5xl mx-auto px-4 py-8">
    <!-- Thread -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6 border border-emerald-200 dark:border-emerald-900">
        <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
                <h2 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400 mb-1"><?= htmlspecialchars($thread['title']) ?></h2>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    by <strong class="text-emerald-700 dark:text-emerald-300"><?= htmlspecialchars($thread['username']) ?></strong>
                    · <?= htmlspecialchars($thread['cat_name'] ?? 'General') ?> · <?= $thread['views'] ?> views
                </div>
            </div>
            <?php if (hasRole('admin') || ($_SESSION['user_id'] ?? 0) == $thread['author_id']): ?>
                <div class="ml-4 space-x-2 flex">
                    <?php if (hasRole('admin')): ?>
                        <!-- Pin Button -->
                        <form method="POST" action="<?= rtrim(SITE_URL, '/') ?>/api/pin-thread.php" style="display:inline;">
                            <input type="hidden" name="thread_id" value="<?= $thread_id ?>">
                            <button type="submit" class="px-3 py-1 text-sm bg-amber-400 hover:bg-amber-500 rounded text-white"> 
                                <i class="fas fa-thumbtack mr-1"></i> <?= $thread['pinned'] ? 'Unpin' : 'Pin' ?>
                            </button>
                        </form>
                        
                        <!-- Lock Button -->
                        <button id="lock-btn" class="px-3 py-1 text-sm bg-orange-500 hover:bg-orange-600 rounded text-white" onclick="toggleLockThread()">
                            <i class="fas fa-<?= $thread['status'] === 'closed' ? 'unlock' : 'lock' ?> mr-1"></i> 
                            <?= $thread['status'] === 'closed' ? 'Unlock' : 'Lock' ?>
                        </button>
                        
                        <!-- Delete Button -->
                        <button id="delete-btn" class="px-3 py-1 text-sm bg-red-500 hover:bg-red-600 rounded text-white" onclick="deleteThread()">
                            <i class="fas fa-trash mr-1"></i> Delete
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="prose max-w-none text-gray-800 dark:text-gray-100 mb-4">
            <?php
            function formatReplyContent($text) {
                // Escape first
                $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
                // Auto-link URLs and render direct image links as <img>
                $urlPattern = '~(https?://[^\s<>"\']+)~i';
                $safe = preg_replace_callback($urlPattern, function($m) {
                    $url = $m[1];
                    $lower = strtolower($url);
                    if (preg_match('/\.(?:png|jpe?g|gif|webp)(?:[?].*)?$/i', $lower)) {
                        return '<img src="' . $url . '" alt="image" style="max-width:100%;height:auto;border-radius:8px;margin:8px 0;">';
                    }
                    $escaped = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                    return '<a href="' . $escaped . '" target="_blank" rel="noopener noreferrer nofollow">' . $escaped . '</a>';
                }, $safe);
                return nl2br($safe);
            }

            echo formatReplyContent($thread['content']);
            ?>
        </div>

        <div class="text-sm text-gray-500">Posted on <?= date('M j, Y g:i A', strtotime($thread['created_at'])) ?></div>
    </div>

    <!-- Reply Form -->
    <?php if (isLoggedIn()): ?>
        <?php if ($thread['status'] === 'closed'): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-lock mr-2"></i> This thread is locked and cannot receive new replies.
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-body">
                    <form id="reply-form">
                        <input type="hidden" name="thread_id" value="<?= $thread_id ?>">
                        <input type="hidden" name="parent_id" value="">
                        <textarea name="content" class="form-control" rows="4" placeholder="Write a reply..." required></textarea>
                        <button type="submit" class="btn btn-primary mt-2">Post Reply</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="card mb-4">
            <div class="card-body">
                <p class="text-gray-600 dark:text-gray-400"><a href="/pages/login.php" class="text-emerald-500 hover:text-emerald-600">Log in</a> to reply to this thread.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Replies -->
    <div id="replies">
        <?php
        // Render replies recursively
        function renderReply($r) {
            ?>
            <div class="card mb-3 reply depth-<?= $r['depth'] ?>">
                <div class="card-body">
                    <div class="d-flex align-items-start">
                        <img src="<?= htmlspecialchars($r['avatar'] ?? '/assets/default-avatar.png') ?>" width="40" class="rounded-circle me-3">
                        <div class="flex-grow-1">
                            <strong><?= htmlspecialchars($r['username']) ?></strong>
                            <small class="text-muted"> • <?= date('M j, Y g:i A', strtotime($r['created_at'])) ?></small>
                            <p class="mt-2"><?php echo formatReplyContent($r['content']); ?></p>
                            <div>
                                <span class="upvote-btn <?= $r['voted'] ? 'voted' : '' ?>" data-id="<?= $r['id'] ?>">
                                    <i class="fas fa-arrow-up"></i> <span><?= $r['upvotes'] ?></span>
                                </span>
                                <a href="#" class="ms-3 text-muted reply-link" data-parent="<?= $r['id'] ?>">Reply</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if (!empty($r['children'])): ?>
                    <div class="replies ms-5">
                        <?php foreach ($r['children'] as $child) { renderReply($child); } ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }

        foreach ($replies as $r) { renderReply($r); }
        ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$('#reply-form').on('submit', async function(e) {
    e.preventDefault();
    const data = $(this).serialize();
    await $.post('/api/post-reply.php', data);
    location.reload();
});

$(document).on('click', '.reply-link', function(e) {
    e.preventDefault();
    const parent = $(this).data('parent');
    $('#reply-form [name="parent_id"]').val(parent);
    $('#reply-form textarea').focus().attr('placeholder', 'Reply to this comment...');
    $('html, body').animate({ scrollTop: $('#reply-form').offset().top }, 500);
});

$(document).on('click', '.upvote-btn', async function() {
    const btn = $(this);
    const id = btn.data('id');
    const res = await fetch('/api/upvote-reply.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ reply_id: id })
    });
    const json = await res.json();
    if (json.success) {
        btn.toggleClass('voted');
        btn.find('span').text(json.count);
    }
});

// Lock/Unlock thread
async function toggleLockThread() {
    const threadId = <?= $thread_id ?>;
    const currentStatus = '<?= $thread['status'] ?>';
    
    if (!confirm('Are you sure you want to ' + (currentStatus === 'closed' ? 'unlock' : 'lock') + ' this thread?')) {
        return;
    }
    
    try {
        const res = await fetch('/api/lock-thread.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ thread_id: threadId })
        });
        
        const json = await res.json();
        if (json.success) {
            const newStatus = json.new_status;
            const btn = document.getElementById('lock-btn');
            
            if (newStatus === 'closed') {
                btn.innerHTML = '<i class="fas fa-unlock mr-1"></i> Unlock';
                btn.classList.remove('bg-orange-500', 'hover:bg-orange-600');
                btn.classList.add('bg-blue-500', 'hover:bg-blue-600');
                alert('Thread locked successfully');
            } else {
                btn.innerHTML = '<i class="fas fa-lock mr-1"></i> Lock';
                btn.classList.remove('bg-blue-500', 'hover:bg-blue-600');
                btn.classList.add('bg-orange-500', 'hover:bg-orange-600');
                alert('Thread unlocked successfully');
            }
            
            // Disable reply form if thread is locked
            if (newStatus === 'closed') {
                const form = document.getElementById('reply-form');
                if (form) {
                    form.style.display = 'none';
                    const msg = document.createElement('div');
                    msg.className = 'bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4';
                    msg.innerHTML = '<i class="fas fa-lock mr-2"></i> This thread is locked and cannot receive new replies.';
                    form.parentNode.insertBefore(msg, form);
                }
            }
        } else {
            alert('Error: ' + (json.error || 'Failed to lock/unlock thread'));
        }
    } catch (error) {
        console.error('Lock error:', error);
        alert('Error: Failed to lock/unlock thread');
    }
}

// Delete thread
async function deleteThread() {
    const threadId = <?= $thread_id ?>;
    
    if (!confirm('Are you sure you want to delete this entire thread? This action cannot be undone.')) {
        return;
    }
    
    const reason = prompt('Please provide a reason for deleting this thread:');
    if (reason === null) return; // User cancelled
    
    try {
        const res = await fetch('/api/delete-thread.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ thread_id: threadId, reason: reason })
        });
        
        const json = await res.json();
        if (json.success) {
            alert('Thread deleted successfully');
            window.location.href = '/pages/forum.php';
        } else {
            alert('Error: ' + (json.error || 'Failed to delete thread'));
        }
    } catch (error) {
        console.error('Delete error:', error);
        alert('Error: Failed to delete thread');
    }
}
</script>
</body>
</html>
