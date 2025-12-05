<?php
// blog.php - Public blog listing with categories and enhanced display
session_status() === PHP_SESSION_NONE && session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header.php';

$isLoggedIn = isset($_SESSION['user_id']);

/**
 * Blog Page Class - Handles blog rendering and data
 */
class BlogPageServer {
    private $pdo;
    private $blogPosts = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadBlogPostsFromDB();
    }
    
    private function loadBlogPostsFromDB() {
        // Create blog_posts table if it doesn't exist
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS blog_posts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE,
                author VARCHAR(100) DEFAULT 'Staff',
                content LONGTEXT,
                excerpt TEXT,
                category VARCHAR(50) DEFAULT 'Update',
                image VARCHAR(10) DEFAULT '📰',
                badge VARCHAR(50),
                type VARCHAR(50) DEFAULT 'update',
                views INT DEFAULT 0,
                is_pinned TINYINT DEFAULT 0,
                published TINYINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_published (published),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Exception $e) {}
        
        // Load posts from database
        try {
            $stmt = $this->pdo->query("
                SELECT bp.*, 
                       (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = bp.id) as comment_count
                FROM blog_posts bp 
                WHERE bp.status = 'published' 
                ORDER BY bp.created_at DESC
            ");
            $dbPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($dbPosts)) {
                foreach ($dbPosts as $post) {
                    $wordCount = str_word_count(strip_tags($post['content'] ?? $post['excerpt'] ?? ''));
                    $readTime = max(1, ceil($wordCount / 200));
                    
                    $this->blogPosts[] = [
                        'id' => $post['id'],
                        'title' => $post['title'],
                        'author' => $post['author'] ?? 'Staff',
                        'date' => date('M d, Y', strtotime($post['created_at'])),
                        'readTime' => $readTime . ' min',
                        'image' => $post['image'] ?? '📰',
                        'category' => $post['category'] ?? 'Update',
                        'badge' => $post['badge'] ?? null,
                        'excerpt' => $post['excerpt'] ?? substr(strip_tags($post['content'] ?? ''), 0, 150) . '...',
                        'views' => (int)($post['views'] ?? 0),
                        'comments' => (int)($post['comment_count'] ?? 0),
                        'type' => $post['type'] ?? 'update',
                        'content' => $post['content'] ?? '',
                        'is_pinned' => (bool)($post['is_pinned'] ?? false)
                    ];
                }
            }
        } catch (Exception $e) {
            error_log('Blog posts load error: ' . $e->getMessage());
        }
        
        // If no posts in database, show helpful message (no fake data)
        if (empty($this->blogPosts)) {
            $this->blogPosts = [];
        }
    }
    
    public function getSampleBlogPosts() {
        return $this->blogPosts;
    }
    
    public function incrementViews($postId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE blog_posts SET views = views + 1 WHERE id = ?");
            $stmt->execute([$postId]);
        } catch (Exception $e) {}
    }
    
    public function getPostById($postId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT bp.*, 
                       (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = bp.id) as comment_count
                FROM blog_posts bp 
                WHERE bp.id = ? AND bp.published = 1
            ");
            $stmt->execute([$postId]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($post) {
                // Increment view count
                $this->incrementViews($postId);
                
                $wordCount = str_word_count(strip_tags($post['content'] ?? ''));
                $readTime = max(1, ceil($wordCount / 200));
                
                return [
                    'id' => $post['id'],
                    'title' => $post['title'],
                    'author' => $post['author'] ?? 'Staff',
                    'date' => date('M d, Y', strtotime($post['created_at'])),
                    'readTime' => $readTime . ' min',
                    'image' => $post['image'] ?? '📰',
                    'category' => $post['category'] ?? 'Update',
                    'badge' => $post['badge'] ?: null,
                    'excerpt' => $post['excerpt'] ?? '',
                    'views' => (int)($post['views'] ?? 0) + 1,
                    'comments' => (int)($post['comment_count'] ?? 0),
                    'type' => $post['type'] ?? 'update',
                    'content' => $post['content'] ?? ''
                ];
            }
        } catch (Exception $e) {
            error_log('Blog post fetch error: ' . $e->getMessage());
        }
        return null;
    }
    
    public function renderBlogCard($post) {
        $html = '<div class="blog-card" style="animation: slideIn 0.5s ease-out;">';
        $html .= '<div class="blog-cover" style="font-size: 3rem; text-align: center; padding: 1.5rem; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 0.5rem 0.5rem 0 0; color: white;">' . $post['image'] . '</div>';
        $html .= '<div class="blog-content" style="padding: 1.5rem; background: #1f2937; color: #f3f4f6; border-radius: 0 0 0.5rem 0.5rem;">'; 
        $html .= '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">';
        $html .= '<span class="blog-category" style="background: #ecfdf5; color: #047857; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600;">' . htmlspecialchars($post['category']) . '</span>';
        if (isset($post['badge']) && $post['badge']) {
            $html .= '<span class="badge" style="background: #fef3c7; color: #d97706; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">' . htmlspecialchars($post['badge']) . '</span>';
        }
        $html .= '</div>';
        $html .= '<h3 style="font-size: 1.125rem; font-weight: bold; margin: 0.5rem 0; line-height: 1.4;"><a href="' . site_url('/pages/blog-view.php?id=' . $post['id'] . '&type=blog') . '" style="text-decoration: none; color: #ffffff; cursor: pointer;">' . htmlspecialchars($post['title']) . '</a></h3>';
        $html .= '<p class="excerpt" style="color: #9ca3af; margin: 0.5rem 0; line-height: 1.5;">' . htmlspecialchars($post['excerpt']) . '</p>';
        $html .= '<div class="blog-meta" style="display: flex; justify-content: space-between; font-size: 0.875rem; color: #9ca3af; margin: 0.75rem 0;">';
        $html .= '<span class="date">📅 ' . htmlspecialchars($post['date']) . '</span>';
        $html .= '<span class="read-time">⏱️ ' . htmlspecialchars($post['readTime']) . ' read</span>';
        $html .= '</div>';
        $html .= '<div class="blog-stats" style="display: flex; gap: 1rem; font-size: 0.875rem; color: #6b7280; padding-top: 0.75rem; border-top: 1px solid #374151;">';
        $html .= '<span>👁️ ' . number_format($post['views']) . ' views</span>';
        $html .= '<span>💬 ' . number_format($post['comments']) . ' comments</span>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
}

// Initialize blog server
$blogServer = new BlogPageServer($pdo);

// Get category filter
$category = $_GET['cat'] ?? 'all';
$postId = $_GET['id'] ?? null;

// Load posts from the database
$blog_posts = $blogServer->getSampleBlogPosts();

// Get featured/pinned
$featured = array_filter($blog_posts, fn($p) => isset($p['is_pinned']) && $p['is_pinned']);
$recent = array_filter($blog_posts, fn($p) => !isset($p['is_pinned']) || !$p['is_pinned']);

// Category mapping for display
$categories = [
    'all' => 'All Posts',
    'update' => 'Updates',
    'event' => 'Events',
    'dev_log' => 'Dev Logs',
    'announcement' => 'Announcements',
    'patch' => 'Patch Notes',
    'spotlight' => 'Spotlight'
];

// Check if viewing single post - get fresh data with incremented views
$single_post = null;
if ($postId) {
    $single_post = $blogServer->getPostById($postId);
}
?>

<style>
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .blog-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 2rem;
        margin-top: 2rem;
    }

    .blog-card {
        background: linear-gradient(135deg, #fafafa 0%, #f5f3ff 100%);
        border-radius: 0.5rem;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        border: 1px solid #f0e6ff;
    }

    .blog-card:hover {
        box-shadow: 0 12px 24px rgba(139, 92, 246, 0.15);
        transform: translateY(-8px);
        border-color: #e9d5ff;
    }

    .blog-hero {
        background: linear-gradient(135deg, #065f46 0%, #047857 100%);
        color: white;
        padding: 2.5rem 1.5rem;
        text-align: center;
        margin-bottom: 2rem;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        animation: fadeIn 0.6s ease-out;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }

    @keyframes fadeIn {
        0% { opacity: 0; transform: translateY(-5px); }
        100% { opacity: 1; transform: translateY(0); }
    }

    .blog-hero h1 {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        font-weight: 700;
    }

    .blog-hero p {
        font-size: 1rem;
        opacity: 0.95;
        color: #d1fae5;
    }

    .blog-single-post {
        max-width: 800px;
        margin: 0 auto;
        background: #ffffff;
        padding: 2rem;
        border-radius: 0.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border: 1px solid #e5e7eb;
    }

    @media (prefers-color-scheme: dark) {
        .blog-single-post {
            background: #1f2937;
            border-color: #374151;
        }
    }

    .blog-single-post h1 {
        font-size: 2.2rem;
        margin-bottom: 1rem;
        line-height: 1.3;
        color: #065f46;
    }

    @media (prefers-color-scheme: dark) {
        .blog-single-post h1 {
            color: #10b981;
        }
    }

    .blog-single-post .post-meta {
        color: #6b7280;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        gap: 1.5rem;
        font-size: 0.95rem;
    }

    .blog-single-post .post-content {
        line-height: 1.8;
        color: #374151;
        font-size: 1.05rem;
        margin-bottom: 2rem;
    }

    @media (prefers-color-scheme: dark) {
        .blog-single-post .post-content {
            color: #e5e7eb;
        }
        
        .blog-stats-box {
            background: #374151 !important;
            color: #e5e7eb;
        }
        
        .blog-comments-section {
            border-color: #4b5563 !important;
        }
        
        .blog-comments-section h2 {
            color: #10b981 !important;
        }
        
        .blog-comment-item {
            background: #374151 !important;
        }
        
        .blog-comment-item p {
            color: #e5e7eb !important;
        }
    }
</style>

<!-- Blog Hero Section -->
<div class="blog-hero">
    <h1>📚 Blog</h1>
</div>

<main class="max-w-4xl mx-auto px-4 py-8" style="min-height: calc(100vh - 400px);">
    <?php if ($single_post): ?>
        <!-- Single Post View -->
        <div class="blog-single-post">
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" style="color: #10b981; text-decoration: none; font-weight: 600;">← Back to Blog</a>
            
            <h1><?php echo htmlspecialchars($single_post['title']); ?></h1>
            
            <div class="post-meta">
                <span>✍️ By <?php echo htmlspecialchars($single_post['author'] ?? 'Unknown'); ?></span>
                <span>📅 <?php echo htmlspecialchars($single_post['date'] ?? date('M d, Y')); ?></span>
                <span style="background: #ecfdf5; color: #047857; padding: 0.25rem 0.75rem; border-radius: 9999px;">📁 <?php echo htmlspecialchars($single_post['category'] ?? 'Post'); ?></span>
            </div>
            
            <div class="post-content">
                <?php echo htmlspecialchars($single_post['content'] ?? $single_post['excerpt'] ?? 'No content available'); ?>
            </div>
            
            <div style="background: #f3f4f6; padding: 1rem; border-radius: 0.5rem; margin-top: 2rem;" class="blog-stats-box">
                <div style="display: flex; gap: 2rem; justify-content: center; text-sm;">
                    <span>👁️ <?php echo number_format($single_post['views'] ?? 0); ?> views</span>
                    <span>💬 <?php echo number_format($single_post['comments'] ?? 0); ?> comments</span>
                </div>
            </div>

            <!-- Comments Section -->
            <div style="margin-top: 3rem; padding-top: 2rem; border-top: 2px solid #e5e7eb;" class="blog-comments-section">
                <h2 style="font-size: 1.5rem; font-weight: bold; margin-bottom: 1.5rem; color: #065f46;">💬 Comments</h2>
                
                <!-- Comment Form -->
                <?php if ($isLoggedIn ?? false): ?>
                    <div style="margin-bottom: 2rem; padding: 1rem; background: #f9fafb; border-radius: 8px;" class="comment-form-container">
                        <form id="commentForm" style="display: flex; flex-direction: column; gap: 1rem;">
                            <input type="hidden" id="postId" value="<?= htmlspecialchars($single_post['id']) ?>">
                            <input type="hidden" id="parentCommentId" value="">
                            <div id="replyingTo" style="display: none; padding: 0.5rem; background: #e0f2fe; border-radius: 6px; margin-bottom: 0.5rem;">
                                <span style="color: #0369a1;">Replying to: <strong id="replyingToName"></strong></span>
                                <button type="button" onclick="cancelReply()" style="margin-left: 1rem; color: #dc2626; background: none; border: none; cursor: pointer;">✕ Cancel</button>
                            </div>
                            <textarea id="commentText" placeholder="Share your thoughts..." required 
                                style="padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; min-height: 80px; font-family: inherit; font-size: 14px; color: #1f2937; background: #ffffff; resize: vertical;"></textarea>
                            <button type="submit" style="background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">Post Comment</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="padding: 1rem; background: #fef3c7; border-radius: 8px; margin-bottom: 2rem;">
                        <p style="margin: 0; color: #92400e;"><a href="<?= site_url('/pages/login.php') ?>" style="color: #d97706; font-weight: bold;">Log in</a> to comment on this post.</p>
                    </div>
                <?php endif; ?>

                <!-- Comments List -->
                <div id="commentsList" style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <?php
                    // Load comments for this post
                    $comments = [];
                    try {
                        $pdo->exec("CREATE TABLE IF NOT EXISTS post_comments (
                            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                            post_id INT UNSIGNED NOT NULL,
                            user_id INT UNSIGNED NOT NULL,
                            content LONGTEXT NOT NULL,
                            parent_comment_id INT UNSIGNED NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_post_id (post_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                        
                        $stmt = $pdo->prepare("
                            SELECT pc.*, u.username, u.profile_image 
                            FROM post_comments pc 
                            LEFT JOIN users u ON pc.user_id = u.id 
                            WHERE pc.post_id = ? 
                            ORDER BY pc.created_at DESC
                        ");
                        $stmt->execute([$single_post['id']]);
                        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        error_log('Blog comments error: ' . $e->getMessage());
                    }
                    ?>
                    
                    <?php if (empty($comments)): ?>
                        <p style="color: #9ca3af; text-align: center;">No comments yet. Be the first to comment!</p>
                    <?php else: ?>
                        <?php 
                        // Organize comments by parent
                        $parentComments = [];
                        $childComments = [];
                        foreach ($comments as $comment) {
                            if (empty($comment['parent_comment_id'])) {
                                $parentComments[] = $comment;
                            } else {
                                $childComments[$comment['parent_comment_id']][] = $comment;
                            }
                        }
                        ?>
                        <?php foreach ($parentComments as $comment): ?>
                            <div class="blog-comment-item" style="background: #f9fafb; padding: 1rem; border-radius: 8px; border-left: 3px solid #10b981;" data-comment-id="<?= $comment['id'] ?>">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #e5e7eb; display: flex; align-items: center; justify-content: center; font-size: 14px; overflow: hidden;">
                                        <?php if (!empty($comment['profile_image'])): ?>
                                            <img src="<?= htmlspecialchars($comment['profile_image']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <?= strtoupper(substr($comment['username'] ?? 'U', 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex: 1;">
                                        <strong style="color: #065f46;"><?= htmlspecialchars($comment['username'] ?? 'User') ?></strong>
                                        <span style="color: #9ca3af; font-size: 0.75rem; margin-left: 0.5rem;"><?= date('M d, Y H:i', strtotime($comment['created_at'])) ?></span>
                                    </div>
                                    <?php if ($isLoggedIn ?? false): ?>
                                        <button onclick="replyToComment(<?= $comment['id'] ?>, '<?= htmlspecialchars(addslashes($comment['username'] ?? 'User')) ?>')" 
                                            style="background: none; border: none; color: #10b981; cursor: pointer; font-size: 0.85rem; padding: 4px 8px;">
                                            ↩️ Reply
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <p style="color: #374151; margin: 0;"><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
                                
                                <!-- Child comments (replies) -->
                                <?php if (!empty($childComments[$comment['id']])): ?>
                                    <div style="margin-top: 1rem; margin-left: 2rem; display: flex; flex-direction: column; gap: 0.75rem;">
                                        <?php foreach ($childComments[$comment['id']] as $reply): ?>
                                            <div style="background: #ffffff; padding: 0.75rem; border-radius: 6px; border-left: 2px solid #6ee7b7;">
                                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                                    <div style="width: 24px; height: 24px; border-radius: 50%; background: #e5e7eb; display: flex; align-items: center; justify-content: center; font-size: 11px; overflow: hidden;">
                                                        <?php if (!empty($reply['profile_image'])): ?>
                                                            <img src="<?= htmlspecialchars($reply['profile_image']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                                        <?php else: ?>
                                                            <?= strtoupper(substr($reply['username'] ?? 'U', 0, 1)) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <strong style="color: #065f46; font-size: 0.85rem;"><?= htmlspecialchars($reply['username'] ?? 'User') ?></strong>
                                                    <span style="color: #9ca3af; font-size: 0.7rem;"><?= date('M d, Y H:i', strtotime($reply['created_at'])) ?></span>
                                                </div>
                                                <p style="color: #374151; margin: 0; font-size: 0.9rem;"><?= nl2br(htmlspecialchars($reply['content'])) ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Blog Feed - Show All Posts -->
        
        <!-- Category Filters -->
        <div class="mb-8 flex flex-wrap gap-3 justify-center">
            <?php foreach ($categories as $key => $label): ?>
                <a href="?cat=<?= htmlspecialchars($key) ?>" class="px-6 py-2 rounded-lg font-medium transition <?= $category === $key ? 'bg-emerald-600 text-white' : 'bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600' ?>">
                    <?= htmlspecialchars($label) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Featured Posts -->
        <?php if (!empty($featured)): ?>
            <div style="margin-bottom: 3rem;">
                <h2 style="font-size: 1.875rem; font-weight: bold; margin-bottom: 1.5rem; border-bottom: 3px solid #10b981; padding-bottom: 0.5rem;">⭐ Featured Posts</h2>
                <div class="blog-grid">
                    <?php foreach ($featured as $post): ?>
                        <?php echo $blogServer->renderBlogCard($post); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Posts -->
        <?php if (!empty($recent)): ?>
            <div>
                <h2 style="font-size: 1.875rem; font-weight: bold; margin-bottom: 1.5rem; border-bottom: 3px solid #059669; padding-bottom: 0.5rem;">📰 Recent Posts</h2>
                <div class="blog-grid">
                    <?php foreach ($recent as $post): ?>
                        <?php echo $blogServer->renderBlogCard($post); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- No Posts Message -->
        <?php if (empty($blog_posts)): ?>
            <div style="text-align: center; padding: 3rem; background: #f9fafb; border-radius: 0.5rem;" class="dark:bg-gray-800">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📰</div>
                <h3 style="color: #374151; font-size: 1.5rem; margin-bottom: 0.5rem;" class="dark:text-gray-200">No Blog Posts Yet</h3>
                <p style="color: #6b7280; font-size: 1.1rem;" class="dark:text-gray-400">Check back soon for updates, announcements, and community news!</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php if (($single_post) && ($isLoggedIn ?? false)): ?>
<script>
// Reply to comment function
function replyToComment(commentId, username) {
    document.getElementById('parentCommentId').value = commentId;
    document.getElementById('replyingTo').style.display = 'block';
    document.getElementById('replyingToName').textContent = username;
    document.getElementById('commentText').placeholder = 'Write your reply to ' + username + '...';
    document.getElementById('commentText').focus();
    // Scroll to form
    document.querySelector('.comment-form-container').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function cancelReply() {
    document.getElementById('parentCommentId').value = '';
    document.getElementById('replyingTo').style.display = 'none';
    document.getElementById('replyingToName').textContent = '';
    document.getElementById('commentText').placeholder = 'Share your thoughts...';
}

// Blog post comments
document.getElementById('commentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const postId = document.getElementById('postId').value;
    const commentText = document.getElementById('commentText').value.trim();
    const parentCommentId = document.getElementById('parentCommentId').value || null;
    
    if (!commentText) {
        alert('Please write a comment');
        return;
    }
    
    try {
        const response = await fetch('<?= site_url('/api/post_comment.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                post_id: postId,
                comment_text: commentText,
                parent_comment_id: parentCommentId,
                type: 'blog'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('commentText').value = '';
            cancelReply();
            location.reload(); // Reload to show new comment
        } else {
            alert('Error posting comment: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error posting comment');
    }
});
</script>
<?php endif; ?>
