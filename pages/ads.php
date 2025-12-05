<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];

// Create ads table if doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ads (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        story_id INT UNSIGNED,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        image_url VARCHAR(255),
        status ENUM('active', 'paused', 'ended') DEFAULT 'paused',
        budget DECIMAL(10, 2),
        spent DECIMAL(10, 2) DEFAULT 0,
        impressions INT DEFAULT 0,
        clicks INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Table already exists
}

$message = '';
$message_type = '';
$showCreateForm = false;

// Handle user-facing ad creation (no admin required)
$actionParam = isset($_GET['action']) ? $_GET['action'] : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) && $_POST['action'] === 'create')) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $story_id = intval($_POST['story_id'] ?? 0) ?: null;
    $budget = floatval($_POST['budget'] ?? 0);
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $image = $_FILES['image'] ?? null;

    // Basic validation
    if (empty($title) || $budget <= 0) {
        $message = 'Title and a positive budget are required';
        $message_type = 'error';
    } else {
        // Handle image upload
        $image_url = null;
        if ($image && isset($image['tmp_name']) && $image['size'] > 0) {
            $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
            if (in_array($image['type'], $allowed)) {
                $ext = pathinfo($image['name'], PATHINFO_EXTENSION);
                $fname = 'ad-' . time() . '.' . $ext;
                $uploadDir = dirname(__DIR__) . '/uploads/ads/';
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
                if (move_uploaded_file($image['tmp_name'], $uploadDir . $fname)) {
                    $image_url = site_url('/uploads/ads/' . $fname);
                }
            }
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO ads (user_id, story_id, title, description, image_url, status, budget, spent, impressions, clicks, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0, NOW(), NOW())');
            $status = 'pending';
            $stmt->execute([$user_id, $story_id, $title, $description, $image_url, $status, $budget]);
            $message = 'Ad submitted for review. It will go live once approved.';
            $message_type = 'success';
            // Redirect to avoid form re-submission
            header('Location: ' . site_url('/pages/ads.php'));
            exit;
        } catch (Exception $e) {
            $message = 'Failed to create ad: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

if ($actionParam === 'create') {
    $showCreateForm = true;
}

// Fetch user's ads
$ads = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, s.title as story_title 
        FROM ads a 
        LEFT JOIN stories s ON a.story_id = s.id
        WHERE a.user_id = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $ads = $stmt->fetchAll();
} catch (Exception $e) {
    $ads = [];
}

// Fetch user's published stories for targeting select
try {
    $sstmt = $pdo->prepare("SELECT id, title FROM stories WHERE author_id = ? AND status = 'published' ORDER BY created_at DESC");
    $sstmt->execute([$user_id]);
    $userStoriesList = $sstmt->fetchAll();
} catch (Exception $e) {
    $userStoriesList = [];
}

// Handle ad actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $ad_id = (int)($_POST['ad_id'] ?? 0);

    if ($action === 'pause' && $ad_id) {
        try {
            $stmt = $pdo->prepare("UPDATE ads SET status = 'paused' WHERE id = ? AND user_id = ?");
            $stmt->execute([$ad_id, $user_id]);
            $message = 'Ad paused successfully';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Error pausing ad';
            $message_type = 'error';
        }
    } elseif ($action === 'resume' && $ad_id) {
        try {
            $stmt = $pdo->prepare("UPDATE ads SET status = 'active' WHERE id = ? AND user_id = ?");
            $stmt->execute([$ad_id, $user_id]);
            $message = 'Ad resumed successfully';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Error resuming ad';
            $message_type = 'error';
        }
    } elseif ($action === 'delete' && $ad_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM ads WHERE id = ? AND user_id = ?");
            $stmt->execute([$ad_id, $user_id]);
            $message = 'Ad deleted successfully';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Error deleting ad';
            $message_type = 'error';
        }
    }
}

$userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
?>
<?php
    $page_title = 'Manage Ads - Scroll Novels';
    $page_head = '';
    require_once __DIR__ . '/../includes/header.php';
?>

<main class="flex-1 flex flex-col">
    <div class="max-w-6xl mx-auto px-4 py-12 flex-1">
        <!-- Page Title -->
        <div class="mb-8">
            <h2 class="text-4xl font-bold text-emerald-700 dark:text-emerald-400 mb-2">📢 Manage Ads</h2>
            <p class="text-gray-600 dark:text-gray-400">Promote your stories and reach more readers</p>
        </div>

        <!-- How It Works Info Box -->
        <div class="mb-8 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
            <h3 class="text-lg font-bold text-blue-800 dark:text-blue-200 mb-4">💡 How Advertising Works</h3>
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-semibold text-blue-700 dark:text-blue-300 mb-2">📊 What You Get</h4>
                    <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                        <li>• <strong>Homepage Featured:</strong> Your story appears in "Sponsored Books" section</li>
                        <li>• <strong>Priority Placement:</strong> Higher tiers get better positions</li>
                        <li>• <strong>Impressions & Clicks:</strong> Track how many see and click your ad</li>
                        <li>• <strong>AD Badge:</strong> Visible promotion badge on your story</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold text-blue-700 dark:text-blue-300 mb-2">💳 Patreon Tiers & Payment</h4>
                    <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                        <li>• <strong>$10 Tier:</strong> Basic promotion (1 week)</li>
                        <li>• <strong>$25 Tier:</strong> Standard promotion (2 weeks)</li>
                        <li>• <strong>$50 Tier:</strong> Premium promotion (1 month)</li>
                        <li>• <strong>$100 Tier:</strong> Featured promotion (2 months)</li>
                        <li>• <strong>$200 Tier:</strong> Elite promotion (3 months + priority)</li>
                    </ul>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-blue-200 dark:border-blue-700">
                <h4 class="font-semibold text-blue-700 dark:text-blue-300 mb-2">📝 How to Purchase</h4>
                <ol class="text-sm text-gray-700 dark:text-gray-300 space-y-1 list-decimal list-inside">
                    <li>Create your ad below (select your story, set budget)</li>
                    <li>Go to our <a href="https://www.patreon.com/scrollnovels" target="_blank" class="text-emerald-600 hover:underline font-medium">Patreon page</a> and subscribe to your desired tier</li>
                    <li>Open a <a href="<?= site_url('/pages/support.php') ?>" class="text-emerald-600 hover:underline font-medium">Support Ticket</a> with your Patreon payment proof (screenshot)</li>
                    <li>Our team will activate your ad within 24 hours</li>
                </ol>
            </div>
        </div>

        <!-- Message -->
        <?php if ($message): ?>
            <div class="mb-8 p-4 rounded-lg border <?= $message_type === 'success' ? 'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-200' : 'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-200' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Create Ad Button (user-facing create page) -->
        <div class="mb-8">
            <a href="<?= site_url('/pages/ads.php?action=create') ?>" class="inline-block px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors">
                ✨ Create New Ad
            </a>
        </div>

        <?php if ($showCreateForm): ?>
            <div class="mb-8 card">
                <h3 class="text-xl font-bold mb-3">Create New Ad</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label>
                        <input name="title" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                        <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Target Story (optional)</label>
                            <select name="story_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white" style="color: inherit;">
                                <option value="" style="background: white; color: #111827;" class="dark:bg-gray-700 dark:text-white">-- All Stories --</option>
                                <?php foreach ($userStoriesList as $us): ?>
                                    <option value="<?= (int)$us['id'] ?>" style="background: white; color: #111827;" class="dark:bg-gray-700 dark:text-white"><?= htmlspecialchars($us['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                    </div>
                    <div class="mb-3 grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Budget (USD)</label>
                            <input name="budget" type="number" step="0.01" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white" required />
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Image (optional)</label>
                            <input name="image" type="file" accept="image/*" class="w-full" />
                        </div>
                    </div>
                    <div class="flex gap-3 mt-4">
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded">Submit Ad</button>
                        <a href="<?= site_url('/pages/ads.php') ?>" class="px-4 py-2 border rounded">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Ads List -->
        <?php if (empty($ads)): ?>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-12 text-center shadow border border-emerald-200 dark:border-emerald-900">
                <div class="text-5xl mb-4">📢</div>
                <h3 class="text-2xl font-bold text-gray-700 dark:text-gray-300 mb-2">No Ads Yet</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">Start promoting your stories to reach more readers!</p>
                <a href="<?= site_url('/pages/ads.php?action=create') ?>" class="inline-block px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors">
                    Create Your First Ad
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-6">
                <?php foreach ($ads as $ad): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow border border-emerald-200 dark:border-emerald-900 hover:shadow-lg transition-shadow">
                        <div class="flex items-start gap-6">
                            <!-- Ad Image -->
                            <div class="flex-shrink-0">
                                <?php if ($ad['image_url']): ?>
                                    <img src="<?= htmlspecialchars($ad['image_url']) ?>" alt="Ad" class="w-24 h-24 object-cover rounded-lg">
                                <?php else: ?>
                                    <div class="w-24 h-24 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center">
                                        <span class="text-3xl">📢</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Ad Info -->
                            <div class="flex-grow">
                                <h3 class="text-xl font-bold text-emerald-700 dark:text-emerald-400 mb-1"><?= htmlspecialchars($ad['title']) ?></h3>
                                <?php if ($ad['story_title']): ?>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Story: <strong><?= htmlspecialchars($ad['story_title']) ?></strong></p>
                                <?php endif; ?>

                                <div class="grid grid-cols-3 gap-4 mb-4">
                                    <div>
                                        <p class="text-xs text-gray-600 dark:text-gray-400">Budget</p>
                                        <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">$<?= number_format($ad['budget'] ?? 0, 2) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-600 dark:text-gray-400">Impressions</p>
                                        <p class="text-lg font-bold text-blue-600 dark:text-blue-400"><?= number_format($ad['impressions'] ?? 0) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-600 dark:text-gray-400">Clicks</p>
                                        <p class="text-lg font-bold text-purple-600 dark:text-purple-400"><?= number_format($ad['clicks'] ?? 0) ?></p>
                                    </div>
                                </div>

                                <!-- Status Badge -->
                                <div class="flex items-center gap-2 mb-4">
                                    <span class="inline-block px-3 py-1 rounded-full text-sm font-medium <?= 
                                        $ad['status'] === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200' :
                                        ($ad['status'] === 'paused' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-200' :
                                        'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-200')
                                    ?>">
                                        <?= ucfirst($ad['status']) ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex-shrink-0 flex flex-col gap-2">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="ad_id" value="<?= $ad['id'] ?>">
                                    <input type="hidden" name="action" value="<?= $ad['status'] === 'active' ? 'pause' : 'resume' ?>">
                                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= 
                                        $ad['status'] === 'active' ? 
                                        'bg-yellow-100 text-yellow-800 hover:bg-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-200' :
                                        'bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-200'
                                    ?>">
                                        <?= $ad['status'] === 'active' ? '⏸️ Pause' : '▶️ Resume' ?>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this ad?');">
                                    <input type="hidden" name="ad_id" value="<?= $ad['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="px-4 py-2 bg-red-100 text-red-800 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-200 rounded-lg text-sm font-medium transition-colors">
                                        🗑️ Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

