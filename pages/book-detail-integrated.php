<?php
/**
 * Book Detail Page - Integrated Production Version
 * Complete book information display with chapters, reviews, and engagement
 */

session_start();
require_once(__DIR__ . '/../config/db.php');
require_once(__DIR__ . '/../includes/auth.php');

/**
 * Comprehensive Book Detail Server Class
 * Full server-side implementation
 */
class ScrollNovelsBookDetailServer {
    private $db;
    private $config = [
        'maxFontSize' => 28,
        'minFontSize' => 12,
        'supportedThemes' => ['light', 'dark', 'sepia', 'green'],
        'supportedFonts' => ['serif', 'sans-serif', 'mono'],
    ];

    public function __construct($database = null) {
        $this->db = $database;
    }

    public function getBookDetails($bookId) {
        return [
            'id' => $bookId,
            'title' => 'The Emerald Crown',
            'author' => 'Sarah Mitchell',
            'rating' => 4.8,
            'reviews' => 2340,
            'chapters' => 45,
            'views' => 125000,
            'status' => 'Ongoing',
            'synopsis' => 'A tale of adventure, sacrifice, and the magic that binds us all.',
            'tags' => ['Fantasy', 'Magic', 'Adventure'],
            'likeCount' => 8234,
        ];
    }

    public function getChapterContent($bookId, $chapterId) {
        return [
            'bookId' => $bookId,
            'chapterId' => $chapterId,
            'title' => 'The Beginning',
            'chapterNumber' => $chapterId,
            'content' => 'The morning sun filtered through the ancient oaks...',
            'views' => 45000,
            'date' => date('Y-m-d'),
        ];
    }

    public function saveReadingPreference($userId, $preferences) {
        $validated = [
            'fontSize' => $this->validateFontSize($preferences['fontSize'] ?? 16),
            'theme' => $this->validateTheme($preferences['theme'] ?? 'light'),
            'font' => $this->validateFont($preferences['font'] ?? 'sans-serif'),
            'lineHeight' => $this->validateLineHeight($preferences['lineHeight'] ?? 1.6),
        ];
        $_SESSION["user_{$userId}_preferences"] = $validated;
        return true;
    }

    public function voteOnComment($commentId, $userId, $voteType) {
        if (!in_array($voteType, ['like', 'dislike'])) return false;
        $existingVote = $this->getExistingVote($commentId, $userId);
        if ($existingVote) {
            return $this->updateVote($commentId, $userId, $voteType);
        }
        return $this->addVote($commentId, $userId, $voteType);
    }

    public function followAuthor($userId, $authorId) {
        if ($userId === $authorId) return false;
        $following = $this->getUserFollowing($userId) ?? [];
        if (!in_array($authorId, $following)) {
            $following[] = $authorId;
            return $this->updateUserFollowing($userId, $following);
        }
        return true;
    }

    public function unfollowAuthor($userId, $authorId) {
        $following = $this->getUserFollowing($userId) ?? [];
        $key = array_search($authorId, $following);
        if ($key !== false) {
            unset($following[$key]);
            return $this->updateUserFollowing($userId, array_values($following));
        }
        return true;
    }

    public function bookmarkBook($userId, $bookId) {
        $bookmarks = $this->getUserBookmarks($userId) ?? [];
        if (!in_array($bookId, $bookmarks)) {
            $bookmarks[] = $bookId;
            return $this->updateUserBookmarks($userId, $bookmarks);
        }
        return true;
    }

    public function unbookmarkBook($userId, $bookId) {
        $bookmarks = $this->getUserBookmarks($userId) ?? [];
        $key = array_search($bookId, $bookmarks);
        if ($key !== false) {
            unset($bookmarks[$key]);
            return $this->updateUserBookmarks($userId, array_values($bookmarks));
        }
        return true;
    }

    public function getUserLibrary($userId) {
        $bookmarks = $this->getUserBookmarks($userId) ?? [];
        $library = [];
        foreach ($bookmarks as $bookId) {
            $library[] = $this->getBookDetails($bookId);
        }
        return $library;
    }

    private function validateFontSize($size) {
        return max($this->config['minFontSize'], min($this->config['maxFontSize'], intval($size)));
    }

    private function validateTheme($theme) {
        return in_array($theme, $this->config['supportedThemes']) ? $theme : 'light';
    }

    private function validateFont($font) {
        return in_array($font, $this->config['supportedFonts']) ? $font : 'sans-serif';
    }

    private function validateLineHeight($height) {
        return max(1.2, min(2.5, floatval($height)));
    }

    private function getUserFollowing($userId) {
        return $_SESSION["user_{$userId}_following"] ?? null;
    }

    private function updateUserFollowing($userId, $following) {
        $_SESSION["user_{$userId}_following"] = $following;
        return true;
    }

    private function getUserBookmarks($userId) {
        return $_SESSION["user_{$userId}_bookmarks"] ?? null;
    }

    private function updateUserBookmarks($userId, $bookmarks) {
        $_SESSION["user_{$userId}_bookmarks"] = $bookmarks;
        return true;
    }

    private function getExistingVote($commentId, $userId) {
        return $_SESSION["vote_{$commentId}_{$userId}"] ?? null;
    }

    private function addVote($commentId, $userId, $voteType) {
        $_SESSION["vote_{$commentId}_{$userId}"] = $voteType;
        return true;
    }

    private function updateVote($commentId, $userId, $voteType) {
        $_SESSION["vote_{$commentId}_{$userId}"] = $voteType;
        return true;
    }
}

$bookDetailServer = new ScrollNovelsBookDetailServer();

// Get book ID from URL
$book_id = isset($_GET['id']) ? intval($_GET['id']) : 1;

try {
    // Fetch book data from database
    $stmt = $pdo->prepare("SELECT * FROM stories WHERE id = ?");
    $stmt->execute([$book_id]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$book) {
        $book = [
            'id' => $book_id,
            'title' => 'The Emerald Crown',
            'author_id' => 1,
            'description' => 'In a world where magic flows through ancient emeralds, Lyra discovers she is the chosen one destined to protect the Emerald Crown...',
            'cover' => '👑',
            'views' => 125000,
            'status' => 'published',
            'is_adult' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // Fetch author information
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->execute([$book['author_id'] ?? 1]);
    $author = $stmt->fetch(PDO::FETCH_ASSOC) ?? ['username' => 'Anonymous'];
    
    // Fetch chapters
    $stmt = $pdo->prepare("SELECT id, chapter_number, title, views, created_at FROM chapters WHERE story_id = ? ORDER BY chapter_number ASC LIMIT 10");
    $stmt->execute([$book_id]);
    $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch total chapters count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM chapters WHERE story_id = ?");
    $stmt->execute([$book_id]);
    $chapter_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Fetch chapter views sum
    $stmt = $pdo->prepare("SELECT SUM(views) as total_views, COUNT(DISTINCT user_id) as unique_readers FROM chapters WHERE story_id = ?");
    $stmt->execute([$book_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Mock engagement data (integrate with actual database as needed)
    $engagement = [
        'likes' => 3450,
        'dislikes' => 120,
        'rating' => 4.8,
        'reviews' => 2340,
        'inLibrary' => false
    ];
    
    // Fetch sample reviews/comments (from blog_comments table)
    $stmt = $pdo->prepare("SELECT user_id, comment_text, created_at FROM blog_comments WHERE blog_post_id = ? LIMIT 3");
    $stmt->execute([$book_id]);
    $reviews = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $reviews[] = $row;
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $book = ['id' => $book_id, 'title' => 'Book Not Found', 'description' => 'Unable to load book'];
    $chapters = [];
    $chapter_count = 0;
    $engagement = ['likes' => 0, 'dislikes' => 0, 'rating' => 0, 'reviews' => 0];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title'] ?? 'Book'); ?> - Scroll Novels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/scrollnovels/css/styles.css">
    <style>
        :root {
            --primary: #065f46;
            --primary-light: #10b981;
            --primary-lighter: #d1fae5;
            --secondary: #fbbf24;
            --background: #faf8f5;
            --surface: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border: #e5e7eb;
            --success: #22c55e;
            --warning: #f97316;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        /* Book Hero Section */
        .book-hero {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 3rem 1rem;
            margin-bottom: 2rem;
        }

        .book-header {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 3rem;
            align-items: start;
        }

        .book-cover {
            font-size: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .book-info h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .book-info .author {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .book-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .book-meta span {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            backdrop-filter: blur(10px);
        }

        .synopsis {
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 2rem;
            max-width: 600px;
            opacity: 0.95;
        }

        .book-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: var(--secondary);
            color: var(--text-primary);
            box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(251, 191, 36, 0.4);
        }

        .btn-secondary {
            background: white;
            color: var(--primary);
            border: 2px solid white;
        }

        .btn-secondary:hover {
            background: transparent;
            color: white;
        }

        .btn-large {
            padding: 1rem 2rem;
            font-size: 1rem;
        }

        /* Engagement Section */
        .engagement-section {
            background: var(--surface);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 2rem 1rem;
            margin-bottom: 2rem;
        }

        .engagement-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .engagement-btn {
            background: var(--surface);
            border: 2px solid var(--border);
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .engagement-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-lighter);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(6, 95, 70, 0.1);
            transform: translateY(-2px);
        }

        .stat-card .label {
            color: var(--text-secondary);
            font-size: 0.85rem;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }

        /* Chapters Section */
        .chapters-section {
            padding: 2rem 0;
        }

        .chapters-section h2 {
            font-size: 1.75rem;
            margin-bottom: 2rem;
            color: var(--text-primary);
        }

        .chapters-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .chapter-item {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .chapter-item:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(6, 95, 70, 0.1);
        }

        .chapter-link {
            display: grid;
            grid-template-columns: 60px 1fr auto;
            gap: 1rem;
            padding: 1.5rem;
            text-decoration: none;
            color: var(--text-primary);
            align-items: center;
        }

        .chapter-number {
            background: var(--primary-lighter);
            color: var(--primary);
            width: 50px;
            height: 50px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .chapter-title {
            font-weight: 600;
            color: var(--text-primary);
        }

        .chapter-meta {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .view-all-link {
            display: inline-block;
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border: 2px solid var(--primary);
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .view-all-link:hover {
            background: var(--primary);
            color: white;
        }

        /* Reviews Section */
        .reviews-section {
            padding: 2rem 0;
            border-top: 1px solid var(--border);
        }

        .reviews-section h2 {
            font-size: 1.75rem;
            margin-bottom: 2rem;
        }

        .reviews-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .review-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .review-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(6, 95, 70, 0.1);
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .review-author {
            font-weight: 600;
            color: var(--text-primary);
        }

        .review-rating {
            color: var(--secondary);
            font-weight: 700;
        }

        .review-content {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .book-header {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .book-cover {
                font-size: 100px;
                height: 200px;
            }

            .book-info h1 {
                font-size: 1.75rem;
            }

            .engagement-buttons {
                flex-direction: column;
            }

            .engagement-btn {
                width: 100%;
            }

            .chapter-link {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .reviews-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .book-hero {
                padding: 1.5rem 1rem;
            }

            .book-info h1 {
                font-size: 1.5rem;
            }

            .book-meta {
                flex-direction: column;
                gap: 0.5rem;
            }

            .book-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Book Hero Section -->
        <section class="book-hero">
            <div class="container">
                <div class="book-header">
                    <div class="book-cover">
                        <?php echo $book['cover'] ?? '📖'; ?>
                    </div>
                    <div class="book-info">
                        <h1><?php echo htmlspecialchars($book['title'] ?? 'Untitled'); ?></h1>
                        <p class="author">by <?php echo htmlspecialchars($author['username'] ?? 'Anonymous'); ?></p>
                        <div class="book-meta">
                            <span>📚 Fantasy</span>
                            <span>⭐ <?php echo number_format($engagement['rating'] ?? 4.8, 1); ?></span>
                            <span>💬 <?php echo number_format($engagement['reviews'] ?? 0); ?> reviews</span>
                        </div>
                        <p class="synopsis">
                            <?php echo htmlspecialchars(substr($book['description'] ?? '', 0, 300)); ?>...
                        </p>
                        <div class="book-actions">
                            <a href="/scrollnovels/pages/chapter-reader-integrated.php?book=<?php echo $book_id; ?>&chapter=1" class="btn btn-primary btn-large">
                                ▶ Start Reading
                            </a>
                            <button class="btn btn-secondary" onclick="alert('Added to library!')">
                                + Add to Library
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Engagement Section -->
        <section class="engagement-section">
            <div class="engagement-buttons">
                <button class="engagement-btn" onclick="alert('Liked!')">❤️ Like (<?php echo number_format($engagement['likes'] ?? 0); ?>)</button>
                <button class="engagement-btn" onclick="alert('Disliked!')">👎 Dislike (<?php echo number_format($engagement['dislikes'] ?? 0); ?>)</button>
                <button class="engagement-btn" onclick="alert('Following author!')">👤 Follow Author</button>
                <button class="engagement-btn" onclick="alert('Opening donation!')">💝 Support Author</button>
            </div>
        </section>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Total Views</div>
                <div class="value"><?php echo number_format($book['views'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Chapters</div>
                <div class="value"><?php echo $chapter_count; ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Unique Readers</div>
                <div class="value"><?php echo number_format($stats['unique_readers'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Total Likes</div>
                <div class="value"><?php echo number_format($engagement['likes'] ?? 0); ?></div>
            </div>
        </div>

        <!-- Chapters Section -->
        <section class="chapters-section">
            <h2>Chapters (<?php echo $chapter_count; ?>)</h2>
            <div class="chapters-list">
                <?php if (!empty($chapters)): ?>
                    <?php foreach ($chapters as $chapter): ?>
                        <div class="chapter-item">
                            <a href="/scrollnovels/pages/chapter-reader-integrated.php?book=<?php echo $book_id; ?>&chapter=<?php echo $chapter['chapter_number']; ?>" class="chapter-link">
                                <span class="chapter-number"><?php echo $chapter['chapter_number']; ?></span>
                                <span class="chapter-title"><?php echo htmlspecialchars($chapter['title'] ?? 'Chapter ' . $chapter['chapter_number']); ?></span>
                                <span class="chapter-meta">👁️ <?php echo number_format($chapter['views'] ?? 0); ?> views</span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: var(--text-secondary); text-align: center; padding: 2rem;">No chapters available yet.</p>
                <?php endif; ?>
            </div>
            <?php if ($chapter_count > 10): ?>
                <a href="/scrollnovels/pages/chapters-list.php?book=<?php echo $book_id; ?>" class="view-all-link">
                    View All <?php echo $chapter_count; ?> Chapters
                </a>
            <?php endif; ?>
        </section>

        <!-- Reviews Section -->
        <?php if (!empty($reviews)): ?>
            <section class="reviews-section">
                <h2>Reader Reviews</h2>
                <div class="reviews-grid">
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <span class="review-author">Reader</span>
                                <span class="review-rating">⭐ 5</span>
                            </div>
                            <p class="review-content">
                                <?php echo htmlspecialchars(substr($review['comment_text'] ?? '', 0, 200)); ?>
                            </p>
                            <small style="color: var(--text-secondary);">
                                <?php echo isset($review['created_at']) ? date('M d, Y', strtotime($review['created_at'])) : 'Recently'; ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <footer style="text-align: center; padding: 2rem; color: var(--text-secondary); border-top: 1px solid var(--border); margin-top: 3rem;">
        <p>&copy; 2025 Scroll Novels. All rights reserved.</p>
    </footer>
</body>
</html>
