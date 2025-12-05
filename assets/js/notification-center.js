// Notification system frontend - simplified version

let notificationState = {
    isOpen: false,
    notifications: [],
    unreadCount: 0,
    currentPage: 1
};

// Initialize notification system when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('notificationBell')) {
        loadNotifications();
        setInterval(loadNotifications, 30000); // Refresh every 30 seconds
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const bell = document.getElementById('notificationBell');
            const dropdown = document.getElementById('notificationDropdown');
            if (bell && dropdown && !bell.contains(e.target)) {
                dropdown.classList.add('hidden');
                notificationState.isOpen = false;
            }
        });
    }
    
    const badge = document.getElementById('notificationBadge');
    const list = document.getElementById('notificationList');

    async function fetchUnreadCount() {
        try {
            const res = await fetch(window.SITE_URL + '/api/notifications-unread.php');
            const data = await res.json();
            if (data.success) {
                const count = data.unread || 0;
                if (badge) {
                    badge.textContent = count;
                    badge.style.display = count > 0 ? 'flex' : 'none';
                }
            }
        } catch (e) {}
    }

    async function fetchNotifications() {
        try {
            const res = await fetch(window.SITE_URL + '/api/notifications-list.php');
            const data = await res.json();
            if (data.success && Array.isArray(data.notifications) && list) {
                list.innerHTML = data.notifications.map(n => `
                    <div class="p-3 border-b border-emerald-200 dark:border-emerald-900">
                        <div class="text-sm">${escapeHtml(n.title || n.type)}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(n.message || '')}</div>
                        <div class="text-xs text-gray-400">${new Date(n.created_at).toLocaleString()}</div>
                    </div>
                `).join('');
            }
        } catch (e) {
            if (list) {
                list.innerHTML = '<div class="p-4 text-center text-gray-500 dark:text-gray-400">Failed to load notifications</div>';
            }
        }
    }

    window.toggleNotificationDropdown = function() {
        const dd = document.getElementById('notificationDropdown');
        if (!dd) return;
        const isHidden = dd.classList.contains('hidden');
        dd.classList.toggle('hidden');
        if (isHidden) {
            fetchNotifications();
        }
    };

    window.markAllNotificationsRead = async function() {
        try {
            const res = await fetch(window.SITE_URL + '/api/notifications-mark-read.php', { method: 'POST' });
            const data = await res.json();
            if (data.success && badge) {
                badge.textContent = '0';
                badge.style.display = 'none';
            }
        } catch (e) {}
    };

    // initial poll
    fetchUnreadCount();
    // periodic updates
    setInterval(fetchUnreadCount, 60000);
});

/**
 * Toggle notification dropdown
 */
function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notificationDropdown');
    notificationState.isOpen = !notificationState.isOpen;
    
    if (notificationState.isOpen) {
        dropdown.classList.remove('hidden');
        loadNotifications();
    } else {
        dropdown.classList.add('hidden');
    }
}

/**
 * Load notifications from API
 */
function loadNotifications(page = 1, filter = 'all') {
    const listDiv = document.getElementById('notificationList');
    if (!listDiv) return;
    
    fetch(`${window.SITE_URL}/api/notifications/get-notifications.php?page=${page}&limit=10&filter=${filter}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                notificationState.notifications = data.data || [];
                notificationState.unreadCount = data.unread_count || 0;
                
                updateNotificationBadge();
                renderNotificationList();
            }
        })
        .catch(e => console.error('Error loading notifications:', e));
}

/**
 * Render notification list in dropdown
 */
function renderNotificationList() {
    const listDiv = document.getElementById('notificationList');
    if (!listDiv) return;
    
    if (notificationState.notifications.length === 0) {
        listDiv.innerHTML = '<div class="p-6 text-center text-gray-500 dark:text-gray-400">No notifications yet</div>';
        return;
    }
    
    let html = '';
    notificationState.notifications.forEach(notif => {
        const isUnread = notif.is_read == 0 ? 'bg-emerald-50 dark:bg-emerald-900/20' : '';
        const unreadDot = notif.is_read == 0 ? '<div class="w-2 h-2 rounded-full bg-emerald-500 flex-shrink-0 mt-1"></div>' : '<div class="w-2 h-2 rounded-full bg-transparent flex-shrink-0 mt-1"></div>';
        const timeAgo = getTimeAgo(notif.created_at);
        
        const notifData = notif.data || {};
        const title = notif.title || notifData.book_title || 'Notification';
        const message = notif.message || notifData.message || 'You have a new update';
        const link = notif.link || notifData.url || '#';
        
        html += `
            <div class="p-3 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition ${isUnread}">
                <div class="flex gap-2 items-start">
                    ${unreadDot}
                    <div class="flex-1 min-w-0">
                        <a href="${escapeHtml(link)}" onclick="markNotificationRead(${notif.id}, event)" class="block">
                            <div class="font-semibold text-emerald-700 dark:text-emerald-400 text-sm truncate">${escapeHtml(title)}</div>
                            <div class="text-xs text-gray-600 dark:text-gray-400 line-clamp-2">${escapeHtml(message)}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-500 mt-1">${timeAgo}</div>
                        </a>
                    </div>
                    <button onclick="deleteNotification(${notif.id}, event)" class="text-gray-400 hover:text-red-600 text-sm flex-shrink-0">✕</button>
                </div>
            </div>
        `;
    });
    
    listDiv.innerHTML = html;
}

/**
 * Update notification badge
 */
function updateNotificationBadge() {
    const badge = document.getElementById('notificationBadge');
    if (badge) {
        if (notificationState.unreadCount > 0) {
            badge.textContent = notificationState.unreadCount > 99 ? '99+' : notificationState.unreadCount;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
}

/**
 * Mark single notification as read
 */
function markNotificationRead(id, event) {
    event.preventDefault();
    
    fetch(`${window.SITE_URL}/api/notifications/mark-read.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `notification_id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
            const notif = notificationState.notifications.find(n => n.id === id);
            if (notif && notif.link) {
                window.location.href = notif.link;
            }
        }
    })
    .catch(e => console.error('Error marking notification read:', e));
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsRead() {
    fetch(`${window.SITE_URL}/api/notifications/mark-read.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'mark_all=1'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
        }
    })
    .catch(e => console.error('Error marking all read:', e));
}

/**
 * Delete notification
 */
function deleteNotification(id, event) {
    event.preventDefault();
    event.stopPropagation();
    
    if (!confirm('Delete this notification?')) return;
    
    fetch(`${window.SITE_URL}/api/notifications/delete-notification.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `notification_id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
        }
    })
    .catch(e => console.error('Error deleting notification:', e));
}

/**
 * Get human-readable time ago
 */
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

/**
 * Escape HTML
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}
