<?php
/**
 * BOOK DETAILS PAGE
 * Complete book page with info, chapters, and recommendations
 */

require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$bookId = intval($_GET['id'] ?? 0);

if (!$bookId) {
    header('Location: ' . site_url('/pages/browse.php'));
    exit;
}

// Get book details
$stmt = $pdo->prepare("
    SELECT s.*, u.username, u.id as author_id
    FROM stories s 
    LEFT JOIN users u ON s.author_id = u.id
    WHERE s.id = ? LIMIT 1
");
$stmt->execute([$bookId]);
$book = $stmt->fetch();

if (!$book) {
    header('Location: ' . site_url('/pages/browse.php'));
    exit;
}

// Get chapters
$chaptersStmt = $pdo->prepare("SELECT * FROM chapters WHERE story_id = ? ORDER BY chapter_number ASC");
$chaptersStmt->execute([$bookId]);
$chapters = $chaptersStmt->fetchAll();

// Get similar books
$similarStmt = $pdo->prepare("
    SELECT * FROM stories 
    WHERE id != ? AND status = 'published'
    ORDER BY views DESC 
    LIMIT 6
");
$similarStmt->execute([$bookId]);
$similar = $similarStmt->fetchAll();

// Get book stats
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT user_id) as total_readers,
        SUM(views) as total_views,
        COUNT(*) as total_ratings
    FROM chapters 
    WHERE story_id = ?
");
$statsStmt->execute([$bookId]);
$stats = $statsStmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - Scroll Novels</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
        }

        .book-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 40px;
        }

        .book-cover {
            width: 100%;
            max-width: 300px;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .book-info h1 {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .book-meta {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .book-meta a {
            color: white;
            text-decoration: none;
        }

        .book-meta a:hover {
            text-decoration: underline;
        }

        .book-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: white;
            color: var(--primary);
            border: none;
            font-weight: bold;
        }

        .btn-primary:hover {
            background: #f0f0f0;
            color: var(--primary);
        }

        .section {
            margin-bottom: 50px;
        }

        .section-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 25px;
            color: #333;
            border-bottom: 3px solid var(--primary);
            padding-bottom: 10px;
            display: inline-block;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: var(--primary);
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        .chapters-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .chapter-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }

        .chapter-item:hover {
            background: #f8f9fa;
            padding-left: 20px;
        }

        .chapter-item:last-child {
            border-bottom: none;
        }

        .chapter-title {
            flex: 1;
        }

        .chapter-number {
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .chapter-info {
            font-size: 12px;
            color: #999;
        }

        .chapter-actions {
            display: flex;
            gap: 10px;
        }

        .chapter-actions a {
            padding: 5px 15px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            transition: all 0.2s;
        }

        .chapter-actions a:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }

        .book-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: pointer;
        }

        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .book-card-image {
            width: 100%;
            height: 250px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
        }

        .book-card-body {
            padding: 15px;
        }

        .book-card-title {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }

        .book-card-author {
            font-size: 12px;
            color: #999;
            margin-bottom: 10px;
        }

        .book-card-stats {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--primary);
        }

        .comments-section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .comment-form textarea {
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: none;
        }

        .comment-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .comment-item:last-child {
            border-bottom: none;
        }

        .comment-author {
            font-weight: bold;
            color: #333;
        }

        .comment-date {
            font-size: 12px;
            color: #999;
        }

        .comment-text {
            margin-top: 10px;
            color: #666;
        }

        .btn-read {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            color: white;
            font-weight: bold;
            padding: 12px 30px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-read:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .tags {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .tag {
            background: #f0f0f0;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .tag:hover {
            background: var(--primary);
            color: white;
        }
    </style>
</head>
<body>

<!-- HEADER -->
<div class="book-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-3 text-center">
                <div style="width: 200px; height: 300px; background: #555; border-radius: 8px; margin: 0 auto; display: flex; align-items: center; justify-content: center; color: white; font-size: 48px;">
                    <i class="fas fa-book"></i>
                </div>
            </div>
            <div class="col-md-9">
                <div class="book-info">
                    <h1><?php echo htmlspecialchars($book['title']); ?></h1>
                    <div class="book-meta">
                        by <a href="<?php echo site_url('/pages/profile.php?id=' . $book['author_id']); ?>">
                            <?php echo htmlspecialchars($book['username']); ?>
                        </a>
                    </div>
                    <p><?php echo htmlspecialchars(substr($book['description'], 0, 300)); ?></p>
                    
                    <div class="tags">
                        <span class="tag">Fiction</span>
                        <span class="tag">Adventure</span>
                        <span class="tag">Fantasy</span>
                    </div>

                    <div class="book-actions">
                        <a href="<?php echo site_url('/pages/book-reader.php?id=' . $bookId); ?>" class="btn btn-read">
                            <i class="fas fa-book-open"></i> Start Reading
                        </a>
                        <button class="btn btn-outline-light">
                            <i class="fas fa-heart"></i> Add to Library
                        </button>
                        <button class="btn btn-outline-light">
                            <i class="fas fa-share"></i> Share
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- STATS -->
<div class="container">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($book['views']); ?></div>
            <div class="stat-label">Total Reads</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count($chapters); ?></div>
            <div class="stat-label">Chapters</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">4.8/5</div>
            <div class="stat-label">Rating</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total_readers'] ?? 0; ?></div>
            <div class="stat-label">Readers</div>
        </div>
    </div>
</div>

<!-- CHAPTERS -->
<div class="container">
    <div class="section">
        <h2 class="section-title"><i class="fas fa-list"></i> Chapters (<?php echo count($chapters); ?>)</h2>
        <div class="chapters-list">
            <?php foreach (array_slice($chapters, 0, 10) as $chapter): ?>
                <div class="chapter-item">
                    <div class="chapter-title">
                        <div class="chapter-number">Chapter <?php echo $chapter['chapter_number']; ?>: <?php echo htmlspecialchars($chapter['title']); ?></div>
                        <div class="chapter-info">
                            Updated: <?php echo date('M d, Y', strtotime($chapter['created_at'])); ?>
                        </div>
                    </div>
                    <div class="chapter-actions">
                        <a href="<?php echo site_url('/pages/book-reader.php?id=' . $bookId . '&chapter=' . $chapter['id']); ?>">
                            Read
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($chapters) > 10): ?>
            <div class="mt-3 text-center">
                <a href="#" class="btn btn-outline-primary">View All Chapters</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- RECOMMENDATIONS -->
<div class="container">
    <div class="section">
        <h2 class="section-title"><i class="fas fa-heart"></i> Similar Books</h2>
        <div class="row">
            <?php foreach ($similar as $rec): ?>
                <div class="col-md-4 col-sm-6 mb-4">
                    <div class="book-card">
                        <div class="book-card-image">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="book-card-body">
                            <div class="book-card-title"><?php echo htmlspecialchars(substr($rec['title'], 0, 30)); ?></div>
                            <div class="book-card-author"><?php echo htmlspecialchars(substr($rec['description'], 0, 40)); ?></div>
                            <div class="book-card-stats">
                                <span><i class="fas fa-eye"></i> <?php echo number_format($rec['views']); ?></span>
                                <span><i class="fas fa-star"></i> 4.5</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- COMMENTS -->
<div class="container">
    <div class="section">
        <h2 class="section-title"><i class="fas fa-comments"></i> Comments</h2>
        <div class="comments-section">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="mb-4">
                    <textarea class="form-control mb-2" placeholder="Share your thoughts..." rows="3"></textarea>
                    <button class="btn btn-primary">Post Comment</button>
                </div>
            <?php else: ?>
                <p><a href="<?php echo site_url('/pages/login.php'); ?>">Login</a> to comment</p>
            <?php endif; ?>

            <div class="comment-item">
                <div class="d-flex justify-content-between">
                    <span class="comment-author">Sarah Johnson</span>
                    <span class="comment-date">2 days ago</span>
                </div>
                <div class="comment-text">This book is absolutely amazing! The plot kept me engaged throughout. Can't wait for the next chapter!</div>
            </div>

            <div class="comment-item">
                <div class="d-flex justify-content-between">
                    <span class="comment-author">Michael Chen</span>
                    <span class="comment-date">1 week ago</span>
                </div>
                <div class="comment-text">Great character development. The author really knows how to create tension.</div>
            </div>
        </div>
    </div>
</div>

<footer style="background: #f8f9fa; padding: 30px 0; margin-top: 50px; text-align: center; color: #666;">
    <div class="container">
        <p>&copy; 2024 Scroll Novels. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
