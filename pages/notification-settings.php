<?php
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$page_title = 'Notification Settings - Scroll Novels';
$message = '';
$error = '';

$page_head = ''
    . '<script>tailwind.config={darkMode:"class"};</script>'
    . '<link rel="stylesheet" href="' . asset_url('css/global.css') . '">'
    . '<link rel="stylesheet" href="' . asset_url('css/theme.css') . '">'
    . '<script src="' . asset_url('js/theme.js') . '" defer></script>'
    . '<style>:root{--transition-base:200ms ease-in-out}body{transition:background-color var(--transition-base),color var(--transition-base)}</style>';

require_once __DIR__ . '/includes/header.php';

// Get current settings
$stmt = $pdo->prepare("SELECT * FROM user_notification_settings WHERE user_id = ?");
$stmt->execute([$user_id]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
    $insert = $pdo->prepare("INSERT INTO user_notification_settings (user_id) VALUES (?)");
    $insert->execute([$user_id]);
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<main class="flex-1">
    <div class="max-w-2xl mx-auto px-4 py-12">
        <h1 class="text-3xl font-bold text-emerald-700 dark:text-emerald-400 mb-8">⚙️ Notification Settings</h1>

        <?php if ($message): ?>
            <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/20 border border-green-300 dark:border-green-700 rounded-lg text-green-700 dark:text-green-400">
                ✓ <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/20 border border-red-300 dark:border-red-700 rounded-lg text-red-700 dark:text-red-400">
                ✕ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 space-y-6">
            <!-- Content Notifications -->
            <div>
                <h2 class="text-xl font-bold text-emerald-700 dark:text-emerald-400 mb-4">📚 Content Notifications</h2>
                <div class="space-y-3">
                    <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer">
                        <input type="checkbox" class="notification-toggle" data-setting="new_chapter" <?= $settings['new_chapter'] ? 'checked' : '' ?> class="w-5 h-5 rounded">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">New Chapters</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Get notified when authors publish new chapters</div>
                        </div>
                    </label>
                </div>
            </div>

            <hr class="border-emerald-200 dark:border-emerald-900">

            <!-- Interaction Notifications -->
            <div>
                <h2 class="text-xl font-bold text-emerald-700 dark:text-emerald-400 mb-4">💬 Interaction Notifications</h2>
                <div class="space-y-3">
                    <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer">
                        <input type="checkbox" class="notification-toggle" data-setting="comment" <?= $settings['comment'] ? 'checked' : '' ?>>
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">Comments</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">New comments on your posts or chapters</div>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer">
                        <input type="checkbox" class="notification-toggle" data-setting="reply" <?= $settings['reply'] ? 'checked' : '' ?>>
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">Replies</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Replies to your comments</div>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer">
                        <input type="checkbox" class="notification-toggle" data-setting="review" <?= $settings['review'] ? 'checked' : '' ?>>
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">Reviews</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">New reviews on your stories</div>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer">
                        <input type="checkbox" class="notification-toggle" data-setting="rating" <?= $settings['rating'] ? 'checked' : '' ?>>
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">Ratings</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">New star ratings on your stories</div>
                        </div>
                    </label>
                </div>
            </div>

            <hr class="border-emerald-200 dark:border-emerald-900">

            <!-- System Notifications -->
            <div>
                <h2 class="text-xl font-bold text-emerald-700 dark:text-emerald-400 mb-4">🔔 System Notifications</h2>
                <div class="space-y-3">
                    <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer">
                        <input type="checkbox" class="notification-toggle" data-setting="system" <?= $settings['system'] ? 'checked' : '' ?>>
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">System Updates</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Important site announcements and updates</div>
                        </div>
                    </label>
                </div>
            </div>

            <hr class="border-emerald-200 dark:border-emerald-900">

            <!-- Monetization Notifications -->
            <div>
                <h2 class="text-xl font-bold text-emerald-700 dark:text-emerald-400 mb-4">💰 Monetization Notifications</h2>
                <div class="space-y-3">
                    <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer">
                        <input type="checkbox" class="notification-toggle" data-setting="monetization" <?= $settings['monetization'] ? 'checked' : '' ?>>
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">Monetization Alerts</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">New subscribers, tips, and earnings</div>
                        </div>
                    </label>
                </div>
            </div>

            <hr class="border-emerald-200 dark:border-emerald-900">

            <!-- Delivery Methods -->
            <div>
                <h2 class="text-xl font-bold text-emerald-700 dark:text-emerald-400 mb-4">✉️ Delivery Methods</h2>
                <div class="space-y-3">
                    <label class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer">
                        <input type="checkbox" class="notification-toggle" data-setting="email_notifications" <?= $settings['email_notifications'] ? 'checked' : '' ?>>
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">Email Notifications</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Receive notifications via email</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Save Button -->
            <div class="flex gap-3 pt-4">
                <button onclick="saveSettings()" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                    💾 Save Changes
                </button>
                <a href="<?= site_url('/pages/notifications.php') ?>" class="px-6 py-2 border-2 border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20 font-medium transition">
                    ← Back
                </a>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
const toggles = document.querySelectorAll('.notification-toggle');

function saveSettings() {
    const data = {};
    toggles.forEach(toggle => {
        data[toggle.dataset.setting] = toggle.checked ? 1 : 0;
    });
    
    fetch(`${window.SITE_URL}/api/notifications/update-settings.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Settings saved successfully!');
            location.reload();
        } else {
            alert('Error saving settings: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(e => {
        console.error('Error:', e);
        alert('Error saving settings');
    });
}
</script>

</body>
</html>
