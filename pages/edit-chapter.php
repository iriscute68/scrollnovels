<?php
/**
 * Edit Chapter Page - Comprehensive Implementation
 * Full chapter editing interface with preview and statistics
 */
session_start();
require_once(__DIR__ . '/../config/db.php');
require_once(__DIR__ . '/../includes/auth.php');

requireLogin();

$book_id = $_GET['book'] ?? 1;
$chapter_id = $_GET['chapter'] ?? 1;

// Fetch chapter from database
try {
    $stmt = $pdo->prepare("
        SELECT c.*, s.author_id 
        FROM chapters c 
        JOIN stories s ON c.story_id = s.id 
        WHERE c.id = ? AND s.author_id = ?
    ");
    $stmt->execute([$chapter_id, $_SESSION['user_id']]);
    $chapter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$chapter) {
        $chapter = [
            'id' => $chapter_id,
            'chapter_number' => $chapter_id,
            'title' => 'Chapter ' . $chapter_id,
            'content' => 'The morning sun filtered through the ancient oaks...',
        ];
    }
} catch (Exception $e) {
    $chapter = [];
}

$word_count = isset($chapter['content']) ? str_word_count($chapter['content']) : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = htmlspecialchars($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $chapter_number = intval($_POST['number'] ?? 1);
    
    try {
        $stmt = $pdo->prepare("
            UPDATE chapters 
            SET title = ?, content = ?, chapter_number = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $content, $chapter_number, $chapter_id]);
        
        header('Location: ?book=' . $book_id . '&chapter=' . $chapter_id . '&success=1');
        exit;
    } catch (Exception $e) {
        $error = "Failed to update chapter: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Chapter - Scroll Novels</title>
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="stylesheet" href="/css/editor.css">
</head>
<body class="bg-background text-text-primary">
    <?php include(__DIR__ . '/../admin/header.php'); ?>

    <section class="edit-chapter-page">
        <div class="editor-container">
            <!-- Header -->
            <div class="editor-header">
                <a href="/pages/book-detail-integrated.php?id=<?php echo $book_id; ?>" class="back-link">← Back</a>
                <h1>Edit Chapter</h1>
                <div class="word-count">
                    <span><?php echo $word_count; ?> words</span>
                </div>
            </div>

            <!-- Main Editor -->
            <div class="editor-layout">
                <form method="POST" class="editor-form">
                    <!-- Chapter Info -->
                    <div class="form-group">
                        <label for="number">Chapter Number</label>
                        <input type="number" id="number" name="number" 
                            value="<?php echo htmlspecialchars($chapter['chapter_number'] ?? 1); ?>" min="1">
                    </div>

                    <div class="form-group">
                        <label for="title">Chapter Title</label>
                        <input type="text" id="title" name="title" 
                            value="<?php echo htmlspecialchars($chapter['title'] ?? ''); ?>">
                    </div>

                    <!-- Content Editor -->
                    <div class="form-group">
                        <label for="content">Chapter Content</label>
                        <textarea id="content" name="content" rows="25" class="code-editor"><?php echo htmlspecialchars($chapter['content'] ?? ''); ?></textarea>
                    </div>

                    <div class="editor-actions">
                        <button type="submit" class="btn btn-primary">Save Chapter</button>
                        <a href="/pages/book-detail-integrated.php?id=<?php echo $book_id; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>

                <!-- Sidebar -->
                <aside class="editor-sidebar">
                    <!-- Preview -->
                    <div class="sidebar-widget">
                        <h3>Preview</h3>
                        <div class="preview-box">
                            <p class="preview-chapter">Chapter <?php echo htmlspecialchars($chapter['chapter_number'] ?? 1); ?></p>
                            <h4><?php echo htmlspecialchars($chapter['title'] ?? ''); ?></h4>
                            <p class="preview-text"><?php echo substr(htmlspecialchars($chapter['content'] ?? ''), 0, 150); ?>...</p>
                        </div>
                    </div>

                    <!-- Settings -->
                    <div class="sidebar-widget">
                        <h3>Settings</h3>
                        <label>
                            <input type="checkbox" checked>
                            Comments allowed
                        </label>
                        <label>
                            <input type="checkbox" checked>
                            Show word count
                        </label>
                    </div>

                    <!-- Stats -->
                    <div class="sidebar-widget">
                        <h3>Stats</h3>
                        <div class="stats">
                            <div class="stat">
                                <span>Words:</span> 
                                <strong><?php echo $word_count; ?></strong>
                            </div>
                            <div class="stat">
                                <span>Characters:</span> 
                                <strong><?php echo strlen($chapter['content'] ?? ''); ?></strong>
                            </div>
                            <div class="stat">
                                <span>Paragraphs:</span> 
                                <strong><?php echo count(array_filter(explode("\n\n", $chapter['content'] ?? ''))); ?></strong>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <?php include(__DIR__ . '/../admin/footer.php'); ?>
    <script src="/js/editor.js"></script>
</body>
</html>
