<?php // admin/pages/stories.php ?>
<h4>Story Moderation</h4>
<div class="mb-3">
    <div class="row">
        <div class="col-md-8">
            <input type="text" id="storySearch" class="form-control" placeholder="Search stories..." onkeyup="filterStories()">
        </div>
        <div class="col-md-4">
            <select id="storyStatus" class="form-control" onchange="filterStories()">
                <option value="">All Status</option>
                <option value="draft">Draft</option>
                <option value="pending">Pending</option>
                <option value="published">Published</option>
                <option value="archived">Archived</option>
            </select>
        </div>
    </div>
</div>
<?php
$stories = $pdo->query("SELECT s.id, s.title, s.status, s.author_id, u.username, s.views, s.created_at FROM stories s JOIN users u ON s.author_id = u.id ORDER BY s.created_at DESC LIMIT 100")->fetchAll();
?>
<div class="list-group" id="storiesList">
    <?php foreach ($stories as $s): ?>
        <div class="list-group-item story-item" data-title="<?= htmlspecialchars(strtolower($s['title'])) ?>" data-status="<?= $s['status'] ?>" style="cursor: pointer;" onclick="openStoryModal(<?= $s['id'] ?>, <?= json_encode($s) ?>)">
            <div class="d-flex justify-content-between align-items-center">
                <div style="flex: 1;">
                    <h6 style="margin: 0;"><?= htmlspecialchars($s['title']) ?></h6>
                    <small>by <?= htmlspecialchars($s['username']) ?> | Views: <?= $s['views'] ?> | <?= $s['created_at'] ?></small>
                </div>
                <span class="badge bg-<?= $s['status'] === 'published' ? 'success' : ($s['status'] === 'pending' || $s['status'] === 'pending_review' ? 'warning' : ($s['status'] === 'draft' ? 'secondary' : 'danger')) ?>"><?= ucfirst($s['status']) ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Story Moderation Modal -->
<div class="modal fade" id="storyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="storyTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="storyDetails"></div>
                <input type="hidden" id="currentAuthorId" value="">
                <hr>
                <div class="mt-3">
                    <label class="form-label"><strong>Reason/Message to Author (optional)</strong></label>
                    <textarea id="moderationReason" class="form-control mb-3" rows="2" placeholder="Explain why the story is being removed/unpublished..."></textarea>
                </div>
                <hr>
                <div class="mt-3">
                    <label class="form-label"><strong>Boost Options</strong></label>
                    <select id="boostOption" class="form-select mb-2">
                        <option value="">Select boost duration...</option>
                        <option value="24h">24 Hours - Homepage Featured</option>
                        <option value="7d">7 Days - Homepage Featured</option>
                        <option value="30d">30 Days - Homepage Featured</option>
                    </select>
                    <button type="button" class="btn btn-success" onclick="boostStory()">🚀 Apply Boost</button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info" id="publishBtn" onclick="publishStory()">📤 Publish</button>
                <button type="button" class="btn btn-warning" id="unpublishBtn" onclick="unpublishStory()">📥 Unpublish</button>
                <button type="button" class="btn btn-danger" id="deleteBtn" onclick="deleteStory()">🗑️ Delete</button>
            </div>
        </div>
    </div>
</div>
<script>
let currentStoryId = null;
let currentStoryStatus = null;
let currentAuthorId = null;

function filterStories() {
    const searchTerm = document.getElementById('storySearch').value.toLowerCase();
    const statusFilter = document.getElementById('storyStatus').value;
    const items = document.querySelectorAll('.story-item');
    
    items.forEach(item => {
        const title = item.dataset.title || '';
        const status = item.dataset.status || '';
        const matchesSearch = title.includes(searchTerm);
        const matchesStatus = !statusFilter || status === statusFilter;
        item.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });
}

function openStoryModal(id, story) {
    currentStoryId = id;
    currentStoryStatus = story.status;
    currentAuthorId = story.author_id;
    document.getElementById('storyTitle').textContent = story.title;
    document.getElementById('currentAuthorId').value = story.author_id;
    document.getElementById('moderationReason').value = '';
    document.getElementById('storyDetails').innerHTML = `
        <p><strong>Author:</strong> ${story.username}</p>
        <p><strong>Status:</strong> <span class="badge bg-${story.status === 'active' || story.status === 'published' ? 'success' : story.status === 'pending' ? 'warning' : 'danger'}">${story.status}</span></p>
        <p><strong>Views:</strong> ${story.views}</p>
        <p><strong>Created:</strong> ${story.created_at}</p>
    `;
    
    // Show/hide buttons based on status
    const publishBtn = document.getElementById('publishBtn');
    const unpublishBtn = document.getElementById('unpublishBtn');
    
    if (story.status === 'active' || story.status === 'published') {
        publishBtn.style.display = 'none';
        unpublishBtn.style.display = 'inline-block';
    } else {
        publishBtn.style.display = 'inline-block';
        unpublishBtn.style.display = 'none';
    }
    
    new bootstrap.Modal(document.getElementById('storyModal')).show();
}

function publishStory() {
    if (!currentStoryId) return;
    if (confirm('Publish this story?')) {
        fetch('<?= site_url('/admin/api/story-moderation.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'publish', story_id: currentStoryId})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Story published!');
                bootstrap.Modal.getInstance(document.getElementById('storyModal')).hide();
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function unpublishStory() {
    if (!currentStoryId) return;
    const reason = document.getElementById('moderationReason').value.trim();
    if (confirm('Unpublish this story?' + (reason ? '\\n\\nReason: ' + reason : ''))) {
        fetch('<?= site_url('/admin/api/story-moderation.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'unpublish', 
                story_id: currentStoryId,
                author_id: currentAuthorId,
                reason: reason
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Story unpublished!' + (reason ? ' Author has been notified.' : ''));
                bootstrap.Modal.getInstance(document.getElementById('storyModal')).hide();
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function deleteStory() {
    if (!currentStoryId) return;
    const reason = document.getElementById('moderationReason').value.trim();
    if (confirm('Are you sure you want to delete this story? This action cannot be undone.' + (reason ? '\\n\\nReason: ' + reason : ''))) {
        fetch('<?= site_url('/admin/api/story-moderation.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'delete', 
                story_id: currentStoryId,
                author_id: currentAuthorId,
                reason: reason
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Story deleted!' + (reason ? ' Author has been notified.' : ''));
                bootstrap.Modal.getInstance(document.getElementById('storyModal')).hide();
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function boostStory() {
    if (!currentStoryId) return;
    const boostOption = document.getElementById('boostOption').value;
    if (!boostOption) {
        alert('Please select a boost duration');
        return;
    }
    
    if (confirm('Boost this story for ' + boostOption + '?')) {
        fetch('<?= site_url('/admin/api/story-moderation.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'boost', story_id: currentStoryId, duration: boostOption})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Story boosted successfully!');
                bootstrap.Modal.getInstance(document.getElementById('storyModal')).hide();
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}
</script>
