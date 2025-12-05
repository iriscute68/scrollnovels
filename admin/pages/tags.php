<?php
// admin/pages/tags.php
// Extract unique tags from stories.tags column (comma-separated)
$allTags = [];
try {
    $storiesWithTags = $pdo->query("SELECT id, tags FROM stories WHERE tags IS NOT NULL AND tags != ''")->fetchAll();
    foreach ($storiesWithTags as $story) {
        $storyTags = array_map('trim', explode(',', $story['tags']));
        foreach ($storyTags as $tag) {
            if (!empty($tag)) {
                if (!isset($allTags[$tag])) {
                    $allTags[$tag] = ['name' => $tag, 'story_count' => 0, 'id' => null, 'description' => '', 'created_at' => date('Y-m-d H:i:s')];
                }
                $allTags[$tag]['story_count']++;
            }
        }
    }
    // Also get tags from tags table if it exists
    try {
        $dbTags = $pdo->query("SELECT * FROM tags ORDER BY name")->fetchAll();
        foreach ($dbTags as $dbTag) {
            $tagName = $dbTag['name'];
            if (!isset($allTags[$tagName])) {
                $allTags[$tagName] = ['name' => $tagName, 'story_count' => 0, 'id' => $dbTag['id'], 'description' => $dbTag['description'] ?? '', 'created_at' => $dbTag['created_at'] ?? date('Y-m-d H:i:s')];
            } else {
                $allTags[$tagName]['id'] = $dbTag['id'];
                $allTags[$tagName]['description'] = $dbTag['description'] ?? '';
            }
        }
    } catch (Exception $e) {}
} catch (Exception $e) {
    error_log('Tags page error: ' . $e->getMessage());
}
usort($allTags, function($a, $b) { return $b['story_count'] - $a['story_count']; });
$tags = array_slice($allTags, 0, 100);
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3>Manage Story Tags</h3>
    </div>
    <div class="col-md-6 text-end">
        <a href="javascript:void(0)" class="btn btn-primary" onclick="showCreateTagModal()"><i class="fas fa-plus"></i> Create Tag</a>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Tag Name</th>
                <th>Stories</th>
                <th>Description</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tags as $tag): ?>
            <tr>
                <td>
                    <span class="badge bg-primary"><?= htmlspecialchars($tag['name']) ?></span>
                </td>
                <td><?= $tag['story_count'] ?></td>
                <td><?= substr(htmlspecialchars($tag['description'] ?? ''), 0, 40) ?></td>
                <td><?= !empty($tag['created_at']) ? date('M d, Y', strtotime($tag['created_at'])) : '-' ?></td>
                <td>
                    <?php if (!empty($tag['id'])): ?>
                    <button class="btn btn-sm btn-warning" onclick="editTag(<?= $tag['id'] ?>)"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="deleteTag(<?= $tag['id'] ?>)"><i class="fas fa-trash"></i></button>
                    <?php else: ?>
                    <button class="btn btn-sm btn-success" onclick="createTagFromExisting('<?= htmlspecialchars($tag['name'], ENT_QUOTES) ?>')"><i class="fas fa-plus"></i> Add to DB</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Create/Edit Tag Modal -->
<div class="modal fade" id="tagModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tagModalTitle">Create Tag</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="tagId">
                <div class="mb-3">
                    <label class="form-label">Tag Name</label>
                    <input type="text" class="form-control" id="tagName">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" id="tagDescription" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveTag()">Save Tag</button>
            </div>
        </div>
    </div>
</div>

<script>
function showCreateTagModal() {
    document.getElementById('tagId').value = '';
    document.getElementById('tagName').value = '';
    document.getElementById('tagDescription').value = '';
    document.getElementById('tagModalTitle').innerText = 'Create Tag';
    new bootstrap.Modal(document.getElementById('tagModal')).show();
}

function editTag(id) {
    fetch('/api/admin/get-tag.php?id=' + id)
        .then(r => r.json())
        .then(d => {
            document.getElementById('tagId').value = id;
            document.getElementById('tagName').value = d.name;
            document.getElementById('tagDescription').value = d.description || '';
            document.getElementById('tagModalTitle').innerText = 'Edit Tag';
            new bootstrap.Modal(document.getElementById('tagModal')).show();
        });
}

function saveTag() {
    const id = document.getElementById('tagId').value;
    const name = document.getElementById('tagName').value;
    const description = document.getElementById('tagDescription').value;

    fetch('/api/admin/save-tag.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id || null, name: name, description: description})
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            bootstrap.Modal.getInstance(document.getElementById('tagModal')).hide();
            location.reload();
        } else {
            alert('Error: ' + d.error);
        }
    });
}

function deleteTag(id) {
    if (confirm('Delete this tag?')) {
        fetch('/api/admin/delete-tag.php', {
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
