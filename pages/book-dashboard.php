<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$book_id = (int)($_GET['id'] ?? 0);

if (!$book_id) {
    header('Location: ' . site_url('/pages/dashboard.php'));
    exit;
}

// Fetch story details
try {
    $stmt = $pdo->prepare("
        SELECT * FROM stories WHERE id = ? AND author_id = ? LIMIT 1
    ");
    $stmt->execute([$book_id, $user_id]);
    $story = $stmt->fetch();
    
    if (!$story) {
        header('Location: ' . site_url('/pages/dashboard.php'));
        exit;
    }
} catch (Exception $e) {
    header('Location: ' . site_url('/pages/dashboard.php'));
    exit;
}

// Fetch all chapters for this story
try {
    $stmt = $pdo->prepare("
        SELECT id, sequence, title, created_at, updated_at, status
        FROM chapters 
        WHERE story_id = ? 
        ORDER BY sequence ASC
    ");
    $stmt->execute([$book_id]);
    $chapters = $stmt->fetchAll();
} catch (Exception $e) {
    $chapters = [];
}

// Fetch story statistics
$stats = [
    'total_chapters' => count($chapters),
    'total_views' => 0,
    'total_likes' => 0,
    'total_reviews' => 0
];

try {
    $result = $pdo->query("SELECT COALESCE(SUM(views), 0) as views FROM stories WHERE id = $book_id")->fetch();
    $stats['total_views'] = $result['views'] ?? 0;
} catch (Exception $e) {}

try {
    $result = $pdo->query("SELECT COUNT(*) as reviews FROM reviews WHERE story_id = $book_id")->fetch();
    $stats['total_reviews'] = $result['reviews'] ?? 0;
} catch (Exception $e) {}

try {
    // Count how many users have this story in their library
    $result = $pdo->query("SELECT COUNT(*) as library_count FROM user_library WHERE story_id = $book_id")->fetch();
    $stats['total_library'] = $result['library_count'] ?? 0;
} catch (Exception $e) {
    $stats['total_library'] = 0;
}

$userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);
?>
<?php
    $page_title = htmlspecialchars($story['title']) . ' - Dashboard - Scroll Novels';
    $page_head = '';
    require_once __DIR__ . '/../includes/header.php';
?>

<!-- Main Content -->
<main class="flex-1">
    <div class="max-w-7xl mx-auto px-4 py-12">
        <!-- Book Header -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow border border-emerald-200 dark:border-emerald-900 mb-8">
            <div class="flex flex-col md:flex-row gap-6 items-start">
                <!-- Cover Image -->
                <div class="flex-shrink-0">
                    <?php if (!empty($story['cover'])): ?>
                        <img src="<?= htmlspecialchars($story['cover']) ?>" alt="<?= htmlspecialchars($story['title']) ?>" class="w-32 h-48 object-cover rounded-lg shadow border-4 border-emerald-200 dark:border-emerald-700">
                    <?php else: ?>
                        <div class="w-32 h-48 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-5xl border-4 border-emerald-200 dark:border-emerald-700">📚</div>
                    <?php endif; ?>
                </div>

                <!-- Story Info -->
                <div class="flex-1">
                    <h1 class="text-3xl font-bold text-emerald-700 dark:text-emerald-400 mb-2"><?= htmlspecialchars($story['title']) ?></h1>
                    <p class="text-gray-600 dark:text-gray-400 mb-4"><?= htmlspecialchars(substr($story['description'] ?? '', 0, 200)) ?></p>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div>
                            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?= $stats['total_chapters'] ?></p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Chapters</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?= format_number($stats['total_views']) ?></p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Views</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?= $stats['total_reviews'] ?></p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Reviews</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-amber-500">🔖 <?= $stats['total_library'] ?></p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">In Libraries</p>
                        </div>
                    </div>

                    <div class="flex gap-3 flex-wrap">
                        <a href="<?= site_url('/pages/book.php?id=' . $book_id) ?>" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">📖 Read Story</a>
                        <a href="<?= site_url('/pages/edit-story.php?id=' . $book_id) ?>" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors">✏️ Edit Story</a>
                        <a href="<?= site_url('/story/chapter_edit.php?story_id=' . $book_id) ?>" class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors">➕ New Chapter</a>
                        <button onclick="deleteStory(<?= $book_id ?>)" class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors">🗑️ Delete Story</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chapters Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow border border-emerald-200 dark:border-emerald-900">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">Chapters</h2>
                <span class="text-sm font-medium px-3 py-1 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 rounded-full"><?= count($chapters) ?> chapter<?= count($chapters) != 1 ? 's' : '' ?></span>
            </div>

            <?php if (empty($chapters)): ?>
                <div class="text-center py-12">
                    <p class="text-gray-600 dark:text-gray-400 mb-4 text-lg">No chapters yet</p>
                    <a href="<?= site_url('/story/chapter_edit.php?story_id=' . $book_id) ?>" class="inline-block px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition">Write First Chapter</a>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($chapters as $chapter): ?>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600 hover:shadow-md transition-shadow flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <?php $cnum = $chapter['number'] ?? $chapter['sequence'] ?? 0; ?>
                                    <span class="text-sm font-bold px-3 py-1 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 rounded"><?= $cnum ?></span>
                                    <h3 class="font-bold text-lg text-emerald-700 dark:text-emerald-400"><?= htmlspecialchars($chapter['title'] ?? 'Chapter ' . $cnum) ?></h3>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                    📅 Updated <?= date('M d, Y', strtotime($chapter['updated_at'])) ?>
                                    <span class="mx-2">•</span>
                                    <span class="px-2 py-1 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 text-xs rounded capitalize"><?= $chapter['status'] ?></span>
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <a href="<?= site_url('/pages/read.php?story_id=' . $book_id . '&chapter_id=' . $chapter['id']) ?>" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded transition">Read</a>
                                <a href="<?= site_url('/story/chapter_edit.php?story_id=' . $book_id . '&chapter_id=' . $chapter['id']) ?>" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded transition">Edit</a>
                                <button onclick="deleteChapter(<?= $chapter['id'] ?>, <?= $book_id ?>)" class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded transition">Delete</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Footer -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
async function deleteChapter(chapterId, bookId) {
    if (!confirm('Are you sure you want to delete this chapter? This cannot be undone.')) {
        return;
    }

    try {
        const response = await fetch('<?= site_url('/api/delete-chapter.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ chapter_id: chapterId })
        });
        
        const data = await response.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to delete chapter');
        }
    } catch (error) {
        console.error(error);
        alert('Error deleting chapter');
    }
}

async function deleteStory(storyId) {
    if (!confirm('Are you sure you want to delete this story? This action CANNOT be undone and all chapters will be deleted permanently.')) {
        return;
    }

    if (!confirm('This is your final warning. Are you absolutely sure?')) {
        return;
    }

    try {
        const response = await fetch('<?= site_url('/api/delete-story.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ story_id: storyId })
        });
        
        const data = await response.json();
        if (data.success) {
            window.location.href = '<?= site_url('/pages/dashboard.php') ?>';
        } else {
            alert(data.error || 'Failed to delete story');
        }
    } catch (error) {
        console.error(error);
        alert('Error deleting story');
    }
}
</script>

</body>
</html>

