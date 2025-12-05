<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
$postId = intval($_GET['id'] ?? 0);

if (!$postId) {
    header('Location: ' . SITE_URL . '/pages/community.php');
    exit;
}

try {
    // Ensure community_replies table exists with reply_to support
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        author_id INT NOT NULL,
        reply_to_id INT,
        content LONGTEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reply_to_id) REFERENCES community_replies(id) ON DELETE CASCADE,
        INDEX (post_id),
        INDEX (reply_to_id),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Add reply_to_id column if it doesn't exist (for backwards compatibility)
    try {
        $pdo->exec("ALTER TABLE community_replies ADD COLUMN reply_to_id INT DEFAULT NULL");
    } catch (Exception $e) {
        // Column might already exist
    }
    
    $stmt = $pdo->prepare("SELECT cp.*, u.username FROM community_posts cp 
        JOIN users u ON cp.author_id = u.id WHERE cp.id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) throw new Exception('Post not found');
    
    // Get replies with nested structure (primary replies only)
    $stmt = $pdo->prepare("SELECT cr.*, u.username FROM community_replies cr 
        JOIN users u ON cr.author_id = u.id WHERE cr.post_id = ? AND cr.reply_to_id IS NULL ORDER BY cr.created_at ASC");
    $stmt->execute([$postId]);
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get nested replies for each primary reply
    foreach ($replies as &$reply) {
        $nested_stmt = $pdo->prepare("SELECT cr.*, u.username FROM community_replies cr 
            JOIN users u ON cr.author_id = u.id WHERE cr.reply_to_id = ? ORDER BY cr.created_at ASC");
        $nested_stmt->execute([$reply['id']]);
        $reply['nested_replies'] = $nested_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    die('Error: ' . htmlspecialchars($e->getMessage()));
}

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="min-h-screen bg-gradient-to-b from-slate-50 to-white dark:from-slate-900 dark:to-slate-800">
    <div class="max-w-4xl mx-auto px-4 py-12">
        
        <a href="<?= SITE_URL ?>/pages/community.php" class="inline-flex items-center gap-2 text-emerald-600 hover:text-emerald-700 mb-6">
            ← Back to Forum
        </a>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-8 mb-8">
            <h1 class="text-3xl font-bold text-slate-900 dark:text-white mb-4">
                <?= htmlspecialchars($post['title']) ?>
            </h1>
            
            <div class="flex items-center gap-4 text-sm text-slate-600 dark:text-slate-400 mb-6">
                <span class="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center text-white font-bold">
                    <?= strtoupper(substr($post['username'], 0, 1)) ?>
                </span>
                <div>
                    <div class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($post['username']) ?></div>
                    <div><?= date('M d, Y \a\t h:i A', strtotime($post['created_at'])) ?></div>
                </div>
            </div>

            <div class="prose dark:prose-invert max-w-none mb-6">
                <?= nl2br(htmlspecialchars($post['content'])) ?>
                <?php 
                // Show images if present
                $images = [];
                if (!empty($post['images'])) {
                    $decoded = json_decode($post['images'], true);
                    if (is_array($decoded)) $images = $decoded;
                }
                if (!empty($images)): ?>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <?php foreach ($images as $img): ?>
                            <a href="<?= htmlspecialchars($img) ?>" target="_blank" class="block">
                                <img src="<?= htmlspecialchars($img) ?>" alt="Attachment" class="rounded-lg shadow object-cover w-full h-40">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex gap-4 pt-4 border-t border-slate-200 dark:border-slate-700">
                <button onclick="markHelpful('post', <?= $postId ?>)" class="helpful-btn px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-emerald-100 dark:hover:bg-emerald-700 hover:text-emerald-600 dark:hover:text-emerald-300 rounded transition" data-type="post" data-id="<?= $postId ?>">
                    👍 Helpful
                </button>
                <button onclick="reportContent('post', <?= $postId ?>)" class="px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-red-100 dark:hover:bg-red-700 hover:text-red-600 dark:hover:text-red-300 rounded transition">
                    🚩 Report
                </button>
            </div>
        </div>

        <h2 class="text-2xl font-bold text-slate-900 dark:text-white mb-6">
            💬 <?= count($replies) ?> Replies
        </h2>

        <?php foreach ($replies as $reply): ?>
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-6 mb-4">
                <div class="flex items-start gap-4 mb-4">
                    <span class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-cyan-500 flex items-center justify-center text-white font-bold flex-shrink-0">
                        <?= strtoupper(substr($reply['username'], 0, 1)) ?>
                    </span>
                    <div class="flex-1">
                        <div class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($reply['username']) ?></div>
                        <div class="text-sm text-slate-500 dark:text-slate-400"><?= date('M d, Y \a\t h:i A', strtotime($reply['created_at'])) ?></div>
                    </div>
                </div>
                
                <div class="prose dark:prose-invert max-w-none mb-4">
                    <?= nl2br(htmlspecialchars($reply['content'])) ?>
                </div>

                <div class="flex gap-4 mb-4">
                    <button onclick="markHelpful('reply', <?= $reply['id'] ?>)" class="helpful-btn text-sm font-semibold text-slate-600 dark:text-slate-400 hover:text-emerald-600 transition" data-type="reply" data-id="<?= $reply['id'] ?>">
                        👍 Helpful
                    </button>
                    <button onclick="reportContent('reply', <?= $reply['id'] ?>)" class="text-sm font-semibold text-slate-600 dark:text-slate-400 hover:text-red-600 transition">
                        🚩 Report
                    </button>
                    <?php if ($isLoggedIn): ?>
                    <button onclick="toggleNestedReplyForm(<?= $reply['id'] ?>)" class="text-sm font-semibold text-slate-600 dark:text-slate-400 hover:text-blue-600 transition">
                        💬 Reply
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Nested Reply Form -->
                <?php if ($isLoggedIn): ?>
                <div id="nested-reply-form-<?= $reply['id'] ?>" class="hidden bg-slate-50 dark:bg-slate-700/50 rounded p-4 mb-4 border border-slate-200 dark:border-slate-600">
                    <form onsubmit="submitNestedReply(event, <?= $reply['id'] ?>)">
                        <textarea placeholder="Reply to this comment..." rows="3" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-500 rounded bg-white dark:bg-slate-700 text-slate-900 dark:text-white mb-2 text-sm" required></textarea>
                        <div class="flex gap-2">
                            <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm font-medium">Reply</button>
                            <button type="button" onclick="toggleNestedReplyForm(<?= $reply['id'] ?>)" class="px-3 py-1 bg-slate-300 dark:bg-slate-600 text-slate-900 dark:text-white rounded text-sm font-medium">Cancel</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Nested Replies -->
                <?php if (!empty($reply['nested_replies'])): ?>
                <div class="ml-4 pl-4 border-l-2 border-slate-300 dark:border-slate-600 space-y-3 mt-4">
                    <?php foreach ($reply['nested_replies'] as $nested): ?>
                    <div class="bg-slate-50 dark:bg-slate-700/50 rounded p-3">
                        <div class="flex items-start gap-2 mb-2">
                            <span class="w-8 h-8 rounded-full bg-gradient-to-br from-green-400 to-teal-500 flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
                                <?= strtoupper(substr($nested['username'], 0, 1)) ?>
                            </span>
                            <div class="flex-1">
                                <div class="text-sm font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($nested['username']) ?></div>
                                <div class="text-xs text-slate-500 dark:text-slate-400"><?= date('M d, Y \a\t h:i A', strtotime($nested['created_at'])) ?></div>
                            </div>
                        </div>
                        <div class="text-sm text-slate-700 dark:text-slate-300">
                            <?= nl2br(htmlspecialchars($nested['content'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if ($isLoggedIn): ?>
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-6 mt-8">
                <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-4">Add Your Reply</h3>
                <form method="post" action="<?= SITE_URL ?>/api/community-reply.php">
                    <input type="hidden" name="post_id" value="<?= $postId ?>">
                    <textarea name="content" placeholder="Share your thoughts..." rows="6"
                              class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg 
                              bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-500 mb-4"
                              required></textarea>
                    <button type="submit" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-semibold">
                        Post Reply
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6 mt-8 text-center">
                <p class="text-slate-700 dark:text-slate-300 mb-3">Sign in to reply</p>
                <a href="<?= SITE_URL ?>/auth/login.php" class="inline-block px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-semibold">
                    Sign In
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Report Modal -->
<div id="reportModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden items-center justify-center">
    <div class="bg-white dark:bg-slate-800 rounded-lg p-6 max-w-md w-full mx-4 shadow-xl">
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-4">🚩 Report Content</h3>
        <form id="reportForm">
            <input type="hidden" id="reportType" name="type" value="">
            <input type="hidden" id="reportId" name="content_id" value="">
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Reason</label>
                <select name="reason" class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white" required>
                    <option value="">Select a reason...</option>
                    <option value="spam">Spam or misleading</option>
                    <option value="harassment">Harassment or hate speech</option>
                    <option value="inappropriate">Inappropriate content</option>
                    <option value="copyright">Copyright violation</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Additional details (optional)</label>
                <textarea name="details" rows="3" class="w-full px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-500" placeholder="Provide more context..."></textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition">Submit Report</button>
                <button type="button" onclick="closeReportModal()" class="flex-1 px-4 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-lg font-semibold transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// Helpful button functionality
function markHelpful(type, id) {
    <?php if (!$isLoggedIn): ?>
    alert('Please log in to mark content as helpful');
    window.location.href = '<?= SITE_URL ?>/auth/login.php';
    return;
    <?php endif; ?>
    
    fetch('<?= SITE_URL ?>/api/community-helpful.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `type=${type}&id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const btn = document.querySelector(`[data-type="${type}"][data-id="${id}"]`);
            if (btn) {
                btn.innerHTML = '👍 Helpful (' + (data.count || 1) + ')';
                btn.classList.add('text-emerald-600', 'dark:text-emerald-400');
            }
        } else {
            alert(data.message || 'Error marking as helpful');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Failed to mark as helpful');
    });
}

// Report functionality
function reportContent(type, id) {
    <?php if (!$isLoggedIn): ?>
    alert('Please log in to report content');
    window.location.href = '<?= SITE_URL ?>/auth/login.php';
    return;
    <?php endif; ?>
    
    document.getElementById('reportType').value = type;
    document.getElementById('reportId').value = id;
    document.getElementById('reportModal').classList.remove('hidden');
    document.getElementById('reportModal').classList.add('flex');
}

function closeReportModal() {
    document.getElementById('reportModal').classList.add('hidden');
    document.getElementById('reportModal').classList.remove('flex');
    document.getElementById('reportForm').reset();
}

document.getElementById('reportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('<?= SITE_URL ?>/api/report-content.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Report submitted successfully. Thank you for helping keep our community safe!');
            closeReportModal();
        } else {
            alert(data.message || 'Error submitting report');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Failed to submit report');
    });
});

// Toggle nested reply form
function toggleNestedReplyForm(replyId) {
    const form = document.getElementById('nested-reply-form-' + replyId);
    if (form) {
        form.classList.toggle('hidden');
        if (!form.classList.contains('hidden')) {
            form.querySelector('textarea').focus();
        }
    }
}

// Submit nested reply
function submitNestedReply(event, replyId) {
    event.preventDefault();
    const textarea = event.target.querySelector('textarea');
    const content = textarea.value.trim();
    
    if (!content) {
        alert('Please enter a reply');
        return;
    }
    
    const formData = new FormData();
    formData.append('post_id', <?= $postId ?>);
    formData.append('reply_to_id', replyId);
    formData.append('content', content);
    
    fetch('<?= SITE_URL ?>/api/community-reply.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Reply posted successfully!');
            location.reload();
        } else {
            alert(data.message || 'Error posting reply');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Failed to post reply');
    });
}

// Close modal when clicking outside
document.getElementById('reportModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReportModal();
    }
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php';

