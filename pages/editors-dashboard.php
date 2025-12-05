<?php
// pages/editors-dashboard.php - Editor services and reviews dashboard
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get editor profile
try {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND (is_verified_editor = 1 OR role = "editor")');
    $stmt->execute([$user_id]);
    $editor = $stmt->fetch();
    
    if (!$editor) {
        header('Location: ' . site_url('/pages/dashboard.php'));
        exit;
    }
} catch (Exception $e) {
    $error = 'Error loading profile';
}

// Handle service updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_services') {
        $service_types = trim($_POST['service_types'] ?? '');
        $rates = trim($_POST['rates'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        try {
            $stmt = $pdo->prepare('UPDATE users SET bio = ? WHERE id = ?');
            $stmt->execute([$bio, $user_id]);
            $success = '✅ Services updated successfully';
        } catch (Exception $e) {
            $error = 'Error updating services';
        }
    }
    
    // Handle sample uploads
    elseif ($action === 'upload_samples') {
        $files = $_FILES['samples'] ?? [];
        
        if (!empty($files['name'][0])) {
            $upload_count = 0;
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === 0) {
                    $filename = 'sample-' . time() . '-' . $i . '.' . pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                    $upload_path = __DIR__ . '/../uploads/samples/';
                    if (!is_dir($upload_path)) @mkdir($upload_path, 0755, true);
                    
                    if (move_uploaded_file($files['tmp_name'][$i], $upload_path . $filename)) {
                        $upload_count++;
                    }
                }
            }
            $success = "✅ Uploaded $upload_count sample files";
        }
    }
}

// Get editor reviews
$reviews = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM editor_reviews WHERE editor_id = ? ORDER BY created_at DESC LIMIT 10');
    $stmt->execute([$user_id]);
    $reviews = $stmt->fetchAll();
} catch (Exception $e) {
    error_log($e->getMessage());
}

// Get pending requests
$requests = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM editing_requests WHERE editor_id = ? AND status != "completed" ORDER BY created_at DESC LIMIT 10');
    $stmt->execute([$user_id]);
    $requests = $stmt->fetchAll();
} catch (Exception $e) {
    error_log($e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<main class="flex-1">
    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-emerald-700 dark:text-emerald-400 mb-2">✏️ Editor Dashboard</h1>
            <p class="text-gray-600 dark:text-gray-400">Manage your services, rates, and client projects</p>
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
                <!-- Services Section -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 border border-emerald-200 dark:border-emerald-900">
                    <h2 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400 mb-6">📋 Services & Rates</h2>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_services">
                        
                        <div>
                            <label class="block text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Bio</label>
                            <textarea name="bio" rows="4" class="w-full px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-600" placeholder="Describe your editing expertise..."><?= htmlspecialchars($editor['bio'] ?? '') ?></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Service Types</label>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="service_types[]" value="proofreading" class="rounded">
                                    <span>Proofreading</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="service_types[]" value="copy-editing" class="rounded">
                                    <span>Copy Editing</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="service_types[]" value="developmental" class="rounded">
                                    <span>Developmental Editing</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="service_types[]" value="line-editing" class="rounded">
                                    <span>Line Editing</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Rates (per word)</label>
                            <input type="text" name="rates" class="w-full px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg bg-white dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-600" placeholder="e.g., $0.05 - $0.10" value="<?= htmlspecialchars($editor['rates'] ?? '') ?>">
                        </div>

                        <button type="submit" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium">
                            💾 Update Services
                        </button>
                    </form>
                </div>

                <!-- Sample Uploads -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 border border-emerald-200 dark:border-emerald-900">
                    <h2 class="text-2xl font-bold text-emerald-700 dark:text-emerald-400 mb-6">📄 Sample Edits</h2>
                    
                    <p class="text-gray-600 dark:text-gray-400 mb-4">Upload samples of your editing work to showcase your skills</p>

                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="action" value="upload_samples">
                        
                        <div>
                            <label class="block text-sm font-medium text-emerald-700 dark:text-emerald-400 mb-2">Upload Sample Documents</label>
                            <input type="file" name="samples[]" multiple accept=".pdf,.doc,.docx,.txt" class="w-full px-4 py-2 border border-emerald-300 dark:border-emerald-700 rounded-lg">
                        </div>

                        <button type="submit" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium">
                            📤 Upload Samples
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
                            <span class="text-gray-700 dark:text-gray-300">Active Requests</span>
                            <span class="font-bold"><?= count($requests) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-700 dark:text-gray-300">Total Reviews</span>
                            <span class="font-bold"><?= count($reviews) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-700 dark:text-gray-300">Avg Rating</span>
                            <span class="font-bold">
                                <?php 
                                if (!empty($reviews)) {
                                    $avg = array_sum(array_column($reviews, 'rating')) / count($reviews);
                                    echo number_format($avg, 1);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Recent Reviews -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-emerald-200 dark:border-emerald-900">
                    <h3 class="text-lg font-bold text-emerald-700 dark:text-emerald-400 mb-4">⭐ Recent Reviews</h3>
                    <?php if (count($reviews) > 0): ?>
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            <?php foreach (array_slice($reviews, 0, 5) as $review): ?>
                                <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border-l-4 border-yellow-500">
                                    <div class="flex justify-between items-start mb-1">
                                        <div class="font-medium text-gray-900 dark:text-white text-sm"><?= htmlspecialchars($review['reviewer_name'] ?? 'Anonymous') ?></div>
                                        <span class="text-xs font-bold text-yellow-600">⭐ <?= $review['rating'] ?? '0' ?>/5</span>
                                    </div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 line-clamp-2"><?= htmlspecialchars(substr($review['comment'] ?? '', 0, 80)) ?>...</p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">No reviews yet</p>
                    <?php endif; ?>
                </div>

                <!-- Quick Links -->
                <div class="space-y-2">
                    <a href="<?= site_url('/pages/dashboard.php') ?>" class="block px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg text-center font-medium">
                        ← Back to Dashboard
                    </a>
                    <a href="<?= site_url('/pages/editors.php') ?>" class="block px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-center font-medium">
                        👁️ View Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

