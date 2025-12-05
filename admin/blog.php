<?php
// admin/blog.php - Manage blog posts
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
require_once __DIR__ . '/topbar.php';

if (!isset($_SESSION['admin_user'])) {
    header('Location: /pages/login.php');
    exit;
}

require_once __DIR__ . '/../config.php';

// Get all blog posts
$stmt = $pdo->prepare("
    SELECT id, title, level, show_on_ticker, is_pinned, active_from, active_until, created_at 
    FROM announcements 
    ORDER BY is_pinned DESC, created_at DESC 
    LIMIT 100
");
$stmt->execute();
$blog_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="flex-1 overflow-auto bg-background text-foreground">
    <div class="max-w-6xl mx-auto p-6 space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">📝 Blog Posts & Announcements</h1>
            <a href="/admin/blog_create.php" class="btn btn-primary">+ New Post</a>
        </div>

        <!-- Search & Filter -->
        <div class="card p-4 space-y-4">
            <div class="flex gap-3 flex-wrap">
                <input type="text" id="searchInput" class="input-field flex-1 min-w-[200px]" 
                    placeholder="Search posts...">
                <select id="filterLevel" class="input-field">
                    <option value="">All Types</option>
                    <option value="info">📰 Info</option>
                    <option value="notice">📢 Notice</option>
                    <option value="alert">⚠️ Alert</option>
                    <option value="system">⚙️ System</option>
                </select>
                <button onclick="filterPosts()" class="btn btn-ghost">Filter</button>
            </div>
        </div>

        <!-- Blog Posts Table -->
        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Title</th>
                        <th class="px-4 py-3 text-left font-medium">Type</th>
                        <th class="px-4 py-3 text-center font-medium">Active</th>
                        <th class="px-4 py-3 text-center font-medium">Ticker</th>
                        <th class="px-4 py-3 text-center font-medium">Pinned</th>
                        <th class="px-4 py-3 text-right font-medium">Created</th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody id="postsTable" class="divide-y divide-border">
                    <?php foreach ($blog_posts as $post): ?>
                        <tr class="hover:bg-muted/50 transition-colors">
                            <td class="px-4 py-3 font-medium"><?= htmlspecialchars($post['title']) ?></td>
                            <td class="px-4 py-3">
                                <span class="badge <?= $post['level'] === 'alert' ? 'badge-error' : ($post['level'] === 'notice' ? 'badge-warning' : 'badge-info') ?>">
                                    <?= ucfirst($post['level']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php
                                $now = new DateTime();
                                $from = new DateTime($post['active_from']);
                                $until = $post['active_until'] ? new DateTime($post['active_until']) : null;
                                $is_active = ($now >= $from) && (!$until || $now <= $until);
                                ?>
                                <span class="<?= $is_active ? 'text-success' : 'text-muted-foreground' ?>">
                                    <?= $is_active ? '✓' : '○' ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span><?= $post['show_on_ticker'] ? '📢' : '' ?></span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span><?= $post['is_pinned'] ? '📌' : '' ?></span>
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-muted-foreground">
                                <?= date('M d, Y', strtotime($post['created_at'])) ?>
                            </td>
                            <td class="px-4 py-3 text-right space-x-1">
                                <a href="/admin/blog_create.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-ghost">Edit</a>
                                <button onclick="deleteBlog(<?= $post['id'] ?>)" class="btn btn-sm btn-error">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
function filterPosts() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const level = document.getElementById('filterLevel').value;
    const rows = document.querySelectorAll('#postsTable tr');

    rows.forEach(row => {
        const title = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
        const type = row.querySelector('td:nth-child(2)').textContent;
        
        const matchSearch = title.includes(search);
        const matchLevel = !level || type.includes(level.charAt(0).toUpperCase() + level.slice(1));
        
        row.style.display = (matchSearch && matchLevel) ? '' : 'none';
    });
}

document.getElementById('searchInput').addEventListener('input', filterPosts);
document.getElementById('filterLevel').addEventListener('change', filterPosts);

async function deleteBlog(id) {
    if (!confirm('Delete this blog post?')) return;

    const res = await fetch('/admin/ajax/delete_blog_post.php?id=' + id, {
        method: 'POST',
        credentials: 'same-origin'
    });

    const result = await res.json();
    if (result.ok) {
        alert('Blog post deleted');
        location.reload();
    } else {
        alert('Error: ' + result.message);
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
