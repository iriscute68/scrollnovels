<?php // admin/pages/ads.php ?>
<h4>Ad Management</h4>
<?php
// Ensure payment_amount and expected_views columns exist
try {
    $pdo->exec("ALTER TABLE ads ADD COLUMN IF NOT EXISTS payment_amount DECIMAL(10, 2) DEFAULT 0");
    $pdo->exec("ALTER TABLE ads ADD COLUMN IF NOT EXISTS expected_views INT DEFAULT 0");
    $pdo->exec("ALTER TABLE ads ADD COLUMN IF NOT EXISTS current_views INT DEFAULT 0");
} catch (Exception $e) {
    // Columns may already exist
}

// Handle payment/views update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ad_id']) && isset($_POST['update_payment'])) {
    $ad_id = (int)$_POST['ad_id'];
    $payment_amount = floatval($_POST['payment_amount'] ?? 0);
    $expected_views = (int)($_POST['expected_views'] ?? 0);
    
    try {
        $stmt = $pdo->prepare("UPDATE ads SET payment_amount = ?, expected_views = ?, status = ? WHERE id = ?");
        $new_status = ($payment_amount > 0 && $expected_views > 0) ? 'active' : 'paused';
        $stmt->execute([$payment_amount, $expected_views, $new_status, $ad_id]);
        echo '<div class="alert alert-success">Ad campaign updated successfully!</div>';
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error updating ad: ' . $e->getMessage() . '</div>';
    }
}

// Handle ad deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ad'])) {
    $ad_id = (int)$_POST['ad_id'];
    try {
        $pdo->prepare("DELETE FROM ads WHERE id = ?")->execute([$ad_id]);
        echo '<div class="alert alert-success">Ad deleted successfully!</div>';
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error deleting ad</div>';
    }
}

$ads = $pdo->query("SELECT * FROM ads ORDER BY created_at DESC LIMIT 50")->fetchAll();
?>
<div class="table-responsive">
    <table class="table table-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>User</th>
                <th>Payment</th>
                <th>Views</th>
                <th>Progress</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ads as $a): 
                $current_views = (int)($a['current_views'] ?? 0);
                $expected_views = (int)($a['expected_views'] ?? 0);
                $views_percent = ($expected_views > 0) ? min(100, round(($current_views / $expected_views) * 100)) : 0;
                $payment = (float)($a['payment_amount'] ?? 0);
                $auto_ended = ($current_views >= $expected_views && $expected_views > 0) ? true : false;
                $display_status = $auto_ended ? 'ended' : $a['status'];
            ?>
                <tr>
                    <td><?= $a['id'] ?></td>
                    <td><?= htmlspecialchars(substr($a['title'] ?? $a['description'] ?? '', 0, 30)) ?></td>
                    <td><?= htmlspecialchars($a['user_id']) ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="ad_id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="update_payment" value="1">
                            <input type="number" name="payment_amount" value="<?= $payment ?>" step="0.01" min="0" style="width:70px" class="form-control form-control-sm" required>
                    </td>
                    <td>
                            <input type="number" name="expected_views" value="<?= $expected_views ?>" min="0" style="width:70px" class="form-control form-control-sm" required>
                            <button type="submit" class="btn btn-sm btn-primary mt-1">Save</button>
                        </form>
                    </td>
                    <td>
                        <div style="width:100px;">
                            <small><?= $current_views ?>/<?= $expected_views ?></small>
                            <div class="progress" style="height:8px;">
                                <div class="progress-bar <?= $views_percent >= 100 ? 'bg-danger' : 'bg-success' ?>" style="width:<?= $views_percent ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-<?= $display_status === 'active' ? 'success' : ($display_status === 'ended' ? 'danger' : 'secondary') ?>">
                            <?= $display_status === 'ended' ? 'ENDED' : $display_status ?>
                        </span>
                        <?php if ($auto_ended): ?>
                            <small class="d-block text-warning">Views exhausted</small>
                        <?php endif; ?>
                    </td>
                    <td><?= date('M d', strtotime($a['created_at'])) ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="ad_id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="delete_ad" value="1">
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this ad?')">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
