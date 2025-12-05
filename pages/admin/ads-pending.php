<?php
// pages/admin/ads-pending.php - Admin dashboard for verifying ad payments

session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

requireAdmin();

$page_title = 'Pending Ads - Admin Dashboard';

// Fetch all pending ads with details
$stmt = $pdo->prepare("
    SELECT a.*, s.title as book_title, u.username, u.id as user_id
    FROM ads a
    JOIN stories s ON s.id = a.book_id
    JOIN users u ON u.id = a.user_id
    WHERE a.payment_status IN ('pending', 'paid')
    ORDER BY a.updated_at DESC
");
$stmt->execute();
$pending_ads = $stmt->fetchAll();

// Get count by status
$stmt = $pdo->prepare("SELECT payment_status, COUNT(*) as count FROM ads GROUP BY payment_status");
$stmt->execute();
$status_counts = [];
foreach ($stmt->fetchAll() as $row) {
    $status_counts[$row['payment_status']] = $row['count'];
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<main class="flex-1">
    <div style="max-width: 1200px; margin: 0 auto; padding: 20px;">
        
        <h1>📋 Pending Ad Verification</h1>
        <p style="color: #6b7280; margin-bottom: 20px;">
            Review and approve ad payment proofs below.
        </p>

        <!-- Status Overview -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 30px;">
            <div style="padding: 15px; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; text-align: center;">
                <p style="margin: 0; font-size: 12px; color: #92400e; text-transform: uppercase;">Pending Payment</p>
                <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #92400e;">
                    <?= $status_counts['pending'] ?? 0 ?>
                </p>
            </div>
            <div style="padding: 15px; background: #dbeafe; border: 1px solid #7dd3fc; border-radius: 8px; text-align: center;">
                <p style="margin: 0; font-size: 12px; color: #0c4a6e; text-transform: uppercase;">Awaiting Verification</p>
                <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #0c4a6e;">
                    <?= $status_counts['paid'] ?? 0 ?>
                </p>
            </div>
            <div style="padding: 15px; background: #ecfdf5; border: 1px solid #6ee7b7; border-radius: 8px; text-align: center;">
                <p style="margin: 0; font-size: 12px; color: #065f46; text-transform: uppercase;">Approved</p>
                <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #065f46;">
                    <?= $status_counts['approved'] ?? 0 ?>
                </p>
            </div>
        </div>

        <?php if (empty($pending_ads)): ?>
            <div style="padding: 40px; text-align: center; background: #f3f4f6; border-radius: 8px;">
                <p style="color: #6b7280; margin: 0;">✅ No pending ads to review!</p>
            </div>
        <?php else: ?>
            <div style="display: grid; gap: 20px;">
                <?php foreach ($pending_ads as $ad): ?>
                    <div class="admin-ad-card" style="padding: 20px; background: white; border: 1px solid #e5e7eb; border-radius: 8px; position: relative;">
                        
                        <!-- Ad Info Header -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 20px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #f3f4f6;">
                            <div>
                                <p style="margin: 0; color: #6b7280; font-size: 12px; text-transform: uppercase;">Book</p>
                                <h3 style="margin: 5px 0; font-size: 16px; color: #1f2937;">
                                    <?= htmlspecialchars($ad['book_title']) ?>
                                </h3>
                                <p style="margin: 5px 0 0 0; color: #9ca3af; font-size: 13px;">
                                    by <strong><?= htmlspecialchars($ad['username']) ?></strong>
                                </p>
                            </div>
                            <div>
                                <p style="margin: 0; color: #6b7280; font-size: 12px; text-transform: uppercase;">Payment Details</p>
                                <p style="margin: 5px 0; font-weight: bold; font-size: 14px;">
                                    $<?= number_format($ad['amount'], 2) ?> for <?= number_format($ad['package_views']) ?> 👁️
                                </p>
                            </div>
                            <div style="text-align: right;">
                                <span style="background: <?= $ad['payment_status'] === 'paid' ? '#dbeafe' : '#fef3c7' ?>; color: <?= $ad['payment_status'] === 'paid' ? '#0c4a6e' : '#92400e' ?>; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-block;">
                                    <?= ucfirst($ad['payment_status']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Proof Image -->
                        <div style="margin-bottom: 20px;">
                            <?php if ($ad['proof_image']): ?>
                                <p style="margin: 0 0 10px 0; color: #6b7280; font-size: 12px; text-transform: uppercase;">Proof Image</p>
                                <a href="<?= htmlspecialchars($ad['proof_image']) ?>" target="_blank">
                                    <img src="<?= htmlspecialchars($ad['proof_image']) ?>" alt="Proof" style="max-width: 300px; max-height: 300px; border-radius: 6px; border: 1px solid #d1d5db; cursor: pointer;">
                                </a>
                                <p style="margin: 8px 0 0 0; font-size: 12px; color: #6b7280;">
                                    <a href="<?= htmlspecialchars($ad['proof_image']) ?>" target="_blank" style="color: #3b82f6;">📸 View Full Size</a>
                                </p>
                            <?php else: ?>
                                <div style="padding: 20px; background: #f3f4f6; border-radius: 6px; color: #6b7280; text-align: center;">
                                    No proof image uploaded yet
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Messages -->
                        <div style="margin-bottom: 20px; padding: 12px; background: #f9fafb; border-radius: 6px; max-height: 150px; overflow-y: auto;">
                            <p style="margin: 0 0 10px 0; color: #6b7280; font-size: 12px; text-transform: uppercase;">Messages</p>
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <?php
                                $stmt_msg = $pdo->prepare("SELECT * FROM ad_messages WHERE ad_id = ? ORDER BY created_at DESC LIMIT 5");
                                $stmt_msg->execute([$ad['id']]);
                                $messages = $stmt_msg->fetchAll();
                                if (empty($messages)):
                                ?>
                                    <p style="color: #9ca3af; font-size: 13px; margin: 0;">No messages yet</p>
                                <?php else: ?>
                                    <?php foreach (array_reverse($messages) as $msg): ?>
                                        <div style="font-size: 12px; padding: 8px; background: white; border-radius: 4px;">
                                            <strong style="color: <?= $msg['sender'] === 'user' ? '#3b82f6' : '#10b981' ?>;">
                                                <?= $msg['sender'] === 'user' ? '👤' : '🔧' ?> <?= ucfirst($msg['sender']) ?>:
                                            </strong>
                                            <?= htmlspecialchars($msg['message']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <button class="approve-btn btn btn-success" data-ad-id="<?= $ad['id'] ?>" style="flex: 1; padding: 10px;">
                                ✅ Approve & Boost
                            </button>
                            <button class="reject-btn btn btn-danger" data-ad-id="<?= $ad['id'] ?>" style="flex: 1; padding: 10px;">
                                ❌ Reject
                            </button>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Approve Modal -->
        <div id="approveModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
            <div style="background: white; border-radius: 8px; padding: 30px; max-width: 500px; width: 90%;">
                <h2 style="margin-top: 0;">✅ Approve Ad?</h2>
                <p style="color: #6b7280;">
                    This will boost the book and notify the user. The payment will be marked as verified.
                </p>
                <div style="margin-bottom: 20px;">
                    <label for="adminNote" style="display: block; margin-bottom: 8px; font-weight: bold;">Admin Note (Optional)</label>
                    <textarea id="adminNote" placeholder="E.g. 'Payment verified on Patreon'" style="width: 100%; min-height: 60px; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit;"></textarea>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button id="confirmApprove" class="btn btn-success" style="flex: 1;">Approve</button>
                    <button id="cancelApprove" class="btn btn-secondary" style="flex: 1;">Cancel</button>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div id="rejectModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
            <div style="background: white; border-radius: 8px; padding: 30px; max-width: 500px; width: 90%;">
                <h2 style="margin-top: 0;">❌ Reject Ad?</h2>
                <p style="color: #6b7280;">
                    Provide a reason for rejection. The user will be notified.
                </p>
                <div style="margin-bottom: 20px;">
                    <label for="rejectReason" style="display: block; margin-bottom: 8px; font-weight: bold;">Reason *</label>
                    <textarea id="rejectReason" placeholder="E.g. 'Invalid payment proof' or 'Proof image too blurry'" style="width: 100%; min-height: 60px; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit;" required></textarea>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button id="confirmReject" class="btn btn-danger" style="flex: 1;">Reject</button>
                    <button id="cancelReject" class="btn btn-secondary" style="flex: 1;">Cancel</button>
                </div>
            </div>
        </div>

    </div>
</main>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>

<style>
.btn {
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
    font-size: 14px;
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
}
</style>

<script>
let currentAdId = null;

// Approve button handlers
document.querySelectorAll('.approve-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        currentAdId = this.dataset.adId;
        document.getElementById('adminNote').value = '';
        document.getElementById('approveModal').style.display = 'flex';
    });
});

document.getElementById('confirmApprove').addEventListener('click', async function() {
    const adminNote = document.getElementById('adminNote').value;
    try {
        const response = await fetch('<?= site_url('/api/ads/admin-approve.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ad_id: currentAdId, admin_note: adminNote })
        });
        const data = await response.json();
        if (data.success) {
            alert('✅ Ad approved! User notified and book boosted.');
            location.reload();
        } else {
            alert('❌ Error: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        alert('❌ Error: ' + error.message);
    }
});

document.getElementById('cancelApprove').addEventListener('click', function() {
    document.getElementById('approveModal').style.display = 'none';
});

// Reject button handlers
document.querySelectorAll('.reject-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        currentAdId = this.dataset.adId;
        document.getElementById('rejectReason').value = '';
        document.getElementById('rejectModal').style.display = 'flex';
    });
});

document.getElementById('confirmReject').addEventListener('click', async function() {
    const reason = document.getElementById('rejectReason').value;
    if (!reason.trim()) {
        alert('Please provide a reason for rejection');
        return;
    }
    try {
        const response = await fetch('<?= site_url('/api/ads/admin-reject.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ad_id: currentAdId, reason: reason })
        });
        const data = await response.json();
        if (data.success) {
            alert('✅ Ad rejected. User notified.');
            location.reload();
        } else {
            alert('❌ Error: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        alert('❌ Error: ' + error.message);
    }
});

document.getElementById('cancelReject').addEventListener('click', function() {
    document.getElementById('rejectModal').style.display = 'none';
});
</script>
</body>
</html>
