/**
 * Notification Center - JavaScript Component
 * Displays real-time notifications for support tickets, forum moderation, competitions, etc.
 */

class NotificationCenter {
    constructor(options = {}) {
        this.options = {
            pollInterval: options.pollInterval || 30000, // Poll every 30 seconds
            maxNotifications: options.maxNotifications || 50,
            ...options
        };
        
        this.notifications = [];
        this.unreadCount = 0;
        this.init();
    }

    init() {
        this.createHTML();
        this.attachEventListeners();
        this.startPolling();
        this.loadNotifications();
    }

    createHTML() {
        // Create container if doesn't exist
        if (!document.getElementById('notification-center')) {
            const container = document.createElement('div');
            container.id = 'notification-center';
            container.className = 'notification-center';
            container.innerHTML = '<div id="notifications-list"></div>';
            document.body.appendChild(container);
        }
    }

    attachEventListeners() {
        // Could add click handlers here for actions
    }

    startPolling() {
        // Poll for new notifications periodically
        setInterval(() => this.loadNotifications(), this.options.pollInterval);
    }

    loadNotifications() {
        fetch('/api/get-notifications.php')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.displayNotifications(data.notifications);
                    this.updateUnreadCount(data.unread_count);
                }
            })
            .catch(e => console.error('Failed to load notifications:', e));
    }

    displayNotifications(notifications) {
        const list = document.getElementById('notifications-list');
        
        if (notifications.length === 0) {
            list.innerHTML = '<div style="padding: 1rem; text-align: center; color: #9ca3af;">No notifications</div>';
            return;
        }

        list.innerHTML = notifications.map(notif => this.createNotificationHTML(notif)).join('');
    }

    createNotificationHTML(notif) {
        const iconMap = {
            'ticket_reply': '💬',
            'forum_warn': '⚠️',
            'forum_delete': '🗑️',
            'forum_suspend': '🚫',
            'competition': '🏆',
            'new_ticket': '📋',
            'general': 'ℹ️'
        };

        const icon = iconMap[notif.type] || 'ℹ️';
        const time = this.formatTime(notif.created_at);
        const unreadClass = !notif.is_read ? 'unread' : '';

        return `
            <div class="notification-item ${unreadClass}" onclick="notificationCenter.handleNotificationClick('${notif.reference_type}', ${notif.reference_id})">
                <div class="notification-icon notification-icon.${notif.type}">
                    ${icon}
                </div>
                <div class="notification-content">
                    <div class="notification-title">${this.escapeHtml(notif.title)}</div>
                    <div class="notification-message">${this.escapeHtml(notif.message)}</div>
                    <div class="notification-time">${time}</div>
                </div>
                <button class="notification-btn dismiss" onclick="event.stopPropagation(); notificationCenter.dismissNotification(${notif.id})">✕</button>
            </div>
        `;
    }

    handleNotificationClick(type, id) {
        // Navigate to relevant page based on notification type
        const routes = {
            'support_ticket': '/pages/support.php?ticket=' + id,
            'forum_post': '/pages/forum.php?post=' + id,
            'competition': '/pages/competitions.php?comp=' + id
        };

        if (routes[type]) {
            window.location.href = routes[type];
        }
    }

    dismissNotification(notifId) {
        fetch('/api/dismiss-notification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification_id: notifId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                this.loadNotifications();
            }
        })
        .catch(e => console.error(e));
    }

    updateUnreadCount(count) {
        this.unreadCount = count;
        // Update badge if exists
        const badge = document.getElementById('notification-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    formatTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;

        // Less than a minute
        if (diff < 60000) return 'Just now';
        
        // Less than an hour
        if (diff < 3600000) {
            const minutes = Math.floor(diff / 60000);
            return minutes + 'm ago';
        }
        
        // Less than a day
        if (diff < 86400000) {
            const hours = Math.floor(diff / 3600000);
            return hours + 'h ago';
        }
        
        // Less than a week
        if (diff < 604800000) {
            const days = Math.floor(diff / 86400000);
            return days + 'd ago';
        }
        
        // Otherwise show date
        return date.toLocaleDateString();
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    addNotification(title, message, type = 'general', referenceId = null, referenceType = null) {
        // Add notification immediately (before server sync)
        const notif = {
            id: Date.now(),
            title,
            message,
            type,
            is_read: false,
            reference_id: referenceId,
            reference_type: referenceType,
            created_at: new Date().toISOString()
        };
        
        this.notifications.unshift(notif);
        this.displayNotifications(this.notifications);
        this.unreadCount++;
        this.updateUnreadCount(this.unreadCount);
    }
}

// Initialize notification center when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof notificationCenter === 'undefined') {
        window.notificationCenter = new NotificationCenter({
            pollInterval: 30000 // 30 seconds
        });
    }
});
