<?php
// admin/blog_list.php - Manage blog posts with search and filters
session_start();
require_once __DIR__ . '/inc/db.php';

// Check admin login
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Get search and filter parameters
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(title LIKE ? OR slug LIKE ? OR tags LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $where[] = "status = ?";
    $params[] = $status;
}

if ($category) {
    $where[] = "category = ?";
    $params[] = $category;
}

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM posts $where_clause");
$count_stmt->execute($params);
$total = $count_stmt->fetch()['total'];

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Fetch posts
$stmt = $pdo->prepare("
    SELECT p.*, u.username 
    FROM posts p 
    LEFT JOIN users u ON p.user_id = u.id 
    $where_clause
    ORDER BY p.created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$limit, $offset]));
$posts = $stmt->fetchAll();

// Get available categories
$categories_stmt = $pdo->query("SELECT DISTINCT category FROM posts ORDER BY category");
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Blog Management - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #0f0820; color: #F5F0E8; }
        .card { background: #1a0f3a; border: 1px solid rgba(212,175,55,0.1); }
        .btn-gold { background: #D4AF37; color: #120A2A; }
        .btn-gold:hover { background: #e0c158; }
        .status-badge { 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 12px; 
            font-weight: bold;
        }
        .status-draft { background: #FEF3C7; color: #92400E; }
        .status-published { background: #D1FAE5; color: #065F46; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="max-w-7xl mx-auto p-6 mt-16">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-4xl font-bold text-yellow-400">📝 Blog Management</h1>
        <a href="blog_new.php" class="btn-gold px-6 py-2 rounded-lg font-semibold">+ New Blog Post</a>
    </div>

    <!-- Filters -->
    <div class="card p-6 rounded-lg mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="text" id="search" placeholder="Search posts..." 
                   class="px-4 py-2 bg-gray-900 border border-gray-700 rounded text-white" 
                   value="<?= htmlspecialchars($search) ?>" />
            
            <select id="status" class="px-4 py-2 bg-gray-900 border border-gray-700 rounded text-white">
                <option value="">All Statuses</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
            </select>

            <select id="category" class="px-4 py-2 bg-gray-900 border border-gray-700 rounded text-white">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button onclick="applyFilters()" class="btn-gold px-4 py-2 rounded">🔍 Search</button>
        </div>
    </div>

    <!-- Results Count -->
    <p class="text-gray-400 mb-4">Found <strong><?= $total ?></strong> blog post(s)</p>

    <!-- Posts Table -->
    <div class="card rounded-lg overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-800 border-b border-gray-700">
                <tr>
                    <th class="px-6 py-4 text-left">Title</th>
                    <th class="px-6 py-4 text-left">Category</th>
                    <th class="px-6 py-4 text-left">Author</th>
                    <th class="px-6 py-4 text-center">Status</th>
                    <th class="px-6 py-4 text-right">Views</th>
                    <th class="px-6 py-4 text-left">Created</th>
                    <th class="px-6 py-4 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($posts)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-400">
                            No blog posts found. <a href="blog_new.php" class="text-yellow-400 hover:underline">Create one</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-800 transition">
                            <td class="px-6 py-4">
                                <div class="font-semibold"><?= htmlspecialchars(substr($post['title'], 0, 50)) ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($post['slug']) ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm"><?= htmlspecialchars($post['category'] ?? 'Uncategorized') ?></td>
                            <td class="px-6 py-4 text-sm"><?= htmlspecialchars($post['username'] ?? 'System') ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="status-badge status-<?= $post['status'] ?>">
                                    <?= ucfirst($post['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-sm"><?= (int)$post['views'] ?></td>
                            <td class="px-6 py-4 text-sm text-gray-400">
                                <?= date('M d, Y', strtotime($post['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <a href="blog_edit.php?id=<?= $post['id'] ?>" class="text-blue-400 hover:text-blue-300">Edit</a>
                                <span class="text-gray-600">|</span>
                                <button onclick="deletePost(<?= $post['id'] ?>)" class="text-red-400 hover:text-red-300">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total > $limit): ?>
        <div class="mt-6 flex justify-center gap-2">
            <?php for ($p = 1; $p <= ceil($total / $limit); $p++): ?>
                <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&category=<?= urlencode($category) ?>"
                   class="px-4 py-2 rounded <?= $p === $page ? 'btn-gold' : 'bg-gray-800 border border-gray-700' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function applyFilters() {
    const search = document.getElementById('search').value;
    const status = document.getElementById('status').value;
    const category = document.getElementById('category').value;
    window.location = `?search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}&category=${encodeURIComponent(category)}`;
}

function deletePost(id) {
    if (confirm('Are you sure? This cannot be undone.')) {
        fetch('api/blog_delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        }).then(r => r.json()).then(d => {
            if (d.success) location.reload();
            else alert('Error: ' + (d.error || 'Unknown error'));
        });
    }
}
</script>
</body>
</html>
