<?php
// pages/ads/chat.php - Ad proof upload and message chat interface

session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$ad_id = (int)($_GET['ad_id'] ?? 0);

if (!$ad_id) {
    header('Location: ' . site_url('/pages/ads/create.php'));
    exit;
}

// Fetch ad details (user can only view their own ads)
$stmt = $pdo->prepare("
    SELECT a.*, s.title as book_title, u.username
    FROM ads a
    JOIN stories s ON s.id = a.book_id
    JOIN users u ON u.id = a.user_id
    WHERE a.id = ? AND a.user_id = ?
");
$stmt->execute([$ad_id, $user_id]);
$ad = $stmt->fetch();

if (!$ad) {
    header('Location: ' . site_url('/pages/ads/create.php'));
    exit;
}

// Fetch messages for this ad
$stmt = $pdo->prepare("
    SELECT * FROM ad_messages
    WHERE ad_id = ?
    ORDER BY created_at ASC
");
$stmt->execute([$ad_id]);
$messages = $stmt->fetchAll();

// Format values
$views_formatted = number_format($ad['package_views']);
$amount_formatted = number_format($ad['amount'], 2);

// Determine status color/label
$status_color = '#fef3c7';
$status_text = '⏳ Pending Payment';
$status_text_color = '#92400e';

if ($ad['payment_status'] === 'paid') {
    $status_color = '#dbeafe';
    $status_text = '✅ Awaiting Admin Verification';
    $status_text_color = '#0c4a6e';
} elseif ($ad['payment_status'] === 'approved') {
    $status_color = '#ecfdf5';
    $status_text = '🎉 Approved & Boosted!';
    $status_text_color = '#065f46';
} elseif ($ad['payment_status'] === 'rejected') {
    $status_color = '#fee2e2';
    $status_text = '❌ Rejected';
    $status_text_color = '#7f1d1d';
}

$page_title = 'Ad Chat - ' . htmlspecialchars($ad['book_title']);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<main class="flex-1">
    <div style="max-width: 800px; margin: 0 auto; padding: 20px;">
        
        <h1>💬 Ad Chat & Proof Upload</h1>

        <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 30px;">
            
            <!-- Left Panel: Ad Info -->
            <div style="padding: 20px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;">
                <h3 style="margin-top: 0;">📊 Ad Information</h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <p style="margin: 0; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Book</p>
                        <p style="margin: 5px 0; font-weight: bold; font-size: 14px;"><?= htmlspecialchars($ad['book_title']) ?></p>
                    </div>
                    <div>
                        <p style="margin: 0; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Views</p>
                        <p style="margin: 5px 0; font-weight: bold; font-size: 14px;"><?= $views_formatted ?> 👁️</p>
                    </div>
                    <div>
                        <p style="margin: 0; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Amount</p>
                        <p style="margin: 5px 0; font-weight: bold; font-size: 14px;">$<?= $amount_formatted ?></p>
                    </div>
                    <div>
                        <p style="margin: 0; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Status</p>
                        <span style="background: <?= $status_color ?>; color: <?= $status_text_color ?>; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-block;">
                            <?= $status_text ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Messages Section -->
            <div style="padding: 20px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; max-height: 350px; overflow-y: auto;">
                <h3 style="margin-top: 0;">💬 Messages</h3>
                
                <?php if (empty($messages)): ?>
                    <p style="color: #9ca3af; text-align: center; padding: 20px 0;">No messages yet. Upload your payment proof below!</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach ($messages as $msg): ?>
                            <div style="padding: 12px; background: white; border-radius: 6px; border-left: 3px solid <?= $msg['sender'] === 'user' ? '#3b82f6' : '#10b981' ?>;">
                                <p style="margin: 0 0 5px 0; font-weight: bold; font-size: 12px; color: #6b7280;">
                                    <?= $msg['sender'] === 'user' ? '👤 You' : '🔧 Admin' ?>
                                </p>
                                <p style="margin: 0; font-size: 13px; color: #1f2937;">
                                    <?= htmlspecialchars($msg['message']) ?>
                                </p>
                                <?php if ($msg['image']): ?>
                                    <a href="<?= htmlspecialchars($msg['image']) ?>" target="_blank" style="display: inline-block; margin-top: 8px; font-size: 12px; color: #3b82f6;">
                                        📸 View Image
                                    </a>
                                <?php endif; ?>
                                <p style="margin: 5px 0 0 0; font-size: 11px; color: #9ca3af;">
                                    <?= date('M d, H:i', strtotime($msg['created_at'])) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Upload Proof Form -->
        <?php if ($ad['payment_status'] === 'pending'): ?>
            <div style="padding: 20px; background: white; border: 2px solid #3b82f6; border-radius: 8px;">
                <h3 style="margin-top: 0;">📤 Upload Payment Proof</h3>
                <p style="color: #6b7280; margin-bottom: 20px;">
                    After completing your Patreon payment, upload a screenshot of your confirmation email or Patreon receipt.
                </p>

                <form id="uploadProofForm">
                    <div style="margin-bottom: 15px;">
                        <label for="message" style="display: block; margin-bottom: 8px; font-weight: bold;">Message (Optional)</label>
                        <textarea id="message" name="message" placeholder="Add any additional notes..." 
                                  style="width: 100%; min-height: 60px; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; font-size: 14px;"></textarea>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label for="proof_image" style="display: block; margin-bottom: 8px; font-weight: bold;">Proof Image *</label>
                        <input type="file" id="proof_image" name="proof_image" accept="image/*" required 
                               style="display: block; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; width: 100%; margin-bottom: 10px;">
                        <div id="imagePreview" style="max-width: 200px; margin-top: 10px; display: none;">
                            <img id="previewImg" src="" alt="Preview" style="max-width: 100%; border-radius: 6px; border: 1px solid #d1d5db;">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        ✅ Upload & Submit
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div style="padding: 20px; background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 8px; text-align: center;">
                <p style="color: #6b7280; margin: 0;">
                    This ad is no longer accepting uploads. Status: <?= htmlspecialchars($ad['payment_status']) ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Back Link -->
        <div style="text-align: center; margin-top: 30px;">
            <a href="<?= site_url('/pages/dashboard.php') ?>" style="color: #3b82f6; text-decoration: none;">
                ← Back to Dashboard
            </a>
        </div>

    </div>
</main>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>

<script>
// Image preview
document.getElementById('proof_image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            document.getElementById('previewImg').src = event.target.result;
            document.getElementById('imagePreview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
});

// Upload form submission
document.getElementById('uploadProofForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData();
    formData.append('ad_id', <?= $ad_id ?>);
    formData.append('message', document.getElementById('message').value);
    formData.append('proof_image', document.getElementById('proof_image').files[0]);

    try {
        const response = await fetch('<?= site_url('/api/ads/upload-proof.php') ?>', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert('✅ Proof uploaded! Our admin will review it within 24 hours.');
            location.reload();
        } else {
            alert('❌ Error: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        alert('❌ Upload failed: ' + error.message);
    }
});
</script>
</body>
</html>
