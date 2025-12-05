<?php
// admin/pages/points-purchase.php - Admin manage point purchases with chat

$request_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'] ?? 0;

// Verify admin role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$admin = $stmt->fetch();
if (!$admin || !in_array($admin['role'] ?? '', ['admin', 'superadmin', 'super_admin'])) {
    echo '<div class="alert alert-danger">Access denied</div>';
    exit;
}

try {
    // Get request details
    $stmt = $pdo->prepare("
        SELECT ppr.*, u.username, u.email 
        FROM point_purchase_requests ppr
        JOIN users u ON ppr.user_id = u.id
        WHERE ppr.id = ?
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();
    
    if (!$request) {
        echo '<div class="alert alert-danger">Request not found</div>';
        exit;
    }
    
    // Get messages
    $stmt = $pdo->prepare("
        SELECT ppm.*, u.username
        FROM point_purchase_messages ppm
        LEFT JOIN users u ON ppm.user_id = u.id
        WHERE ppm.request_id = ?
        ORDER BY ppm.created_at ASC
    ");
    $stmt->execute([$request_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get package info
    $packages = [
        1 => ['price' => 10, 'points' => 1100],
        2 => ['price' => 25, 'points' => 3000],
        3 => ['price' => 50, 'points' => 6500],
        4 => ['price' => 100, 'points' => 14000],
    ];
    $package = $packages[$request['package_id']] ?? ['price' => 0, 'points' => 0];
    
    // Handle approval
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        $notes = trim($_POST['notes'] ?? '');
        
        if ($action === 'approve') {
            // Award points
            $pdo->prepare("
                UPDATE user_points 
                SET points = points + ?, lifetime_points = lifetime_points + ?, updated_at = NOW()
                WHERE user_id = ?
            ")->execute([$request['points'], $request['points'], $request['user_id']]);
            
            // Add transaction
            $pdo->prepare("
                INSERT INTO point_transactions (user_id, points, description, type, created_at)
                VALUES (?, ?, ?, 'earn', NOW())
            ")->execute([$request['user_id'], $request['points'], 'Purchased ' . $request['points'] . ' points for $' . $request['price']]);
            
            // Update request
            $pdo->prepare("
                UPDATE point_purchase_requests 
                SET status = 'approved', admin_id = ?, admin_notes = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$user_id, $notes, $request_id]);
            
            // Notify user
            $pdo->prepare("
                INSERT INTO notifications (user_id, actor_id, type, message, url, created_at)
                VALUES (?, ?, 'points_approved', 'Your point purchase has been approved!', ?, NOW())
            ")->execute([
                $request['user_id'],
                $user_id,
                '/scrollnovels/pages/points-dashboard.php'
            ]);
            
            echo '<div class="alert alert-success">✓ Points approved and awarded!</div>';
            
            // Refresh request
            $stmt = $pdo->prepare("
                SELECT ppr.*, u.username, u.email 
                FROM point_purchase_requests ppr
                JOIN users u ON ppr.user_id = u.id
                WHERE ppr.id = ?
            ");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();
        } elseif ($action === 'reject') {
            $pdo->prepare("
                UPDATE point_purchase_requests 
                SET status = 'rejected', admin_id = ?, admin_notes = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$user_id, $notes, $request_id]);
            
            $pdo->prepare("
                INSERT INTO notifications (user_id, actor_id, type, message, url, created_at)
                VALUES (?, ?, 'points_rejected', 'Your point purchase request was rejected.', ?, NOW())
            ")->execute([
                $request['user_id'],
                $user_id,
                '/scrollnovels/pages/points-dashboard.php'
            ]);
            
            echo '<div class="alert alert-warning">✓ Request rejected</div>';
            
            // Refresh request
            $stmt = $pdo->prepare("
                SELECT ppr.*, u.username, u.email 
                FROM point_purchase_requests ppr
                JOIN users u ON ppr.user_id = u.id
                WHERE ppr.id = ?
            ");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();
        }
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="container-fluid">
    <h4>💳 Point Purchase Request #<?= $request_id ?></h4>
    
    <div class="row mt-4">
        <!-- Request Info -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Request Details</h6>
                    <p><strong>User:</strong> <?= htmlspecialchars($request['username']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($request['email']) ?></p>
                    <p><strong>Points:</strong> <?= number_format($request['points']) ?></p>
                    <p><strong>Price:</strong> $<?= number_format($request['price'], 2) ?></p>
                    <p>
                        <strong>Status:</strong>
                        <span class="badge badge-<?= $request['status'] === 'approved' ? 'success' : ($request['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                            <?= ucfirst($request['status']) ?>
                        </span>
                    </p>
                    <p><strong>Created:</strong> <?= date('M d, Y · H:i', strtotime($request['created_at'])) ?></p>
                </div>
            </div>
        </div>

        <!-- Chat Messages -->
        <div class="col-md-9">
            <div class="card">
                <div class="card-body" style="height: 400px; overflow-y: auto;">
                    <h6 class="card-title">Conversation</h6>
                    <div id="messagesContainer" class="space-y-3">
                        <?php if (empty($messages)): ?>
                        <p class="text-muted text-center py-4">No messages yet</p>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                            <div class="mb-3">
                                <strong><?= htmlspecialchars($msg['username'] ?? 'Unknown') ?></strong>
                                <small class="text-muted">• <?= date('M d, H:i', strtotime($msg['created_at'])) ?></small>
                                <p class="mt-1 mb-0"><?= htmlspecialchars($msg['message']) ?></p>
                                <?php if (!empty($msg['image_url'])): ?>
                                <img src="<?= htmlspecialchars($msg['image_url']) ?>" alt="Proof" style="max-width: 200px; margin-top: 8px; border-radius: 4px;">
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Admin Reply Area -->
                <?php if ($request['status'] !== 'approved' && $request['status'] !== 'rejected'): ?>
                <div class="card-footer">
                    <form method="POST" id="replyForm" class="mb-3">
                        <textarea name="admin_message" class="form-control form-control-sm mb-2" placeholder="Reply to user..." rows="2"></textarea>
                        <button type="submit" class="btn btn-sm btn-primary">Send Reply</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <?php if ($request['status'] !== 'approved' && $request['status'] !== 'rejected'): ?>
            <div class="mt-4">
                <form method="POST" id="actionForm" class="card p-3 bg-light">
                    <div class="form-group">
                        <label for="notes">Admin Notes (Optional)</label>
                        <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Add your notes..."></textarea>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="action" value="approve" class="btn btn-success">✓ Approve & Award Points</button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger">✗ Reject Request</button>
                        <a href="admin.php?page=points" class="btn btn-secondary">← Back</a>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="mt-4">
                <a href="admin.php?page=points" class="btn btn-secondary">← Back</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Auto scroll to bottom
const container = document.getElementById('messagesContainer');
if (container) {
    container.scrollTop = container.scrollHeight;
}

// Handle admin message sending
document.getElementById('replyForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const message = document.querySelector('[name="admin_message"]').value.trim();
    
    if (!message) {
        alert('Please enter a message');
        return;
    }
    
    const formData = new FormData();
    formData.append('request_id', <?= $request_id ?>);
    formData.append('message', message);
    
    fetch('<?= site_url('/api/send-admin-points-message.php') ?>', {
        method: 'POST',
        body: formData
    }).then(r => r.json()).then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to send'));
        }
    }).catch(e => alert('Error: ' + e.message));
});
</script>
