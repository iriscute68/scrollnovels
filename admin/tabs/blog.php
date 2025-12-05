<?php
require_once __DIR__ . '/../blog.php';
?>
    ");
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $posts = [];
}
?>

<style>
.blog-container {
    padding: 1.5rem;
}

.blog-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    background: linear-gradient(135deg, #1e40af 0%, #1e293b 100%);
    padding: 1.5rem;
    border-radius: 8px;
    border-left: 4px solid #60a5fa;
}

.blog-header h2 {
    color: #e0f2fe;
    font-size: 1.8rem;
    margin: 0;
    font-weight: 700;
}

.blog-header button {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
}

.blog-header button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.posts-table {
    width: 100%;
    border-collapse: collapse;
    background: #1e293b;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.posts-table thead {
    background: linear-gradient(90deg, #0f172a 0%, #1e293b 100%);
}

.posts-table th {
    color: #e0f2fe;
    padding: 1rem;
    text-align: left;
    font-weight: 700;
    border-bottom: 2px solid #334155;
    font-size: 0.9rem;
}

.posts-table td {
    padding: 1rem;
    border-bottom: 1px solid #334155;
    color: #cbd5e1;
}

.posts-table tbody tr:hover {
    background-color: rgba(51, 65, 85, 0.3);
}

.status-badge {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-published {
    background: linear-gradient(135deg, #065f46 0%, #047857 100%);
    color: #d1fae5;
}

.status-draft {
    background: linear-gradient(135deg, #3730a3 0%, #4c1d95 100%);
    color: #e9d5ff;
}

.status-scheduled {
    background: linear-gradient(135deg, #0c4a6e 0%, #0284c7 100%);
    color: #cffafe;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-small {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 600;
}

.btn-edit {
    background: #3b82f6;
    color: white;
}

.btn-edit:hover {
    background: #60a5fa;
}

.btn-delete {
    background: #ef4444;
    color: white;
}

.btn-delete:hover {
    background: #f87171;
}

.btn-view {
    background: #8b5cf6;
    color: white;
}

.btn-view:hover {
    background: #a78bfa;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #94a3b8;
}

.empty-state svg {
    width: 80px;
    height: 80px;
    opacity: 0.5;
    margin-bottom: 1rem;
}

/* Modal/Create Form */
.create-form {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.create-form.active {
    display: flex;
}

.create-form-content {
    background: #1e293b;
    border-radius: 12px;
    padding: 2rem;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.create-form-content h3 {
    color: #e0f2fe;
    font-size: 1.5rem;
    margin: 0 0 1.5rem 0;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    color: #cbd5e1;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    background: #0f172a;
    border: 2px solid #334155;
    color: #e2e8f0;
    border-radius: 6px;
    font-family: inherit;
    font-size: 0.95rem;
}

.form-group textarea {
    resize: vertical;
    min-height: 150px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #60a5fa;
    box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.1);
}

.form-buttons {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.btn-primary {
    flex: 1;
    padding: 0.75rem;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-secondary {
    flex: 1;
    padding: 0.75rem;
    background: #334155;
    color: #e2e8f0;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background: #475569;
}

.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: linear-gradient(135deg, #1e40af 0%, #1e293b 100%);
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid #334155;
}

.stat-card-label {
    color: #94a3b8;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.stat-card-value {
    color: #e0f2fe;
    font-size: 2rem;
    font-weight: 700;
}

@media (max-width: 768px) {
    .blog-header {
        flex-direction: column;
        gap: 1rem;
    }

    .posts-table {
        font-size: 0.85rem;
    }

    .posts-table th,
    .posts-table td {
        padding: 0.75rem;
    }

    .action-buttons {
        flex-direction: column;
    }
}
</style>

<div class="blog-container">
    <!-- Stats Cards -->
    <div class="stats-cards">
        <div class="stat-card">
            <div class="stat-card-label">Total Posts</div>
            <div class="stat-card-value"><?= count($posts) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-label">Published</div>
            <div class="stat-card-value"><?= count(array_filter($posts, fn($p) => $p['status'] === 'published')) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-label">Drafts</div>
            <div class="stat-card-value"><?= count(array_filter($posts, fn($p) => $p['status'] === 'draft')) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-label">Total Views</div>
            <div class="stat-card-value"><?= number_format(array_sum(array_column($posts, 'views'))) ?></div>
        </div>
    </div>

    <!-- Header -->
    <div class="blog-header">
        <h2>📝 Blog Management</h2>
        <button onclick="openCreateForm()">+ New Post</button>
    </div>

    <!-- Posts Table -->
    <?php if (count($posts) > 0): ?>
        <table class="posts-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Status</th>
                    <th>Views</th>
                    <th>Comments</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($post['title']) ?></strong></td>
                        <td><?= htmlspecialchars($post['author_name'] ?? 'Unknown') ?></td>
                        <td>
                            <span class="status-badge status-<?= $post['status'] ?>">
                                <?= ucfirst($post['status']) ?>
                            </span>
                        </td>
                        <td><?= number_format($post['views']) ?></td>
                        <td><?= $post['comments_count'] ?> comments</td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-small btn-edit" onclick="editPost(<?= $post['id'] ?>)">Edit</button>
                                <button class="btn-small btn-view" onclick="viewPost(<?= $post['id'] ?>)">View</button>
                                <button class="btn-small btn-delete" onclick="deletePost(<?= $post['id'] ?>)">Delete</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty-state">
            <p>No blog posts yet. Create your first post!</p>
            <button onclick="openCreateForm()" style="margin-top: 1rem; padding: 0.75rem 1.5rem; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer;">Create Post</button>
        </div>
    <?php endif; ?>
</div>

<!-- Create Post Modal -->
<div class="create-form" id="createForm">
    <div class="create-form-content">
        <h3>Create New Blog Post</h3>
        <form onsubmit="submitPost(event)">
            <div class="form-group">
                <label for="post-title">Title</label>
                <input type="text" id="post-title" required placeholder="Enter blog post title">
            </div>

            <div class="form-group">
                <label for="post-category">Category</label>
                <select id="post-category" required>
                    <option value="">Select category</option>
                    <option value="update">Update</option>
                    <option value="feature">Feature</option>
                    <option value="tutorial">Tutorial</option>
                    <option value="announcement">Announcement</option>
                    <option value="news">News</option>
                </select>
            </div>

            <div class="form-group">
                <label for="post-content">Content</label>
                <textarea id="post-content" required placeholder="Write your blog post content here..."></textarea>
            </div>

            <div class="form-group">
                <label for="post-tags">Tags (comma-separated)</label>
                <input type="text" id="post-tags" placeholder="e.g. important, feature, update">
            </div>

            <div class="form-group">
                <label for="post-status">Status</label>
                <select id="post-status" required>
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="scheduled">Scheduled</option>
                </select>
            </div>

            <div class="form-buttons">
                <button type="submit" class="btn-primary">Publish Post</button>
                <button type="button" class="btn-secondary" onclick="closeCreateForm()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateForm() {
    document.getElementById('createForm').classList.add('active');
}

function closeCreateForm() {
    document.getElementById('createForm').classList.remove('active');
}

function submitPost(event) {
    event.preventDefault();
    const title = document.getElementById('post-title').value;
    const category = document.getElementById('post-category').value;
    const content = document.getElementById('post-content').value;
    const tags = document.getElementById('post-tags').value;
    const status = document.getElementById('post-status').value;

    // Send to API
    fetch('/admin/api/create-blog-post.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, category, content, tags, status })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Post created successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(e => alert('Error: ' + e.message));
}

function editPost(id) {
    window.location.href = '/admin/?tab=blog&action=edit&id=' + id;
}

function viewPost(id) {
    window.location.href = '/pages/blog-post.php?id=' + id;
}

function deletePost(id) {
    if (confirm('Are you sure you want to delete this post?')) {
        fetch('/admin/api/delete-blog-post.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Post deleted!');
                location.reload();
            } else {
                alert('Error deleting post');
            }
        });
    }
}

// Close modal when clicking outside
document.getElementById('createForm').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCreateForm();
    }
});
</script>
