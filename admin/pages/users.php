<?php
// admin/pages/users.php - User management

// Ensure required tables exist
try {
    // Create story_genres table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS story_genres (
            id INT AUTO_INCREMENT PRIMARY KEY,
            story_id INT NOT NULL,
            genre_id INT,
            genre_name VARCHAR(100),
            FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
            INDEX idx_story (story_id)
        )
    ");
    
    // Create genres table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS genres (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) UNIQUE,
            slug VARCHAR(100) UNIQUE
        )
    ");
    
    // Create story_tags table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS story_tags (
            id INT AUTO_INCREMENT PRIMARY KEY,
            story_id INT NOT NULL,
            tag_id INT,
            tag_name VARCHAR(100),
            FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
            INDEX idx_story (story_id)
        )
    ");
    
    // Create tags table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tags (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) UNIQUE,
            slug VARCHAR(100) UNIQUE
        )
    ");
} catch (Exception $e) {
    // Tables may already exist
}

// Get users with their status info
$users = $pdo->query("
    SELECT u.id, u.username, u.email, u.wallet_balance, u.role, u.status, COUNT(s.id) as story_count, u.created_at
    FROM users u 
    LEFT JOIN stories s ON s.author_id = u.id 
    GROUP BY u.id 
    ORDER BY u.id DESC 
    LIMIT 50
")->fetchAll();

// Get recommended stories (using genres/tags from stories table directly)
$recommended = $pdo->query("
    SELECT s.id, s.title, s.author_id, u.username, s.views,
           s.genres as genres,
           s.tags as tags
    FROM stories s
    JOIN users u ON s.author_id = u.id
    WHERE (
        s.tags LIKE '%LGBTQ+%' OR s.tags LIKE '%BL%' OR s.tags LIKE '%GL%' OR s.tags LIKE '%Sapphic%' 
        OR s.tags LIKE '%Female Protagonist%' OR s.tags LIKE '%FemPro%' OR s.tags LIKE '%Female Lead%' OR s.tags LIKE '%Women Lead%'
        OR s.genres LIKE '%LGBTQ+%' OR s.genres LIKE '%Romance%' OR s.genres LIKE '%Fiction%'
    )
    ORDER BY s.views DESC
    LIMIT 10
")->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h4>User Management</h4>
    </div>
    <div class="col-md-4">
        <a href="<?= site_url('/admin/admin.php?page=recommended-content') ?>" class="btn btn-info float-end">
            <i class="fas fa-star"></i> Recommended Content
        </a>
    </div>
</div>

<div class="mb-3">
    <input type="text" id="userSearch" class="form-control" placeholder="Search users by username or email...">
</div>

<div class="table-responsive">
    <table class="table table-striped" id="usersTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Stories</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="usersBody">
            <?php foreach ($users as $u): ?>
                <tr id="user-row-<?= $u['id'] ?>">
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <span class="badge bg-<?= $u['role'] === 'super_admin' ? 'danger' : ($u['role'] === 'moderator' ? 'warning' : 'secondary') ?>">
                            <?= htmlspecialchars($u['role']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : ($u['status'] === 'banned' ? 'danger' : 'warning') ?>">
                            <?= htmlspecialchars($u['status']) ?>
                        </span>
                    </td>
                    <td><?= $u['story_count'] ?></td>
                    <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-sm btn-info" onclick="viewUser(<?= $u['id'] ?>)" title="View Profile">
                                <i class="fas fa-user"></i>
                            </button>
                            <button class="btn btn-sm btn-secondary" onclick="muteUser(<?= $u['id'] ?>)" title="Mute User">
                                <i class="fas fa-microphone-slash"></i>
                            </button>
                            <button class="btn btn-sm btn-warning" onclick="tempBanUser(<?= $u['id'] ?>)" title="Temporary Ban">
                                <i class="fas fa-ban"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="permBanUser(<?= $u['id'] ?>)" title="Permanent Ban">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Recommended Content Section -->
<div class="card mt-5">
    <div class="card-header bg-info">
        <h5 class="mb-0"><i class="fas fa-star"></i> Recommended Content - LGBTQ+ & Female Protagonists</h5>
    </div>
    <div class="card-body">
        <p class="text-muted">These stories showcase diverse representation and are recommended for platform promotion:</p>
        <div class="row">
            <?php foreach ($recommended as $story): ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title text-truncate"><?= htmlspecialchars($story['title']) ?></h6>
                            <p class="card-text small">
                                <strong>Author:</strong> <?= htmlspecialchars($story['username']) ?><br>
                                <strong>Views:</strong> <?= number_format($story['views']) ?><br>
                                <strong>Rating:</strong> <?= round($story['rating'], 1) ?>/5
                            </p>
                            <p class="card-text small text-muted">
                                <strong>Tags:</strong> <?= htmlspecialchars(substr($story['tags'] ?? 'N/A', 0, 40)) ?><?= strlen($story['tags'] ?? '') > 40 ? '...' : '' ?>
                            </p>
                        </div>
                        <div class="card-footer bg-light">
                            <button class="btn btn-sm btn-primary" onclick="viewStory(<?= $story['id'] ?>)">View</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (empty($recommended)): ?>
            <div class="alert alert-info">No recommended stories found yet.</div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('userSearch').addEventListener('keyup', debounce(async function(e) {
    const query = this.value.trim();
    if (query.length < 2) {
        location.reload();
        return;
    }
    
    try {
        const response = await fetch('<?= site_url('/api/admin/search-users.php') ?>?q=' + encodeURIComponent(query));
        const data = await response.json();
        
        if (data.length > 0) {
            const tbody = document.getElementById('usersBody');
            tbody.innerHTML = '';
            
            data.forEach(user => {
                const statusBadge = user.status === 'active' ? '<span class="badge bg-success">active</span>' : 
                                   user.status === 'banned' ? '<span class="badge bg-danger">banned</span>' : 
                                   '<span class="badge bg-warning">suspended</span>';
                const roleBadge = user.role === 'super_admin' ? '<span class="badge bg-danger">super_admin</span>' :
                                 user.role === 'moderator' ? '<span class="badge bg-warning">moderator</span>' :
                                 '<span class="badge bg-secondary">' + htmlEscape(user.role) + '</span>';
                
                const row = `
                    <tr id="user-row-${user.id}">
                        <td>${user.id}</td>
                        <td>${htmlEscape(user.username)}</td>
                        <td>${htmlEscape(user.email)}</td>
                        <td>${roleBadge}</td>
                        <td>${statusBadge}</td>
                        <td>${user.story_count || 0}</td>
                        <td>${new Date(user.created_at).toLocaleDateString()}</td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <button class="btn btn-sm btn-info" onclick="viewUser(${user.id})" title="View Profile">
                                    <i class="fas fa-user"></i>
                                </button>
                                <button class="btn btn-sm btn-secondary" onclick="muteUser(${user.id})" title="Mute User">
                                    <i class="fas fa-microphone-slash"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="tempBanUser(${user.id})" title="Temporary Ban">
                                    <i class="fas fa-ban"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="permBanUser(${user.id})" title="Permanent Ban">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }
    } catch (e) {
        console.error(e);
    }
}, 300));

function viewUser(userId) {
    window.location.href = '<?= site_url('/admin/admin.php?page=profile') ?>&id=' + userId;
}

function viewStory(storyId) {
    window.location.href = '<?= site_url('/pages/story-view.php') ?>?id=' + storyId;
}

function muteUser(userId) {
    const days = prompt('Mute user for how many days? (0 = permanent mute)', '7');
    if (days === null) return;
    
    if (isNaN(days) || days < 0) {
        alert('Please enter a valid number');
        return;
    }
    
    moderateUser(userId, 'mute', days);
}

function tempBanUser(userId) {
    const days = prompt('Ban user for how many days?', '7');
    if (days === null) return;
    
    if (isNaN(days) || days < 1) {
        alert('Please enter a valid number of days (at least 1)');
        return;
    }
    
    moderateUser(userId, 'temp_ban', days);
}

function permBanUser(userId) {
    if (!confirm('Permanently ban this user? This action cannot be undone.')) return;
    
    moderateUser(userId, 'perm_ban', null);
}

function moderateUser(userId, action, days) {
    fetch('<?= site_url('/api/admin/moderate-user.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            user_id: userId,
            action: action,
            days: days
        })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            alert('User ' + action.replace('_', ' ') + ' successful');
            location.reload();
        } else {
            alert('Error: ' + d.error);
        }
    })
    .catch(e => alert('Error: ' + e.message));
}

function htmlEscape(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
</script>
