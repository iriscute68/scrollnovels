<?php
/**
 * pages/write-chapter.php - DEPRECATED: Redirect to dashboard
 * Chapter writing functionality moved to dashboard
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/includes/functions.php';

// Redirect to dashboard
header('Location: ' . site_url('/pages/dashboard.php'));
exit;
$userId = $_SESSION['user_id'];
$page_title = 'Write New Chapter';

// Get user's published stories
$storyStmt = $pdo->prepare("SELECT id, title FROM stories WHERE author_id = ? AND status = 'published' ORDER BY created_at DESC");
$storyStmt->execute([$userId]);
$userStories = $storyStmt->fetchAll();

// If story_id provided, load that story
$selectedStory = null;
$nextChapterNumber = 1;
$storyId = (int)($_GET['story_id'] ?? $_POST['story_id'] ?? 0);
$editChapterId = (int)($_GET['edit'] ?? 0);
$editChapter = null;

// If editing an existing chapter, load its data
if ($editChapterId) {
    $chapterStmt = $pdo->prepare("
        SELECT c.*, s.id as story_id, s.title as story_title 
        FROM chapters c
        JOIN stories s ON c.story_id = s.id
        WHERE c.id = ? AND s.author_id = ?
    ");
    $chapterStmt->execute([$editChapterId, $userId]);
    $editChapter = $chapterStmt->fetch();
    
    if ($editChapter) {
        $storyId = $editChapter['story_id'];
        $selectedStory = ['id' => $editChapter['story_id'], 'title' => $editChapter['story_title']];
        $page_title = 'Edit Chapter: ' . htmlspecialchars($editChapter['title']);
    }
}

if ($storyId) {
    $storyCheck = $pdo->prepare("SELECT id, title FROM stories WHERE id = ? AND author_id = ?");
    $storyCheck->execute([$storyId, $userId]);
    $selectedStory = $storyCheck->fetch();
    
    if ($selectedStory) {
        // Get next chapter number
        $chapterCheck = $pdo->prepare("SELECT MAX(sequence) as maxNum FROM chapters WHERE story_id = ?");
        $chapterCheck->execute([$storyId]);
        $result = $chapterCheck->fetch();
        $nextChapterNumber = ($result['maxNum'] ?? 0) + 1;
    }
}

// Handle chapter submission
$success = false;
$error = null;
$uploaded_images = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storyId = (int)($_POST['story_id'] ?? 0);
    $editChapterId = (int)($_POST['edit_chapter_id'] ?? 0);
    $chapterTitle = trim($_POST['chapter_title'] ?? '');
    $chapterContent = trim($_POST['chapter_content'] ?? '');
    
    if (!$storyId || !$chapterTitle || !$chapterContent) {
        $error = 'Story, title, and content are required';
    } else {
        // Verify story belongs to user
        $verify = $pdo->prepare("SELECT id FROM stories WHERE id = ? AND author_id = ?");
        $verify->execute([$storyId, $userId]);
        if (!$verify->fetch()) {
            $error = 'Story not found or permission denied';
        } else {
            try {
                // Handle image uploads
                if (!empty($_FILES['chapter_images']['name'][0])) {
                    $uploadDir = dirname(__DIR__) . '/uploads/chapters/';
                    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                    
                    $fileCount = count($_FILES['chapter_images']['name']);
                    for ($i = 0; $i < $fileCount; $i++) {
                        if ($_FILES['chapter_images']['error'][$i] === 0) {
                            $file = [
                                'name' => $_FILES['chapter_images']['name'][$i],
                                'type' => $_FILES['chapter_images']['type'][$i],
                                'tmp_name' => $_FILES['chapter_images']['tmp_name'][$i],
                                'size' => $_FILES['chapter_images']['size'][$i]
                            ];
                            
                            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                            if (!in_array($file['type'], $allowed)) {
                                $error = 'Invalid image format. Only JPEG, PNG, GIF, WebP allowed.';
                                break;
                            } elseif ($file['size'] > 5 * 1024 * 1024) {
                                $error = 'Image must be less than 5MB';
                                break;
                            } else {
                                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                                $filename = 'chapter-' . time() . '-' . $i . '.' . $ext;
                                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                                    $uploaded_images[] = site_url('/uploads/chapters/' . $filename);
                                }
                            }
                        }
                    }
                }
                
                if (!$error) {
                    // Get next chapter number
                    $numStmt = $pdo->prepare("SELECT MAX(sequence) as maxNum FROM chapters WHERE story_id = ?");
                    $numStmt->execute([$storyId]);
                    $numResult = $numStmt->fetch();
                    $newNumber = ($numResult['maxNum'] ?? 0) + 1;
                    
                    // Append images to content if any were uploaded
                    if (!empty($uploaded_images)) {
                        $chapterContent .= "\n\n<!-- CHAPTER IMAGES -->\n";
                        foreach ($uploaded_images as $img) {
                            $chapterContent .= '<img src="' . $img . '" style="max-width: 100%; height: auto; margin: 10px 0;" />' . "\n";
                        }
                    }
                    
                    // Create or update chapter
                    if ($editChapterId) {
                        // Update existing chapter
                        $stmt = $pdo->prepare("UPDATE chapters SET title = ?, content = ?, updated_at = NOW() WHERE id = ? AND story_id = ?");
                        $stmt->execute([$chapterTitle, $chapterContent, $editChapterId, $storyId]);
                    } else {
                        // Create new chapter
                        $stmt = $pdo->prepare("INSERT INTO chapters (story_id, sequence, title, content, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->execute([$storyId, $newNumber, $chapterTitle, $chapterContent]);
                    }
                    
                    // Update story updated_at
                    $updateStory = $pdo->prepare("UPDATE stories SET updated_at = NOW() WHERE id = ?");
                    $updateStory->execute([$storyId]);
                    
                    $success = true;
                    $_POST = [];
                    header("Refresh: 2; url=" . site_url("/pages/book.php?id={$storyId}"));
                }
            } catch (Exception $e) {
                $error = 'Error ' . ($editChapterId ? 'updating' : 'creating') . ' chapter: ' . $e->getMessage();
            }
        }
    }
}
?>

<?php
    if (empty($page_title)) $page_title = 'Write New Chapter';
    $page_head = '<script src="https://cdn.tailwindcss.com"></script>'
        . '<link rel="stylesheet" href="' . asset_url('css/global.css') . '">'
        . '<link rel="stylesheet" href="' . asset_url('css/theme.css') . '">';

    require_once __DIR__ . '/../includes/header.php';
?>

<main class="flex-1 max-w-4xl mx-auto px-4 py-12 w-full">
    <!-- Back Button -->
    <a href="<?= site_url('/pages/dashboard.php') ?>" class="text-emerald-600 dark:text-emerald-400 hover:underline mb-6 inline-block">← Back to Dashboard</a>

    <!-- Page Title -->
    <h1 class="text-4xl font-bold text-emerald-700 dark:text-emerald-400 mb-2">
        <?= $editChapter ? '✏️ Edit Chapter' : '✍️ Write New Chapter' ?>
    </h1>
    <p class="text-gray-600 dark:text-gray-400 mb-8">
        <?= $editChapter ? 'Update your chapter content' : 'Share your story one chapter at a time' ?>
    </p>

    <!-- Messages -->
    <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 rounded-lg text-red-800 dark:text-red-300">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="mb-6 p-4 bg-emerald-100 dark:bg-emerald-900/30 border border-emerald-300 dark:border-emerald-700 rounded-lg text-emerald-800 dark:text-emerald-300">
            ✅ Chapter created successfully! Redirecting...
        </div>
    <?php endif; ?>

    <!-- Write Form -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-emerald-200 dark:border-emerald-900 p-8">
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <!-- Story Selection -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">📖 Select Story *</label>
                <select name="story_id" required onchange="updateChapterNumber()" 
                    <?= $editChapterId ? 'disabled' : '' ?>
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 <?= $editChapterId ? 'opacity-50 cursor-not-allowed' : '' ?>">
                    <option value="">Choose a story...</option>
                    <?php foreach ($userStories as $story): ?>
                        <option value="<?= $story['id'] ?>" <?= $storyId == $story['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($story['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($editChapterId): ?>
                    <input type="hidden" name="story_id" value="<?= $storyId ?>">
                    <input type="hidden" name="edit_chapter_id" value="<?= $editChapterId ?>">
                <?php endif; ?>
                <?php if (empty($userStories)): ?>
                    <p class="text-sm text-red-600 dark:text-red-400 mt-2">You need to create a story first</p>
                    <a href="<?= site_url('/pages/write-story.php') ?>" class="text-emerald-600 dark:text-emerald-400 hover:underline text-sm">Create a new story</a>
                <?php endif; ?>
            </div>

            <!-- Chapter Title -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">📝 Chapter Title *</label>
                <input type="text" name="chapter_title" placeholder="e.g., Chapter 1: The Beginning" required 
                    value="<?= htmlspecialchars($_POST['chapter_title'] ?? ($editChapter['title'] ?? '')) ?>"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <!-- Chapter Content -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">📖 Chapter Content *</label>
                <textarea name="chapter_content" placeholder="Write your chapter content here..." required rows="12"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-sm"
                    ><?= htmlspecialchars($_POST['chapter_content'] ?? ($editChapter['content'] ?? '')) ?></textarea>
                <p class="text-xs text-gray-500 dark:text-gray-500 mt-2">💡 Tip: You can use HTML formatting if needed</p>
            </div>

            <!-- Image Upload -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">🖼️ Upload Chapter Images (Optional)</label>
                <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors" onclick="document.getElementById('imageInput').click()">
                    <input type="file" id="imageInput" name="chapter_images[]" multiple accept="image/*" class="hidden">
                    <div class="text-4xl mb-2">🖼️</div>
                    <p class="text-gray-700 dark:text-gray-300 font-medium">Click to upload or drag and drop</p>
                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">PNG, JPG, GIF, WebP up to 5MB each</p>
                </div>
                <div id="imagePreview" class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4"></div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex gap-4 pt-4">
                <button type="submit" class="flex-1 px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold transition-colors">
                    <?= $editChapterId ? '✏️ Update Chapter' : '✅ Publish Chapter' ?>
                </button>
                <a href="<?= site_url('/pages/dashboard.php') ?>" class="flex-1 px-6 py-3 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-medium transition-colors text-center hover:bg-gray-50 dark:hover:bg-gray-700">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Info Box -->
    <div class="mt-8 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-900 rounded-lg">
        <h3 class="font-bold text-blue-900 dark:text-blue-300 mb-2">💡 Writing Tips</h3>
        <ul class="text-sm text-blue-800 dark:text-blue-300 space-y-1">
            <li>• Write clear, engaging chapter titles</li>
            <li>• Break long chapters into smaller paragraphs</li>
            <li>• Add images to enhance your story</li>
            <li>• Save regularly - content is auto-saved as you type</li>
        </ul>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// Image preview
document.getElementById('imageInput')?.addEventListener('change', function(e) {
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';
    
    for (let file of this.files) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'w-full h-32 object-cover rounded-lg border border-gray-300 dark:border-gray-600';
            preview.appendChild(img);
        };
        reader.readAsDataURL(file);
    }
});

// Drag and drop
const dropZone = document.querySelector('[onclick="document.getElementById(\'imageInput\').click()"]');
if (dropZone) {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.add('bg-gray-100', 'dark:bg-gray-600');
        });
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.remove('bg-gray-100', 'dark:bg-gray-600');
        });
    });
    
    dropZone.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        document.getElementById('imageInput').files = files;
        document.getElementById('imageInput').dispatchEvent(new Event('change'));
    });
}

function updateChapterNumber() {
    // Can be used to dynamically show chapter number
}
</script>

</body>
</html>

