<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$currentPage = 'book';
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
$story = null;
$chapters = [];
$reviews = [];
$userReview = null;
$isBookSaved = false;

// Get book ID - support both 'id' and 'book_id' parameters
$bookId = (int)($_GET['id'] ?? $_GET['book_id'] ?? 0);

if (!$bookId) {
    error_log("book.php: No book id provided in query string");
    header('Location: ' . site_url('/pages/browse.php'));
    exit;
}

// Fetch story details - try both published and active stories
try {
    $stmt = $pdo->prepare("
        SELECT s.*, u.id as author_id, u.username as author_name, u.profile_image
        FROM stories s 
        LEFT JOIN users u ON s.author_id = u.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$bookId]);
    $story = $stmt->fetch();
    
    if (!$story) {
        error_log("book.php: story not found for id: " . $bookId);
        header('Location: ' . site_url('/pages/browse.php'));
        exit;
    }
    
    // Debug: log tags value
    error_log("DEBUG: Story ID " . $bookId . " has tags: " . ($story['tags'] ?? 'NULL'));
} catch (Exception $e) {
    header('Location: ' . site_url('/pages/browse.php'));
    exit;
}

// Increment views
try {
    $stmt = $pdo->prepare("UPDATE stories SET views = views + 1 WHERE id = ?");
    $stmt->execute([$bookId]);
} catch (Exception $e) {}

// Fetch chapters
try {
    $stmt = $pdo->prepare("SELECT * FROM chapters WHERE story_id = ? ORDER BY sequence ASC");
    $stmt->execute([$bookId]);
    $chapters = $stmt->fetchAll();
} catch (Exception $e) {
    $chapters = [];
}

// Ensure reviews table exists with correct schema
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            story_id INT NOT NULL,
            user_id INT NOT NULL,
            rating TINYINT NOT NULL DEFAULT 5,
            review_text LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_story (user_id, story_id),
            KEY idx_story_id (story_id),
            KEY idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Add review_text column if missing
    $pdo->exec("ALTER TABLE reviews ADD COLUMN IF NOT EXISTS review_text LONGTEXT AFTER rating");
    
    // Add updated_at column if missing
    $pdo->exec("ALTER TABLE reviews ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
} catch (Exception $e) {
    error_log("Reviews table setup: " . $e->getMessage());
}

// Ensure review_interactions table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS review_interactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        review_id INT NOT NULL,
        user_id INT NOT NULL,
        type ENUM('like', 'dislike') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_interaction (review_id, user_id, type),
        KEY idx_review_id (review_id),
        KEY idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    error_log("Review interactions table setup: " . $e->getMessage());
}

// Fetch reviews with user info AND like/dislike counts from database
try {
    $stmt = $pdo->prepare("
        SELECT 
            r.*, 
            u.username, 
            u.profile_image, 
            u.id as author_id,
            (SELECT COUNT(*) FROM review_interactions WHERE review_id = r.id AND type = 'like') as likes,
            (SELECT COUNT(*) FROM review_interactions WHERE review_id = r.id AND type = 'dislike') as dislikes
        FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.story_id = ?
        GROUP BY r.user_id
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$bookId]);
    $reviews = $stmt->fetchAll();
} catch (Exception $e) {
    $reviews = [];
}

// Fetch current user's review if logged in
if ($isLoggedIn && $userId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM reviews WHERE story_id = ? AND user_id = ?");
        $stmt->execute([$bookId, $userId]);
        $userReview = $stmt->fetch();
    } catch (Exception $e) {}
    
    // Check if book is already saved by user
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM saved_stories WHERE user_id = ? AND story_id = ? LIMIT 1");
        $stmt->execute([$userId, $bookId]);
        $isBookSaved = (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        $isBookSaved = false;
    }
}

// Calculate rating
$rating = 0;
$totalRating = 0;
if (!empty($reviews)) {
    foreach ($reviews as $review) {
        $totalRating += (float)$review['rating'];
    }
    $rating = round($totalRating / count($reviews), 1);
}

// Fetch top supporters (donations) - for counting only
$topSupporters = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.profile_image, SUM(d.amount) as total_donated
        FROM donations d
        LEFT JOIN users u ON d.donor_id = u.id
        WHERE d.story_id = ?
        GROUP BY d.donor_id
        ORDER BY total_donated DESC
        LIMIT 5
    ");
    $stmt->execute([$bookId]);
    $topSupporters = $stmt->fetchAll();
} catch (Exception $e) {
    $topSupporters = [];
}

// Fetch library count
$libraryCount = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM saved_stories WHERE story_id = ?");
    $stmt->execute([$bookId]);
    $libraryCount = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $libraryCount = 0;
}

// Fetch author points balance for display
$author_points = 0;
try {
    if (!empty($story['author_id'])) {
        $stmt = $pdo->prepare("SELECT points FROM user_points WHERE user_id = ?");
        $stmt->execute([(int)$story['author_id']]);
        $author_points = (int)($stmt->fetchColumn() ?? 0);
    }
} catch (Exception $e) {
    $author_points = 0;
}

// Fetch reading list status breakdown
$readingStatusCount = [];
try {
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count
        FROM user_list_status
        WHERE story_id = ?
        GROUP BY status
    ");
    $stmt->execute([$bookId]);
    $readingStatusCount = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $readingStatusCount = [];
}

$totalReaders = array_sum($readingStatusCount) ?: 0;

// Fetch rating distribution
$ratingDistribution = [];
try {
    $stmt = $pdo->prepare("
        SELECT rating, COUNT(*) as count
        FROM reviews
        WHERE story_id = ?
        GROUP BY rating
        ORDER BY rating DESC
    ");
    $stmt->execute([$bookId]);
    $ratingDistribution = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $ratingDistribution = [];
}

// Get current user's reading status
$userReadingStatus = null;
if ($isLoggedIn && $userId) {
    try {
        $stmt = $pdo->prepare("SELECT status FROM user_list_status WHERE story_id = ? AND user_id = ?");
        $stmt->execute([$bookId, $userId]);
        $userReadingStatus = $stmt->fetchColumn();
    } catch (Exception $e) {}
}

// Fetch similar books ("Books Like This") based on genre and tags
$similarBooks = [];
try {
    $similarConditions = [];
    $similarParams = [$bookId]; // Exclude current book
    
    // Match by genre
    if (!empty($story['genre'])) {
        $similarConditions[] = "genre = ?";
        $similarParams[] = $story['genre'];
    }
    
    // Match by tags (if tags contain common keywords)
    if (!empty($story['tags'])) {
        $storyTags = array_map('trim', explode(',', $story['tags']));
        foreach (array_slice($storyTags, 0, 3) as $tag) { // Use first 3 tags
            if (!empty($tag)) {
                $similarConditions[] = "tags LIKE ?";
                $similarParams[] = "%$tag%";
            }
        }
    }
    
    if (!empty($similarConditions)) {
        $sql = "SELECT s.id, s.title, s.slug, s.cover, s.cover_image, s.synopsis, s.genre, s.views, s.likes, 
                       u.username as author_name
                FROM stories s
                LEFT JOIN users u ON s.author_id = u.id
                WHERE s.id != ? AND s.status = 'published' AND (" . implode(' OR ', $similarConditions) . ")
                ORDER BY s.views DESC, s.likes DESC
                LIMIT 6";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($similarParams);
        $similarBooks = $stmt->fetchAll();
    }
    
    // Fallback: if no similar books, get popular books in same category
    if (empty($similarBooks) && !empty($story['genre'])) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.title, s.slug, s.cover, s.cover_image, s.synopsis, s.genre, s.views, s.likes,
                   u.username as author_name
            FROM stories s
            LEFT JOIN users u ON s.author_id = u.id
            WHERE s.id != ? AND s.status = 'published'
            ORDER BY s.views DESC
            LIMIT 6
        ");
        $stmt->execute([$bookId]);
        $similarBooks = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Similar books error: " . $e->getMessage());
    $similarBooks = [];
}


?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800">
    <div class="max-w-6xl mx-auto px-4 py-8">

        <!-- Breadcrumb -->
        <div class="mb-6 text-sm text-gray-600 dark:text-gray-400">
            <a href="<?= site_url('/pages/browse.php') ?>" class="hover:text-emerald-600">Browse</a>
            <span> / </span>
            <span><?= htmlspecialchars($story['title']) ?></span>
        </div>

        <!-- Book Header Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden mb-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 p-8">
                
                <!-- Book Cover -->
                <div class="md:col-span-1">
                    <div class="aspect-[3/4] bg-emerald-50 dark:bg-emerald-900/30 rounded-lg overflow-hidden shadow-lg flex items-center justify-center">
                        <?php $coverImage = !empty($story['cover_image']) ? $story['cover_image'] : (!empty($story['cover']) ? $story['cover'] : ''); ?>
                        <?php if (!empty($coverImage)): ?>
                            <img src="<?= htmlspecialchars($coverImage) ?>" alt="<?= htmlspecialchars($story['title']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="text-6xl">📚</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Book Info -->
                <div class="md:col-span-3 space-y-4">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">
                                <?= htmlspecialchars($story['title']) ?>
                            </h1>
                            <!-- Status Badge -->
                            <?php 
                            $status = $story['series_status'] ?? 'ongoing';
                            if ($status === 'completed') {
                                $statusColor = 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
                            } elseif ($status === 'hiatus') {
                                $statusColor = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                            } else {
                                $statusColor = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                            }
                            $statusLabel = ucfirst($status);
                            ?>
                            <span class="px-3 py-1 rounded-full text-sm font-semibold <?= $statusColor ?>">
                                <?= $statusLabel ?>
                            </span>
                        </div>
                        <p class="text-lg text-emerald-600 dark:text-emerald-400 font-semibold">
                            by <a href="<?= site_url('/pages/profile.php?user_id=' . (int)($story['author_id'] ?? 0)) ?>" class="hover:underline"><?= htmlspecialchars($story['author_name'] ?? 'Unknown') ?></a>
                        </p>
                    </div>

                    <!-- Stats -->
                    <div class="flex flex-wrap gap-6 py-4 border-y border-gray-200 dark:border-gray-700">
                        <div>
                            <div class="text-2xl font-bold text-emerald-600">⭐ <?= htmlspecialchars($rating) ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400"><?= count($reviews) ?> reviews</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-blue-600">👁️ <?= format_number($story['views'] ?? 0) ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Views</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-purple-600">📖 <?= count($chapters) ?></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Chapters</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-amber-600">💰 <span id="authorPointsBalance"><?= htmlspecialchars($author_points) ?></span></div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Author Points</div>
                        </div>
                    </div>

                    <!-- Rating Breakdown -->
                    <?php
                    // Calculate rating distribution
                    $ratingCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
                    foreach ($reviews as $review) {
                        $r = (int)$review['rating'];
                        if (isset($ratingCounts[$r])) $ratingCounts[$r]++;
                    }
                    $totalReviews = count($reviews) > 0 ? count($reviews) : 1;
                    ?>
                    <div class="py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Rating Distribution</h3>
                        <div class="space-y-2">
                            <?php for ($stars = 5; $stars >= 1; $stars--): ?>
                                <?php 
                                $count = $ratingCounts[$stars];
                                $percentage = ($count / $totalReviews) * 100;
                                ?>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400 w-8">☆ <?= $stars ?></span>
                                    <div class="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-emerald-500 to-emerald-600 transition-all duration-300" style="width: <?= $percentage ?>%"></div>
                                    </div>
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400 w-16 text-right">
                                        <?= number_format($percentage, 0) ?>% (<?= $count ?>)
                                    </span>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">About</h3>
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                            <?= nl2br(htmlspecialchars($story['description'] ?? '')) ?>
                        </p>
                    </div>

                    <!-- Category & Tags -->
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Tags</h3>
                        <div class="flex flex-wrap gap-2">
                            <?php if (!empty($story['tags'])): ?>
                                <?php foreach (explode(',', $story['tags']) as $tag): ?>
                                    <a href="<?= site_url('/pages/browse.php?tag=' . urlencode(trim($tag))) ?>" 
                                       class="inline-block px-3 py-1 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 rounded-full text-sm hover:bg-emerald-200 dark:hover:bg-emerald-900 transition">
                                        #<?= htmlspecialchars(trim($tag)) ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Genres -->
                    <?php if (!empty($story['genres'])): ?>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">📚 Genres</h3>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach (explode(',', $story['genres']) as $genre): ?>
                                <a href="<?= site_url('/pages/browse.php?category=' . urlencode(trim($genre))) ?>" 
                                   class="inline-block px-3 py-1 bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 rounded-full text-sm hover:bg-blue-200 dark:hover:bg-blue-900 transition">
                                    <?= htmlspecialchars(trim($genre)) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Content Warnings -->
                    <?php if (!empty($story['content_warnings'])): ?>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">⚠️ Content Warnings</h3>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach (explode(',', $story['content_warnings']) as $warning): ?>
                                <span class="inline-block px-3 py-1 bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300 rounded-full text-sm">
                                    <?= htmlspecialchars(trim($warning)) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row flex-wrap gap-2 sm:gap-3 pt-4">
                        <?php if (count($chapters) > 0): ?>
                            <a href="<?= site_url('/pages/read.php?id=' . $bookId . '&ch=1') ?>" 
                               class="flex-1 min-w-fit px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg transition flex items-center justify-center gap-2">
                                📖 Start Reading
                            </a>
                        <?php else: ?>
                            <button class="flex-1 min-w-fit px-6 py-3 bg-gray-400 text-white font-semibold rounded-lg cursor-not-allowed" disabled>
                                No Chapters Yet
                            </button>
                        <?php endif; ?>
                        
                        <a onclick="openSupportModal(<?= $bookId ?>)" class="flex-1 px-6 py-3 bg-pink-600 hover:bg-pink-700 text-white font-semibold rounded-lg transition flex items-center justify-center gap-2 cursor-pointer">
                            💝 Support
                        </a>

                        <?php if ($isLoggedIn): ?>
                            <button class="flex-1 px-6 py-3 <?= $isBookSaved ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-blue-600 hover:bg-blue-700' ?> text-white font-semibold rounded-lg transition flex items-center justify-center gap-2" onclick="toggleAddToLibrary(<?= $bookId ?>)" id="library-btn">
                                <?= $isBookSaved ? '✅ Saved' : '🔖 Save' ?>
                            </button>
                            <button class="flex-1 px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition flex items-center justify-center gap-2" onclick="reportBook(<?= $bookId ?>)">
                                🚩 Report
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
            <div class="flex border-b border-gray-200 dark:border-gray-700 flex-wrap overflow-x-auto">
                <button onclick="switchTab('chapters')" id="chapters-tab" class="px-4 sm:px-6 py-3 sm:py-4 font-semibold text-xs sm:text-sm text-emerald-600 border-b-2 border-emerald-600 transition whitespace-nowrap">
                    📖 Ch (<?= count($chapters) ?>)
                </button>
                <button onclick="switchTab('reviews')" id="reviews-tab" class="px-4 sm:px-6 py-3 sm:py-4 font-semibold text-xs sm:text-sm text-gray-600 dark:text-gray-400 border-b-2 border-transparent hover:border-gray-300 transition whitespace-nowrap">
                    💬 Reviews (<?= count($reviews) ?>)
                </button>
                <button onclick="switchTab('supporters')" id="supporters-tab" class="px-4 sm:px-6 py-3 sm:py-4 font-semibold text-xs sm:text-sm text-gray-600 dark:text-gray-400 border-b-2 border-transparent hover:border-gray-300 transition whitespace-nowrap">
                    🏆 Top Supporters (<span id="supporters-count">0</span>)
                </button>
                <button onclick="switchTab('status')" id="status-tab" class="px-4 sm:px-6 py-3 sm:py-4 font-semibold text-xs sm:text-sm text-gray-600 dark:text-gray-400 border-b-2 border-transparent hover:border-gray-300 transition whitespace-nowrap">
                    📊 Stats
                </button>
            </div>

            <!-- Chapters Tab -->
            <div id="chapters-content" class="p-8">
                <?php if (count($chapters) > 0): ?>
                    <div class="space-y-3">
                        <?php foreach ($chapters as $chapter): ?>
                            <?php $chNum = $chapter['number'] ?? $chapter['sequence'] ?? 0; ?>
                            <div class="group flex items-center gap-3">
                                <a href="<?= site_url('/pages/read.php?id=' . $bookId . '&ch=' . $chNum) ?>"
                                   class="flex-1 block p-4 bg-gradient-to-r from-emerald-50 to-green-50 dark:from-gray-700 dark:to-gray-600 rounded-lg hover:shadow-md transition">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h4 class="font-semibold text-gray-900 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400">
                                                Chapter <?= (int)$chNum ?> - <?= htmlspecialchars($chapter['title'] ?? 'Untitled') ?>
                                            </h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                                <?= date('M d, Y', strtotime($chapter['created_at'] ?? 'now')) ?>
                                            </p>
                                        </div>
                                        <span class="text-2xl">→</span>
                                    </div>
                                </a>
                                <?php if ($isLoggedIn && $userId && $story['author_id'] == $userId): ?>
                                    <a href="<?= site_url('/story/chapter_edit.php?chapter_id=' . $chapter['id'] . '&story_id=' . $bookId) ?>" 
                                       class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition opacity-0 group-hover:opacity-100">
                                        ✏️ Edit
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <p class="text-gray-500 dark:text-gray-400 text-lg">No chapters published yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Reviews Tab -->
            <div id="reviews-content" class="p-8 hidden">
                <!-- Write Review (if logged in) -->
                <?php if ($isLoggedIn && $userId): ?>
                    <div class="mb-8 p-6 bg-gradient-to-r from-emerald-50 to-green-50 dark:from-gray-700 dark:to-gray-600 rounded-lg">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-4">
                            <?= $userReview ? 'Update Your Review' : 'Write a Review' ?>
                        </h3>
                        <form id="review-form" class="space-y-4">
                            <input type="hidden" name="story_id" value="<?= $bookId ?>">
                            <div id="reviewMessage" class="hidden mb-4 p-3 rounded-lg text-sm font-medium"></div>
                            
                            <!-- Rating -->
                            <div>
                                <label class="block font-medium text-gray-900 dark:text-white mb-3">Rating</label>
                                <div class="rating-stars" id="ratingStars">
                                    <input type="radio" name="rating" value="1" id="star1" <?= ($userReview && $userReview['rating'] == 1) ? 'checked' : '' ?>>
                                    <label for="star1" class="star"></label>
                                    
                                    <input type="radio" name="rating" value="2" id="star2" <?= ($userReview && $userReview['rating'] == 2) ? 'checked' : '' ?>>
                                    <label for="star2" class="star"></label>
                                    
                                    <input type="radio" name="rating" value="3" id="star3" <?= ($userReview && $userReview['rating'] == 3) ? 'checked' : '' ?>>
                                    <label for="star3" class="star"></label>
                                    
                                    <input type="radio" name="rating" value="4" id="star4" <?= ($userReview && $userReview['rating'] == 4) ? 'checked' : '' ?>>
                                    <label for="star4" class="star"></label>
                                    
                                    <input type="radio" name="rating" value="5" id="star5" <?= ($userReview && $userReview['rating'] == 5) ? 'checked' : '' ?>>
                                    <label for="star5" class="star"></label>
                                </div>
                            </div>
                            <style>
                            .rating-stars {
                                display: flex;
                                gap: 8px;
                                cursor: pointer;
                                justify-content: flex-start;
                                flex-wrap: wrap;
                            }
                            
                            .rating-stars input {
                                display: none;
                            }
                            
                            .rating-stars .star {
                                width: 40px;
                                height: 40px;
                                min-width: 40px;
                                display: inline-block;
                                background: #d1d5db;
                                clip-path: polygon(
                                    50% 0%,
                                    61% 35%,
                                    98% 35%,
                                    68% 57%,
                                    79% 91%,
                                    50% 70%,
                                    21% 91%,
                                    32% 57%,
                                    2% 35%,
                                    39% 35%
                                );
                                transition: all 0.2s ease;
                                cursor: pointer;
                                touch-action: manipulation;
                            }
                            
                            /* Highlight stars - using data attribute set by JS */
                            .rating-stars .star.active {
                                background: #fbbf24 !important;
                                transform: scale(1.1);
                            }
                            
                            /* Hover effect */
                            .rating-stars .star:hover {
                                background: #f4b860;
                                transform: scale(1.05);
                            }
                            </style>
                            <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const ratingStars = document.getElementById('ratingStars');
                                if (ratingStars) {
                                    // Get stars in correct order (labels only)
                                    const stars = Array.from(ratingStars.querySelectorAll('label.star'));
                                    const inputs = Array.from(ratingStars.querySelectorAll('input[type="radio"]'));
                                    
                                    // Function to update star display
                                    function updateStars(rating) {
                                        stars.forEach((star, index) => {
                                            if (index < rating) {
                                                star.classList.add('active');
                                            } else {
                                                star.classList.remove('active');
                                            }
                                        });
                                    }
                                    
                                    // Click handler for stars - use data attribute for reliable value
                                    stars.forEach((star, index) => {
                                        star.addEventListener('click', function(e) {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            const value = index + 1;
                                            // Uncheck all first
                                            inputs.forEach(inp => inp.checked = false);
                                            // Check the correct one
                                            if (inputs[index]) {
                                                inputs[index].checked = true;
                                            }
                                            updateStars(value);
                                            console.log('Rating set to:', value); // Debug
                                        });
                                    });
                                    
                                    // Also handle clicking on the input labels
                                    inputs.forEach((input, index) => {
                                        input.addEventListener('change', function() {
                                            updateStars(parseInt(this.value));
                                        });
                                    });
                                    
                                    // Hover effects
                                    stars.forEach((star, index) => {
                                        star.addEventListener('mouseenter', function() {
                                            for (let i = 0; i <= index; i++) {
                                                stars[i].style.background = '#f4b860';
                                            }
                                        });
                                        star.addEventListener('mouseleave', function() {
                                            stars.forEach(s => s.style.background = '');
                                        });
                                    });
                                    
                                    // Initialize with existing rating
                                    const checked = ratingStars.querySelector('input:checked');
                                    if (checked) {
                                        updateStars(parseInt(checked.value));
                                    }
                                }
                            });
                            </script>

                            <!-- Review Text -->
                            <div>
                                <label class="block font-medium text-gray-900 dark:text-white mb-2 text-sm sm:text-base">Review</label>
                                <textarea name="review_text" class="w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm" 
                                          rows="4" placeholder="Share your thoughts..."><?= htmlspecialchars($userReview['review_text'] ?? '') ?></textarea>
                            </div>

                            <button type="submit" class="w-full sm:w-auto px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg transition text-sm sm:text-base">
                                <?= $userReview ? 'Update Review' : 'Post Review' ?>
                            </button>
                        </form>
                    </div>

                    <hr class="my-8 border-gray-200 dark:border-gray-700">
                <?php endif; ?>

                <!-- Reviews List -->
                <?php if (count($reviews) > 0): ?>
                    <div class="space-y-6">
                        <?php foreach ($reviews as $review): ?>
                            <div class="p-6 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-emerald-200 dark:bg-emerald-900 flex items-center justify-center">
                                            <?php if (!empty($review['profile_image'])): ?>
                                                <img src="<?= htmlspecialchars($review['profile_image']) ?>" alt="Avatar" class="w-full h-full rounded-full object-cover">
                                            <?php else: ?>
                                                <span class="text-lg">👤</span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <a href="<?= site_url('/pages/profile.php?user_id=' . (int)$review['author_id']) ?>" class="font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                                                <?php 
                                                    $revUsername = isset($review['username']) ? $review['username'] : 'Anonymous';
                                                    echo htmlspecialchars($revUsername);
                                                ?>
                                            </a>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                <?php 
                                                    $revDate = isset($review['created_at']) ? $review['created_at'] : 'now';
                                                    echo date('M d, Y', strtotime($revDate));
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="inline-block w-5 h-5" style="background: <?= $i <= (int)$review['rating'] ? '#fbbf24' : '#d1d5db' ?>; clip-path: polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%);"></span>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <p class="text-gray-700 dark:text-gray-300 mb-4">
                                    <?= nl2br(htmlspecialchars($review['review_text'] ?? $review['content'] ?? '')) ?>
                                </p>
                                <div class="flex gap-4">
                                    <button class="flex items-center gap-2 text-gray-600 dark:text-gray-400 hover:text-green-600 transition review-like-btn" data-review-id="<?= $review['id'] ?>" onclick="likeReview(<?= $review['id'] ?>)">
                                        👍 <span class="like-count"><?= (int)($review['likes'] ?? 0) ?></span>
                                    </button>
                                    <button class="flex items-center gap-2 text-gray-600 dark:text-gray-400 hover:text-red-600 transition review-dislike-btn" data-review-id="<?= $review['id'] ?>" onclick="dislikeReview(<?= $review['id'] ?>)">
                                        👎 <span class="dislike-count"><?= (int)($review['dislikes'] ?? 0) ?></span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <p class="text-gray-500 dark:text-gray-400 text-lg">No reviews yet. Be the first to review!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Comments Tab (Discussion on Book) -->
            <div id="comments-content" class="p-8 hidden">
                <!-- Post Comment (if logged in) -->
                <?php if ($isLoggedIn && $userId): ?>
                    <div class="mb-8 p-6 bg-gradient-to-r from-blue-50 to-cyan-50 dark:from-gray-700 dark:to-gray-600 rounded-lg">
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-4">💭 Share Your Thoughts</h3>
                        <form id="comment-form" class="space-y-4">
                            <input type="hidden" name="story_id" value="<?= $bookId ?>">
                            <input type="hidden" name="chapter_id" value="0">
                            
                            <textarea id="comment-content" name="content" class="w-full p-4 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500" 
                                      rows="4" placeholder="Share your thoughts about this book..." required></textarea>
                            
                            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
                                Post Comment
                            </button>
                        </form>
                    </div>
                    <hr class="my-8 border-gray-200 dark:border-gray-700">
                <?php endif; ?>

                <!-- Comments List -->
                <div id="comments-list" class="space-y-6">
                    <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                        <p>Loading comments...</p>
                    </div>
                </div>
            </div>
            <div id="supporters-content" class="p-8 hidden">
                <div id="supporters-loading" style="display:block; text-align:center; padding:3rem 0; color:#6b7280;">
                    <p>Loading supporters...</p>
                </div>
                <div id="supporters-list" class="space-y-4" style="display:none;">
                    <!-- Populated by JavaScript -->
                </div>
                <div id="supporters-empty" class="text-center py-12" style="display:none;">
                    <p class="text-gray-500 dark:text-gray-400 text-lg">No supporters yet. Be the first to support!</p>
                </div>
            </div>

            <!-- Reading Status Tab -->
            <div id="status-content" class="p-8 hidden">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <?php
                    $reading = $readingStatusCount['reading'] ?? 0;
                    $planned = $readingStatusCount['planned'] ?? 0;
                    $abandoned = $readingStatusCount['abandoned'] ?? 0;
                    $completed = $readingStatusCount['completed'] ?? 0;
                    $total = $reading + $planned + $abandoned + $completed ?: 1;
                    ?>
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <div class="text-3xl font-bold text-blue-600"><?= $reading ?></div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Currently Reading</div>
                        <div class="text-xs text-gray-500 mt-1"><?= round(($reading / $total) * 100, 1) ?>%</div>
                    </div>
                    <div class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                        <div class="text-3xl font-bold text-purple-600"><?= $planned ?></div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Planned</div>
                        <div class="text-xs text-gray-500 mt-1"><?= round(($planned / $total) * 100, 1) ?>%</div>
                    </div>
                    <div class="p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                        <div class="text-3xl font-bold text-orange-600"><?= $completed ?></div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Completed</div>
                        <div class="text-xs text-gray-500 mt-1"><?= round(($completed / $total) * 100, 1) ?>%</div>
                    </div>
                    <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                        <div class="text-3xl font-bold text-red-600"><?= $abandoned ?></div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Abandoned</div>
                        <div class="text-xs text-gray-500 mt-1"><?= round(($abandoned / $total) * 100, 1) ?>%</div>
                    </div>
                </div>

                <?php if ($isLoggedIn && $userId): ?>
                    <div class="p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Your Reading Status</h4>
                        <div class="flex gap-2 flex-wrap">
                            <?php
                            $statusOptions = ['reading' => '📖 Reading', 'planned' => '📋 Planned', 'completed' => '✅ Completed', 'abandoned' => '❌ Abandoned'];
                            foreach ($statusOptions as $option => $label):
                            ?>
                                <button class="px-4 py-2 rounded-lg font-semibold transition <?= ($userReadingStatus === $option) ? 'bg-emerald-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-300' ?>"
                                        onclick="setReadingStatus(<?= $bookId ?>, '<?= $option ?>')">
                                    <?= $label ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

<script>
function switchTab(tab) {
    console.log('📑 switchTab called with:', tab);
    // Hide all content
    document.getElementById('chapters-content').classList.add('hidden');
    document.getElementById('reviews-content').classList.add('hidden');
    document.getElementById('supporters-content').classList.add('hidden');
    document.getElementById('status-content').classList.add('hidden');
    
    // Remove active state from all tabs
    document.getElementById('chapters-tab').classList.remove('text-emerald-600', 'border-emerald-600');
    document.getElementById('reviews-tab').classList.remove('text-emerald-600', 'border-emerald-600');
    document.getElementById('supporters-tab').classList.remove('text-emerald-600', 'border-emerald-600');
    document.getElementById('status-tab').classList.remove('text-emerald-600', 'border-emerald-600');
    
    // Show selected content
    document.getElementById(tab + '-content').classList.remove('hidden');
    document.getElementById(tab + '-tab').classList.add('text-emerald-600', 'border-emerald-600');
    
    // Load supporters if supporters tab is selected
    if (tab === 'supporters') {
        console.log('💫 Calling loadSupporters()');
        loadSupporters();
    }
}

function loadSupporters() {
    const loading = document.getElementById('supporters-loading');
    const list = document.getElementById('supporters-list');
    const empty = document.getElementById('supporters-empty');
    const count = document.getElementById('supporters-count');
    
    const authorId = <?= json_encode($story['author_id'] ?? null) ?>;
    
    // Hide loading immediately no matter what
    if (loading) loading.style.display = 'none';
    
    if (!authorId) {
        if (empty) empty.style.display = 'block';
        if (count) count.textContent = '0';
        return;
    }
    
    const url = '<?= site_url('/api/supporters/get-top-supporters.php') ?>?author_id=' + authorId + '&limit=200';
    
    // Use XMLHttpRequest as fallback to fetch
    const xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.onload = function() {
        try {
            const data = JSON.parse(xhr.responseText);
            
            if (data.success && data.data && data.data.length > 0) {
                if (list) {
                    list.innerHTML = '';
                    data.data.forEach((s, i) => {
                        const tips = s.tip_amount ? '$' + parseFloat(s.tip_amount).toFixed(2) : '';
                        const pts = s.points_total ? parseInt(s.points_total) + ' pts' : '';
                        const support = [tips, pts].filter(x => x).join(' + ') || 'Supporter';
                        
                        const html = '<a href="<?= site_url('/pages/profile.php') ?>?user_id=' + s.supporter_id + '" class="block p-3 bg-yellow-50 dark:bg-gray-700 rounded hover:shadow transition">' +
                            '<div class="flex items-center gap-3">' +
                            '<div class="font-bold text-yellow-600 min-w-8 text-center">#' + (i+1) + '</div>' +
                            '<div class="flex-1">' +
                            '<div class="font-semibold text-gray-900 dark:text-white">' + (s.username || 'Anonymous') + '</div>' +
                            '<div class="text-sm text-gray-600 dark:text-gray-400">' + support + '</div>' +
                            '</div></div></a>';
                        list.insertAdjacentHTML('beforeend', html);
                    });
                    list.style.display = 'block';
                }
                if (empty) empty.style.display = 'none';
                if (count) count.textContent = data.data.length;
            } else {
                if (list) list.style.display = 'none';
                if (empty) empty.style.display = 'block';
                if (count) count.textContent = '0';
            }
        } catch (e) {
            if (empty) {
                empty.style.display = 'block';
                empty.innerHTML = '<p style="color: red;">Error loading supporters</p>';
            }
            if (list) list.style.display = 'none';
        }
    };
    xhr.onerror = function() {
        if (empty) {
            empty.style.display = 'block';
            empty.innerHTML = '<p style="color: red;">Failed to connect</p>';
        }
        if (list) list.style.display = 'none';
    };
    xhr.send();
}

function toggleAddToLibrary(storyId) {
    const btn = document.getElementById('library-btn');
    fetch('<?= site_url('/api/add_library.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({story_id: storyId})
    }).then(r => r.json()).then(data => {
        if (data.success) {
            btn.textContent = data.action === 'added' ? '✅ Already Saved' : '🔖 Add to Library';
            btn.classList.toggle('bg-yellow-600', data.action === 'added');
            btn.classList.toggle('hover:bg-yellow-700', data.action === 'added');
            btn.classList.toggle('bg-blue-600', data.action !== 'added');
            btn.classList.toggle('hover:bg-blue-700', data.action !== 'added');
        } else {
            alert(data.error || 'Error updating library');
        }
    }).catch(e => console.error(e));
}

function reportBook(storyId) {
    <?php if (!$isLoggedIn): ?>
    alert('Please log in to report this story.');
    window.location.href = '<?= site_url('/pages/login.php') ?>';
    return;
    <?php endif; ?>
    document.getElementById('report-story-id').value = storyId;
    document.getElementById('reportMessage').classList.add('hidden');
    document.getElementById('report-modal').classList.remove('hidden');
}

function closeReportModal() {
    document.getElementById('report-modal').classList.add('hidden');
    document.getElementById('report-reason').value = '';
    document.getElementById('report-description').value = '';
    document.getElementById('reportMessage').classList.add('hidden');
}

// Show report message
function showReportMessage(message, type = 'success') {
    const msgDiv = document.getElementById('reportMessage');
    if (!msgDiv) return;
    msgDiv.textContent = message;
    msgDiv.classList.remove('hidden');
    msgDiv.className = type === 'success' 
        ? 'mb-4 p-3 rounded-lg text-sm font-medium bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300'
        : 'mb-4 p-3 rounded-lg text-sm font-medium bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300';
}

// Report form submission
document.getElementById('report-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const storyId = document.getElementById('report-story-id').value;
    const reason = document.getElementById('report-reason').value;
    const description = document.getElementById('report-description').value;
    
    console.log('Report submission:', {storyId, reason, description});
    
    if (!storyId) {
        showReportMessage('Story ID not found. Please refresh the page.', 'error');
        return;
    }
    
    if (!reason) {
        showReportMessage('Please select a reason', 'error');
        return;
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';
    
    fetch('<?= site_url('/api/submit-report.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({story_id: parseInt(storyId), reason: reason, description: description})
    }).then(r => {
        console.log('Report response status:', r.status);
        return r.json();
    }).then(data => {
        console.log('Report response data:', data);
        if (data.success) {
            showReportMessage('✓ ' + data.message, 'success');
            setTimeout(() => {
                closeReportModal();
            }, 1500);
        } else {
            showReportMessage('✗ ' + (data.error || 'Error submitting report'), 'error');
        }
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Report';
    }).catch(e => {
        console.error('Report submission error:', e);
        showReportMessage('✗ Network error. Please try again.', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Report';
    });
});

function setReadingStatus(storyId, status) {
    fetch('<?= site_url('/api/interactions.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'set_reading_status', story_id: storyId, status: status})
    }).then(r => r.json()).then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Error updating status');
        }
    }).catch(e => console.error(e));
}

function toggleSaveStory(storyId) {
    const btn = event.target.closest('button');
    fetch('<?= site_url('/api/stories/') ?>' + storyId + '/save', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'}
    }).then(r => r.json()).then(data => {
        if (data.success) {
            btn.classList.toggle('bg-yellow-600');
            btn.classList.toggle('bg-blue-600');
        }
    });
}

function likeReview(reviewId) {
    const btn = document.querySelector(`[data-review-id="${reviewId}"].review-like-btn`);
    const countSpan = btn?.querySelector('.like-count');
    fetch('<?= site_url('/api/interactions.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'like_review', review_id: reviewId, id: reviewId})
    }).then(r => r.json()).then(data => {
        if (data.success && countSpan) {
            countSpan.textContent = data.likes || 0;
            btn.classList.add('text-green-600');
        }
    }).catch(e => console.error('Error liking review:', e));
}

function dislikeReview(reviewId) {
    const btn = document.querySelector(`[data-review-id="${reviewId}"].review-dislike-btn`);
    const countSpan = btn?.querySelector('.dislike-count');
    fetch('<?= site_url('/api/interactions.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'dislike_review', review_id: reviewId, id: reviewId})
    }).then(r => r.json()).then(data => {
        if (data.success && countSpan) {
            countSpan.textContent = data.dislikes || 0;
            btn.classList.add('text-red-600');
        }
    }).catch(e => console.error('Error disliking review:', e));
}

document.getElementById('review-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Get rating value
    const ratingInput = document.querySelector('input[name="rating"]:checked');
    if (!ratingInput) {
        const msgDiv = document.getElementById('reviewMessage');
        msgDiv.className = 'mb-4 p-3 rounded-lg text-sm font-medium bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300';
        msgDiv.textContent = '✗ Please select a rating';
        msgDiv.classList.remove('hidden');
        return;
    }
    
    const rating = ratingInput.value;
    const review_text = document.querySelector('textarea[name="review_text"]').value;
    const story_id = <?= $bookId ?>;
    
    const formData = new FormData();
    formData.append('story_id', story_id);
    formData.append('rating', rating);
    formData.append('review_text', review_text);
    
    fetch('<?= site_url('/api/reviews/save-review.php') ?>', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const msgDiv = document.getElementById('reviewMessage');
        if (data.success) {
            msgDiv.className = 'mb-4 p-3 rounded-lg text-sm font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300';
            msgDiv.textContent = '✓ Review ' + data.action + ' successfully!';
            msgDiv.classList.remove('hidden');
            
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            msgDiv.className = 'mb-4 p-3 rounded-lg text-sm font-medium bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300';
            msgDiv.textContent = '✗ ' + (data.error || 'Error saving review');
            msgDiv.classList.remove('hidden');
        }
    })
    .catch(e => {
        console.error('Error:', e);
        const msgDiv = document.getElementById('reviewMessage');
        msgDiv.className = 'mb-4 p-3 rounded-lg text-sm font-medium bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300';
        msgDiv.textContent = '✗ Error saving review';
        msgDiv.classList.remove('hidden');
    });
});

function openSupportModal(bookId) {
    const authorId = <?= $story['author_id'] ?? 'null' ?>;
    if (!authorId) {
        alert('Unable to load support links');
        return;
    }
    
    document.getElementById('supportAuthorName').textContent = '<?= htmlspecialchars($story['author_name'] ?? 'Author') ?>';
    
    // Fetch author support links
    fetch('<?= site_url('/api/supporters/get-author-links.php') ?>?author_id=' + authorId)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const hasLinks = (data.data?.kofi || data.data?.patreon || data.data?.paypal);
                
                if (data.data?.kofi) {
                    document.getElementById('kofiLink').href = data.data.kofi;
                    document.getElementById('kofiLink').classList.remove('hidden');
                } else {
                    document.getElementById('kofiLink').classList.add('hidden');
                }
                
                if (data.data?.patreon) {
                    document.getElementById('patreonLink').href = data.data.patreon;
                    document.getElementById('patreonLink').classList.remove('hidden');
                } else {
                    document.getElementById('patreonLink').classList.add('hidden');
                }
                
                // Always show points button for logged-in users
                <?php if ($isLoggedIn): ?>
                document.getElementById('pointsBtn').classList.remove('hidden');
                <?php else: ?>
                document.getElementById('pointsBtn').classList.add('hidden');
                <?php endif; ?>
                
                <?php if ($isLoggedIn): ?>
                const hasAnySupport = hasLinks; // Show 'no links' message when external links are missing; points button shown separately
                <?php else: ?>
                const hasAnySupport = hasLinks;
                <?php endif; ?>
                
                if (hasAnySupport) {
                    document.getElementById('noLinksMessage').classList.add('hidden');
                } else {
                    document.getElementById('noLinksMessage').classList.remove('hidden');
                }
            } else {
                document.getElementById('noLinksMessage').classList.remove('hidden');
                document.getElementById('kofiLink').classList.add('hidden');
                document.getElementById('patreonLink').classList.add('hidden');
                document.getElementById('pointsLink').classList.add('hidden');
            }
            
            document.getElementById('supportLinkModal').classList.remove('hidden');
        })
        .catch(e => {
            console.error('Error loading support links:', e);
            document.getElementById('noLinksMessage').classList.remove('hidden');
            document.getElementById('kofiLink').classList.add('hidden');
            document.getElementById('patreonLink').classList.add('hidden');
            document.getElementById('pointsLink').classList.add('hidden');
            document.getElementById('supportLinkModal').classList.remove('hidden');
        });
}

function closeSupportModal() {
    document.getElementById('supportLinkModal').classList.add('hidden');
}

// Comments functionality
async function loadComments() {
    try {
        const response = await fetch('<?= site_url('/api/get-comments.php') ?>?story_id=<?= $bookId ?>');
        const data = await response.json();
        
        if (data.success) {
            const commentsList = document.getElementById('comments-list');
            
            if (data.comments && data.comments.length > 0) {
                commentsList.innerHTML = data.comments.map(comment => `
                    <div class="p-6 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-start gap-3 mb-3">
                            <div class="w-10 h-10 rounded-full bg-blue-200 dark:bg-blue-900 flex items-center justify-center text-lg">👤</div>
                            <div>
                                <h4 class="font-semibold text-gray-900 dark:text-white">${htmlEscapeComment(comment.username || 'Anonymous')}</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">${new Date(comment.created_at).toLocaleDateString()}</p>
                            </div>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300">${htmlEscapeComment(comment.content)}</p>
                    </div>
                `).join('');
            } else {
                commentsList.innerHTML = '<div class="text-center py-12"><p class="text-gray-500 dark:text-gray-400">No comments yet. Be the first to comment!</p></div>';
            }
        } else {
            console.error('Error:', data.error);
            document.getElementById('comments-list').innerHTML = '<div class="text-center py-12"><p class="text-red-500">Error loading comments</p></div>';
        }
    } catch (error) {
        console.error('Error loading comments:', error);
        document.getElementById('comments-list').innerHTML = '<div class="text-center py-12"><p class="text-red-500">Error loading comments</p></div>';
    }
}

function htmlEscapeComment(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text || '').replace(/[&<>"']/g, m => map[m]);
}

// Submit comment
document.getElementById('comment-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const content = document.getElementById('comment-content').value.trim();
    
    if (!content) {
        alert('Please enter a comment');
        return;
    }
    
    try {
        const response = await fetch('<?= site_url('/api/comment.php') ?>', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('comment-content').value = '';
            alert('Comment posted successfully!');
            loadComments();
        } else {
            alert(data.error || 'Error posting comment');
        }
    } catch (error) {
        console.error(error);
        alert('Error posting comment. Please try again.');
    }
});

// Load comments when switching to comments tab
const originalSwitchTab = window.switchTab || function() {};
window.switchTab = function(tab) {
    if (tab === 'comments') {
        loadComments();
    }
    if (tab === 'reviews') {
        loadReviewCounts();
    }
    if (tab === 'supporters') {
        loadSupporters();
    }
    // Call original switchTab logic if it exists
    const elem = document.getElementById(tab + '-content');
    if (elem) {
        document.querySelectorAll('[id$="-content"]').forEach(e => e.classList.add('hidden'));
        elem.classList.remove('hidden');
        
        // Update active tab styling
        document.querySelectorAll('[id$="-tab"]').forEach(t => {
            t.classList.remove('text-emerald-600', 'border-b-2', 'border-emerald-600');
            t.classList.add('text-gray-600', 'dark:text-gray-400', 'border-b-2', 'border-transparent');
        });
        document.getElementById(tab + '-tab')?.classList.remove('text-gray-600', 'dark:text-gray-400', 'border-transparent');
        document.getElementById(tab + '-tab')?.classList.add('text-emerald-600', 'border-emerald-600');
    }
};

// Load review interaction counts
function loadReviewCounts() {
    document.querySelectorAll('[data-review-id]').forEach(btn => {
        const reviewId = btn.getAttribute('data-review-id');
        fetch('<?= site_url('/api/interactions.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'get_review_counts', review_id: reviewId})
        }).then(r => r.json()).then(data => {
            if (data.success) {
                const likeBtn = document.querySelector(`[data-review-id="${reviewId}"].review-like-btn`);
                const dislikeBtn = document.querySelector(`[data-review-id="${reviewId}"].review-dislike-btn`);
                if (likeBtn) likeBtn.querySelector('.like-count').textContent = data.likes || 0;
                if (dislikeBtn) dislikeBtn.querySelector('.dislike-count').textContent = data.dislikes || 0;
            }
        }).catch(e => console.error('Error loading review counts:', e));
    });
};
;
</script>

<!-- Report Modal -->
<div id="report-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">🚩 Report This Story</h2>
            <button onclick="closeReportModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 text-2xl leading-none">✕</button>
        </div>
        
        <form id="report-form" class="p-6 space-y-4">
            <input type="hidden" id="report-story-id" name="story_id">
            
            <div id="reportMessage" class="hidden mb-4 p-3 rounded-lg text-sm font-medium"></div>
            
            <div>
                <label class="block font-semibold text-gray-900 dark:text-white mb-2">Reason for Report</label>
                <select id="report-reason" name="reason" required class="w-full p-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                    <option value="">Select a reason...</option>
                    <option value="sexual_content">Sexual/Explicit Content</option>
                    <option value="violence">Graphic Violence/Gore</option>
                    <option value="plagiarism">Plagiarism/Copyright</option>
                    <option value="spam">Spam</option>
                    <option value="harassment">Harassment/Hate Speech</option>
                    <option value="misleading">Misleading/False Info</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div>
                <label class="block font-semibold text-gray-900 dark:text-white mb-2">Details (Optional)</label>
                <textarea id="report-description" name="description" placeholder="Please provide any additional details that help us understand your report..." 
                          class="w-full p-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 resize-none"
                          rows="4"></textarea>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeReportModal()" 
                        class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 font-medium transition">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition">
                    Submit Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Support Modal (Ko-fi / Patreon) -->
<div id="supportLinkModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full mx-4">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">❤️ Support This Author</h2>
            <button onclick="closeSupportModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 text-2xl leading-none">✕</button>
        </div>
        
        <div class="p-6 space-y-4">
            <p class="text-gray-700 dark:text-gray-300 text-sm">Support <span id="supportAuthorName" class="font-semibold"></span> to help them keep writing amazing stories!</p>
            
            <div id="supportLinksContainer" class="space-y-3">
                <!-- Ko-fi Link -->
                <a id="kofiLink" href="#" target="_blank" class="hidden block w-full px-4 py-3 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white rounded-lg font-medium transition text-center">
                    ❤️ Support on Ko-fi
                </a>
                
                <!-- Patreon Link -->
                <a id="patreonLink" href="#" target="_blank" class="hidden block w-full px-4 py-3 bg-gradient-to-r from-red-800 to-red-900 hover:from-red-900 hover:to-black text-white rounded-lg font-medium transition text-center">
                    🎉 Join on Patreon
                </a>
                
                <!-- Points Link -->
                <button id="pointsBtn" onclick="openPointsModal()" aria-label="Support with points" class="hidden w-full px-4 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white rounded-lg font-medium transition text-center">
                    ⭐ Support with Points
                </button>
            </div>
            
            <div id="noLinksMessage" class="text-center text-gray-500 dark:text-gray-400 py-4">
                <p>This author hasn't set up support links yet.</p>
            </div>
            
            <button onclick="closeSupportModal()" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 font-medium transition">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Points Support Modal -->
<div id="pointsModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full mx-4">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">⭐ Support with Points</h2>
            <button onclick="closePointsModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 text-2xl leading-none">✕</button>
        </div>
        
        <div class="p-6 space-y-4">
            <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg p-4">
                <p class="text-sm text-gray-700 dark:text-gray-300 mb-2">Your Points Balance:</p>
                <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400" id="userPointsBalance">0</p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Points to Give</label>
                <input type="number" id="pointsAmount" min="1" value="10" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-emerald-500">
            </div>
            
            <div id="pointsMessage" class="hidden p-3 rounded-lg text-sm font-medium"></div>
            
            <div class="flex gap-3">
                <button onclick="givePoints()" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                    Give Points
                </button>
                <a href="<?= site_url('/pages/points-dashboard.php') ?>" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition text-center">
                    Buy More
                </a>
            </div>
            
            <button onclick="closePointsModal()" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 font-medium transition">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
async function openPointsModal() {
    <?php if (!$isLoggedIn): ?>
    alert('Please log in to support with points');
    return;
    <?php endif; ?>
    
    // Close support modal
    closeSupportModal();
    
    // Fetch user's points balance
    try {
        const response = await fetch('<?= site_url('/api/supporters/get-user-points.php') ?>');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('userPointsBalance').textContent = data.points || 0;
        } else {
            document.getElementById('userPointsBalance').textContent = '0';
        }
    } catch (error) {
        console.error('Error loading points:', error);
        document.getElementById('userPointsBalance').textContent = '0';
    }
    
    document.getElementById('pointsModal').classList.remove('hidden');
}

function closePointsModal() {
    document.getElementById('pointsModal').classList.add('hidden');
    document.getElementById('pointsMessage').classList.add('hidden');
}

async function givePoints() {
    const amount = parseInt(document.getElementById('pointsAmount').value);
    const authorId = <?= $story['author_id'] ?? 'null' ?>;
    const storyId = <?= $bookId ?>;
    
    if (!amount || amount < 1) {
        showPointsMessage('Please enter a valid amount', 'error');
        return;
    }
    
    if (!authorId) {
        showPointsMessage('Error: Author not found', 'error');
        return;
    }
    
    try {
        const response = await fetch('<?= site_url('/api/supporters/give-points.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                author_id: authorId,
                story_id: storyId,
                points: amount
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showPointsMessage('✓ ' + (data.message || 'Points given successfully!'), 'success');
            // Update current user's balance
            document.getElementById('userPointsBalance').textContent = data.new_balance || 0;

            // If author balance provided, update the display (if element exists)
            try {
                const authorBalEl = document.getElementById('authorPointsBalance');
                if (authorBalEl && typeof data.author_balance !== 'undefined') {
                    authorBalEl.textContent = data.author_balance;
                }
            } catch (e) { /* ignore */ }

            // Refresh supporters list: use server-rendered HTML if provided, otherwise call loader
            try {
                if (data.author_supporters_html) {
                    const list = document.getElementById('supporters-list');
                    if (list) {
                        list.innerHTML = data.author_supporters_html;
                        list.classList.remove('hidden');
                        document.getElementById('supporters-empty').classList.add('hidden');
                    }
                } else if (typeof loadSupporters === 'function') {
                    loadSupporters();
                }
            } catch (e) { console.error(e); }

            setTimeout(() => {
                closePointsModal();
            }, 1400);
        } else {
            showPointsMessage('✗ ' + (data.message || 'Failed to give points'), 'error');
        }
    } catch (error) {
        console.error('Error giving points:', error);
        showPointsMessage('✗ Network error. Please try again.', 'error');
    }
}

function showPointsMessage(message, type) {
    const msgDiv = document.getElementById('pointsMessage');
    msgDiv.textContent = message;
    msgDiv.className = 'p-3 rounded-lg text-sm font-medium ' + 
        (type === 'success' ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300' : 
         'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300');
    msgDiv.classList.remove('hidden');
}
</script>

<!-- Books Like This Section -->
<?php if (!empty($similarBooks)): ?>
<section class="max-w-6xl mx-auto px-4 py-8 mb-8">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2">
            <span class="text-2xl">📚</span> Books Like This
        </h2>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <?php foreach ($similarBooks as $similar): ?>
                <a href="<?= site_url('/pages/book.php?id=' . $similar['id']) ?>" 
                   class="group block bg-gray-50 dark:bg-gray-700 rounded-lg overflow-hidden hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
                    <!-- Cover -->
                    <div class="aspect-[3/4] bg-gradient-to-br from-emerald-100 to-blue-100 dark:from-emerald-900/30 dark:to-blue-900/30 overflow-hidden">
                        <?php if (!empty($similar['cover'])): ?>
                            <img src="<?= htmlspecialchars($similar['cover']) ?>" 
                                 alt="<?= htmlspecialchars($similar['title']) ?>"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <?php elseif (!empty($similar['cover_image'])): ?>
                            <img src="<?= htmlspecialchars($similar['cover_image']) ?>" 
                                 alt="<?= htmlspecialchars($similar['title']) ?>"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-4xl">📖</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Info -->
                    <div class="p-3">
                        <h3 class="font-semibold text-sm text-gray-900 dark:text-white line-clamp-2 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
                            <?= htmlspecialchars($similar['title']) ?>
                        </h3>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                            <?= htmlspecialchars($similar['author_name'] ?? 'Unknown') ?>
                        </p>
                        <div class="flex items-center gap-2 mt-2 text-xs text-gray-500 dark:text-gray-400">
                            <span>👁️ <?= number_format($similar['views'] ?? 0) ?></span>
                            <span>❤️ <?= number_format($similar['likes'] ?? 0) ?></span>
                        </div>
                        <?php if (!empty($similar['genre'])): ?>
                            <span class="inline-block mt-2 px-2 py-0.5 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 text-xs rounded">
                                <?= htmlspecialchars($similar['genre']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
