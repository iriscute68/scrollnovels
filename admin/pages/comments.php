<?php
// admin/pages/comments.php

// Ensure required tables exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS comment_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            comment_id INT NOT NULL,
            reporter_id INT NOT NULL,
            reason VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (comment_id) REFERENCES book_comments(id) ON DELETE CASCADE,
            FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_comment (comment_id)
        )
    ");
} catch (Exception $e) {
    // Table may already exist
}

$comments = $pdo->query("
    SELECT c.*, u.username, s.title as story_title, 
           (SELECT COUNT(*) FROM comment_reports WHERE comment_id = c.id) as report_count
    FROM book_comments c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN stories s ON c.story_id = s.id
    ORDER BY c.created_at DESC
    LIMIT 100
")->fetchAll();
?>

<div class="row mb-3">
    <div class="col">
        <h3>Book Comments Moderation</h3>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Author</th>
                <th>Story</th>
                <th>Comment</th>
                <th>Reports</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($comments as $comment): ?>
            <tr>
                <td><?= htmlspecialchars($comment['username']) ?></td>
                <td><?= htmlspecialchars($comment['story_title']) ?></td>
                <td><?= substr(htmlspecialchars($comment['content']), 0, 50) ?>...</td>
                <td>
                    <?php if ($comment['report_count'] > 0): ?>
                        <span class="badge bg-danger"><?= $comment['report_count'] ?></span>
                    <?php else: ?>
                        <span class="badge bg-success">0</span>
                    <?php endif; ?>
                </td>
                <td><?= date('M d, Y', strtotime($comment['created_at'])) ?></td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="viewComment(<?= $comment['id'] ?>)"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="deleteComment(<?= $comment['id'] ?>)"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Comment Modal -->
<div class="modal fade" id="commentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Comment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="commentBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewComment(id) {
    fetch('<?= site_url('/api/admin/get-comment.php') ?>?id=' + id)
        .then(r => r.json())
        .then(d => {
            document.getElementById('commentBody').innerHTML = `
                <p><strong>Author:</strong> ${d.username}</p>
                <p><strong>Story:</strong> ${d.story_title}</p>
                <p><strong>Comment:</strong></p>
                <p>${d.content}</p>
            `;
            new bootstrap.Modal(document.getElementById('commentModal')).show();
        });
}

function deleteComment(id) {
    if (confirm('Delete this comment?')) {
        fetch('<?= site_url('/api/admin/delete-comment.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id})
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) location.reload();
            else alert('Error: ' + (d.error || 'Unknown error'));
        });
    }
}
</script>
