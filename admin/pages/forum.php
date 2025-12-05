<?php // admin/pages/forum.php ?>
<h4>Forum Moderation</h4>
<div class="row">
    <div class="col-md-8 mb-3">
        <div class="card">
            <div class="card-header"><h6>Recent Forum Topics</h6></div>
            <div class="card-body">
                <?php
                $threads = $pdo->query("SELECT ft.id, ft.title, ft.author_id, u.username, ft.created_at, ft.views, ft.status, ft.pinned FROM forum_topics ft JOIN users u ON ft.author_id = u.id ORDER BY ft.created_at DESC LIMIT 20")->fetchAll();
                ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Views</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($threads as $t): ?>
                            <tr>
                                <td><?= htmlspecialchars(substr($t['title'], 0, 40)) ?></td>
                                <td><?= htmlspecialchars($t['username']) ?></td>
                                <td><?= $t['views'] ?></td>
                                <td>
                                    <?php if (!empty($t['pinned'])): ?>
                                        <span class="badge bg-info">📌 Pinned</span>
                                    <?php elseif (($t['status'] ?? 'active') === 'removed'): ?>
                                        <span class="badge bg-danger">Removed</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= site_url('/pages/forum-topic.php?id=' . $t['id']) ?>" target="_blank" class="btn btn-info" title="View"><i class="fas fa-eye"></i></a>
                                        <?php if (empty($t['pinned'])): ?>
                                            <button class="btn btn-warning" onclick="pinTopic(<?= $t['id'] ?>)" title="Pin"><i class="fas fa-thumbtack"></i></button>
                                        <?php else: ?>
                                            <button class="btn btn-secondary" onclick="unpinTopic(<?= $t['id'] ?>)" title="Unpin"><i class="fas fa-thumbtack"></i></button>
                                        <?php endif; ?>
                                        <button class="btn btn-danger" onclick="removeTopic(<?= $t['id'] ?>)" title="Remove"><i class="fas fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card">
            <div class="card-header"><h6>Flagged/Reported Posts</h6></div>
            <div class="card-body">
                <?php
                try {
                    $flagged = $pdo->query("SELECT fp.*, u.username, ft.title as topic_title FROM forum_posts fp JOIN users u ON fp.author_id = u.id JOIN forum_topics ft ON fp.topic_id = ft.id WHERE fp.is_flagged = 1 OR fp.reports > 0 ORDER BY fp.created_at DESC LIMIT 10")->fetchAll();
                    if (empty($flagged)):
                ?>
                    <p class="text-muted">No flagged posts at this time.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($flagged as $fp): ?>
                        <div class="list-group-item">
                            <strong><?= htmlspecialchars(substr($fp['topic_title'], 0, 30)) ?></strong>
                            <br><small>by <?= htmlspecialchars($fp['username']) ?></small>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-danger" onclick="removePost(<?= $fp['id'] ?>)">Remove</button>
                                <button class="btn btn-sm btn-secondary" onclick="dismissFlag(<?= $fp['id'] ?>)">Dismiss</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; } catch (Exception $e) { ?>
                    <p class="text-muted">No flagged posts at this time.</p>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<script>
function pinTopic(id) {
    if (confirm('Pin this topic? It will appear at the top of the forum.')) {
        fetch('<?= site_url('/api/admin/forum-moderation.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'pin', topic_id: id})
        }).then(r => r.json()).then(d => {
            if (d.success) location.reload();
            else alert('Error: ' + (d.error || 'Unknown error'));
        });
    }
}

function unpinTopic(id) {
    if (confirm('Unpin this topic?')) {
        fetch('<?= site_url('/api/admin/forum-moderation.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'unpin', topic_id: id})
        }).then(r => r.json()).then(d => {
            if (d.success) location.reload();
            else alert('Error: ' + (d.error || 'Unknown error'));
        });
    }
}

function removeTopic(id) {
    if (confirm('Remove this topic? This will hide it from users.')) {
        fetch('<?= site_url('/api/admin/forum-moderation.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'remove_topic', topic_id: id})
        }).then(r => r.json()).then(d => {
            if (d.success) location.reload();
            else alert('Error: ' + (d.error || 'Unknown error'));
        });
    }
}

function removePost(id) {
    if (confirm('Remove this post?')) {
        fetch('<?= site_url('/api/admin/forum-moderation.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'remove_post', post_id: id})
        }).then(r => r.json()).then(d => {
            if (d.success) location.reload();
            else alert('Error: ' + (d.error || 'Unknown error'));
        });
    }
}

function dismissFlag(id) {
    fetch('<?= site_url('/api/admin/forum-moderation.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'dismiss_flag', post_id: id})
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
        else alert('Error: ' + (d.error || 'Unknown error'));
    });
}
</script>
