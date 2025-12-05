<?php
/**
 * Book Editor - Full book editing interface
 * Combines BookStack style editing with story uploads
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

$book_id = $_GET['id'] ?? 0;

if (!$book_id) {
    die('Book ID required');
}

// Fetch book
$stmt = $pdo->prepare("
    SELECT s.*, u.username, COUNT(c.id) as chapter_count 
    FROM stories s
    LEFT JOIN users u ON s.author_id = u.id
    LEFT JOIN chapters c ON c.story_id = s.id
    WHERE s.id = ?
    GROUP BY s.id
");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if (!$book) {
    die('Book not found');
}

// Handle AJAX updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'update_metadata') {
            $stmt = $pdo->prepare("
                UPDATE stories 
                SET title = ?, description = ?, status = ?, is_adult = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $_POST['title'] ?? '',
                $_POST['description'] ?? '',
                $_POST['status'] ?? 'draft',
                $_POST['is_adult'] ?? 0,
                $book_id
            ]);
            
            echo json_encode(['success' => $result, 'message' => 'Updated successfully']);
            
        } elseif ($_POST['action'] === 'upload_cover') {
            if (!isset($_FILES['cover'])) {
                throw new Exception('No file uploaded');
            }
            
            $file = $_FILES['cover'];
            $uploadDir = dirname(__DIR__) . '/uploads/books/covers/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Validate
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mimeType, $allowedMimes)) {
                throw new Exception('Invalid image format');
            }
            
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('File too large');
            }
            
            // Delete old cover
            $stmt = $pdo->prepare("SELECT cover FROM stories WHERE id = ?");
            $stmt->execute([$book_id]);
            $old = $stmt->fetch();
            if ($old && $old['cover'] && file_exists($uploadDir . $old['cover'])) {
                unlink($uploadDir . $old['cover']);
            }
            
            // Save new
            $filename = 'cover_' . $book_id . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $stmt = $pdo->prepare("UPDATE stories SET cover = ? WHERE id = ?");
                $stmt->execute([$filename, $book_id]);
                
                echo json_encode(['success' => true, 'filename' => $filename]);
            } else {
                throw new Exception('Failed to save file');
            }
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
    <title>Edit: <?= htmlspecialchars($book['title']) ?> - Admin</title>
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

        .editor-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .editor-main {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 30px;
        }

        .editor-sidebar {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
        }

        .editor-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .editor-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
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

        .cover-upload {
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .cover-upload:hover {
            border-color: var(--accent);
            background: rgba(99, 102, 241, 0.05);
        }

        .cover-preview {
            max-width: 100%;
            border-radius: 6px;
            margin-top: 10px;
        }

        .sidebar-widget {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-widget:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .widget-title {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }

        .btn-save {
            background: var(--accent);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-save:hover {
            background: #4f46e5;
        }

        .chapters-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .chapter-item {
            padding: 10px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .back-link {
            color: var(--accent);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 20px;
        }

        .back-link:hover {
            color: #4f46e5;
        }

        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #6b7280;
            margin-right: 8px;
        }

        .status-indicator.published {
            background: #10b981;
        }

        .status-indicator.draft {
            background: #6b7280;
        }

        @media (max-width: 768px) {
            .editor-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="editor-container">
        <!-- Main Editor -->
        <div class="editor-main">
            <a href="books_management.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Books
            </a>

            <!-- Title -->
            <div class="editor-section">
                <div class="section-title">
                    <i class="fas fa-heading"></i> Title & Description
                </div>
                <div class="mb-3">
                    <label class="form-label">Book Title</label>
                    <input type="text" id="bookTitle" class="form-control" value="<?= htmlspecialchars($book['title']) ?>" placeholder="Enter book title">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea id="bookDescription" class="form-control" rows="5" placeholder="Enter book description..."><?= htmlspecialchars($book['description']) ?></textarea>
                </div>
            </div>

            <!-- Cover Image -->
            <div class="editor-section">
                <div class="section-title">
                    <i class="fas fa-image"></i> Cover Image
                </div>
                <div class="cover-upload" onclick="document.getElementById('coverInput').click()">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 30px; color: var(--accent); margin-bottom: 10px;"></i>
                    <p style="margin: 0; color: var(--text-secondary);">Click to upload cover image</p>
                    <small style="color: var(--text-secondary);">JPG, PNG, GIF or WebP (Max 5MB)</small>
                </div>
                <input type="file" id="coverInput" style="display: none;" accept="image/*" onchange="uploadCover(event)">
                
                <?php if ($book['cover']): ?>
                    <img src="/uploads/books/covers/<?= htmlspecialchars($book['cover']) ?>" class="cover-preview" alt="Cover">
                <?php endif; ?>
            </div>

            <!-- Chapters -->
            <div class="editor-section">
                <div class="section-title">
                    <i class="fas fa-list"></i> Chapters (<?= count($chapters) ?>)
                </div>
                <?php if (count($chapters) > 0): ?>
                    <div class="chapters-list">
                        <?php foreach ($chapters as $ch): ?>
                            <div class="chapter-item">
                                <span>Chapter <?= $ch['chapter_number'] ?>: <?= htmlspecialchars(substr($ch['title'], 0, 40)) ?></span>
                                <small style="color: var(--text-secondary);"><?= $ch['views'] ?> views</small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-secondary);">No chapters yet</p>
                <?php endif; ?>
                <button class="btn btn-primary mt-3" style="width: 100%;">
                    <i class="fas fa-plus"></i> Add Chapter
                </button>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="editor-sidebar">
            <!-- Status -->
            <div class="sidebar-widget">
                <div class="widget-title">Status</div>
                <select id="bookStatus" class="form-select">
                    <option value="draft" <?= $book['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="pending" <?= $book['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="published" <?= $book['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                </select>
            </div>

            <!-- Adult Content -->
            <div class="sidebar-widget">
                <div class="widget-title">Content Settings</div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="isAdult" <?= $book['is_adult'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="isAdult">Adult Content</label>
                </div>
            </div>

            <!-- Stats -->
            <div class="sidebar-widget">
                <div class="widget-title">Statistics</div>
                <div style="font-size: 12px;">
                    <p style="margin: 5px 0;"><strong>Views:</strong> <?= number_format($book['views'] ?? 0) ?></p>
                    <p style="margin: 5px 0;"><strong>Chapters:</strong> <?= $book['chapter_count'] ?></p>
                    <p style="margin: 5px 0;"><strong>Created:</strong> <?= date('M d, Y', strtotime($book['created_at'])) ?></p>
                </div>
            </div>

            <!-- Actions -->
            <div class="sidebar-widget">
                <button class="btn-save" onclick="saveBook()">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function saveBook() {
            const formData = new FormData();
            formData.append('action', 'update_metadata');
            formData.append('title', document.getElementById('bookTitle').value);
            formData.append('description', document.getElementById('bookDescription').value);
            formData.append('status', document.getElementById('bookStatus').value);
            formData.append('is_adult', document.getElementById('isAdult').checked ? 1 : 0);

            fetch('book_editor.php?id=<?= $book_id ?>', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Book saved successfully!');
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }

        function uploadCover(event) {
            const file = event.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('action', 'upload_cover');
            formData.append('cover', file);

            fetch('book_editor.php?id=<?= $book_id ?>', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Cover uploaded successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
    </script>
</body>
</html>
