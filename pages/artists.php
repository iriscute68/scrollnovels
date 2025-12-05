<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);

// Fetch verified artists/editors (handle missing bio column)
try {
    $artists = $pdo->query("SELECT DISTINCT u.id, u.username, u.profile_image FROM users u LEFT JOIN stories s ON u.id = s.author_id WHERE (u.is_verified_artist = 1 OR u.is_approved_admin = 1) AND u.username != 'admin' ORDER BY u.username ASC LIMIT 50")->fetchAll();
} catch (Exception $e) {
    $artists = [];
}

// Load book card component
if (file_exists(dirname(__DIR__) . '/includes/components/book-card.php')) {
    require_once dirname(__DIR__) . '/includes/components/book-card.php';
}
?>
<?php
    $page_title = 'Editors & Artists - Scroll Novels';
    $page_head = '';
    require_once __DIR__ . '/../includes/header.php';
?>

<!-- Main Content -->
<main class="flex-1">
    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="mb-8">
            <h2 class="text-4xl font-bold text-emerald-700 dark:text-emerald-400 mb-2">Verified Editors & Artists</h2>
            <p class="text-gray-600 dark:text-gray-400">Meet our talented team of professional editors and artists</p>
        </div>

        <!-- Artists Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($artists as $artist): ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-emerald-200 dark:border-emerald-900 p-6 hover:shadow-xl transition-shadow">
                    <div class="flex items-center mb-4">
                        <div class="w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mr-4">
                            <?php if (!empty($artist['avatar'])): ?>
                                <img src="<?= htmlspecialchars($artist['avatar']) ?>" alt="<?= htmlspecialchars($artist['username']) ?>" class="w-16 h-16 rounded-full object-cover">
                            <?php else: ?>
                                <span class="text-3xl">👤</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-emerald-700 dark:text-emerald-400">
                                <a href="<?= site_url('/pages/profile.php?user=' . urlencode($artist['username'])) ?>" class="hover:underline">
                                    <?= htmlspecialchars($artist['username']) ?>
                                </a>
                            </h3>
                            <span class="text-sm text-emerald-600 dark:text-emerald-300">✓ Verified</span>
                        </div>
                    </div>
                    <?php if (!empty($artist['bio'])): ?>
                        <p class="text-gray-700 dark:text-gray-300 text-sm mb-4">
                            <?= htmlspecialchars(substr($artist['bio'], 0, 150)) ?>...
                        </p>
                    <?php endif; ?>
                    <a href="<?= site_url('/pages/profile.php?user=' . urlencode($artist['username'])) ?>" class="inline-block px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium transition-colors">
                        View Profile
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($artists)): ?>
            <div class="text-center py-12">
                <p class="text-gray-600 dark:text-gray-400">No verified editors or artists yet. Check back soon!</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Footer -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
const isLoggedIn = <?= json_encode($isLoggedIn) ?>;

document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const closeSidebarBtn = document.getElementById('closeSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('translate-x-full');
        sidebar.classList.add('translate-x-0');
        if (overlay) {
            overlay.classList.remove('hidden');
            overlay.classList.add('block');
        }
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('translate-x-full');
        sidebar.classList.remove('translate-x-0');
        if (overlay) {
            overlay.classList.add('hidden');
            overlay.classList.remove('block');
        }
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e){
            e.preventDefault();
            if (!isLoggedIn) {
                window.location.href = '<?= site_url('/pages/login.php') ?>';
                return;
            }
            openSidebar();
        });
    }

    if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);
});
</script>
</body>
</html>

