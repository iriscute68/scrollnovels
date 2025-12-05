<?php
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$userId = $_SESSION['user_id'] ?? null;
$isLoggedIn = isset($_SESSION['user_id']);

// Get book and chapter IDs (check both GET and POST to preserve on form submit)
$bookId = (int)($_GET['id'] ?? $_POST['story_id'] ?? 0);
$chapterNum = (int)($_GET['ch'] ?? $_POST['chapter_id'] ?? 1);

if (!$bookId) {
    header('Location: ' . site_url('/pages/browse.php'));
    exit;
}

// Fetch story
try {
    $stmt = $pdo->prepare("
        SELECT s.*, u.username as author_name, u.profile_image
        FROM stories s 
        LEFT JOIN users u ON s.author_id = u.id 
        WHERE s.id = ? AND s.status = 'published'
    ");
    $stmt->execute([$bookId]);
    $story = $stmt->fetch();
    
    if (!$story) {
        header('Location: ' . site_url('/pages/browse.php'));
        exit;
    }
} catch (Exception $e) {
    header('Location: ' . site_url('/pages/browse.php'));
    exit;
}

// Detect whether chapters use `number` or `sequence` column (compat)
$seqCol = 'sequence'; // Default to sequence since most tables use it
try {
    $existsStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chapters' AND COLUMN_NAME = 'number'");
    $existsStmt->execute();
    $result = $existsStmt->fetch();
    if ($result && $result['cnt'] > 0) {
        $seqCol = 'number';
    }
} catch (Exception $e) {
    // Default to sequence
}

// Fetch current chapter
try {
    $stmt = $pdo->prepare("SELECT * FROM chapters WHERE story_id = ? AND $seqCol = ?");
    $stmt->execute([$bookId, $chapterNum]);
    $chapter = $stmt->fetch();
    
    if (!$chapter) {
        // Try first chapter if specified chapter doesn't exist
        if ($seqCol === 'number') {
            $stmt = $pdo->prepare("SELECT * FROM chapters WHERE story_id = ? ORDER BY number ASC LIMIT 1");
        } else {
            $stmt = $pdo->prepare("SELECT * FROM chapters WHERE story_id = ? ORDER BY sequence ASC LIMIT 1");
        }
        $stmt->execute([$bookId]);
        $chapter = $stmt->fetch();
        
        if (!$chapter) {
            error_log("read.php: No chapter found for story_id={$bookId} ch={$chapterNum}");
            $error = "No chapters available for this story.";
        }
    }
} catch (Exception $e) {
    error_log("read.php: Error loading chapter for story_id={$bookId} ch={$chapterNum} - " . $e->getMessage());
    $error = "Error loading chapter.";
}

// Fetch all chapters for navigation
$allChapters = [];
try {
    // Build query based on detected column
    if ($seqCol === 'number') {
        $query = "SELECT id, number as chapter_order, number, title FROM chapters WHERE story_id = ? ORDER BY number ASC";
    } else {
        $query = "SELECT id, sequence as chapter_order, sequence, title FROM chapters WHERE story_id = ? ORDER BY sequence ASC";
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute([$bookId]);
    $allChapters = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("read.php: Error loading chapter list - " . $e->getMessage());
}

// Find previous and next chapters (Pattern 2 from BookStack)
$prevChapter = null;
$nextChapter = null;
if ($chapter && count($allChapters) > 0) {
    // Get current chapter number using the detected column
    $currentNum = (int)($chapter[$seqCol] ?? $chapter['chapter_order'] ?? 1);
    
    foreach ($allChapters as $ch) {
        $chNum = (int)($ch['chapter_order'] ?? 1);
        
        // Find closest previous chapter (highest number less than current)
        if ($chNum < $currentNum) {
            if (!$prevChapter) {
                $prevChapter = $ch;
            } else {
                $prevNum = (int)($prevChapter['chapter_order'] ?? 1);
                if ($chNum > $prevNum) {
                    $prevChapter = $ch;
                }
            }
        }
        
        // Find closest next chapter (lowest number greater than current)
        if ($chNum > $currentNum) {
            if (!$nextChapter) {
                $nextChapter = $ch;
            } else {
                $nextNum = (int)($nextChapter['chapter_order'] ?? 1);
                if ($chNum < $nextNum) {
                    $nextChapter = $ch;
                }
            }
        }
    }
}

// Track reading progress if logged in
if ($isLoggedIn && $userId && $chapter) {
    try {
        $currentChapterNum = (int)($chapter[$seqCol] ?? $chapter['chapter_order'] ?? 1);
        
        // Create reading_progress table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS reading_progress (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            story_id INT UNSIGNED NOT NULL,
            chapter_number INT DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_progress (user_id, story_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Update or insert reading progress
        $stmt = $pdo->prepare("
            INSERT INTO reading_progress (user_id, story_id, chapter_number, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                chapter_number = GREATEST(chapter_number, VALUES(chapter_number)),
                updated_at = NOW()
        ");
        $stmt->execute([$userId, $bookId, $currentChapterNum]);
    } catch (Exception $e) {
        error_log("Failed to track reading progress: " . $e->getMessage());
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-b from-emerald-50 to-green-100 dark:from-gray-900 dark:to-gray-800">
    <main class="max-w-6xl mx-auto px-3 sm:px-4 py-4 sm:py-8">
        
            <!-- Header with Controls -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 sm:p-6 mb-4 sm:mb-6 border border-emerald-200 dark:border-emerald-900">
            <div class="mb-4">
                <p class="text-xs sm:text-sm text-emerald-600 dark:text-emerald-400 font-medium mb-1">
                    <a href="<?= site_url('/pages/book.php?id=' . $bookId) ?>" class="hover:underline">← Back to <?= htmlspecialchars(substr($story['title'], 0, 30)) ?></a>
                </p>
                <h1 class="text-xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2 line-clamp-2">
                    ࿐࿔*:･ﾟ༒︎ Ch. <?= (int)($chapter[$seqCol] ?? $chapter['number'] ?? $chapter['sequence'] ?? 0) ?> - <?= htmlspecialchars(substr($chapter['title'] ?? 'Untitled', 0, 50)) ?> ༒︎
                </h1>
                <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">
                    By <?= htmlspecialchars($story['author_name'] ?? 'Unknown') ?>
                    • Published <?= date('M d, Y', strtotime($chapter['created_at'] ?? 'now')) ?>
                </p>
            </div>

            <!-- Reading Controls -->
            <div class="flex flex-wrap gap-3 items-center justify-between bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                <div class="flex gap-2 flex-wrap">
                    <label class="flex items-center gap-2 text-sm">
                        <span>Font Size:</span>
                        <select id="fontSize" class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
                            <option value="14">Small</option>
                            <option value="16" selected>Normal</option>
                            <option value="18">Large</option>
                            <option value="20">XL</option>
                        </select>
                    </label>

                    <label class="flex items-center gap-2 text-sm">
                        <span>Line Height:</span>
                        <select id="lineHeight" class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
                            <option value="1.5">Compact</option>
                            <option value="1.8" selected>Normal</option>
                            <option value="2.0">Spacious</option>
                        </select>
                    </label>

                    <label class="flex items-center gap-2 text-sm">
                        <span>Text Align:</span>
                        <select id="textAlign" class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-600 text-gray-900 dark:text-white">
                            <option value="left">Left</option>
                            <option value="justify" selected>Justify</option>
                            <option value="center">Center</option>
                        </select>
                    </label>
                </div>

                <div class="flex gap-2">
                    <button id="flipBtn" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium transition-colors" onclick="toggleFlip()">
                        ۫ . . ✸ Flip
                    </button>
                    <button id="fullscreenBtn" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium transition-colors" onclick="toggleFullscreen()">
                        ⛶ ໒꒱ Fullscreen
                    </button>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <?php if (isset($error)): ?>
            <div class="bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-lg mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Reading Area -->
        <?php if ($chapter): ?>
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Chapter Navigator Sidebar -->
                <aside class="lg:col-span-1 order-2 lg:order-1">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 border border-emerald-200 dark:border-emerald-900 sticky top-4 max-h-[calc(100vh-100px)] overflow-y-auto">
                        <h3 class="font-bold text-gray-900 dark:text-white mb-4 text-sm">˚₊‧꒷︶꒷꒥꒷ Chapters</h3>
                        <div class="space-y-2">
                            <?php foreach ($allChapters as $ch): ?>
                                <?php $chNum = $ch['chapter_order'] ?? $ch['sequence'] ?? 0; ?>
                                <a href="<?= site_url('/pages/read.php?id=' . $bookId . '&ch=' . $chNum) ?>"
                                   class="block p-2 rounded-lg text-sm transition-colors <?= ($chNum == ($chapter[$seqCol] ?? $chapter['number'] ?? $chapter['sequence'] ?? 0)) ? 'bg-emerald-200 dark:bg-emerald-900/50 text-emerald-900 dark:text-emerald-300 font-medium' : 'hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300' ?>">
                                    <div class="font-medium">Ch. <?= (int)$chNum ?></div>
                                    <div class="text-xs truncate"><?= htmlspecialchars(substr($ch['title'] ?? 'Untitled', 0, 25)) ?></div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </aside>

                <!-- Main Reading Content -->
                <article class="lg:col-span-2 order-1 lg:order-2">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 md:p-12 border border-emerald-200 dark:border-emerald-900" id="readingContent">
                        <div id="chapterContent" class="prose prose-invert max-w-none text-gray-900 dark:text-gray-200 leading-relaxed">
                            <!-- Display chapter content with proper image parsing -->
                            <?php
                                // Check if content contains images
                                $hasImages = strpos($chapter['content'], '<img') !== false;
                                if ($hasImages) {
                                    // Content has HTML images - render as is
                                    echo $chapter['content'];
                                } else {
                                    // Check if images field has JSON
                                    $images = [];
                                    if (!empty($chapter['images'])) {
                                        $images = json_decode($chapter['images'], true) ?? [];
                                    }
                                    
                                    // If there are images, display them
                                    if (!empty($images)) {
                                        echo '<div class="space-y-6">';
                                        foreach ($images as $img) {
                                            echo '<div class="flex justify-center my-8">';
                                            echo '<img src="' . htmlspecialchars($img) . '" alt="Chapter image" class="max-w-full h-auto rounded-lg shadow-md max-h-[600px]">';
                                            echo '</div>';
                                        }
                                        echo '</div>';
                                    }
                                    // Display text content
                                    echo '<p>' . nl2br(htmlspecialchars($chapter['content'])) . '</p>';
                                }
                            ?>
                        </div>

                        <!-- Chapter Stats -->
                        <div class="mt-8 pt-6 border-t border-gray-300 dark:border-gray-700 flex justify-between text-sm text-gray-600 dark:text-gray-400">
                            <span>Word Count: <?= isset($chapter['word_count']) ? (int)$chapter['word_count'] : str_word_count(strip_tags($chapter['content'])) ?> words</span>
                            <span>Reading Time: ~<?= ceil((isset($chapter['word_count']) ? (int)$chapter['word_count'] : str_word_count(strip_tags($chapter['content']))) / 200) ?> min</span>
                        </div>
                    </div>

                    <!-- Chapter Navigation -->
                    <div class="flex flex-col sm:flex-row gap-2 sm:gap-4 mt-6">
                        <?php if ($prevChapter): ?>
                            <?php $prevNum = $prevChapter['chapter_order'] ?? $prevChapter['sequence'] ?? 0; ?>
                            <a href="<?= site_url('/pages/read.php?id=' . $bookId . '&ch=' . $prevNum) ?>"
                               class="flex-1 px-4 sm:px-6 py-3 text-sm sm:text-base bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors text-center truncate">
                                ← Prev: <?= htmlspecialchars(substr($prevChapter['title'] ?? 'Untitled', 0, 20)) ?>
                            </a>
                        <?php else: ?>
                            <div class="flex-1 px-4 sm:px-6 py-3 text-sm sm:text-base bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-lg font-medium text-center cursor-not-allowed">
                                ← First
                            </div>
                        <?php endif; ?>

                        <?php if ($nextChapter): ?>
                            <?php $nextNum = $nextChapter['chapter_order'] ?? $nextChapter['sequence'] ?? 0; ?>
                            <a href="<?= site_url('/pages/read.php?id=' . $bookId . '&ch=' . $nextNum) ?>"
                               class="flex-1 px-4 sm:px-6 py-3 text-sm sm:text-base bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors text-center truncate">
                                Next: <?= htmlspecialchars(substr($nextChapter['title'] ?? 'Untitled', 0, 20)) ?> →
                            </a>
                        <?php else: ?>
                            <div class="flex-1 px-4 sm:px-6 py-3 text-sm sm:text-base bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-lg font-medium text-center cursor-not-allowed">
                                Last →
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Comments Section -->
                    <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 sm:p-6 md:p-8 border border-emerald-200 dark:border-emerald-900">
                        <h3 class="text-lg sm:text-2xl font-bold text-gray-900 dark:text-white mb-6">༻❁༺ Discussion ࿐</h3>

                        <!-- Comment Form (if logged in) -->
                        <?php if ($isLoggedIn && $userId): ?>
                            <form id="commentForm" class="mb-8 p-4 sm:p-6 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <input type="hidden" name="story_id" value="<?= (int)$bookId ?>">
                                <input type="hidden" name="chapter_id" value="<?= isset($chapter['id']) ? (int)$chapter['id'] : 0 ?>">
                                <div id="commentMessage" class="hidden mb-4 p-3 rounded-lg text-xs sm:text-sm font-medium"></div>
                                <label class="block text-sm sm:text-base font-medium text-gray-900 dark:text-white mb-3">Share your thoughts:</label>
                                <textarea id="commentText" name="content" placeholder="What do you think?" 
                                          class="w-full px-3 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm border border-gray-300 dark:border-gray-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none" 
                                          rows="4" required></textarea>
                                <div class="flex gap-2 mt-3 flex-wrap">
                                    <button type="button" onclick="document.getElementById('commentText').value = ''" 
                                            class="flex-1 sm:flex-none px-3 sm:px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 font-medium">
                                        Cancel
                                    </button>
                                    <button type="submit" 
                                            class="flex-1 sm:flex-none px-3 sm:px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors">
                                        Post
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="mb-8 p-3 sm:p-4 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg">
                                <p class="text-xs sm:text-sm text-blue-700 dark:text-blue-400">
                                    <a href="<?= site_url('/pages/login.php') ?>" class="font-medium hover:underline">Login</a> 
                                    to share your thoughts!
                                </p>
                            </div>
                        <?php endif; ?>

                        <!-- Comments List -->
                        <div id="commentsList" class="space-y-4">
                            <p class="text-gray-500 dark:text-gray-400 text-center py-4">Loading comments...</p>
                        </div>
                    </div>
                </article>

                <!-- Action Sidebar -->
                <aside class="lg:col-span-1 order-3 mt-6 lg:mt-0">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 border border-emerald-200 dark:border-emerald-900 sticky top-4">
                        <h3 class="font-bold text-gray-900 dark:text-white mb-4 text-xs sm:text-sm">✦꒷꒦ Actions ✦꒷꒦</h3>
                        <div class="space-y-2">
                            <a href="<?= site_url('/pages/book.php?id=' . $bookId) ?>" class="block w-full px-3 sm:px-4 py-2 text-xs sm:text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors text-center truncate">
                                📖 Info
                            </a>
                            <?php if ($isLoggedIn && $userId): ?>
                                <button onclick="addToLibrary(<?= $bookId ?>)" class="w-full px-3 sm:px-4 py-2 text-xs sm:text-sm bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition-colors truncate">
                                    ♡ Add Library
                                </button>
                                <button onclick="shareChapter()" class="w-full px-3 sm:px-4 py-2 text-xs sm:text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors truncate">
                                    🔗 Share
                                </button>
                            <?php else: ?>
                                <a href="<?= site_url('/pages/login.php') ?>" class="block w-full px-3 sm:px-4 py-2 text-xs sm:text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors text-center">
                                    🔐 Login
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </aside>
            </div>
        <?php endif; ?>

    </main>
</div>

<script>
// Restore saved preferences and load comments
window.addEventListener('load', function() {
    // Font Size Control
    const fontSizeSelect = document.getElementById('fontSize');
    if (fontSizeSelect) {
        fontSizeSelect.addEventListener('change', function(e) {
            const chapterContent = document.getElementById('chapterContent');
            if (chapterContent) {
                chapterContent.style.fontSize = e.target.value + 'px';
                localStorage.setItem('fontSize', e.target.value);
            }
        });
    }

    // Line Height Control
    const lineHeightSelect = document.getElementById('lineHeight');
    if (lineHeightSelect) {
        lineHeightSelect.addEventListener('change', function(e) {
            const chapterContent = document.getElementById('chapterContent');
            if (chapterContent) {
                chapterContent.style.lineHeight = e.target.value;
                localStorage.setItem('lineHeight', e.target.value);
            }
        });
    }

    // Text Align Control
    const textAlignSelect = document.getElementById('textAlign');
    if (textAlignSelect) {
        textAlignSelect.addEventListener('change', function(e) {
            const chapterContent = document.getElementById('chapterContent');
            if (chapterContent) {
                chapterContent.style.textAlign = e.target.value;
                localStorage.setItem('textAlign', e.target.value);
            }
        });
    }

    // Restore saved preferences
    const fontSize = localStorage.getItem('fontSize') || '16';
    const lineHeight = localStorage.getItem('lineHeight') || '1.8';
    const textAlign = localStorage.getItem('textAlign') || 'justify';
    
    const fontSizeElem = document.getElementById('fontSize');
    const lineHeightElem = document.getElementById('lineHeight');
    const textAlignElem = document.getElementById('textAlign');
    const chapterContentElem = document.getElementById('chapterContent');
    
    if (fontSizeElem) fontSizeElem.value = fontSize;
    if (lineHeightElem) lineHeightElem.value = lineHeight;
    if (textAlignElem) textAlignElem.value = textAlign;
    
    if (chapterContentElem) {
        chapterContentElem.style.fontSize = fontSize + 'px';
        chapterContentElem.style.lineHeight = lineHeight;
        chapterContentElem.style.textAlign = textAlign;
    }
    
    // Restore writing mode (flip) preference
    const writingMode = localStorage.getItem('writingMode') || 'horizontal';
    if (chapterContentElem) {
        chapterContentElem.style.writingMode = writingMode === 'vertical' ? 'vertical-rl' : 'horizontal-tb';
    }
    
    // Load comments after page loads
    setTimeout(function() {
        loadComments();
    }, 100);
    
    // Attach comment form handler
    const commentForm = document.getElementById('commentForm');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const storyId = document.querySelector('input[name="story_id"]')?.value;
            const chapterId = document.querySelector('input[name="chapter_id"]')?.value;
            const content = document.getElementById('commentText').value;
            
            if (!storyId || !chapterId) {
                showCommentMessage('Error: Missing story or chapter ID', 'error');
                return;
            }
            
            if (!content.trim()) {
                showCommentMessage('Comment cannot be empty', 'error');
                return;
            }

            // Use JSON instead of FormData for cleaner API
            const url = '<?= site_url('/api/comment.php') ?>';
            const params = new URLSearchParams({
                story_id: storyId,
                chapter_id: chapterId,
                content: content
            });
            
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(r => {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status + ': ' + r.statusText);
                }
                return r.text();
            })
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Response text:', text);
                    throw new Error('Invalid JSON: ' + text.substring(0, 200));
                }
            })
            .then(data => {
                if (data.success) {
                    document.getElementById('commentText').value = '';
                    showCommentMessage('✓ Comment posted!', 'success');
                    setTimeout(() => {
                        loadComments();
                    }, 500);
                } else {
                    showCommentMessage('✗ ' + (data.error || 'Failed to post comment'), 'error');
                }
            })
            .catch(e => {
                console.error('Fetch error:', e);
                showCommentMessage('✗ Network error: ' + e.message, 'error');
            });
        });
    }
});

// Show comment message
function showCommentMessage(message, type = 'success') {
    const msgDiv = document.getElementById('commentMessage');
    if (msgDiv) {
        msgDiv.textContent = message;
        msgDiv.className = type === 'success' 
            ? 'mb-4 p-3 rounded-lg text-sm font-medium bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300'
            : 'mb-4 p-3 rounded-lg text-sm font-medium bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300';
    }
}

// Fullscreen Toggle
function toggleFullscreen() {
    const elem = document.getElementById('readingContent');
    if (elem) {
        if (!document.fullscreenElement) {
            elem.requestFullscreen?.() || elem.webkitRequestFullscreen?.();
        } else {
            document.exitFullscreen?.();
        }
    }
}

// Flip (Horizontal scroll for mobile)
function toggleFlip() {
    const content = document.getElementById('chapterContent');
    if (content) {
        if (content.style.writingMode === 'vertical-rl') {
            content.style.writingMode = 'horizontal-tb';
            localStorage.setItem('writingMode', 'horizontal');
        } else {
            content.style.writingMode = 'vertical-rl';
            localStorage.setItem('writingMode', 'vertical');
        }
    }
}

// Add to Library
function addToLibrary(storyId) {
    fetch('<?= site_url('/api/add_library.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ story_id: storyId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            alert('✓ Added to your library!');
        } else {
            alert('Error: ' + (data.error || 'Failed to add to library'));
        }
    })
    .catch(e => alert('Error: ' + e.message));
}

// Share Chapter
function shareChapter() {
    const url = window.location.href;
    const title = '<?= htmlspecialchars($story['title']) ?> - Chapter <?= (int)($chapter[$seqCol] ?? $chapter['sequence'] ?? 0) ?>';
    
    if (navigator.share) {
        navigator.share({
            title: title,
            url: url
        }).catch(e => console.log('Share failed:', e));
    } else {
        const textArea = document.createElement('textarea');
        textArea.value = url;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('✓ Link copied to clipboard!');
    }
}

// Load Comments
function loadComments() {
    const commentsList = document.getElementById('commentsList');
    if (!commentsList) {
        console.error('Comments list element not found');
        return;
    }
    
    const storyId = <?= (int)$bookId ?>;
    const chapterId = <?= isset($chapter['id']) ? (int)$chapter['id'] : 0 ?>;
    
    console.log('Loading comments for story:', storyId, 'chapter:', chapterId);
    
    if (!storyId || !chapterId) {
        console.error('Missing storyId or chapterId');
        commentsList.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-center py-4">Comments not available</p>';
        return;
    }

    const apiUrl = `<?= site_url('/api/get-comments.php') ?>?story_id=${storyId}&chapter_id=${chapterId}`;
    console.log('Fetching from:', apiUrl);
    
    fetch(apiUrl)
        .then(r => {
            console.log('Response status:', r.status);
            if (!r.ok) {
                throw new Error('HTTP ' + r.status + ': ' + r.statusText);
            }
            return r.text();
        })
        .then(text => {
            console.log('Response text:', text.substring(0, 200));
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e, 'Text:', text.substring(0, 500));
                throw new Error('Invalid JSON response from server');
            }
        })
        .then(data => {
            console.log('Received data:', data);
            if (data.success === false) {
                throw new Error(data.error || 'Server error');
            }
            if (data.comments && data.comments.length > 0) {
                const renderComment = (comment, isReply = false) => `
                    <div class="${isReply ? 'ml-8 pl-4 border-l-2 border-blue-400 dark:border-blue-500' : ''} p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                        <div class="flex gap-3 mb-3">
                            <div class="flex-shrink-0">
                                ${comment.profile_image ? `
                                    <img src="${escapeHtml(comment.profile_image)}" alt="${escapeHtml(comment.username)}" class="${isReply ? 'w-8 h-8' : 'w-10 h-10'} rounded-full object-cover">
                                ` : `
                                    <div class="${isReply ? 'w-8 h-8' : 'w-10 h-10'} rounded-full bg-emerald-200 dark:bg-emerald-900 flex items-center justify-center text-lg">👤</div>
                                `}
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <a href="<?= site_url('/pages/profile.php') ?>?user_id=${comment.user_id}" class="font-semibold ${isReply ? 'text-blue-600 dark:text-blue-400 text-sm' : 'text-emerald-600 dark:text-emerald-400'} hover:underline">${escapeHtml(comment.username || 'Anonymous')}</a>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">${formatDate(comment.created_at)}</p>
                            </div>
                        </div>
                        <p class="${isReply ? 'text-sm' : 'text-base'} text-gray-700 dark:text-gray-300 mb-3">${escapeHtml(comment.content)}</p>
                        ${!isReply ? `<button class="text-sm text-emerald-600 dark:text-emerald-400 hover:underline" onclick="showReplyForm(${comment.id})">
                            💬 Reply
                        </button>` : ''}
                        <div id="reply-form-${comment.id}" class="hidden mt-3 p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-300 dark:border-gray-600">
                            <textarea id="reply-text-${comment.id}" placeholder="Write a reply..." class="w-full p-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm resize-none" rows="2"></textarea>
                            <div class="flex gap-2 mt-2">
                                <button onclick="submitReply(${comment.id})" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm font-medium transition">Post Reply</button>
                                <button onclick="cancelReply(${comment.id})" class="px-3 py-1 bg-gray-400 hover:bg-gray-500 text-white rounded text-sm font-medium transition">Cancel</button>
                            </div>
                        </div>
                    </div>
                    ${(comment.replies && comment.replies.length > 0) ? comment.replies.map(reply => renderComment(reply, true)).join('') : ''}
                `;
                commentsList.innerHTML = data.comments.map(comment => renderComment(comment)).join('');
            } else {
                commentsList.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-center py-4">No comments yet. Be the first!</p>';
            }
        })
        .catch(e => {
            console.error('Error loading comments:', e);
            commentsList.innerHTML = '<p class="text-red-600 dark:text-red-400 text-center py-4">Error loading comments: ' + e.message + '</p>';
        });
}

function showReplyForm(commentId) {
    const form = document.getElementById(`reply-form-${commentId}`);
    if (form) {
        form.classList.toggle('hidden');
        if (!form.classList.contains('hidden')) {
            document.getElementById(`reply-text-${commentId}`).focus();
        }
    }
}

function cancelReply(commentId) {
    const form = document.getElementById(`reply-form-${commentId}`);
    if (form) {
        form.classList.add('hidden');
        document.getElementById(`reply-text-${commentId}`).value = '';
    }
}

function submitReply(commentId) {
    const replyText = document.getElementById(`reply-text-${commentId}`).value.trim();
    if (!replyText) {
        alert('Please write a reply');
        return;
    }
    
    const storyId = <?= (int)$bookId ?>;
    const chapterId = <?= isset($chapter['id']) ? (int)$chapter['id'] : 0 ?>;
    
    fetch('<?= site_url('/api/comment.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            story_id: storyId,
            chapter_id: chapterId,
            content: replyText,
            reply_to: commentId
        })
    })
    .then(r => r.text())
    .then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error('Invalid JSON response');
        }
    })
    .then(data => {
        if (data.success) {
            cancelReply(commentId);
            showCommentMessage('✓ Reply posted!', 'success');
            setTimeout(() => loadComments(), 500);
        } else {
            alert('Error posting reply: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(e => alert('Error: ' + e.message));
}

// Helper functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Keyboard Navigation
document.addEventListener('keydown', function(e) {
    // Left arrow = previous chapter
    if (e.key === 'ArrowLeft' && <?= $prevChapter ? 'true' : 'false' ?>) {
        window.location.href = '<?= $prevChapter ? site_url('/pages/read.php?id=' . $bookId . '&ch=' . ($prevChapter['chapter_order'] ?? $prevChapter['sequence'] ?? 0)) : '#' ?>';
    }
    // Right arrow = next chapter
    if (e.key === 'ArrowRight' && <?= $nextChapter ? 'true' : 'false' ?>) {
        window.location.href = '<?= $nextChapter ? site_url('/pages/read.php?id=' . $bookId . '&ch=' . ($nextChapter['chapter_order'] ?? $nextChapter['sequence'] ?? 0)) : '#' ?>';
    }
});

// Reading Time Tracker - Track user's reading time for supporter points
<?php if ($isLoggedIn && $chapter): ?>
(function() {
    const TRACK_INTERVAL = 60000; // Check every 60 seconds
    const bookId = <?= $bookId ?>;
    const chapterId = <?= $chapter['id'] ?>;
    const userId = <?= $userId ?>;
    
    // Start tracking if user is logged in
    setInterval(() => {
        // Only track if page is visible and user is still on page
        if (!document.hidden) {
            fetch('<?= site_url('/api/reading/track-time.php') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    book_id: bookId, 
                    chapter_id: chapterId 
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.points_awarded && data.points_awarded > 0) {
                    // Show notification when points are awarded
                    const notification = document.createElement('div');
                    notification.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 12px 20px; border-radius: 6px; font-weight: bold; z-index: 9999; animation: slideIn 0.3s ease-out;';
                    notification.textContent = `🎉 +${data.points_awarded} Points Earned!`;
                    document.body.appendChild(notification);
                    
                    setTimeout(() => notification.remove(), 3000);
                }
            })
            .catch(e => console.log('Reading tracker error:', e));
        }
    }, TRACK_INTERVAL);
})();
<?php endif; ?>
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

