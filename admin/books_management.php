<?php
/**
 * Comprehensive Book Management System
 * Combines BookStack and LNReader patterns with local story management
 * Features: Create, Read, Update, Delete, Upload, Settings
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireLogin();

// Admin check
$isAdmin = false;
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1) {
    $isAdmin = true;
} elseif (isset($_SESSION['admin_id']) && $_SESSION['admin_id']) {
    $isAdmin = true;
}

if (!$isAdmin) {
    header("Location: " . site_url('/pages/dashboard.php'));
    exit;
}

// Define upload directories
$uploadDir = dirname(__DIR__) . '/uploads/books/';
$coverDir = $uploadDir . 'covers/';
$contentDir = $uploadDir . 'content/';

// Create directories if they don't exist
foreach ([$uploadDir, $coverDir, $contentDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['ajax'] === 'create_book') {
            // Create new book/story
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $author_id = $_POST['author_id'] ?? $_SESSION['user_id'];
            $category_id = $_POST['category_id'] ?? null;
            $status = $_POST['status'] ?? 'draft';
            $is_adult = $_POST['is_adult'] ?? 0;
            
            if (empty($title)) {
                throw new Exception('Title is required');
            }
            
            // Create slug from title
            $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
            $slug = trim($slug, '-');
            
            // Check for duplicate slug
            $stmt = $pdo->prepare("SELECT id FROM stories WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->rowCount() > 0) {
                $slug .= '-' . time();
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO stories (title, slug, description, author_id, category_id, status, is_adult, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $result = $stmt->execute([$title, $slug, $description, $author_id, $category_id, $status, $is_adult]);
            
            if ($result) {
                $bookId = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'id' => $bookId, 'message' => 'Book created successfully']);
            } else {
                throw new Exception('Failed to create book');
            }
            
        } elseif ($_POST['ajax'] === 'update_book') {
            $book_id = $_POST['book_id'] ?? 0;
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $status = $_POST['status'] ?? 'draft';
            $is_adult = $_POST['is_adult'] ?? 0;
            
            if (!$book_id || empty($title)) {
                throw new Exception('Book ID and title are required');
            }
            
            $stmt = $pdo->prepare("
                UPDATE stories 
                SET title = ?, description = ?, status = ?, is_adult = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([$title, $description, $status, $is_adult, $book_id]);
            
            echo json_encode(['success' => $result, 'message' => $result ? 'Book updated' : 'Update failed']);
            
        } elseif ($_POST['ajax'] === 'upload_cover') {
            $book_id = $_POST['book_id'] ?? 0;
            
            if (!$book_id) {
                throw new Exception('Book ID is required');
            }
            
            if (!isset($_FILES['cover']) || $_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('No file uploaded or upload error');
            }
            
            $file = $_FILES['cover'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedMimes)) {
                throw new Exception('Invalid image format');
            }
            
            if ($file['size'] > 5 * 1024 * 1024) { // 5MB max
                throw new Exception('File too large (max 5MB)');
            }
            
            // Delete old cover if exists
            $stmt = $pdo->prepare("SELECT cover FROM stories WHERE id = ?");
            $stmt->execute([$book_id]);
            $old = $stmt->fetch();
            if ($old && $old['cover'] && file_exists($coverDir . $old['cover'])) {
                unlink($coverDir . $old['cover']);
            }
            
            // Save new cover
            $filename = 'cover_' . $book_id . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            if (move_uploaded_file($file['tmp_name'], $coverDir . $filename)) {
                $stmt = $pdo->prepare("UPDATE stories SET cover = ? WHERE id = ?");
                $stmt->execute([$filename, $book_id]);
                echo json_encode(['success' => true, 'filename' => $filename]);
            } else {
                throw new Exception('Failed to save file');
            }
            
        } elseif ($_POST['ajax'] === 'delete_book') {
            $book_id = $_POST['book_id'] ?? 0;
            $permanent = $_POST['permanent'] ?? false;
            
            if (!$book_id) {
                throw new Exception('Book ID is required');
            }
            
            if ($permanent) {
                // Hard delete
                $stmt = $pdo->prepare("SELECT cover FROM stories WHERE id = ?");
                $stmt->execute([$book_id]);
                $book = $stmt->fetch();
                
                if ($book && $book['cover'] && file_exists($coverDir . $book['cover'])) {
                    unlink($coverDir . $book['cover']);
                }
                
                // Delete chapters
                $pdo->prepare("DELETE FROM chapters WHERE story_id = ?")->execute([$book_id]);
                
                // Delete story
                $stmt = $pdo->prepare("DELETE FROM stories WHERE id = ?");
                $result = $stmt->execute([$book_id]);
                
                echo json_encode(['success' => $result, 'message' => 'Book permanently deleted']);
            } else {
                // Soft delete
                $stmt = $pdo->prepare("UPDATE stories SET status = 'deleted' WHERE id = ?");
                $result = $stmt->execute([$book_id]);
                echo json_encode(['success' => $result, 'message' => 'Book moved to trash']);
            }
            
        } elseif ($_POST['ajax'] === 'get_book_stats') {
            $book_id = $_POST['book_id'] ?? 0;
            
            $stmt = $pdo->prepare("
                SELECT 
                    s.id,
                    s.title,
                    s.views,
                    COUNT(DISTINCT c.id) as chapter_count,
                    SUM(c.views) as total_chapter_views
                FROM stories s
                LEFT JOIN chapters c ON c.story_id = s.id
                WHERE s.id = ?
                GROUP BY s.id
            ");
            $stmt->execute([$book_id]);
            $stats = $stmt->fetch();
            
            echo json_encode(['success' => !!$stats, 'data' => $stats]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch books for listing
$page = $_GET['page'] ?? 1;
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$limit = 20;
$offset = ($page - 1) * $limit;

$query = "SELECT s.*, u.username, COUNT(c.id) as chapter_count 
          FROM stories s
          LEFT JOIN users u ON s.author_id = u.id
          LEFT JOIN chapters c ON c.story_id = s.id
          WHERE 1=1";

$params = [];

if ($search) {
    $query .= " AND s.title LIKE ?";
    $params[] = "%$search%";
}

if ($status) {
    $query .= " AND s.status = ?";
    $params[] = $status;
}

$query .= " GROUP BY s.id ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$books = $stmt->fetchAll();

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM stories WHERE 1=1";
$countParams = [];
if ($search) {
    $countQuery .= " AND title LIKE ?";
    $countParams[] = "%$search%";
}
if ($status) {
    $countQuery .= " AND status = ?";
    $countParams[] = $status;
}

$stmt = $pdo->prepare($countQuery);
$stmt->execute($countParams);
$totalCount = $stmt->fetch()['total'] ?? 0;
$totalPages = ceil($totalCount / $limit);

// Get statistics
$stats = [
    'total_books' => $pdo->query("SELECT COUNT(*) FROM stories")->fetchColumn(),
    'draft_books' => $pdo->query("SELECT COUNT(*) FROM stories WHERE status = 'draft'")->fetchColumn(),
    'published_books' => $pdo->query("SELECT COUNT(*) FROM stories WHERE status = 'published'")->fetchColumn(),
    'total_chapters' => $pdo->query("SELECT COUNT(*) FROM chapters")->fetchColumn(),
    'total_views' => $pdo->query("SELECT SUM(views) FROM stories")->fetchColumn(),
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books Management - Admin Panel</title>
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
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        body {
            background: linear-gradient(135deg, var(--bg-primary) 0%, #0b0b0d 100%);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container-fluid {
            padding: 30px;
            max-width: 1400px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }

        .btn-primary {
            background: var(--accent);
            border: none;
        }

        .btn-primary:hover {
            background: #4f46e5;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--accent);
        }

        .books-table {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }

        .books-table table {
            margin: 0;
            color: var(--text-primary);
        }

        .books-table thead {
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid var(--border-color);
        }

        .books-table th {
            padding: 15px;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 12px;
            text-transform: uppercase;
        }

        .books-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .books-table tbody tr:hover {
            background: rgba(99, 102, 241, 0.05);
        }

        .book-title {
            font-weight: 600;
            color: var(--text-primary);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-draft {
            background: rgba(107, 114, 128, 0.2);
            color: #d1d5db;
        }

        .status-published {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-sm-custom {
            padding: 6px 12px;
            font-size: 12px;
            border: 1px solid var(--border-color);
            background: transparent;
            color: var(--accent);
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-sm-custom:hover {
            background: rgba(99, 102, 241, 0.1);
        }

        .search-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 10px 15px;
            border-radius: 6px;
        }

        .pagination-custom {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 5px;
        }

        .pagination-custom a,
        .pagination-custom span {
            padding: 6px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: var(--accent);
            text-decoration: none;
            transition: all 0.3s;
        }

        .pagination-custom a:hover {
            background: rgba(99, 102, 241, 0.2);
        }

        .pagination-custom .current {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        .modal-content {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
        }

        .form-control {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .form-control::placeholder {
            color: var(--text-secondary);
        }

        .form-control:focus {
            background: rgba(0, 0, 0, 0.2);
            border-color: var(--accent);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid var(--border-color);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="page-header">
            <div>
                <h1>📚 Books Management</h1>
                <p style="color: var(--text-secondary); margin-top: 5px;">Manage stories, chapters, and book settings</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newBookModal">
                <i class="fas fa-plus"></i> New Book
            </button>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Books</div>
                <div class="stat-value"><?= number_format($stats['total_books']) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Published</div>
                <div class="stat-value"><?= number_format($stats['published_books']) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Draft</div>
                <div class="stat-value"><?= number_format($stats['draft_books']) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Chapters</div>
                <div class="stat-value"><?= number_format($stats['total_chapters']) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Views</div>
                <div class="stat-value"><?= number_format($stats['total_views'] ?? 0) ?></div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div style="margin-bottom: 20px; display: flex; gap: 10px;">
            <form method="GET" style="display: flex; gap: 10px; flex: 1;">
                <input type="text" name="search" class="search-box" style="flex: 1;" placeholder="Search books..." value="<?= htmlspecialchars($search) ?>">
                <select name="status" class="search-box">
                    <option value="">All Status</option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
        </div>

        <!-- Books Table -->
        <div class="books-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Chapters</th>
                        <th>Views</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $book): ?>
                    <tr>
                        <td class="book-title"><?= htmlspecialchars(substr($book['title'], 0, 50)) ?></td>
                        <td><?= htmlspecialchars($book['username'] ?? 'Unknown') ?></td>
                        <td><?= $book['chapter_count'] ?? 0 ?></td>
                        <td><?= number_format($book['views'] ?? 0) ?></td>
                        <td>
                            <span class="status-badge status-<?= $book['status'] ?>">
                                <?= ucfirst($book['status']) ?>
                            </span>
                        </td>
                        <td><?= date('M d, Y', strtotime($book['created_at'])) ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-sm-custom" onclick="editBook(<?= $book['id'] ?>)">Edit</button>
                                <button class="btn-sm-custom" onclick="viewStats(<?= $book['id'] ?>)">Stats</button>
                                <button class="btn-sm-custom" onclick="deleteBook(<?= $book['id'] ?>, false)">Trash</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination-custom">
            <?php if ($page > 1): ?>
                <a href="?page=1&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">« First</a>
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">‹ Previous</a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">Next ›</a>
                <a href="?page=<?= $totalPages ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">Last »</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- New Book Modal -->
    <div class="modal fade" id="newBookModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Book</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="newBookForm">
                        <div class="mb-3">
                            <label for="bookTitle" class="form-label">Book Title</label>
                            <input type="text" class="form-control" id="bookTitle" required>
                        </div>
                        <div class="mb-3">
                            <label for="bookDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="bookDescription" rows="4"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="bookStatus" class="form-label">Status</label>
                            <select class="form-control" id="bookStatus">
                                <option value="draft">Draft</option>
                                <option value="pending">Pending Review</option>
                                <option value="published">Published</option>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="bookAdult">
                            <label class="form-check-label" for="bookAdult">Adult Content</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="createNewBook()">Create</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Modal -->
    <div class="modal fade" id="statsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Book Statistics</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="statsContent">
                    <div class="loading" style="margin-left: 50%;"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function createNewBook() {
            const title = document.getElementById('bookTitle').value;
            const description = document.getElementById('bookDescription').value;
            const status = document.getElementById('bookStatus').value;
            const isAdult = document.getElementById('bookAdult').checked ? 1 : 0;

            const formData = new FormData();
            formData.append('ajax', 'create_book');
            formData.append('title', title);
            formData.append('description', description);
            formData.append('status', status);
            formData.append('is_adult', isAdult);

            fetch('books_management.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Book created! ID: ' + data.id);
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }

        function editBook(bookId) {
            window.location.href = 'book_editor.php?id=' + bookId;
        }

        function viewStats(bookId) {
            const formData = new FormData();
            formData.append('ajax', 'get_book_stats');
            formData.append('book_id', bookId);

            fetch('books_management.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data) {
                    const stats = data.data;
                    document.getElementById('statsContent').innerHTML = `
                        <div>
                            <p><strong>Title:</strong> ${stats.title}</p>
                            <p><strong>Views:</strong> ${stats.views}</p>
                            <p><strong>Chapters:</strong> ${stats.chapter_count}</p>
                            <p><strong>Total Chapter Views:</strong> ${stats.total_chapter_views || 0}</p>
                        </div>
                    `;
                    new bootstrap.Modal(document.getElementById('statsModal')).show();
                }
            });
        }

        function deleteBook(bookId, permanent = false) {
            if (!confirm('Are you sure? This action cannot be undone.')) return;

            const formData = new FormData();
            formData.append('ajax', 'delete_book');
            formData.append('book_id', bookId);
            formData.append('permanent', permanent ? 1 : 0);

            fetch('books_management.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
    </script>
</body>
</html>
