<?php
// pages/blog-view.php - View a single blog post or announcement
if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'announcement'; // 'announcement' or 'blog'

if (!$id) {
    header('Location: ' . site_url('/pages/blog.php'));
    exit;
}

$post = null;
$isAnnouncement = false;

// Query based on type parameter FIRST
try {
    if ($type === 'announcement') {
        // Query announcements table
        $stmt = $pdo->prepare("SELECT a.*, u.username as author_name 
                               FROM announcements a 
                               LEFT JOIN users u ON a.author_id = u.id
                               WHERE a.id = ?");
        $stmt->execute([$id]);
        $post = $stmt->fetch();
        
        if ($post) {
            $isAnnouncement = true;
            // Track view
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS announcement_reads (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    announcement_id INT NOT NULL,
                    user_id INT,
                    ip_address VARCHAR(45),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (announcement_id)
                )");
                
                $userId = $_SESSION['user_id'] ?? null;
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $pdo->prepare("INSERT INTO announcement_reads (announcement_id, user_id, ip_address) VALUES (?, ?, ?)")
                    ->execute([$id, $userId, $ip]);
            } catch (Exception $e) {}
        }
    } elseif ($type === 'blog') {
        // Query blog_posts table
        $stmt = $pdo->prepare("SELECT bp.*, u.username as author_name 
                               FROM blog_posts bp 
                               LEFT JOIN users u ON bp.author_id = u.id
                               WHERE bp.id = ? AND bp.status = 'published'");
        $stmt->execute([$id]);
        $post = $stmt->fetch();
        
        if ($post) {
            // Increment views
            $pdo->prepare("UPDATE blog_posts SET views = views + 1 WHERE id = ?")->execute([$id]);
        }
    }
} catch (Exception $e) {}

if (!$post) {
    header('Location: ' . site_url('/pages/blog.php'));
    exit;
}

$pageTitle = htmlspecialchars($post['title']) . ' - Scroll Novels';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900 py-8">
    <div class="max-w-4xl mx-auto px-4">
        <!-- Back link -->
        <a href="<?= site_url('/pages/blog.php') ?>" class="inline-flex items-center text-emerald-600 hover:text-emerald-700 mb-6">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Blog
        </a>

        <!-- Article Card -->
        <article class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
            <!-- Featured Image -->
            <?php if (!empty($post['featured_image'])): ?>
                <div class="relative w-full h-96 bg-gray-200 dark:bg-gray-700">
                    <img src="<?= htmlspecialchars($post['featured_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-full object-cover">
                </div>
            <?php endif; ?>
            
            <!-- Header -->
            <div class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white p-8">
                <div class="flex items-center gap-2 mb-4">
                    <?php if ($isAnnouncement): ?>
                        <span class="bg-white/20 px-3 py-1 rounded-full text-sm">📢 Announcement</span>
                    <?php else: ?>
                        <span class="bg-white/20 px-3 py-1 rounded-full text-sm">📰 Blog Post</span>
                    <?php endif; ?>
                </div>
                <h1 class="text-3xl md:text-4xl font-bold mb-4"><?= htmlspecialchars($post['title']) ?></h1>
                <div class="flex items-center gap-4 text-emerald-100">
                    <span>By <?= htmlspecialchars($post['author_name'] ?? 'Staff') ?></span>
                    <span>•</span>
                    <span><?= date('F d, Y', strtotime($post['created_at'])) ?></span>
                    <?php if (!$isAnnouncement && isset($post['views'])): ?>
                        <span>•</span>
                        <span><?= number_format($post['views']) ?> views</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Content -->
            <div class="p-8">
                <div class="prose prose-lg dark:prose-invert max-w-none">
                    <?= $post['content'] ?>
                </div>
            </div>

            <!-- Comments Section -->
            <div id="blog-comments-section" class="px-8 pb-8">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">💬 Comments</h2>
                
                <!-- Comment Form -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="mb-8 p-6 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
                        <textarea id="comment-textarea" placeholder="Share your thoughts on this post..." 
                                  class="w-full p-4 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white resize-none"
                                  rows="4"></textarea>
                        <button onclick="submitComment()" 
                                class="mt-4 px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                            Post Comment
                        </button>
                    </div>
                <?php else: ?>
                    <div class="mb-8 p-6 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <p class="text-gray-700 dark:text-gray-300">
                            <a href="<?= site_url('/pages/login.php') ?>" class="text-blue-600 dark:text-blue-400 font-semibold hover:underline">Log in</a> 
                            to comment on this post.
                        </p>
                    </div>
                <?php endif; ?>
                
                <!-- Comments List -->
                <div id="blog-comments-container" class="space-y-4">
                    <p class="text-gray-500 dark:text-gray-400">Loading comments...</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="border-t border-gray-200 dark:border-gray-700 px-8 py-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-emerald-600 rounded-full flex items-center justify-center text-white font-bold text-xl">
                            <?= strtoupper(substr($post['author_name'] ?? 'S', 0, 1)) ?>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($post['author_name'] ?? 'Staff') ?></div>
                            <div class="text-sm text-gray-500">Scroll Novels Team</div>
                        </div>
                    </div>
                    
                    <!-- Share buttons -->
                    <div class="flex items-center gap-2">
                        <button onclick="navigator.clipboard.writeText(window.location.href); alert('Link copied!');" 
                                class="p-2 rounded-full bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            🔗
                        </button>
                    </div>
                </div>
            </div>
        </article>

        <!-- Related Posts -->
        <?php
        try {
            if ($isAnnouncement) {
                $relatedStmt = $pdo->prepare("SELECT id, title, created_at FROM announcements WHERE id != ? ORDER BY created_at DESC LIMIT 3");
            } else {
                $relatedStmt = $pdo->prepare("SELECT id, title, created_at FROM blog_posts WHERE id != ? AND status = 'published' ORDER BY created_at DESC LIMIT 3");
            }
            $relatedStmt->execute([$id]);
            $related = $relatedStmt->fetchAll();
            
            if (!empty($related)):
        ?>
        <div class="mt-8">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">More Posts</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <?php foreach ($related as $rel): ?>
                    <a href="<?= site_url('/pages/blog-view.php?id=' . $rel['id']) ?>" 
                       class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow hover:shadow-md transition-shadow">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2 line-clamp-2"><?= htmlspecialchars($rel['title']) ?></h3>
                        <div class="text-sm text-gray-500"><?= date('M d, Y', strtotime($rel['created_at'])) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php 
            endif;
        } catch (Exception $e) {}
        ?>
    </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

<!-- Comments System -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadComments();
});

async function loadComments() {
    const postId = <?= $id ?>;
    const isAnnouncement = <?= $isAnnouncement ? 'true' : 'false' ?>;
    
    try {
        const response = await fetch('<?= site_url('/api/blog/get-comments.php') ?>?post_id=' + postId + '&type=' + (isAnnouncement ? 'announcement' : 'blog'));
        const data = await response.json();
        
        if (data.success && data.comments) {
            displayComments(data.comments);
        }
    } catch (e) {
        console.error('Error loading comments:', e);
    }
}

function displayComments(comments) {
    const container = document.getElementById('blog-comments-container');
    if (!container) return;
    
    if (comments.length === 0) {
        container.innerHTML = '<p class="text-gray-500 dark:text-gray-400">No comments yet. Be the first to comment!</p>';
        return;
    }
    
    const html = comments.map(c => `
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4">
            <div class="flex justify-between items-start mb-2">
                <div class="font-semibold text-gray-900 dark:text-white">${htmlEscape(c.username || 'Anonymous')}</div>
                <span class="text-xs text-gray-500">${new Date(c.created_at).toLocaleDateString()}</span>
            </div>
            <p class="text-gray-700 dark:text-gray-300 mb-3">${htmlEscape(c.comment_text)}</p>
            <div class="flex gap-3 text-sm">
                <button onclick="likeComment(${c.id})" class="text-gray-600 dark:text-gray-400 hover:text-red-600 flex items-center gap-1">
                    👍 <span id="likes-${c.id}">${c.likes || 0}</span>
                </button>
                <button onclick="toggleReplyForm(${c.id})" class="text-gray-600 dark:text-gray-400 hover:text-blue-600">
                    💬 Reply
                </button>
            </div>
            
            <!-- Reply Form -->
            <div id="reply-form-${c.id}" class="hidden mt-3 p-3 bg-white dark:bg-gray-600 rounded-lg">
                <textarea id="reply-text-${c.id}" placeholder="Write a reply..." class="w-full p-2 rounded border dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm" rows="2"></textarea>
                <div class="flex gap-2 mt-2">
                    <button onclick="submitReply(${c.id})" class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">Reply</button>
                    <button onclick="toggleReplyForm(${c.id})" class="px-3 py-1 bg-gray-300 dark:bg-gray-500 text-gray-700 dark:text-white rounded text-sm">Cancel</button>
                </div>
            </div>
            
            <!-- Replies List -->
            <div id="replies-${c.id}" class="mt-3 pl-4 border-l-2 border-gray-300 dark:border-gray-600 space-y-2">
                <!-- Replies will load here -->
            </div>
        </div>
    `).join('');
    
    container.innerHTML = html;
    
    // Load all replies
    comments.forEach(c => loadReplies(c.id));
}

function htmlEscape(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function toggleReplyForm(commentId) {
    const form = document.getElementById('reply-form-' + commentId);
    if (form) {
        form.classList.toggle('hidden');
        if (!form.classList.contains('hidden')) {
            document.getElementById('reply-text-' + commentId)?.focus();
        }
    }
}

async function loadReplies(commentId) {
    try {
        const response = await fetch('<?= site_url('/api/blog/get-comment-replies.php') ?>?comment_id=' + commentId);
        const data = await response.json();
        
        if (data.success && data.replies) {
            displayReplies(commentId, data.replies);
        }
    } catch (e) {
        console.error('Error loading replies:', e);
    }
}

function displayReplies(commentId, replies) {
    const container = document.getElementById('replies-' + commentId);
    if (!container) return;
    
    if (replies.length === 0) {
        container.innerHTML = '';
        return;
    }
    
    const html = replies.map(r => `
        <div class="text-sm bg-white dark:bg-gray-700 p-2 rounded">
            <div class="flex justify-between mb-1">
                <strong class="text-gray-900 dark:text-white">${htmlEscape(r.username || 'Anonymous')}</strong>
                <span class="text-xs text-gray-500">${new Date(r.created_at).toLocaleDateString()}</span>
            </div>
            <p class="text-gray-700 dark:text-gray-300">${htmlEscape(r.reply_text)}</p>
        </div>
    `).join('');
    
    container.innerHTML = html;
}

async function likeComment(commentId) {
    try {
        const response = await fetch('<?= site_url('/api/blog/like-comment.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({comment_id: commentId})
        });
        
        const data = await response.json();
        if (data.success) {
            document.getElementById('likes-' + commentId).textContent = data.likes;
        }
    } catch (e) {
        console.error('Error liking comment:', e);
    }
}

async function submitReply(commentId) {
    const replyText = document.getElementById('reply-text-' + commentId)?.value.trim();
    
    if (!replyText) {
        alert('Please enter a reply');
        return;
    }
    
    try {
        const response = await fetch('<?= site_url('/api/blog/add-comment-reply.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                comment_id: commentId,
                reply_text: replyText
            })
        });
        
        const data = await response.json();
        if (data.success) {
            document.getElementById('reply-text-' + commentId).value = '';
            toggleReplyForm(commentId);
            loadReplies(commentId);
            alert('Reply posted! The user will be notified.');
        } else {
            alert('Error: ' + (data.error || 'Could not post reply'));
        }
    } catch (e) {
        alert('Error posting reply: ' + e.message);
    }
}

async function submitComment() {
    const <?= session_status() === PHP_SESSION_NONE ? 'isLoggedIn = false' : 'isLoggedIn = true' ?>;
    
    if (!isLoggedIn) {
        alert('Please log in to comment');
        window.location.href = '<?= site_url('/pages/login.php') ?>';
        return;
    }
    
    const textarea = document.getElementById('comment-textarea');
    const text = textarea.value.trim();
    
    if (!text) {
        alert('Please enter a comment');
        return;
    }
    
    try {
        const response = await fetch('<?= site_url('/api/blog/add-comment.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                post_id: <?= $id ?>,
                type: <?= $isAnnouncement ? "'announcement'" : "'blog'" ?>,
                comment_text: text
            })
        });
        
        const data = await response.json();
        if (data.success) {
            textarea.value = '';
            alert('Comment posted successfully!');
            loadComments();
        } else {
            alert('Error: ' + (data.error || 'Could not post comment'));
        }
    } catch (e) {
        alert('Error posting comment: ' + e.message);
    }
}
</script>

<style>
#blog-comments-section {
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 2px solid #e5e7eb;
}

.dark #blog-comments-section {
    border-top-color: #374151;
}
</style>