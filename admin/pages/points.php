<?php // admin/pages/points.php ?>
<h4>💳 Points Management</h4>

<?php
$request_id = (int)($_GET['request'] ?? 0);
$user_id = $_SESSION['user_id'] ?? 0;

// Verify admin role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$admin = $stmt->fetch();
if (!$admin || !in_array($admin['role'] ?? '', ['admin', 'super_admin'])) {
    echo '<div class="alert alert-danger">Access denied</div>';
    exit;
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    $request_id_post = (int)$_POST['request_id'];
    $action = $_POST['action'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    if ($action === 'approve') {
        // Get request details
        $stmt = $pdo->prepare("SELECT * FROM point_purchase_requests WHERE id = ?");
        $stmt->execute([$request_id_post]);
        $req = $stmt->fetch();
        
        if ($req) {
            // Award points to user
            $pdo->prepare("
                UPDATE user_points 
                SET points = points + ?, lifetime_points = lifetime_points + ?, updated_at = NOW()
                WHERE user_id = ?
            ")->execute([$req['points'], $req['points'], $req['user_id']]);
            
            // Add transaction
            $pdo->prepare("
                INSERT INTO point_transactions (user_id, points, description, type)
                VALUES (?, ?, ?, 'earn')
            ")->execute([$req['user_id'], $req['points'], 'Purchased ' . $req['points'] . ' points for $' . $req['price']]);
            
            // Update request
            $pdo->prepare("
                UPDATE point_purchase_requests 
                SET status = 'approved', admin_notes = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$notes, $request_id_post]);
            
            // Notify user
            $pdo->prepare("
                INSERT INTO notifications (user_id, actor_id, type, message, url, created_at)
                VALUES (?, ?, 'points_approved', 'Your point purchase request has been approved!', ?, NOW())
            ")->execute([
                $req['user_id'],
                $user_id,
                '/scrollnovels/pages/points-dashboard.php'
            ]);
            
            echo '<div class="alert alert-success">✓ Points approved and awarded!</div>';
        }
    } elseif ($action === 'reject') {
        $pdo->prepare("
            UPDATE point_purchase_requests 
            SET status = 'rejected', admin_notes = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$notes, $request_id_post]);
        
        // Notify user
        $stmt = $pdo->prepare("SELECT user_id FROM point_purchase_requests WHERE id = ?");
        $stmt->execute([$request_id_post]);
        $req = $stmt->fetch();
        
        if ($req) {
            $pdo->prepare("
                INSERT INTO notifications (user_id, actor_id, type, message, url, created_at)
                VALUES (?, ?, 'points_rejected', 'Your point purchase request has been rejected.', ?, NOW())
            ")->execute([
                $req['user_id'],
                $user_id,
                '/scrollnovels/pages/points-dashboard.php'
            ]);
        }
        
        echo '<div class="alert alert-warning">✓ Request rejected</div>';
    }
}

// If viewing single request
if ($request_id) {
    $stmt = $pdo->prepare("
        SELECT ppr.*, u.username, u.email 
        FROM point_purchase_requests ppr
        JOIN users u ON ppr.user_id = u.id
        WHERE ppr.id = ?
    ");
    $stmt->execute([$request_id]);
    $req = $stmt->fetch();
    
    if (!$req) {
        echo '<div class="alert alert-warning">Request not found</div>';
    } else {
?>
<div class="mb-3">
    <a href="admin.php?page=points" class="btn btn-secondary">← Back to List</a>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h5>#<?= $req['id'] ?> - Point Purchase Request</h5>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <p><strong>User:</strong> <?= htmlspecialchars($req['username']) ?> (<?= htmlspecialchars($req['email']) ?>)</p>
                <p><strong>Points Requested:</strong> <?= number_format($req['points']) ?></p>
                <p><strong>Price:</strong> $<?= number_format($req['price'], 2) ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Status:</strong> <span class="badge bg-<?= $req['status'] === 'pending' ? 'warning' : ($req['status'] === 'approved' ? 'success' : 'danger') ?>"><?= ucfirst($req['status']) ?></span></p>
                <p><strong>Requested:</strong> <?= date('M d, Y H:i', strtotime($req['created_at'])) ?></p>
                <p><strong>Updated:</strong> <?= date('M d, Y H:i', strtotime($req['updated_at'])) ?></p>
            </div>
        </div>
        
        <?php if ($req['admin_notes']): ?>
        <div class="mb-3">
            <h6>Admin Notes:</h6>
            <div class="p-3 bg-light rounded">
                <?= nl2br(htmlspecialchars($req['admin_notes'])) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($req['status'] === 'pending'): ?>
        <hr>
        <div class="mb-3">
            <h6>Action:</h6>
            <form method="POST">
                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                
                <div class="mb-3">
                    <label class="form-label">Notes (Optional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="e.g., Payment received via Patreon"></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" name="action" value="approve" class="btn btn-success">✓ Approve & Award Points</button>
                    <button type="submit" name="action" value="reject" class="btn btn-danger" onclick="return confirm('Reject this request?')">✗ Reject</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
    }
} else {
    // List all pending requests
    $stmt = $pdo->prepare("
        SELECT ppr.*, u.username 
        FROM point_purchase_requests ppr
        JOIN users u ON ppr.user_id = u.id
        WHERE ppr.status IN ('pending', 'approved')
        ORDER BY ppr.status ASC, ppr.created_at DESC
        LIMIT 100
    ");
    $stmt->execute();
    $requests = $stmt->fetchAll();
?>

<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Points</th>
                <th>Price</th>
                <th>Status</th>
                <th>Requested</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td>#<?= $r['id'] ?></td>
                    <td><?= htmlspecialchars($r['username']) ?></td>
                    <td><?= number_format($r['points']) ?></td>
                    <td>$<?= number_format($r['price'], 2) ?></td>
                    <td>
                        <span class="badge bg-<?= $r['status'] === 'pending' ? 'warning' : 'success' ?>">
                            <?= ucfirst($r['status']) ?>
                        </span>
                    </td>
                    <td><?= date('M d', strtotime($r['created_at'])) ?></td>
                    <td>
                        <a href="<?= site_url('/admin/admin.php?page=points&request=' . $r['id']) ?>" class="btn btn-sm btn-info">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (empty($requests)): ?>
    <div class="alert alert-info">No pending or recent point purchase requests.</div>
<?php endif; ?>

<?php } ?>
