<?php
// admin/support-messages.php - Manage user support messages

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__FILE__) . '/../config.php';

// Check if user is moderator or admin
$userRoles = $_SESSION['roles'] ?? [];
if (is_string($userRoles)) {
    $userRoles = json_decode($userRoles, true) ?: [];
}

if (!isset($_SESSION['user_id']) || (!in_array('admin', $userRoles) && !in_array('mod', $userRoles))) {
    if (!headers_sent()) {
        header("Location: login.php");
        exit;
    } else {
        echo '<script>window.location.href="login.php";</script>';
        exit;
    }
}

// Get support messages with filters
$filter = $_GET['filter'] ?? 'open';
$query = "SELECT sm.*, u.username, u.profile_image FROM support_messages sm 
          LEFT JOIN users u ON sm.user_id = u.id WHERE 1=1";
$params = [];

if ($filter !== 'all') {
    $query .= " AND sm.status = ?";
    $params[] = $filter;
}

$query .= " ORDER BY sm.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$messages = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Messages - Admin Dashboard</title>
    <link rel="stylesheet" href="<?= SITE_URL; ?>/assets/css/style.css">
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .admin-header h1 {
            font-size: 2rem;
            margin: 0;
            color: #1a1a1a;
        }

        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            color: #666;
        }

        .filter-btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .filter-btn:hover {
            border-color: #3498db;
        }

        .messages-list {
            display: grid;
            gap: 15px;
        }

        .message-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .message-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-color: #3498db;
        }

        .message-card.unread {
            background: #f0f7ff;
            border-color: #3498db;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background: #e0e0e0;
        }

        .user-details h4 {
            margin: 0;
            font-size: 0.95rem;
            color: #1a1a1a;
        }

        .user-details p {
            margin: 2px 0 0 0;
            font-size: 0.85rem;
            color: #666;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-open {
            background: rgba(52, 152, 219, 0.2);
            color: #2980b9;
        }

        .badge-in_progress {
            background: rgba(230, 126, 34, 0.2);
            color: #d35400;
        }

        .badge-resolved {
            background: rgba(39, 174, 96, 0.2);
            color: #27ae60;
        }

        .badge-closed {
            background: rgba(149, 165, 166, 0.2);
            color: #7f8c8d;
        }

        .message-subject {
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .message-preview {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.4;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .message-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: #999;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 30px;
            max-width: 800px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #1a1a1a;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
            border: none;
            background: none;
        }

        .message-thread {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }

        .thread-message {
            margin-bottom: 15px;
            padding: 12px;
            background: white;
            border-radius: 4px;
            border-left: 4px solid #e0e0e0;
        }

        .thread-message.user {
            border-left-color: #3498db;
        }

        .thread-message.moderator {
            border-left-color: #27ae60;
        }

        .thread-sender {
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .thread-text {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.5;
            word-wrap: break-word;
        }

        .thread-time {
            font-size: 0.8rem;
            color: #999;
            margin-top: 8px;
        }

        .reply-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .reply-form textarea {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-height: 80px;
            font-family: inherit;
            resize: vertical;
        }

        .reply-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #27ae60;
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            background: #229954;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
            flex: 1;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-close {
            background: #e74c3c;
            color: white;
            flex: 1;
        }

        .btn-close:hover {
            background: #c0392b;
        }

        .status-info {
            background: #f0f7ff;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 4px solid #3498db;
        }

        .status-info strong {
            color: #2980b9;
        }
    </style>
</head>
<body>
    <?php include 'inc/admin-header.php'; ?>

    <div class="admin-container">
        <div class="admin-header">
            <h1>💬 Support Messages</h1>
        </div>

        <div class="filters">
            <a href="?filter=open" class="filter-btn <?= $filter === 'open' ? 'active' : '' ?>">
                Open (<?= count(array_filter($messages, fn($m) => $m['status'] === 'open')); ?>)
            </a>
            <a href="?filter=in_progress" class="filter-btn <?= $filter === 'in_progress' ? 'active' : '' ?>">
                In Progress (<?= count(array_filter($messages, fn($m) => $m['status'] === 'in_progress')); ?>)
            </a>
            <a href="?filter=resolved" class="filter-btn <?= $filter === 'resolved' ? 'active' : '' ?>">
                Resolved (<?= count(array_filter($messages, fn($m) => $m['status'] === 'resolved')); ?>)
            </a>
            <a href="?filter=closed" class="filter-btn <?= $filter === 'closed' ? 'active' : '' ?>">
                Closed (<?= count(array_filter($messages, fn($m) => $m['status'] === 'closed')); ?>)
            </a>
            <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                All
            </a>
        </div>

        <?php if (count($messages) > 0): ?>
            <div class="messages-list">
                <?php foreach ($messages as $msg): ?>
                    <div class="message-card <?= $msg['status'] === 'open' ? 'unread' : ''; ?>" onclick="openMessage(<?= $msg['id']; ?>)">
                        <div class="message-header">
                            <div class="user-info">
                                <?php if ($msg['avatar']): ?>
                                    <img src="<?= htmlspecialchars($msg['avatar']); ?>" alt="Avatar" class="user-avatar">
                                <?php else: ?>
                                    <div class="user-avatar" style="background: #ccc;"></div>
                                <?php endif; ?>
                                <div class="user-details">
                                    <h4><?= htmlspecialchars($msg['username'] ?? 'Unknown'); ?></h4>
                                    <p><?= date('M j, Y \a\t g:i A', strtotime($msg['created_at'])); ?></p>
                                </div>
                            </div>
                            <span class="badge badge-<?= $msg['status']; ?>">
                                <?= ucfirst(str_replace('_', ' ', $msg['status'])); ?>
                            </span>
                        </div>
                        <div class="message-subject"><?= htmlspecialchars($msg['subject']); ?></div>
                        <div class="message-preview"><?= htmlspecialchars($msg['message']); ?></div>
                        <div class="message-meta">
                            <span>Last updated: <?= date('g:i A', strtotime($msg['updated_at'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <p>No support messages found.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Message Modal -->
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>Support Ticket</div>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>

            <div id="messageDetails"></div>

            <div id="messageThread" class="message-thread"></div>

            <div id="replyForm" class="reply-form" style="display: none;">
                <textarea id="replyText" placeholder="Type your reply..."></textarea>
                <div class="reply-buttons">
                    <button class="btn btn-primary" onclick="sendReply()">Send Reply</button>
                    <button class="btn btn-close" onclick="markResolved()">Mark Resolved</button>
                    <button class="btn btn-secondary" onclick="closeModal()">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentMessageId = null;

        async function openMessage(messageId) {
            currentMessageId = messageId;
            const modal = document.getElementById('messageModal');

            try {
                const response = await fetch('<?= SITE_URL; ?>/api/admin-verification.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=get_support_messages&id=' + messageId
                });
                const data = await response.json();

                if (data.success && data.messages.length > 0) {
                    const msg = data.messages[0];
                    const details = `
                        <div class="status-info">
                            <strong>Status:</strong> <span class="badge badge-${msg.status}">${msg.status.replace('_', ' ').toUpperCase()}</span>
                            <br><strong>From:</strong> ${msg.username}
                        </div>
                    `;
                    document.getElementById('messageDetails').innerHTML = details;

                    const thread = `
                        <div class="thread-message user">
                            <div class="thread-sender">${msg.username}</div>
                            <div class="thread-text">${msg.message}</div>
                            <div class="thread-time">${new Date(msg.created_at).toLocaleString()}</div>
                        </div>
                    `;
                    document.getElementById('messageThread').innerHTML = thread;

                    document.getElementById('replyForm').style.display = 'flex';
                }
            } catch (err) {
                console.error('Error:', err);
            }

            modal.classList.add('show');
        }

        function closeModal() {
            document.getElementById('messageModal').classList.remove('show');
        }

        async function sendReply() {
            const text = document.getElementById('replyText').value.trim();
            if (!text) {
                alert('Please enter a reply');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'reply_support');
                formData.append('message_id', currentMessageId);
                formData.append('message', text);

                const response = await fetch('<?= SITE_URL; ?>/api/admin-verification.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    alert('Reply sent!');
                    location.reload();
                } else {
                    alert('Error sending reply');
                }
            } catch (err) {
                console.error('Error:', err);
                alert('Error sending reply');
            }
        }

        async function markResolved() {
            try {
                const formData = new FormData();
                formData.append('action', 'close_support');
                formData.append('message_id', currentMessageId);

                const response = await fetch('<?= SITE_URL; ?>/api/admin-verification.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    alert('Ticket resolved!');
                    location.reload();
                } else {
                    alert('Error closing ticket');
                }
            } catch (err) {
                console.error('Error:', err);
                alert('Error closing ticket');
            }
        }

        // Close modal on background click
        document.getElementById('messageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
