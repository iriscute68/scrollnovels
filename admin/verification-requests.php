<?php
// admin/verification-requests.php - Manage artist/editor verification requests

session_start();
require_once dirname(__FILE__) . '/../config.php';

// Check if user is moderator or admin
$userRoles = $_SESSION['roles'] ?? [];
if (is_string($userRoles)) {
    $userRoles = json_decode($userRoles, true) ?: [];
}

if (!isset($_SESSION['user_id']) || (!in_array('admin', $userRoles) && !in_array('mod', $userRoles))) {
    header("Location: login.php");
    exit;
}

// Handle approval/rejection
if ($_POST['action'] ?? '' === 'update_request') {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $notes = $_POST['notes'] ?? '';

    if (in_array($status, ['approved', 'rejected']) && $request_id > 0) {
        $stmt = $pdo->prepare("
            UPDATE verification_requests 
            SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $notes, $_SESSION['user_id'], $request_id]);

        // If approved, update user's verification status
        if ($status === 'approved') {
            $stmt = $pdo->prepare("SELECT user_id, verification_type FROM verification_requests WHERE id = ?");
            $stmt->execute([$request_id]);
            $req = $stmt->fetch();

            if ($req) {
                $column = ($req['verification_type'] === 'artist') ? 'is_verified_artist' : 'is_verified_editor';
                $stmt = $pdo->prepare("UPDATE users SET $column = 1 WHERE id = ?");
                $stmt->execute([$req['user_id']]);
            }
        }

        // Add to history
        $stmt = $pdo->prepare("
            INSERT INTO verification_requests_history 
            (verification_request_id, action, new_status, moderator_id, notes)
            VALUES (?, 'reviewed', ?, ?, ?)
        ");
        $stmt->execute([$request_id, $status, $_SESSION['user_id'], $notes]);
    }
}

// Get all verification requests with filters
$filter = $_GET['filter'] ?? 'pending';
$type_filter = $_GET['type'] ?? '';

$query = "SELECT vr.*, u.username, u.email, u.profile_image FROM verification_requests vr 
          LEFT JOIN users u ON vr.user_id = u.id WHERE 1=1";
$params = [];

if ($filter === 'pending') {
    $query .= " AND vr.status = 'pending'";
} elseif ($filter === 'approved') {
    $query .= " AND vr.status = 'approved'";
} elseif ($filter === 'rejected') {
    $query .= " AND vr.status = 'rejected'";
}

if (!empty($type_filter)) {
    $query .= " AND vr.verification_type = ?";
    $params[] = $type_filter;
}

$query .= " ORDER BY vr.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Requests - Admin Dashboard</title>
    <link rel="stylesheet" href="<?= SITE_URL; ?>/assets/css/style.css">
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .admin-header h1 {
            font-size: 2rem;
            margin: 0;
            color: #1a1a1a;
        }

        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            color: #666;
        }

        .filter-btn.active {
            background: #ff6b6b;
            color: white;
            border-color: #ff6b6b;
        }

        .filter-btn:hover {
            border-color: #ff6b6b;
        }

        .requests-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }

        .requests-table th {
            background: #f5f5f5;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #1a1a1a;
            border-bottom: 2px solid #e0e0e0;
        }

        .requests-table td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .requests-table tr:hover {
            background: #f9f9f9;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background: #e0e0e0;
        }

        .user-details h4 {
            margin: 0;
            font-size: 0.95rem;
            color: #1a1a1a;
        }

        .user-details p {
            margin: 3px 0 0 0;
            font-size: 0.85rem;
            color: #666;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-pending {
            background: rgba(255, 165, 0, 0.2);
            color: #e67e22;
        }

        .badge-approved {
            background: rgba(40, 167, 69, 0.2);
            color: #27ae60;
        }

        .badge-rejected {
            background: rgba(220, 53, 69, 0.2);
            color: #c0392b;
        }

        .badge-artist {
            background: rgba(155, 89, 182, 0.2);
            color: #8e44ad;
        }

        .badge-editor {
            background: rgba(52, 152, 219, 0.2);
            color: #2980b9;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .btn-view {
            background: #3498db;
            color: white;
        }

        .btn-view:hover {
            background: #2980b9;
        }

        .btn-approve {
            background: #27ae60;
            color: white;
        }

        .btn-approve:hover {
            background: #229954;
        }

        .btn-reject {
            background: #e74c3c;
            color: white;
        }

        .btn-reject:hover {
            background: #c0392b;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #1a1a1a;
        }

        .proof-images {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .proof-image {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 4px;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid #e0e0e0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1a1a1a;
        }

        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-height: 100px;
            font-family: inherit;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .modal-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            flex: 1;
        }

        .modal-btn.approve {
            background: #27ae60;
            color: white;
        }

        .modal-btn.reject {
            background: #e74c3c;
            color: white;
        }

        .modal-btn.cancel {
            background: #bdc3c7;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <?php include 'inc/admin-header.php'; ?>

    <div class="admin-container">
        <div class="admin-header">
            <h1>⭐ Verification Requests</h1>
        </div>

        <div class="filters">
            <a href="?filter=pending" class="filter-btn <?= $filter === 'pending' ? 'active' : '' ?>">
                Pending (<?= count(array_filter($requests, fn($r) => $r['status'] === 'pending')); ?>)
            </a>
            <a href="?filter=approved" class="filter-btn <?= $filter === 'approved' ? 'active' : '' ?>">
                Approved (<?= count(array_filter($requests, fn($r) => $r['status'] === 'approved')); ?>)
            </a>
            <a href="?filter=rejected" class="filter-btn <?= $filter === 'rejected' ? 'active' : '' ?>">
                Rejected (<?= count(array_filter($requests, fn($r) => $r['status'] === 'rejected')); ?>)
            </a>
            <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                All
            </a>
        </div>

        <?php if (count($requests) > 0): ?>
            <table class="requests-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $req): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <?php if ($req['avatar']): ?>
                                        <img src="<?= htmlspecialchars($req['avatar']); ?>" alt="Avatar" class="user-avatar">
                                    <?php else: ?>
                                        <div class="user-avatar" style="background: #ccc;"></div>
                                    <?php endif; ?>
                                    <div class="user-details">
                                        <h4><?= htmlspecialchars($req['username']); ?></h4>
                                        <p><?= htmlspecialchars($req['email']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?= $req['verification_type'] === 'artist' ? 'artist' : 'editor'; ?>">
                                    <?= ucfirst($req['verification_type']); ?>
                                </span>
                            </td>
                            <td>
                                <p style="max-width: 300px; color: #666; font-size: 0.9rem;">
                                    <?= htmlspecialchars(substr($req['description'], 0, 100)); ?>...
                                </p>
                            </td>
                            <td>
                                <small style="color: #999;">
                                    <?= date('M j, Y', strtotime($req['created_at'])); ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge badge-<?= $req['status']; ?>">
                                    <?= ucfirst($req['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn btn-view" onclick="openModal(<?= $req['id']; ?>)">
                                        Review
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <p>No verification requests found.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Review Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" id="modalTitle">Review Request</div>

            <div id="requestDetails"></div>

            <div class="form-group">
                <label for="adminNotes">Admin Notes</label>
                <textarea id="adminNotes" placeholder="Your feedback or notes about this request..."></textarea>
            </div>

            <div class="modal-buttons">
                <button class="modal-btn approve" onclick="submitReview('approved')">✓ Approve</button>
                <button class="modal-btn reject" onclick="submitReview('rejected')">✗ Reject</button>
                <button class="modal-btn cancel" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        let currentRequestId = null;

        async function openModal(requestId) {
            currentRequestId = requestId;
            const modal = document.getElementById('reviewModal');

            // Fetch request details via AJAX
            try {
                const response = await fetch('<?= SITE_URL; ?>/api/admin-verification.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=get_request&id=' + requestId
                });
                const data = await response.json();

                if (data.success) {
                    const req = data.request;
                    document.getElementById('modalTitle').textContent = `Review ${req.verification_type.toUpperCase()} Verification - ${req.username}`;

                    let html = `
                        <div style="margin-bottom: 20px;">
                            <p><strong>Email:</strong> ${req.email}</p>
                            <p><strong>Type:</strong> <span class="badge badge-${req.verification_type}">${req.verification_type.toUpperCase()}</span></p>
                            <p><strong>Current Status:</strong> <span class="badge badge-${req.status}">${req.status.toUpperCase()}</span></p>
                            <p><strong>Submitted:</strong> ${new Date(req.created_at).toLocaleDateString()}</p>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <h4>Description</h4>
                            <p style="color: #666; line-height: 1.6;">${req.description}</p>
                        </div>
                    `;

                    if (req.proof_images && req.proof_images.length > 0) {
                        html += `
                            <div style="margin-bottom: 20px;">
                                <h4>Proof Images</h4>
                                <div class="proof-images">
                        `;
                        req.proof_images.forEach(img => {
                            html += `<img src="${'<?= SITE_URL; ?>/uploads/' + img}" class="proof-image" onclick="openImage(this.src)">`;
                        });
                        html += `
                                </div>
                            </div>
                        `;
                    }

                    if (req.admin_notes) {
                        html += `
                            <div style="margin-bottom: 20px; background: #f5f5f5; padding: 15px; border-radius: 4px;">
                                <h4 style="margin-top: 0;">Previous Notes</h4>
                                <p style="color: #666; margin: 0;">${req.admin_notes}</p>
                            </div>
                        `;
                    }

                    document.getElementById('requestDetails').innerHTML = html;
                }
            } catch (err) {
                console.error('Error loading request:', err);
                alert('Error loading request details');
            }

            modal.classList.add('show');
        }

        function closeModal() {
            const modal = document.getElementById('reviewModal');
            modal.classList.remove('show');
        }

        async function submitReview(status) {
            if (!currentRequestId) return;

            const notes = document.getElementById('adminNotes').value;

            try {
                const formData = new FormData();
                formData.append('action', 'update_request');
                formData.append('request_id', currentRequestId);
                formData.append('status', status);
                formData.append('notes', notes);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                if (response.ok) {
                    alert(`Request ${status} successfully!`);
                    location.reload();
                } else {
                    alert('Error updating request');
                }
            } catch (err) {
                console.error('Error:', err);
                alert('Error updating request');
            }
        }

        function openImage(src) {
            window.open(src, '_blank');
        }

        // Close modal on background click
        document.getElementById('reviewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
