<?php
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$page = (int)($_GET['page'] ?? 1);
$filter = $_GET['filter'] ?? 'all';
$page_title = 'Notifications - Scroll Novels';

$page_head = ''
    . '<script>tailwind.config={darkMode:"class"};</script>'
    . '<link rel="stylesheet" href="' . asset_url('css/global.css') . '">'
    . '<link rel="stylesheet" href="' . asset_url('css/theme.css') . '">'
    . '<script src="' . asset_url('js/theme.js') . '" defer></script>'
    . '<style>:root{--transition-base:200ms ease-in-out}body{transition:background-color var(--transition-base),color var(--transition-base)}</style>';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="flex-1">
    <div class="max-w-4xl mx-auto px-4 py-6 sm:py-12">
        <h1 class="text-2xl sm:text-3xl font-bold text-emerald-700 dark:text-emerald-400 mb-6 sm:mb-8">🔔 Notifications</h1>

        <!-- Filters -->
        <div class="mb-6 sm:mb-8 flex flex-wrap gap-2">
            <a href="?filter=all" class="px-3 sm:px-4 py-2 text-xs sm:text-sm rounded-lg font-medium transition whitespace-nowrap <?= $filter === 'all' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-gray-800 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900' ?>">
                All
            </a>
            <a href="?filter=unread" class="px-3 sm:px-4 py-2 text-xs sm:text-sm rounded-lg font-medium transition whitespace-nowrap <?= $filter === 'unread' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-gray-800 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900' ?>">
                Unread
            </a>
            <a href="?filter=new_chapter" class="px-3 sm:px-4 py-2 text-xs sm:text-sm rounded-lg font-medium transition whitespace-nowrap <?= $filter === 'new_chapter' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-gray-800 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900' ?>">
                Chapters
            </a>
            <a href="?filter=comment" class="px-3 sm:px-4 py-2 text-xs sm:text-sm rounded-lg font-medium transition whitespace-nowrap <?= $filter === 'comment' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-gray-800 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900' ?>">
                Comments
            </a>
            <a href="?filter=review" class="px-3 sm:px-4 py-2 text-xs sm:text-sm rounded-lg font-medium transition whitespace-nowrap <?= $filter === 'review' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-gray-800 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900' ?>">
                Reviews
            </a>
            <a href="?filter=system" class="px-3 sm:px-4 py-2 text-xs sm:text-sm rounded-lg font-medium transition whitespace-nowrap <?= $filter === 'system' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-gray-800 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900' ?>">
                System
            </a>
        </div>

        <!-- Actions -->
        <div class="mb-6 flex flex-wrap gap-2 sm:gap-3">
            <button onclick="markAllRead()" class="flex-1 sm:flex-none px-3 sm:px-4 py-2 text-xs sm:text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                ✓ Read
            </button>
            <button onclick="deleteAllNotifications()" class="flex-1 sm:flex-none px-3 sm:px-4 py-2 text-xs sm:text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition">
                🗑️ Delete
            </button>
            <a href="<?= site_url('/pages/notification-settings.php') ?>" class="flex-1 sm:flex-none px-3 sm:px-4 py-2 text-xs sm:text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition text-center">
                ⚙️ Settings
            </a>
        </div>

        <!-- Notifications List -->
        <div id="notificationsList" class="space-y-3">
            <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                <p>Loading notifications...</p>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
let currentFilter = '<?= htmlspecialchars($filter) ?>';
let currentPage = <?= $page ?>;

document.addEventListener('DOMContentLoaded', function() {
    loadNotificationsPage();
});

function loadNotificationsPage() {
    const url = `${window.SITE_URL}/api/notifications/get-notifications.php?page=${currentPage}&limit=50&filter=${currentFilter}`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderNotifications(data.data || []);
            } else {
                document.getElementById('notificationsList').innerHTML = '<div class="text-red-600 p-4">Error loading notifications</div>';
            }
        })
        .catch(e => {
            console.error('Error:', e);
            document.getElementById('notificationsList').innerHTML = '<div class="text-red-600 p-4">Error loading notifications</div>';
        });
}

function renderNotifications(notifications) {
    const container = document.getElementById('notificationsList');
    
    if (notifications.length === 0) {
        container.innerHTML = `
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-emerald-200 dark:border-emerald-900 p-12 text-center">
                <div class="text-6xl mb-4">🔔</div>
                <h3 class="text-xl font-bold text-gray-700 dark:text-gray-300 mb-2">No Notifications</h3>
                <p class="text-gray-500 dark:text-gray-400 mb-4">You're all caught up! Check back later for updates on your stories, comments, and more.</p>
                <a href="${window.SITE_URL}/pages/browse.php" class="inline-block px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                    Discover Stories
                </a>
            </div>
        `;
        return;
    }
    
    let html = '';
    notifications.forEach(notif => {
        const isUnread = notif.is_read == 0;
        const bgClass = isUnread ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-white dark:bg-gray-800';
        const timeAgo = getTimeAgo(notif.created_at);
        
        const data = notif.data || {};
        const title = notif.title || data.book_title || 'Notification';
        const message = notif.message || data.message || '';
        const link = notif.link || data.url || '#';
        
        html += `
            <div class="p-4 sm:p-6 rounded-lg border border-emerald-200 dark:border-emerald-900 ${bgClass} transition hover:shadow-lg">
                <div class="flex flex-col gap-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-start gap-2 flex-1">
                            ${isUnread ? '<span class="w-2 h-2 sm:w-3 sm:h-3 rounded-full bg-emerald-500 flex-shrink-0 mt-1"></span>' : ''}
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-base sm:text-lg text-emerald-700 dark:text-emerald-400 break-words">${escapeHtml(title)}</h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400">${timeAgo}</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray-700 dark:text-gray-300 text-sm sm:text-base break-words">${escapeHtml(message)}</p>
                    <div class="flex flex-wrap gap-2">
                        ${link && link !== '#' ? `<a href="${escapeHtml(link)}" class="flex-1 sm:flex-none px-3 sm:px-4 py-2 text-xs sm:text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded font-medium transition text-center">View →</a>` : ''}
                        ${isUnread ? `<button onclick="markAsRead(${notif.id})" class="flex-1 sm:flex-none px-3 sm:px-4 py-2 text-xs sm:text-sm bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-900 dark:text-white rounded font-medium transition">Read</button>` : ''}
                        <button onclick="deleteNotif(${notif.id})" class="flex-1 sm:flex-none px-3 sm:px-4 py-2 text-xs sm:text-sm bg-red-600 hover:bg-red-700 text-white rounded font-medium transition">Delete</button>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function markAsRead(id) {
    fetch(`${window.SITE_URL}/api/notifications/mark-read.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `notification_id=${id}`
    })
    .then(() => loadNotificationsPage())
    .catch(e => console.error('Error:', e));
}

function markAllRead() {
    fetch(`${window.SITE_URL}/api/notifications/mark-read.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'mark_all=1'
    })
    .then(() => loadNotificationsPage())
    .catch(e => console.error('Error:', e));
}

function deleteNotif(id) {
    if (!confirm('Delete this notification?')) return;
    fetch(`${window.SITE_URL}/api/notifications/delete-notification.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `notification_id=${id}`
    })
    .then(() => loadNotificationsPage())
    .catch(e => console.error('Error:', e));
}

function deleteAllNotifications() {
    if (!confirm('Delete all notifications? This cannot be undone!')) return;
    fetch(`${window.SITE_URL}/api/notifications/delete-notification.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'delete_all=1'
    })
    .then(() => loadNotificationsPage())
    .catch(e => console.error('Error:', e));
}

function getTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    
    return date.toLocaleDateString();
}

function escapeHtml(text) {
    const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
    return String(text).replace(/[&<>"']/g, m => map[m]);
}
</script>

</body>
</html>
