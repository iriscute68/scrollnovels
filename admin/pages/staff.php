<?php
// admin/pages/staff.php

// Get current user role
$currentUserRole = '';
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id'] ?? 0]);
$currentUserData = $stmt->fetch();
$currentUserRole = $currentUserData['role'] ?? '';
$isSuperAdmin = $currentUserRole === 'super_admin';

$admins = $pdo->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM admin_action_logs WHERE actor_id = u.id) as action_count
    FROM users u
    WHERE u.role IN ('admin', 'super_admin', 'moderator')
    ORDER BY u.created_at DESC
")->fetchAll();
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3>Manage Staff & Moderators</h3>
    </div>
    <div class="col-md-6 text-end">
        <a href="javascript:void(0)" class="btn btn-primary" onclick="showAddModModal()"><i class="fas fa-plus"></i> Add Moderator</a>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Actions Logged</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($admins as $admin): ?>
            <tr>
                <td><?= htmlspecialchars($admin['username']) ?></td>
                <td><?= htmlspecialchars($admin['email']) ?></td>
                <td>
                    <?php if ($admin['role'] === 'super_admin'): ?>
                        <span class="badge bg-danger">Super Admin</span>
                    <?php elseif ($admin['role'] === 'admin'): ?>
                        <span class="badge bg-warning">Admin</span>
                    <?php else: ?>
                        <span class="badge bg-info">Moderator</span>
                    <?php endif; ?>
                </td>
                <td><?= $admin['action_count'] ?></td>
                <td><?= date('M d, Y', strtotime($admin['created_at'])) ?></td>
                <td>
                    <?php 
                    // Super admins can only be removed by other super admins
                    // Mods cannot remove super admins
                    $canEdit = $isSuperAdmin || ($admin['role'] !== 'super_admin');
                    $canDelete = $isSuperAdmin && $admin['role'] !== 'super_admin' && $admin['id'] != $_SESSION['user_id'];
                    ?>
                    <?php if ($canEdit): ?>
                    <button class="btn btn-sm btn-warning" onclick="editAdminRole(<?= $admin['id'] ?>)"><i class="fas fa-edit"></i></button>
                    <?php endif; ?>
                    <?php if ($canDelete): ?>
                    <button class="btn btn-sm btn-danger" onclick="removeAdmin(<?= $admin['id'] ?>)"><i class="fas fa-trash"></i></button>
                    <?php elseif ($admin['role'] === 'super_admin' && !$isSuperAdmin): ?>
                    <span class="text-muted small">Protected</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add/Edit Mod Modal -->
<div class="modal fade" id="adminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adminModalTitle">Add Moderator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="adminId">
                <div class="mb-3">
                    <label class="form-label">Search User by Username</label>
                    <input type="text" class="form-control" id="adminUsername" placeholder="Start typing username..." autocomplete="off">
                    <small class="form-text text-muted">Type at least 2 characters to search</small>
                    <div id="userSuggestions" class="list-group mt-2" style="display:none; position: absolute; z-index: 1000; width: calc(100% - 2rem); max-height: 200px; overflow-y: auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1);"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select class="form-select" id="adminRole">
                        <option value="moderator">Moderator</option>
                        <?php if ($isSuperAdmin): ?>
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveAdmin()">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentEditId = null;
let usersSearchCache = {};

function showAddModModal() {
    currentEditId = null;
    document.getElementById('adminId').value = '';
    document.getElementById('adminUsername').value = '';
    document.getElementById('adminUsername').disabled = false;
    document.getElementById('adminRole').value = 'moderator';
    document.getElementById('adminModalTitle').innerText = 'Add Moderator';
    document.getElementById('userSuggestions').style.display = 'none';
    new bootstrap.Modal(document.getElementById('adminModal')).show();
}

function searchUsers(query) {
    if (query.length < 2) {
        document.getElementById('userSuggestions').style.display = 'none';
        return;
    }
    
    fetch('<?= site_url('/api/admin/search-users.php') ?>?q=' + encodeURIComponent(query))
        .then(r => r.json())
        .then(users => {
            const suggestions = document.getElementById('userSuggestions');
            suggestions.innerHTML = '';
            
            if (!users || users.length === 0) {
                suggestions.innerHTML = '<div class="list-group-item text-muted">No users found</div>';
            } else {
                users.forEach(user => {
                    const item = document.createElement('a');
                    item.href = '#';
                    item.className = 'list-group-item list-group-item-action';
                    item.innerHTML = `<strong>${escapeHtml(user.username)}</strong> <small class="text-muted">(${escapeHtml(user.email)})</small>`;
                    item.onclick = (e) => {
                        e.preventDefault();
                        document.getElementById('adminUsername').value = user.username;
                        document.getElementById('adminUsername').dataset.userId = user.id;
                        suggestions.style.display = 'none';
                    };
                    suggestions.appendChild(item);
                });
            }
            suggestions.style.display = 'block';
        })
        .catch(e => console.error('Search error:', e));
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', () => {
    const usernameInput = document.getElementById('adminUsername');
    usernameInput.addEventListener('input', (e) => {
        searchUsers(e.target.value);
    });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#adminUsername') && !e.target.closest('#userSuggestions')) {
            document.getElementById('userSuggestions').style.display = 'none';
        }
    });
});

function editAdminRole(id) {
    currentEditId = id;
    fetch('<?= site_url('/api/admin/get-admin.php') ?>?id=' + id)
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(d => {
            if (d.success === false) {
                alert('Error: ' + (d.error || 'Failed to load admin'));
                return;
            }
            document.getElementById('adminId').value = id;
            document.getElementById('adminUsername').value = d.username + ' (' + d.email + ')';
            document.getElementById('adminUsername').dataset.userId = id;
            document.getElementById('adminRole').value = d.role;
            document.getElementById('adminModalTitle').innerText = 'Edit Admin Role';
            document.getElementById('adminUsername').disabled = true;
            document.getElementById('userSuggestions').style.display = 'none';
            new bootstrap.Modal(document.getElementById('adminModal')).show();
        })
        .catch(e => {
            console.error('Error loading admin:', e);
            alert('Failed to load admin data: ' + e.message);
        });
}

function saveAdmin() {
    const id = document.getElementById('adminId').value || null;
    const usernameInput = document.getElementById('adminUsername');
    const username = usernameInput.value.trim();
    const role = document.getElementById('adminRole').value;

    if (!username || !role) {
        alert('Please fill in all fields');
        return;
    }

    // For new admins, use the stored user ID if available
    let dataToSend = {
        username: username,
        role: role
    };
    
    if (usernameInput.dataset.userId) {
        dataToSend.user_id = parseInt(usernameInput.dataset.userId);
    }
    
    if (id) {
        dataToSend.id = parseInt(id);
    }

    console.log('Saving admin:', dataToSend);

    fetch('<?= site_url('/api/admin/save-admin.php') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(dataToSend)
    })
    .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(d => {
        if (d.success) {
            bootstrap.Modal.getInstance(document.getElementById('adminModal')).hide();
            location.reload();
        } else {
            alert('Error: ' + (d.error || 'Unknown error'));
        }
    })
    .catch(e => {
        console.error('Error saving admin:', e);
        alert('Error: ' + e.message);
    });
}

function removeAdmin(id) {
    if (confirm('Remove this admin? They will lose access to the admin panel.')) {
        fetch('<?= site_url('/api/admin/remove-admin.php') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id})
        })
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(d => {
            if (d.success) {
                location.reload();
            } else {
                alert('Error: ' + (d.error || 'Failed to remove admin'));
            }
        })
        .catch(e => {
            console.error('Error removing admin:', e);
            alert('Error: ' + e.message);
        });
    }
}
</script>
