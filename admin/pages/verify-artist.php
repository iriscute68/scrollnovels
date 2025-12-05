<?php
// admin/pages/verify-artist.php - Manage artist verification requests

$pending = $pdo->query("
    SELECT vr.*, u.username, u.email, u.profile_image, u.role
    FROM verification_requests vr
    JOIN users u ON vr.user_id = u.id
    WHERE u.role = 'author' AND vr.status = 'pending'
    ORDER BY vr.created_at DESC
")->fetchAll();

$approved = $pdo->query("
    SELECT vr.*, u.username, u.email, u.profile_image, u.bio, u.role
    FROM verification_requests vr
    JOIN users u ON vr.user_id = u.id
    WHERE u.role = 'author' AND vr.status = 'approved'
    ORDER BY vr.created_at DESC
    LIMIT 20
")->fetchAll();
?>

<h4>🎨 Artist Verification</h4>

<!-- Pending Applications -->
<div class="card mb-4">
    <div class="card-header bg-warning">
        <h5 class="mb-0">📋 Pending Applications (<?= count($pending) ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($pending)): ?>
            <div class="alert alert-info">No pending artist applications</div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($pending as $req): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card border-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6><?= htmlspecialchars($req['username']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($req['email']) ?></small>
                                    </div>
                                    <img src="<?= $req['profile_image'] ? site_url($req['profile_image']) : 'https://via.placeholder.com/50' ?>" alt="Profile" class="rounded" width="50" height="50">
                                </div>
                                
                                <div class="mb-3">
                                    <h6>Why they should be verified:</h6>
                                    <p class="text-sm"><?= nl2br(htmlspecialchars(substr($req['description'], 0, 200))) ?>...</p>
                                </div>

                                <?php if ($req['proof_images']): ?>
                                    <div class="mb-3">
                                        <h6>Portfolio/Proof:</h6>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <?php 
                                            $images = json_decode($req['proof_images'], true) ?? [];
                                            foreach ($images as $img):
                                            ?>
                                                <img src="<?= site_url($img) ?>" alt="Proof" width="80" height="80" class="rounded border">
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <small class="text-muted d-block mb-3">Submitted: <?= date('M d, Y', strtotime($req['created_at'])) ?></small>

                                <div class="btn-group w-100" role="group">
                                    <button onclick="approveArtist(<?= $req['user_id'] ?>, '<?= htmlspecialchars($req['username']) ?>')" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button onclick="rejectArtist(<?= $req['id'] ?>)" class="btn btn-sm btn-danger">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#messageModal" onclick="setReplyUser(<?= $req['user_id'] ?>, '<?= htmlspecialchars($req['username']) ?>')">
                                        <i class="fas fa-comment"></i> Message
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Approved Artists -->
<div class="card">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">✅ Approved Artists (<?= count($approved) ?>)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Artist</th>
                        <th>Email</th>
                        <th>Specialties</th>
                        <th>Approved</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approved as $artist): ?>
                        <tr>
                            <td><?= htmlspecialchars($artist['username']) ?></td>
                            <td><?= htmlspecialchars($artist['email']) ?></td>
                            <td><?= htmlspecialchars($artist['specialties'] ?? 'N/A') ?></td>
                            <td><?= date('M d, Y', strtotime($artist['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger" onclick="revokeArtist(<?= $artist['user_id'] ?>)">
                                    <i class="fas fa-trash"></i> Revoke
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Message Modal -->
<div class="modal fade" id="messageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Message to <span id="replyUsername"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Message</label>
                    <textarea id="messageContent" class="form-control" rows="4" placeholder="Enter your message..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="sendMessage()">Send</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentReplyUserId = null;

function setReplyUser(userId, username) {
    currentReplyUserId = userId;
    document.getElementById('replyUsername').textContent = username;
}

function approveArtist(userId, username) {
    if (!confirm(`Approve ${username} as an artist?`)) return;
    
    fetch('<?= site_url('/api/admin/approve-artist.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✓ Artist approved!');
            location.reload();
        } else {
            alert('✗ ' + (data.error || 'Failed to approve'));
        }
    })
    .catch(e => alert('Error: ' + e.message));
}

function rejectArtist(requestId) {
    if (!confirm('Reject this artist application?')) return;
    
    fetch('<?= site_url('/api/admin/reject-verification.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ request_id: requestId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✓ Application rejected');
            location.reload();
        } else {
            alert('✗ ' + (data.error || 'Failed to reject'));
        }
    })
    .catch(e => alert('Error: ' + e.message));
}

function revokeArtist(userId) {
    if (!confirm('Revoke artist status from this user?')) return;
    
    fetch('<?= site_url('/api/admin/revoke-artist.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✓ Artist status revoked');
            location.reload();
        } else {
            alert('✗ ' + (data.error || 'Failed to revoke'));
        }
    })
    .catch(e => alert('Error: ' + e.message));
}

function sendMessage() {
    const content = document.getElementById('messageContent').value.trim();
    if (!content) {
        alert('Please enter a message');
        return;
    }
    
    fetch('<?= site_url('/api/send-message.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            recipient_id: currentReplyUserId,
            content: content
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Message sent!');
            document.getElementById('messageContent').value = '';
            bootstrap.Modal.getInstance(document.getElementById('messageModal')).hide();
        } else {
            alert('Error: ' + (data.error || 'Failed to send'));
        }
    })
    .catch(e => alert('Error: ' + e.message));
}
</script>
