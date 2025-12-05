<?php
/**
 * Chapters Management System
 * Similar to BookStack's chapter management
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireLogin();

// Admin check
$isAdmin = isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1;
if (!$isAdmin && !isset($_SESSION['admin_id'])) {
    header("Location: " . site_url('/pages/dashboard.php'));
    exit;
}

$book_id = $_GET['book_id'] ?? 0;

if (!$book_id) {
    die('Book ID required');
}

// Fetch book
$stmt = $pdo->prepare("SELECT * FROM stories WHERE id = ?");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if (!$book) {
    die('Book not found');
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'create_chapter') {
            // Get next chapter number
            $stmt = $pdo->prepare("SELECT MAX(chapter_number) as max_num FROM chapters WHERE story_id = ?");
            $stmt->execute([$book_id]);
            $result = $stmt->fetch();
            $nextNum = ($result['max_num'] ?? 0) + 1;

            $title = $_POST['title'] ?? "Chapter $nextNum";
            $content = $_POST['content'] ?? '';

            $stmt = $pdo->prepare("
                INSERT INTO chapters (story_id, chapter_number, title, content, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            
            $result = $stmt->execute([$book_id, $nextNum, $title, $content]);
            $chapterId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => $result,
                'id' => $chapterId,
                'number' => $nextNum,
                'message' => 'Chapter created'
            ]);
            
        } elseif ($_POST['action'] === 'update_chapter') {
            $chapter_id = $_POST['chapter_id'] ?? 0;
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';

            $stmt = $pdo->prepare("
                UPDATE chapters 
                SET title = ?, content = ?, updated_at = NOW()
                WHERE id = ? AND story_id = ?
            ");
            
            $result = $stmt->execute([$title, $content, $chapter_id, $book_id]);
            echo json_encode(['success' => $result, 'message' => 'Chapter updated']);
            
        } elseif ($_POST['action'] === 'delete_chapter') {
            $chapter_id = $_POST['chapter_id'] ?? 0;

            $stmt = $pdo->prepare("DELETE FROM chapters WHERE id = ? AND story_id = ?");
            $result = $stmt->execute([$chapter_id, $book_id]);
            
            echo json_encode(['success' => $result, 'message' => 'Chapter deleted']);
            
        } elseif ($_POST['action'] === 'get_chapter') {
            $chapter_id = $_POST['chapter_id'] ?? 0;

            $stmt = $pdo->prepare("SELECT * FROM chapters WHERE id = ? AND story_id = ?");
            $stmt->execute([$chapter_id, $book_id]);
            $chapter = $stmt->fetch();
            
            echo json_encode(['success' => !!$chapter, 'data' => $chapter]);
        } elseif ($_POST['action'] === 'reorder_chapters') {
            $order = $_POST['order'] ?? [];
            
            foreach ($order as $position => $chapterId) {
                $stmt = $pdo->prepare("
                    UPDATE chapters 
                    SET chapter_number = ? 
                    WHERE id = ? AND story_id = ?
                ");
                $stmt->execute([$position + 1, $chapterId, $book_id]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Order updated']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch chapters
$stmt = $pdo->prepare("SELECT * FROM chapters WHERE story_id = ? ORDER BY chapter_number ASC");
$stmt->execute([$book_id]);
$chapters = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapters - <?= htmlspecialchars($book['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0f0f12;
            --bg-secondary: #141418;
            --border-color: #22222a;
            --text-primary: #e6e7ea;
            --text-secondary: #9aa0a6;
            --accent: #6366f1;
        }

        body {
            background: linear-gradient(135deg, var(--bg-primary) 0%, #0b0b0d 100%);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container-fluid {
            padding: 30px;
            max-width: 1200px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .chapters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .chapters-list {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }

        .chapter-item {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chapter-item:hover {
            background: rgba(99, 102, 241, 0.05);
        }

        .chapter-item.active {
            background: rgba(99, 102, 241, 0.1);
            border-left: 3px solid var(--accent);
        }

        .chapter-info {
            flex: 1;
        }

        .chapter-title {
            font-weight: 600;
            color: var(--text-primary);
        }

        .chapter-meta {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 3px;
        }

        .chapter-actions {
            display: flex;
            gap: 8px;
        }

        .btn-sm-custom {
            padding: 4px 8px;
            font-size: 11px;
            border: 1px solid var(--border-color);
            background: transparent;
            color: var(--accent);
            border-radius: 3px;
            cursor: pointer;
        }

        .btn-sm-custom:hover {
            background: rgba(99, 102, 241, 0.1);
        }

        .editor-panel {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
        }

        .form-control,
        .form-select,
        textarea {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 10px 15px;
            border-radius: 6px;
        }

        .form-control:focus,
        .form-select:focus,
        textarea:focus {
            background: rgba(0, 0, 0, 0.3);
            border-color: var(--accent);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }

        .form-label {
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 8px;
        }

        .editor-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-action {
            flex: 1;
            padding: 10px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-action:hover {
            background: #4f46e5;
        }

        .btn-action.delete {
            background: #ef4444;
        }

        .btn-action.delete:hover {
            background: #dc2626;
        }

        @media (max-width: 768px) {
            .chapters-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="page-header">
            <div>
                <h1>📖 Chapters</h1>
                <p style="color: var(--text-secondary); margin-top: 5px;"><?= htmlspecialchars($book['title']) ?></p>
            </div>
            <button class="btn btn-primary" onclick="createNewChapter()">
                <i class="fas fa-plus"></i> New Chapter
            </button>
        </div>

        <div class="chapters-grid">
            <!-- Chapters List -->
            <div class="chapters-list" id="chaptersList">
                <?php if (count($chapters) > 0): ?>
                    <?php foreach ($chapters as $ch): ?>
                        <div class="chapter-item" onclick="selectChapter(<?= $ch['id'] ?>, <?= $ch['chapter_number'] ?>, '<?= htmlspecialchars($ch['title']) ?>', `<?= htmlspecialchars($ch['content']) ?>`)">
                            <div class="chapter-info">
                                <div class="chapter-title">Chapter <?= $ch['chapter_number'] ?></div>
                                <div class="chapter-meta"><?= htmlspecialchars(substr($ch['title'], 0, 50)) ?></div>
                            </div>
                            <div class="chapter-actions">
                                <button class="btn-sm-custom" onclick="event.stopPropagation(); deleteChapter(<?= $ch['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 30px; text-align: center; color: var(--text-secondary);">
                        No chapters yet. Create your first chapter!
                    </div>
                <?php endif; ?>
            </div>

            <!-- Editor Panel -->
            <div class="editor-panel">
                <div id="noChapterSelected" style="text-align: center; color: var(--text-secondary); padding: 40px 20px;">
                    <i class="fas fa-book" style="font-size: 40px; margin-bottom: 15px; display: block;"></i>
                    <p>Select a chapter or create a new one to edit</p>
                </div>

                <div id="editorContent" style="display: none;">
                    <div class="mb-3">
                        <label class="form-label">Chapter Title</label>
                        <input type="text" id="chapterTitle" class="form-control" placeholder="Chapter title">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea id="chapterContent" class="form-control" rows="10" placeholder="Chapter content..."></textarea>
                    </div>

                    <div class="editor-actions">
                        <button class="btn-action" onclick="saveChapter()">
                            <i class="fas fa-save"></i> Save
                        </button>
                        <button class="btn-action delete" onclick="deleteCurrentChapter()">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentChapterId = null;

        function createNewChapter() {
            const title = prompt('Enter chapter title:', '');
            if (!title) return;

            const formData = new FormData();
            formData.append('action', 'create_chapter');
            formData.append('title', title);

            fetch('chapters_management.php?book_id=<?= $book_id ?>', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Chapter created!');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }

        function selectChapter(id, num, title, content) {
            currentChapterId = id;

            // Update active state
            document.querySelectorAll('.chapter-item').forEach(el => el.classList.remove('active'));
            event.currentTarget.classList.add('active');

            // Show editor
            document.getElementById('noChapterSelected').style.display = 'none';
            document.getElementById('editorContent').style.display = 'block';

            document.getElementById('chapterTitle').value = title;
            document.getElementById('chapterContent').value = content;
        }

        function saveChapter() {
            if (!currentChapterId) return;

            const formData = new FormData();
            formData.append('action', 'update_chapter');
            formData.append('chapter_id', currentChapterId);
            formData.append('title', document.getElementById('chapterTitle').value);
            formData.append('content', document.getElementById('chapterContent').value);

            fetch('chapters_management.php?book_id=<?= $book_id ?>', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Chapter saved!');
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }

        function deleteChapter(id) {
            if (!confirm('Delete this chapter?')) return;

            const formData = new FormData();
            formData.append('action', 'delete_chapter');
            formData.append('chapter_id', id);

            fetch('chapters_management.php?book_id=<?= $book_id ?>', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Chapter deleted!');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }

        function deleteCurrentChapter() {
            if (!currentChapterId) return;
            deleteChapter(currentChapterId);
        }
    </script>
</body>
</html>
