<?php
// pages/artists-dashboard.php - Artist portfolio and service dashboard
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get artist profile
try {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND (is_verified_artist = 1 OR role = "artist")');
    $stmt->execute([$user_id]);
    $artist = $stmt->fetch();
    
    if (!$artist) {
        header('Location: ' . site_url('/pages/dashboard.php'));
        exit;
    }
} catch (Exception $e) {
    $error = 'Error loading profile';
}

// Handle portfolio updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_profile') {
        $bio = trim($_POST['bio'] ?? '');
        $portfolio_link = trim($_POST['portfolio_link'] ?? '');
        $service_types = implode(',', array_filter(explode(',', $_POST['service_types'] ?? '')));
        
        try {
            $stmt = $pdo->prepare('UPDATE users SET bio = ?, portfolio_link = ? WHERE id = ?');
            $stmt->execute([$bio, $portfolio_link, $user_id]);
            $success = '✅ Profile updated successfully';
        } catch (Exception $e) {
            $error = 'Error updating profile';
        }
    }
    
    // Handle portfolio upload
    elseif ($action === 'upload_portfolio') {
        $files = $_FILES['portfolio_items'] ?? [];
        $titles = $_POST['portfolio_titles'] ?? [];
        
        if (!empty($files['name'][0])) {
            $upload_count = 0;
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === 0 && in_array($files['type'][$i], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                    $filename = 'portfolio-' . time() . '-' . $i . '.' . pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                    $upload_path = __DIR__ . '/../uploads/portfolio/';
                    if (!is_dir($upload_path)) @mkdir($upload_path, 0755, true);
                    
                    if (move_uploaded_file($files['tmp_name'][$i], $upload_path . $filename)) {
                        try {
                            $stmt = $pdo->prepare('INSERT INTO portfolio (user_id, title, image_path, created_at) VALUES (?, ?, ?, NOW())');
                            $stmt->execute([$user_id, $titles[$i] ?? 'Untitled', site_url('/uploads/portfolio/' . $filename)]);
                            $upload_count++;
                        } catch (Exception $e) {
                            error_log($e->getMessage());
                        }
                    }
                }
            }
            $success = "✅ Uploaded $upload_count portfolio items";
        }
    }
}

// Get artist portfolio
$portfolio = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM portfolio WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
    $stmt->execute([$user_id]);
    $portfolio = $stmt->fetchAll();
} catch (Exception $e) {
    error_log($e->getMessage());
}

// Get pending messages/inquiries
$inquiries = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM artist_inquiries WHERE artist_id = ? ORDER BY created_at DESC LIMIT 10');
    $stmt->execute([$user_id]);
    $inquiries = $stmt->fetchAll();
} catch (Exception $e) {
    error_log($e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<main class="flex-1">
    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-emerald-700 dark:text-emerald-400 mb-2">🎨 Artist Dashboard</h1>
            <p class="text-gray-600 dark:text-gray-400">Manage your portfolio and service offerings</p>
        </div>

        <!-- Messages -->
        <?php if ($success): ?>
            <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/20 border border-green-300 dark:border-green-700 rounded-lg text-green-700 dark:text-green-400">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/20 border border-red-300 dark:border-red-700 rounded-lg text-red-700 dark:text-red-400">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Profile Section -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 border border-emerald-200 dark:border-emerald-900">
                    <h2 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400 mb-6">Profile Information</h2>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div>
                            <label class="block text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Bio</label>
                            <textarea name="bio" rows="4" class="w-full px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-600" placeholder="Tell clients about your work..."><?= htmlspecialchars($artist['bio'] ?? '') ?></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Portfolio Website</label>
                            <input type="url" name="portfolio_link" class="w-full px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-600" placeholder="https://yourportfolio.com" value="<?= htmlspecialchars($artist['portfolio_link'] ?? '') ?>">
                        </div>

                        <button type="submit" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium">
                            💾 Update Profile
                        </button>
                    </form>
                </div>

                <!-- Portfolio Section -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 border border-emerald-200 dark:border-emerald-900">
                    <h2 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400 mb-6">Portfolio Items</h2>
                    
                    <?php if (count($portfolio) > 0): ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                            <?php foreach ($portfolio as $item): ?>
                                <div class="relative group rounded-lg overflow-hidden">
                                    <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-40 object-cover">
                                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                        <p class="text-white font-medium text-center px-4"><?= htmlspecialchars($item['title']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-600 dark:text-gray-400 mb-6">No portfolio items yet. Add some to showcase your work!</p>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="action" value="upload_portfolio">
                        
                        <div>
                            <label class="block text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Upload Portfolio Items</label>
                            <input type="file" name="portfolio_items[]" multiple accept="image/*" class="w-full px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg">
                        </div>

                        <button type="submit" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium">
                            📸 Upload Items
                        </button>
                    </form>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Statistics -->
                <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-6 border border-emerald-200 dark:border-emerald-900">
                    <h3 class="text-lg font-bold text-emerald-700 dark:text-emerald-400 mb-4">📊 Stats</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-700 dark:text-gray-300">Portfolio Items</span>
                            <span class="font-bold"><?= count($portfolio) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-700 dark:text-gray-300">Inquiries</span>
                            <span class="font-bold"><?= count($inquiries) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-700 dark:text-gray-300">Profile Views</span>
                            <span class="font-bold">
                                <?php 
                                try {
                                    $stmt = $pdo->prepare('SELECT COALESCE(SUM(view_count), 0) FROM user_stats WHERE user_id = ?');
                                    $stmt->execute([$user_id]);
                                    echo $stmt->fetchColumn();
                                } catch (Exception $e) {
                                    echo '0';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Recent Inquiries -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-emerald-200 dark:border-emerald-900">
                    <h3 class="text-lg font-bold text-emerald-700 dark:text-emerald-400 mb-4">💬 Recent Inquiries</h3>
                    <?php if (count($inquiries) > 0): ?>
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            <?php foreach (array_slice($inquiries, 0, 5) as $inquiry): ?>
                                <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border-l-4 border-emerald-600">
                                    <div class="font-medium text-gray-900 dark:text-white text-sm"><?= htmlspecialchars($inquiry['sender_name'] ?? 'Anonymous') ?></div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-1"><?= date('M d, Y', strtotime($inquiry['created_at'] ?? 'now')) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">No inquiries yet</p>
                    <?php endif; ?>
                </div>

                <!-- Quick Links -->
                <div class="space-y-2">
                    <a href="<?= site_url('/pages/dashboard.php') ?>" class="block px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg text-center font-medium">
                        ← Back to Dashboard
                    </a>
                    <a href="<?= site_url('/pages/artists.php') ?>" class="block px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-center font-medium">
                        👁️ View Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

