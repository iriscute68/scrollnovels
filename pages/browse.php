<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$page_title = 'Browse Stories';
require_once dirname(__DIR__) . '/includes/header.php';

// Use the previous static categories list for predictable browsing
$genres = ['Action', 'Adventure', 'Comedy', 'Contemporary', 'Drama', 'Fantasy', 'Historical', 'Horror', 'Mystery', 'Psychological', 'Romance', 'Satire', 'Sci-fi', 'Short Story', 'Thriller', 'Tragedy'];
$tags = ['Anti-Hero Lead', 'Anti-Villain Lead', 'Apocalypse', 'Artificial Intelligence', 'Attractive Lead', 'Chivalry', 'Competing Love Interest', 'Cozy', 'Crafting', 'Cultivation', 'Cyberpunk', 'Deck Building', 'Dungeon Core', 'Dungeon Crawler', 'Dystopia', 'Female Lead', 'First Contact', 'GameLit', 'Gender Bender', 'Genetically Engineered', 'Grimdark', 'Hard Sci-fi', 'High Fantasy', 'Kingdom Building', 'Lesbian Romance', 'LitRPG', 'Local Protagonist', 'Low Fantasy', 'Magic', 'Magical Girl', 'Magitech', 'Male Gay Romance', 'Male Lead', 'Martial Arts', 'Mecha', 'Modern Knowledge', 'Monster Evolution', 'Multiple Lead Characters', 'Multiple Lovers', 'Mythos', 'Non-Human Lead', 'Non-Humanoid Lead', 'Otome', 'Portal Fantasy / Isekai', 'Post Apocalyptic', 'Progression', 'Reader Interactive', 'Reincarnation', 'Romance Subplot', 'Ruling Class', 'School Life', 'Secret Identity', 'Slice of Life', 'Soft Sci-fi', 'Space Opera', 'Sports', 'Steampunk', 'Strategy', 'Strong Lead', 'Super Heroes', 'Supernatural', 'Survival', 'System Invasion', 'Technologically Engineered', 'Time Loop', 'Time Travel', 'Tower', 'Urban Fantasy', 'Villainous Lead', 'Virtual Reality', 'War and Military', 'Wuxia'];
$categories = array_merge($genres, $tags);

// Get filter parameters
$selectedCategory = isset($_GET['category']) ? trim($_GET['category']) : null;
$selectedTag = isset($_GET['tag']) ? trim($_GET['tag']) : null;
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : null;
$contentType = isset($_GET['type']) ? trim($_GET['type']) : null;

// If tag parameter is set, use it as category filter
if ($selectedTag && !$selectedCategory) {
    $selectedCategory = $selectedTag;
}

// Base query - include library count
$query = "SELECT s.id, s.title, s.slug, s.cover, s.cover_image, s.synopsis, s.genre, s.tags, s.author_id, s.views, s.likes, s.status, s.content_type, s.is_fanfiction, 
    COALESCE(COUNT(ss.id), 0) as library_count
FROM stories s 
LEFT JOIN saved_stories ss ON s.id = ss.story_id
WHERE 1=1";
$params = [];

// Add search filter
if ($searchQuery) {
    $query .= " AND (title LIKE ? OR synopsis LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

// Add category filter (simple LIKE on tags or genre)
if ($selectedCategory) {
    $query .= " AND (tags LIKE ? OR genre LIKE ?)";
    $params[] = "%$selectedCategory%";
    $params[] = "%$selectedCategory%";
}

// Add content type filter
if ($contentType === 'fanfic') {
    $query .= " AND is_fanfiction = 1";
} elseif ($contentType === 'webtoon') {
    $query .= " AND content_type = 'webtoon'";
} elseif ($contentType === 'novel') {
    $query .= " AND content_type = 'novel'";
}

// Simpler ordering prioritizing popular and recent
$query .= " GROUP BY s.id ORDER BY s.views DESC, s.created_at DESC LIMIT 100";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $books = [];
}

// Get category counts
$categoryCounts = [];
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM stories WHERE (tags LIKE ? OR genre LIKE ?)");
    foreach ($categories as $cat) {
        $like = "%$cat%";
        $countStmt->execute([$like, $like]);
        $result = $countStmt->fetch(PDO::FETCH_ASSOC);
        $categoryCounts[$cat] = $result['count'] ?? 0;
    }
} catch (Exception $e) {
    foreach ($categories as $cat) {
        $categoryCounts[$cat] = 0;
    }
}

// Get author names
$authorIds = array_unique(array_column($books, 'author_id'));
$authors = [];
if (!empty($authorIds)) {
    $placeholders = str_repeat('?,', count($authorIds) - 1) . '?';
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id IN ($placeholders)");
    $stmt->execute(array_values($authorIds));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $author) {
        $authors[$author['id']] = $author['username'];
    }
}

function story_link_by_id($id, $slug) {
    if ($slug) return SITE_URL . '/pages/story.php?slug=' . urlencode($slug);
    return SITE_URL . '/pages/book.php?id=' . $id;
}
?>

<main class="flex-1">
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-4xl font-bold text-emerald-700 dark:text-emerald-400 mb-2">📚 Browse Stories</h1>
        <p class="text-gray-600 dark:text-gray-400">Discover amazing stories from our community</p>
    </div>

    <div class="flex gap-8">
        <!-- SIDEBAR: Categories & Filters -->
        <div class="w-72 flex-shrink-0">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow border border-emerald-200 dark:border-emerald-900 sticky top-20">
                <!-- Search Bar -->
                <form method="get" class="mb-6">
                    <label class="block text-sm font-semibold text-emerald-700 dark:text-emerald-400 mb-2">🔍 Search</label>
                    <input type="text" name="search" placeholder="Search stories..." value="<?= htmlspecialchars($searchQuery ?? '') ?>"
                           class="w-full px-3 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <?php if ($selectedCategory): ?><input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory) ?>"><?php endif; ?>
                    <button type="submit" class="mt-2 w-full px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">Search</button>
                </form>

                <hr class="my-4 border-gray-300 dark:border-gray-700">

                <!-- Content Type Filter -->
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-emerald-700 dark:text-emerald-400 mb-3">📖 Content Type</label>
                    <div class="space-y-2">
                        <a href="?<?= http_build_query(array_filter(['search' => $searchQuery, 'category' => $selectedCategory])) ?>" 
                           class="block px-3 py-2 rounded text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition <?= !$contentType ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 font-medium' : '' ?>">
                            ✨ All Types
                        </a>
                        <a href="?type=novel&<?= http_build_query(array_filter(['search' => $searchQuery, 'category' => $selectedCategory])) ?>" 
                           class="block px-3 py-2 rounded text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition <?= $contentType === 'novel' ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 font-medium' : '' ?>">
                            📖 Novels
                        </a>
                        <a href="?type=webtoon&<?= http_build_query(array_filter(['search' => $searchQuery, 'category' => $selectedCategory])) ?>" 
                           class="block px-3 py-2 rounded text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition <?= $contentType === 'webtoon' ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 font-medium' : '' ?>">
                            🎨 Webtoons
                        </a>
                        <a href="?type=fanfic&<?= http_build_query(array_filter(['search' => $searchQuery, 'category' => $selectedCategory])) ?>" 
                           class="block px-3 py-2 rounded text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition <?= $contentType === 'fanfic' ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 font-medium' : '' ?>">
                            ✍️ Fanfiction
                        </a>
                    </div>
                </div>

                <hr class="my-4 border-gray-300 dark:border-gray-700">

                <!-- Genres -->
                <label class="block text-sm font-semibold text-emerald-700 dark:text-emerald-400 mb-3">📚 Genres</label>
                <div class="space-y-1 max-h-[30vh] overflow-y-auto mb-6 pb-4 border-b border-gray-300 dark:border-gray-700">
                    <?php 
                    $resetParams = array_filter(['search' => $searchQuery]);
                    $resetUrl = $resetParams ? '?' . http_build_query($resetParams) : '?';
                    ?>
                    <a href="<?= $resetUrl ?>" 
                       class="block px-3 py-1 rounded text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition <?= !$selectedCategory ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 font-medium' : '' ?>">
                        ✨ All
                    </a>
                    
                    <?php foreach ($genres as $gen): 
                        $genParams = array_filter(['category' => $gen, 'search' => $searchQuery, 'type' => $contentType]);
                        $genUrl = '?' . http_build_query($genParams);
                    ?>
                        <a href="<?= $genUrl ?>" 
                           class="block px-3 py-1 rounded text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition <?= $selectedCategory === $gen ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 font-medium' : '' ?>">
                            <?= htmlspecialchars($gen) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Tags -->
                <label class="block text-sm font-semibold text-emerald-700 dark:text-emerald-400 mb-3">🏷️ Tags</label>
                <div class="space-y-1 max-h-[50vh] overflow-y-auto">
                    <?php foreach ($tags as $tag): 
                        $tagParams = array_filter(['category' => $tag, 'search' => $searchQuery, 'type' => $contentType]);
                        $tagUrl = '?' . http_build_query($tagParams);
                    ?>
                        <a href="<?= $tagUrl ?>" 
                           class="block px-3 py-1 rounded text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition <?= $selectedCategory === $tag ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 font-medium' : '' ?>">
                            <?= htmlspecialchars($tag) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- BOOKS GRID -->
        <div class="flex-1">
            <?php if (empty($books)): ?>
                <div class="p-8 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-center border border-blue-200 dark:border-blue-800">
                    <p class="text-lg text-gray-700 dark:text-gray-300 font-semibold">📭 No stories found</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Try adjusting your filters or search terms</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($books as $book): ?>
                        <div class="group bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow hover:shadow-lg transition transform hover:scale-105 flex flex-col h-full cursor-pointer"
                             onclick="window.location.href='<?= htmlspecialchars(story_link_by_id($book['id'], $book['slug'])) ?>'">
                            
                            <!-- Cover Image -->
                            <div class="h-48 bg-gradient-to-br from-emerald-100 to-blue-100 dark:from-emerald-900/20 dark:to-blue-900/20 overflow-hidden relative">
                                <?php if (!empty($book['cover'])): ?>
                                    <img src="<?= htmlspecialchars($book['cover']) ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-300">
                                <?php elseif (!empty($book['cover_image'])): ?>
                                    <img src="<?= htmlspecialchars($book['cover_image']) ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-300">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-5xl">📖</div>
                                <?php endif; ?>
                                
                                <!-- Views Badge -->
                                <div class="absolute top-2 right-2 bg-emerald-600 text-white px-2 py-1 rounded text-xs font-bold shadow-lg">
                                    👁️ <?= number_format($book['views'] ?? 0) ?>
                                </div>

                                <!-- Library/Bookmark Badge -->
                                <div class="absolute top-10 right-2 bg-blue-600 text-white px-2 py-1 rounded text-xs font-bold shadow-lg">
                                    🔖 <?= $book['library_count'] ?? 0 ?>
                                </div>

                                <!-- Type Badge -->
                                <?php if ($book['is_fanfiction']): ?>
                                    <div class="absolute top-2 left-2 bg-purple-600 text-white px-2 py-1 rounded text-xs font-bold shadow-lg">✍️ Fanfic</div>
                                <?php elseif ($book['content_type'] === 'webtoon'): ?>
                                    <div class="absolute top-2 left-2 bg-pink-600 text-white px-2 py-1 rounded text-xs font-bold shadow-lg">🎨 Webtoon</div>
                                <?php endif; ?>
                            </div>

                            <!-- Content -->
                            <div class="p-4 flex-1 flex flex-col justify-between">
                                <!-- Title & Author -->
                                <div>
                                    <h3 class="font-bold text-lg text-gray-900 dark:text-white line-clamp-2 group-hover:text-emerald-600 transition">
                                        <?= htmlspecialchars($book['title']) ?>
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        by <?= htmlspecialchars($authors[$book['author_id']] ?? 'Unknown') ?>
                                    </p>
                                </div>

                                <!-- Synopsis -->
                                <p class="text-xs text-gray-600 dark:text-gray-400 line-clamp-3 my-3">
                                    <?= htmlspecialchars(substr($book['synopsis'] ?? '', 0, 150)) ?>
                                </p>

                                <!-- Genre Tags -->
                                <?php if (!empty($book['genre'])): ?>
                                    <div class="text-xs space-x-1 mb-3">
                                        <span class="inline-block px-2 py-1 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 rounded">
                                            <?= htmlspecialchars($book['genre']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <!-- Read Button -->
                                <button class="mt-4 block w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                                    📖 Read Story
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

