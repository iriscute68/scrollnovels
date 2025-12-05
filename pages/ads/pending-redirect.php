<?php
// pages/ads/pending-redirect.php - Post-creation page with Patreon redirect instructions

session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$page_title = 'Ad Created - Scroll Novels';

// Get the ad ID from POST or GET
$ad_id = isset($_POST['ad_id']) ? (int)$_POST['ad_id'] : (isset($_GET['ad_id']) ? (int)$_GET['ad_id'] : 0);

if (!$ad_id) {
    // If no ad_id, create one from POST data
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $book_id = (int)($_POST['book_id'] ?? 0);
        $package = $_POST['package'] ?? '';

        if (!$book_id || !$package) {
            header('Location: ' . site_url('/pages/ads/create.php'));
            exit;
        }

        // Load config to get package details
        $config = require dirname(__DIR__, 2) . '/config/ads.php';
        if (!isset($config['packages'][$package])) {
            header('Location: ' . site_url('/pages/ads/create.php'));
            exit;
        }

        $packageData = $config['packages'][$package];

        // Verify book ownership
        $stmt = $pdo->prepare("SELECT id FROM stories WHERE id = ? AND author_id = ?");
        $stmt->execute([$book_id, $user_id]);
        if (!$stmt->fetch()) {
            header('Location: ' . site_url('/pages/ads/create.php'));
            exit;
        }

        // Create ad record
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ads (user_id, book_id, package_views, amount, payment_status, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())
            ");
            $stmt->execute([$user_id, $book_id, $packageData['views'], $packageData['amount']]);
            $ad_id = $pdo->lastInsertId();
        } catch (Exception $e) {
            header('Location: ' . site_url('/pages/ads/create.php'));
            exit;
        }
    } else {
        header('Location: ' . site_url('/pages/ads/create.php'));
        exit;
    }
}

// Fetch ad details
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

// Format numbers
$views_formatted = number_format($ad['package_views']);
$amount_formatted = number_format($ad['amount'], 2);

$config = require dirname(__DIR__, 2) . '/config/ads.php';
$patreon_url = $config['patreon_url'] . '?ad_id=' . $ad_id . '&book_id=' . $ad['book_id'];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<main class="flex-1">
    <div style="max-width: 600px; margin: 40px auto; padding: 20px;">
        
        <!-- Success Message -->
        <div style="padding: 20px; background: #ecfdf5; border: 1px solid #6ee7b7; border-radius: 8px; margin-bottom: 30px; text-align: center;">
            <h2 style="color: #059669; margin: 0 0 10px 0;">✅ Ad Created Successfully!</h2>
            <p style="color: #047857; margin: 0;">Your ad is ready for payment. Follow the steps below to complete the process.</p>
        </div>

        <!-- Ad Details -->
        <div style="padding: 20px; background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 8px; margin-bottom: 30px;">
            <h3 style="margin-top: 0;">📊 Ad Details</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px; color: #6b7280;">Book:</td>
                    <td style="padding: 8px; font-weight: bold;"><?= htmlspecialchars($ad['book_title']) ?></td>
                </tr>
                <tr style="background: white;">
                    <td style="padding: 8px; color: #6b7280;">Views:</td>
                    <td style="padding: 8px; font-weight: bold;"><?= $views_formatted ?> 👁️</td>
                </tr>
                <tr>
                    <td style="padding: 8px; color: #6b7280;">Amount:</td>
                    <td style="padding: 8px; font-weight: bold;">$<?= $amount_formatted ?></td>
                </tr>
                <tr style="background: white;">
                    <td style="padding: 8px; color: #6b7280;">Status:</td>
                    <td style="padding: 8px;"><span class="badge" style="background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px;">⏳ Pending Payment</span></td>
                </tr>
            </table>
        </div>

        <!-- Steps -->
        <div style="margin-bottom: 30px;">
            <h3>📋 Next Steps</h3>
            
            <div style="margin-bottom: 15px; padding: 15px; background: white; border: 1px solid #e5e7eb; border-radius: 8px;">
                <div style="font-weight: bold; color: #1f2937; margin-bottom: 5px;">1️⃣ Visit Patreon & Support</div>
                <p style="margin: 5px 0; color: #6b7280; font-size: 14px;">
                    Click the button below to complete your support payment on Patreon.
                </p>
            </div>

            <div style="margin-bottom: 15px; padding: 15px; background: white; border: 1px solid #e5e7eb; border-radius: 8px;">
                <div style="font-weight: bold; color: #1f2937; margin-bottom: 5px;">2️⃣ Upload Proof & Chat</div>
                <p style="margin: 5px 0; color: #6b7280; font-size: 14px;">
                    After payment, upload a screenshot of your Patreon confirmation in the ad chat. Our admin will verify it within 24 hours.
                </p>
            </div>

            <div style="margin-bottom: 15px; padding: 15px; background: white; border: 1px solid #e5e7eb; border-radius: 8px;">
                <div style="font-weight: bold; color: #1f2937; margin-bottom: 5px;">3️⃣ Boost Applied</div>
                <p style="margin: 5px 0; color: #6b7280; font-size: 14px;">
                    Once approved, your book gets boosted with <?= $views_formatted ?> views and appears in "Sponsored" section!
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 12px; margin-bottom: 30px;">
            <a href="<?= htmlspecialchars($patreon_url) ?>" target="_blank" class="btn btn-primary" style="flex: 1; text-align: center; padding: 12px;">
                💳 Go to Patreon
            </a>
            <a href="<?= site_url('/pages/ads/chat.php?ad_id=' . $ad_id) ?>" class="btn btn-secondary" style="flex: 1; text-align: center; padding: 12px;">
                💬 Go to Chat
            </a>
        </div>

        <!-- Back Link -->
        <div style="text-align: center;">
            <a href="<?= site_url('/pages/dashboard.php') ?>" style="color: #3b82f6; text-decoration: none; font-size: 14px;">
                ← Back to Dashboard
            </a>
        </div>

    </div>
</main>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
</body>
</html>
