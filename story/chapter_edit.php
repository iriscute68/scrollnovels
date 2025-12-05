<?php
// story/chapter_edit.php - Edit/create chapters with multiple image support
if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . site_url('/pages/login.php'));
    exit;
}

$user_id = $_SESSION['user_id'];
$story_id = intval($_GET['story_id'] ?? 0);
$chapter_id = intval($_GET['chapter_id'] ?? 0);

if (!$story_id) {
    header('Location: ' . site_url('/pages/dashboard.php'));
    exit;
}

// Verify ownership - stories table uses author_id
$stmt = $pdo->prepare("SELECT id, title FROM stories WHERE id = ? AND author_id = ?");
$stmt->execute([$story_id, $user_id]);
$story = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$story) {
    header('Location: ' . site_url('/pages/dashboard.php?error=unauthorized'));
    exit;
}

// Create chapter_images table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS chapter_images (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        chapter_id INT UNSIGNED NOT NULL,
        image_url VARCHAR(500) NOT NULL,
        position INT DEFAULT 0,
        caption VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_chapter (chapter_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Helper function
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    return empty($text) ? 'chapter' : $text;
}

$message = '';
$messageType = '';

// Handle image deletion first
if (isset($_GET['delete_image'])) {
    $imageId = intval($_GET['delete_image']);
    try {
        $stmt = $pdo->prepare("SELECT ci.*, c.story_id FROM chapter_images ci JOIN chapters c ON ci.chapter_id = c.id WHERE ci.id = ?");
        $stmt->execute([$imageId]);
        $image = $stmt->fetch();
        
        if ($image && $image['story_id'] == $story_id) {
            $filePath = dirname(__DIR__) . str_replace(site_url(''), '', $image['image_url']);
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
            $stmt = $pdo->prepare("DELETE FROM chapter_images WHERE id = ?");
            $stmt->execute([$imageId]);
            $message = 'Image deleted successfully!';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = 'Error deleting image';
        $messageType = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $sequence = intval($_POST['number'] ?? 1);
    $status = ($_POST['action'] ?? 'save') === 'publish' ? 'published' : 'draft';
    $word_count = str_word_count(strip_tags($content));
    $post_chapter_id = intval($_POST['chapter_id'] ?? 0);

    try {
        if ($post_chapter_id) {
            // Update existing chapter
            $stmt = $pdo->prepare("
                UPDATE chapters 
                SET title = ?, content = ?, sequence = ?, status = ?, word_count = ?, updated_at = NOW() 
                WHERE id = ? AND story_id = ?
            ");
            $stmt->execute([$title, $content, $sequence, $status, $word_count, $post_chapter_id, $story_id]);
            $current_chapter_id = $post_chapter_id;
            $message = 'Chapter updated successfully!';
        } else {
            // Create new chapter
            $stmt = $pdo->prepare("
                INSERT INTO chapters (story_id, title, content, sequence, status, word_count, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$story_id, $title, $content, $sequence, $status, $word_count]);
            $current_chapter_id = $pdo->lastInsertId();
            $message = 'Chapter created successfully!';
        }
        $messageType = 'success';

        // Handle multiple image uploads (max 5)
        if (!empty($_FILES['chapter_images']['name'][0])) {
            $uploadDir = dirname(__DIR__) . '/uploads/chapters/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $position = 0;
            $maxImages = 5;

            // Get current max position
            $stmt = $pdo->prepare("SELECT MAX(position) as max_pos FROM chapter_images WHERE chapter_id = ?");
            $stmt->execute([$current_chapter_id]);
            $maxPos = $stmt->fetch();
            $position = ($maxPos['max_pos'] ?? -1) + 1;

            // Limit to 5 images
            $imageCount = min(count($_FILES['chapter_images']['name']), $maxImages);

            for ($key = 0; $key < $imageCount; $key++) {
                $name = $_FILES['chapter_images']['name'][$key];
                if ($_FILES['chapter_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['chapter_images']['tmp_name'][$key];
                    $fileType = $_FILES['chapter_images']['type'][$key];
                    
                    if (in_array($fileType, $allowedTypes)) {
                        $ext = pathinfo($name, PATHINFO_EXTENSION);
                        $newName = 'chapter-' . $current_chapter_id . '-' . time() . '-' . $position . '.' . $ext;
                        $targetPath = $uploadDir . $newName;
                        
                        if (move_uploaded_file($tmpName, $targetPath)) {
                            $imageUrl = site_url('/uploads/chapters/' . $newName);
                            $stmt = $pdo->prepare("INSERT INTO chapter_images (chapter_id, image_url, position) VALUES (?, ?, ?)");
                            $stmt->execute([$current_chapter_id, $imageUrl, $position]);
                            $position++;
                        }
                    }
                }
            }
        }

        // Redirect to book dashboard instead of book page
        header('Location: ' . site_url('/pages/book-dashboard.php?id=' . $story_id . '&msg=' . urlencode($message)));
        exit;
    } catch (Exception $e) {
        $message = 'Error saving chapter: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Load chapter if editing
$chapter = null;
$chapterImages = [];
if ($chapter_id) {
    $stmt = $pdo->prepare("SELECT * FROM chapters WHERE id = ? AND story_id = ?");
    $stmt->execute([$chapter_id, $story_id]);
    $chapter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$chapter) {
        header('Location: ' . site_url('/pages/book-dashboard.php?id=' . $story_id));
        exit;
    }
    
    // Load existing images
    try {
        $stmt = $pdo->prepare("SELECT * FROM chapter_images WHERE chapter_id = ? ORDER BY position ASC");
        $stmt->execute([$chapter_id]);
        $chapterImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $chapterImages = [];
    }
}

$page_title = ($chapter ? 'Edit Chapter' : 'New Chapter') . ' - ' . htmlspecialchars($story['title']);
require_once dirname(__DIR__) . '/includes/header.php';
?>

<main class="flex-1">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Breadcrumb -->
        <nav class="mb-6 text-sm">
            <a href="<?= site_url('/pages/dashboard.php') ?>" class="text-emerald-600 hover:underline">Dashboard</a>
            <span class="mx-2 text-gray-400">/</span>
            <a href="<?= site_url('/pages/book.php?id=' . $story_id) ?>" class="text-emerald-600 hover:underline"><?= htmlspecialchars($story['title']) ?></a>
            <span class="mx-2 text-gray-400">/</span>
            <span class="text-gray-600 dark:text-gray-400"><?= $chapter ? 'Edit Chapter' : 'New Chapter' ?></span>
        </nav>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 md:p-8">
            <h1 class="text-2xl md:text-3xl font-bold text-emerald-700 dark:text-emerald-400 mb-6">
                <?= $chapter ? '✏️ Edit Chapter' : '📝 Create New Chapter' ?>
            </h1>

            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300' : 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="space-y-6">
                <?php if ($chapter): ?>
                    <input type="hidden" name="chapter_id" value="<?= $chapter['id'] ?>">
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Chapter Title</label>
                        <input type="text" name="title" value="<?= htmlspecialchars($chapter['title'] ?? '') ?>" required
                               class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                               placeholder="Enter chapter title...">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Chapter Number</label>
                        <input type="number" name="number" value="<?= htmlspecialchars($chapter['sequence'] ?? $chapter['number'] ?? '1') ?>" min="1"
                               class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                               placeholder="1">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Content</label>
                    <textarea name="content" rows="20" required
                              class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent font-mono"
                              placeholder="Write your chapter content here..."><?= htmlspecialchars($chapter['content'] ?? '') ?></textarea>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Word count: <span id="wordCount"><?= str_word_count(strip_tags($chapter['content'] ?? '')) ?></span>
                    </p>
                </div>

                <!-- Multiple Images Upload Section -->
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">📷 Chapter Images</label>
                    
                    <!-- Existing Images -->
                    <?php if (!empty($chapterImages)): ?>
                        <div class="mb-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Current Images (<?= count($chapterImages) ?>):</p>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <?php foreach ($chapterImages as $img): ?>
                                    <div class="relative group">
                                        <img src="<?= htmlspecialchars($img['image_url']) ?>" alt="Chapter image" 
                                             class="w-full h-32 object-cover rounded-lg border border-gray-300 dark:border-gray-600">
                                        <a href="?story_id=<?= $story_id ?>&chapter_id=<?= $chapter_id ?>&delete_image=<?= $img['id'] ?>" 
                                           onclick="return confirm('Delete this image?')"
                                           class="absolute top-2 right-2 bg-red-500 hover:bg-red-600 text-white rounded-full w-7 h-7 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow">
                                            ✕
                                        </a>
                                        <span class="absolute bottom-2 left-2 bg-black/60 text-white text-xs px-2 py-1 rounded">#<?= $img['position'] + 1 ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Upload New Images -->
                    <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center hover:border-emerald-500 transition-colors cursor-pointer" onclick="document.getElementById('chapter_images').click()">
                        <input type="file" name="chapter_images[]" id="chapter_images" multiple accept="image/*" class="hidden">
                        <div class="text-4xl mb-2">🖼️</div>
                        <p class="text-gray-600 dark:text-gray-400 font-medium">Click to upload images (max 5)</p>
                        <p class="text-sm text-gray-500 dark:text-gray-500 mt-1">Select up to 5 images per chapter (JPG, PNG, GIF, WebP)</p>
                    </div>
                    <div id="imagePreview" class="mt-4 grid grid-cols-3 md:grid-cols-5 gap-2 hidden"></div>
                    <p id="imageError" class="mt-2 text-red-500 text-sm hidden"></p>
                </div>

                <?php if ($chapter): ?>
                    <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            <strong>Status:</strong> <?= ucfirst($chapter['status'] ?? 'draft') ?> &nbsp;|&nbsp;
                            <strong>Created:</strong> <?= date('M d, Y', strtotime($chapter['created_at'] ?? 'now')) ?> &nbsp;|&nbsp;
                            <strong>Updated:</strong> <?= date('M d, Y', strtotime($chapter['updated_at'] ?? 'now')) ?>
                        </p>
                    </div>
                <?php endif; ?>

                <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="submit" name="action" value="save" 
                            class="px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white font-semibold rounded-lg transition">
                        💾 Save as Draft
                    </button>
                    <button type="submit" name="action" value="publish" 
                            class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg transition">
                        📤 Publish Chapter
                    </button>
                    <a href="<?= site_url('/pages/book-dashboard.php?id=' . $story_id) ?>" 
                       class="px-6 py-3 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-semibold rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        ← Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// Word count updater
const textarea = document.querySelector('textarea[name="content"]');
const wordCountSpan = document.getElementById('wordCount');

if (textarea && wordCountSpan) {
    textarea.addEventListener('input', function() {
        const text = this.value.replace(/<[^>]*>/g, '');
        const words = text.trim().split(/\s+/).filter(word => word.length > 0);
        wordCountSpan.textContent = words.length;
    });
}

// Image preview with max 5 limit
const imageInput = document.getElementById('chapter_images');
const imagePreview = document.getElementById('imagePreview');
const imageError = document.getElementById('imageError');
const MAX_IMAGES = 5;

imageInput?.addEventListener('change', function() {
    imagePreview.innerHTML = '';
    imageError.classList.add('hidden');
    
    if (this.files.length === 0) {
        imagePreview.classList.add('hidden');
        return;
    }
    
    // Check if exceeds max
    if (this.files.length > MAX_IMAGES) {
        imageError.textContent = `You can only upload up to ${MAX_IMAGES} images at a time. You selected ${this.files.length}.`;
        imageError.classList.remove('hidden');
        // Clear the input
        this.value = '';
        imagePreview.classList.add('hidden');
        return;
    }
    
    imagePreview.classList.remove('hidden');
    
    Array.from(this.files).forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const div = document.createElement('div');
            div.className = 'relative';
            div.innerHTML = `
                <img src="${e.target.result}" alt="Preview ${index + 1}" class="w-full h-20 object-cover rounded border border-emerald-300">
                <span class="absolute bottom-1 right-1 bg-emerald-600 text-white text-xs px-1.5 py-0.5 rounded">${index + 1}</span>
            `;
            imagePreview.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
