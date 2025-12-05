<?php
// admin/pages/achievements.php
$achievements = $pdo->query("
    SELECT a.*,
           (SELECT COUNT(*) FROM user_achievements WHERE achievement_id = a.id) as earned_count
    FROM achievements a
    ORDER BY a.created_at DESC
    LIMIT 100
")->fetchAll();
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3>Manage Achievements</h3>
    </div>
    <div class="col-md-6 text-end">
        <a href="javascript:void(0)" class="btn btn-primary" onclick="showCreateAchievementModal()"><i class="fas fa-plus"></i> Create Achievement</a>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Name</th>
                <th>Description</th>
                <th>Users Earned</th>
                <th>Icon</th>
                <th>Points</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($achievements as $ach): ?>
            <tr>
                <td><?= htmlspecialchars($ach['title']) ?></td>
                <td><?= substr(htmlspecialchars($ach['description']), 0, 50) ?></td>
                <td><span class="badge bg-info"><?= $ach['earned_count'] ?></span></td>
                <td><i class="fas <?= htmlspecialchars($ach['icon']) ?>"></i></td>
                <td><?= $ach['points'] ?></td>
                <td><?= date('M d, Y', strtotime($ach['created_at'])) ?></td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick="editAchievement(<?= $ach['id'] ?>)"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="deleteAchievement(<?= $ach['id'] ?>)"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Create/Edit Achievement Modal -->
<div class="modal fade" id="achievementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="achievementModalTitle">Create Achievement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="achievementId">
                <div class="mb-3">
                    <label class="form-label">Achievement Name</label>
                    <input type="text" class="form-control" id="achievementName" placeholder="e.g., First Story Published">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" id="achievementDescription" rows="3" placeholder="What did they do to earn this?"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Icon (Font Awesome class)</label>
                    <input type="text" class="form-control" id="achievementIcon" placeholder="fa-star">
                </div>
                <div class="mb-3">
                    <label class="form-label">Badge Color (Hex)</label>
                    <input type="color" class="form-control" id="achievementBadgeColor" placeholder="#FFD700">
                </div>
                <div class="mb-3">
                    <label class="form-label">Points</label>
                    <input type="number" class="form-control" id="achievementPoints" placeholder="10">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveAchievement()">Save Achievement</button>
            </div>
        </div>
    </div>
</div>

<script>
function showCreateAchievementModal() {
    document.getElementById('achievementId').value = '';
    document.getElementById('achievementName').value = '';
    document.getElementById('achievementDescription').value = '';
    document.getElementById('achievementIcon').value = '';
    document.getElementById('achievementBadgeColor').value = '#FFD700';
    document.getElementById('achievementPoints').value = '';
    document.getElementById('achievementModalTitle').innerText = 'Create Achievement';
    new bootstrap.Modal(document.getElementById('achievementModal')).show();
}

function editAchievement(id) {
    fetch('/api/admin/get-achievement.php?id=' + id)
        .then(r => r.json())
        .then(d => {
            document.getElementById('achievementId').value = id;
            document.getElementById('achievementName').value = d.title;
            document.getElementById('achievementDescription').value = d.description;
            document.getElementById('achievementIcon').value = d.icon;
            document.getElementById('achievementBadgeColor').value = d.badge_color || '#FFD700';
            document.getElementById('achievementPoints').value = d.points;
            document.getElementById('achievementModalTitle').innerText = 'Edit Achievement';
            new bootstrap.Modal(document.getElementById('achievementModal')).show();
        })
        .catch(e => {
            console.error('Error loading achievement:', e);
            alert('Failed to load achievement data');
        });
}

function saveAchievement() {
    const id = document.getElementById('achievementId').value;
    const title = document.getElementById('achievementName').value;
    const description = document.getElementById('achievementDescription').value;
    const icon = document.getElementById('achievementIcon').value;
    const badge_color = document.getElementById('achievementBadgeColor').value;
    const points = document.getElementById('achievementPoints').value;

    if (!title || !description) {
        alert('Please fill in all fields');
        return;
    }

    fetch('/api/admin/save-achievement.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            id: id || null,
            title: title,
            description: description,
            icon: icon,
            badge_color: badge_color,
            points: parseInt(points) || 0
        })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            bootstrap.Modal.getInstance(document.getElementById('achievementModal')).hide();
            location.reload();
        } else {
            alert('Error: ' + d.error);
        }
    })
    .catch(e => {
        console.error('Error saving achievement:', e);
        alert('Error: Failed to save achievement');
    });
}

function deleteAchievement(id) {
    if (confirm('Delete this achievement?')) {
        fetch('/api/admin/delete-achievement.php', {
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
