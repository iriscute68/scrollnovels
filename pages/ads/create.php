<?php
// pages/ads/create.php - Create new ad (book selection + package choice)

session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$page_title = 'Create Ad - Scroll Novels';
$page_head = '<link rel="stylesheet" href="' . asset_url('css/ads.css') . '">';

// Get user's books
$stmt = $pdo->prepare("SELECT id, title, is_sponsored FROM stories WHERE author_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$books = $stmt->fetchAll();

// Get package config
$config = require dirname(__DIR__, 2) . '/config/ads.php';
$packages = $config['packages'];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<main class="flex-1">
    <div class="ad-create">
        <h1>📢 Create an Ad</h1>
        
        <p style="color: #718096; margin-bottom: 20px;">
            Boost your book by purchasing ad views. Choose your book and select a package to get started.
        </p>

        <?php if (empty($books)): ?>
            <div style="padding: 20px; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 6px; color: #92400e;">
                <strong>No books found</strong><br>
                You need to publish a book before creating an ad. <a href="<?= site_url('/story/create.php') ?>">Create a book</a>
            </div>
        <?php else: ?>

            <form id="createAdForm" method="POST" action="<?= site_url('/pages/ads/pending-redirect.php') ?>" onsubmit="return validateForm()">
                
                <!-- Book Selection -->
                <div class="form-group">
                    <label for="book_id">📚 Choose Your Book</label>
                    <select id="book_id" name="book_id" required>
                        <option value="">-- Select a book --</option>
                        <?php foreach ($books as $book): ?>
                            <option value="<?= (int)$book['id'] ?>">
                                <?= htmlspecialchars($book['title']) ?>
                                <?php if ($book['is_sponsored']): ?><span style="color: #f59e0b;"> ⭐ (Already Sponsored)</span><?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Package Selection -->
                <div class="form-group packages">
                    <label>💰 Choose a Package</label>
                    <div class="package-list">
                        <?php foreach ($packages as $code => $pkg): ?>
                            <div class="package" data-value="<?= htmlspecialchars($code) ?>" data-views="<?= (int)$pkg['views'] ?>" data-amount="<?= (float)$pkg['amount'] ?>">
                                <h3><?= htmlspecialchars(explode(' ', $pkg['label'])[0]) ?></h3>
                                <p><?= htmlspecialchars($pkg['label']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="packageInput" name="package" required>
                </div>

                <!-- Package Summary -->
                <div id="packageSummary" class="package-summary" style="display: none;">
                    <p>📊 Selected: <span id="psViews"></span> views — <strong>$<span id="psAmount"></span></strong></p>
                </div>

                <hr style="margin: 20px 0; border: none; border-top: 1px solid #e2e8f0;">

                <!-- Info Box -->
                <div style="padding: 12px; background: #ecfdf5; border: 1px solid #bbf7d0; border-radius: 6px; margin-bottom: 20px; font-size: 14px; color: #166534;">
                    <strong>ℹ️ How it works:</strong><br>
                    1. Choose your book and ad package<br>
                    2. You'll be redirected to Patreon to complete payment<br>
                    3. Upload your payment proof in the chat<br>
                    4. Our admin will verify and boost your book!
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        💳 Continue to Payment
                    </button>
                    <a href="<?= site_url('/pages/dashboard.php') ?>" class="btn btn-secondary" style="flex: 1;">Cancel</a>
                </div>
            </form>

        <?php endif; ?>
    </div>
</main>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>

<script src="<?= asset_url('js/ads.js') ?>"></script>
<script>
function validateForm() {
    const bookId = document.getElementById('book_id').value;
    const packageInput = document.getElementById('packageInput').value;

    if (!bookId) {
        alert('Please select a book');
        return false;
    }

    if (!packageInput) {
        alert('Please select an ad package');
        return false;
    }

    return true;
}
</script>
</body>
</html>
