<?php
// admin/pages/blog.php
$blog_posts = $pdo->query("
    SELECT b.*, u.username,
           (SELECT COUNT(*) FROM blog_comments WHERE blog_post_id = b.id) as comment_count
    FROM blog_posts b
    LEFT JOIN users u ON b.author_id = u.id
    ORDER BY b.created_at DESC
    LIMIT 100
")->fetchAll() ?? [];
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3>Blog Posts Management</h3>
    </div>
    <div class="col-md-6 text-end">
        <a href="blog_create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Create Post</a>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Title</th>
                <th>Author</th>
                <th>Comments</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($blog_posts as $post): ?>
            <tr>
                <td><?= htmlspecialchars($post['title']) ?></td>
                <td><?= htmlspecialchars($post['username']) ?></td>
                <td><span class="badge bg-info"><?= $post['comment_count'] ?></span></td>
                <td>
                    <?php if ($post['status'] === 'published'): ?>
                        <span class="badge bg-success">Published</span>
                    <?php elseif ($post['status'] === 'draft'): ?>
                        <span class="badge bg-warning">Draft</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Archived</span>
                    <?php endif; ?>
                </td>
                <td><?= date('M d, Y', strtotime($post['created_at'])) ?></td>
                <td>
                    <a href="blog_edit.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                    <button class="btn btn-sm btn-danger" onclick="deleteBlogPost(<?= $post['id'] ?>)"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function deleteBlogPost(id) {
    if (confirm('Delete this blog post?')) {
        fetch('<?= site_url('/api/admin/delete-blog.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id})
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) location.reload();
            else alert('Error: ' + d.error);
        });
    }
}
</script>
