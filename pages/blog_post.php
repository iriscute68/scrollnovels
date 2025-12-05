<?php
// blog_post.php - Read blog post with comments
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/components/navbar.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /blog.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT * FROM announcements 
    WHERE id = ? 
    AND active_from <= NOW() 
    AND (active_until IS NULL OR active_until >= NOW())
");
$stmt->execute([$id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    http_response_code(404);
    die('Blog post not found or inactive');
}

// Get comments
$stmt = $pdo->prepare("
    SELECT c.*, u.username, u.profile_picture
    FROM blog_comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.blog_post_id = ?
    AND c.is_approved = 1
    ORDER BY c.created_at DESC
    LIMIT 50
");
$stmt->execute([$id]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// User info for posting comment
$current_user = $_SESSION['user_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> | Scroll Novels</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/assets/css/blog.css">
</head>
<body class="bg-background text-foreground">
    <?php render_navbar(); ?>

    <main class="container mx-auto px-4 py-12">
        <div class="max-w-3xl mx-auto">
            <!-- Back link -->
            <a href="/blog.php" class="inline-flex items-center gap-2 text-primary hover:underline mb-8">
                ← Back to Blog
            </a>

            <!-- Header -->
            <article>
                <div class="mb-4">
                    <span class="badge" style="background: <?= 
                        $post['level'] === 'alert' ? '#ef4444' : 
                        ($post['level'] === 'notice' ? '#f59e0b' : 
                        ($post['level'] === 'system' ? '#6366f1' : '#3b82f6'))
                    ?>">
                        <?= strtoupper($post['level']) ?>
                    </span>
                </div>

                <h1 class="text-4xl font-bold mb-4"><?= htmlspecialchars($post['title']) ?></h1>

                <div class="flex items-center gap-4 text-sm text-muted-foreground mb-8 pb-8 border-b border-border">
                    <time><?= date('M d, Y', strtotime($post['created_at'])) ?></time>
                    <span>•</span>
                    <span><?= ceil(str_word_count(strip_tags($post['content'])) / 200) ?> min read</span>
                </div>

                <!-- Content -->
                <div class="prose prose-invert max-w-none mb-12">
                    <?= nl2br(htmlspecialchars($post['content'])) ?>
                </div>
            </article>

            <!-- Divider -->
            <div class="my-12 border-t border-b border-border py-8">
                <p class="text-center text-muted-foreground">
                    ✦ ✦ ✦
                </p>
            </div>

            <!-- Comments Section -->
            <section class="mt-12">
                <h2 class="text-2xl font-bold mb-6">Comments (<?= count($comments) ?>)</h2>

                <!-- Comment Form -->
                <?php if ($current_user): ?>
                    <div class="card p-6 mb-8">
                        <h3 class="font-semibold mb-4">Share Your Thoughts</h3>
                        <form id="commentForm" class="space-y-4">
                            <textarea name="comment" class="input-field w-full" rows="4" 
                                placeholder="Write a comment..." required></textarea>
                            <button type="submit" class="btn btn-primary">Post Comment</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="card p-6 mb-8 text-center">
                        <p class="text-muted-foreground mb-3">Log in to join the discussion</p>
                        <a href="/pages/login.php?return=/blog_post.php?id=<?= $id ?>" class="btn btn-primary">Log In</a>
                    </div>
                <?php endif; ?>

                <!-- Comments List -->
                <div id="commentsList" class="space-y-4">
                    <?php foreach ($comments as $comment): ?>
                        <div class="card p-4 border border-border">
                            <div class="flex items-start gap-3 mb-3">
                                <img src="<?= $comment['profile_picture'] ?? '/img/default-avatar.png' ?>" 
                                    alt="<?= htmlspecialchars($comment['username']) ?>"
                                    class="w-10 h-10 rounded-full object-cover">
                                <div>
                                    <p class="font-semibold"><?= htmlspecialchars($comment['username']) ?></p>
                                    <time class="text-xs text-muted-foreground">
                                        <?= time_ago($comment['created_at']) ?>
                                    </time>
                                </div>
                            </div>
                            <p class="text-sm"><?= htmlspecialchars($comment['comment_text']) ?></p>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($comments)): ?>
                        <p class="text-center text-muted-foreground py-8">No comments yet. Be the first!</p>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <?php require_once __DIR__ . '/components/footer.php'; ?>

    <script>
    <?php if ($current_user): ?>
    document.getElementById('commentForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = document.querySelector('[name="comment"]').value;

        const res = await fetch('/ajax/post_blog_comment.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ blog_post_id: <?= $id ?>, comment_text: text })
        });

        const result = await res.json();
        if (result.ok) {
            document.querySelector('[name="comment"]').value = '';
            alert('Comment posted! It will appear after review.');
        } else {
            alert('Error: ' + result.message);
        }
    });
    <?php endif; ?>

    function time_ago(date) {
        const seconds = Math.floor((new Date() - new Date(date)) / 1000);
        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + ' years ago';
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + ' months ago';
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + ' days ago';
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + ' hours ago';
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + ' minutes ago';
        return Math.floor(seconds) + ' seconds ago';
    }
    </script>
</body>
</html>

