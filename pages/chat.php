<?php
// pages/chat.php - Direct messaging system
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireLogin();

$user = getCurrentUser();
$conv_id = (int)($_GET['conv'] ?? 0);

// Fetch conversations from database with fallback
$conversations = [];
try {
    $stmt = $pdo->prepare("
        SELECT cc.id, cc.created_at,
               CASE 
                   WHEN cc.user1_id = ? THEN u2.username
                   ELSE u1.username
               END as name,
               CASE 
                   WHEN cc.user1_id = ? THEN cc.user2_id
                   ELSE cc.user1_id
               END as other_user_id,
               (SELECT message FROM chat_messages WHERE conversation_id = cc.id ORDER BY created_at DESC LIMIT 1) as last_message,
               0 as unread
        FROM chat_conversations cc
        LEFT JOIN users u1 ON cc.user1_id = u1.id
        LEFT JOIN users u2 ON cc.user2_id = u2.id
        WHERE cc.user1_id = ? OR cc.user2_id = ?
        ORDER BY cc.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Use sample data if database fails
}

// If no conversations from DB, use sample data
if (empty($conversations)) {
    $conversations = [
        ['id' => 1, 'name' => 'Sarah Chen', 'last_message' => 'Thanks for the feedback!', 'unread' => 0, 'created_at' => '2025-11-18', 'timestamp' => '10:30 AM'],
        ['id' => 2, 'name' => 'Alex Rivera', 'last_message' => 'Looking forward to your next chapter', 'unread' => 2, 'created_at' => '2025-11-17', 'timestamp' => '9:15 AM'],
        ['id' => 3, 'name' => 'Jordan Taylor', 'last_message' => 'Great collaboration!', 'unread' => 0, 'created_at' => '2025-11-16', 'timestamp' => '8:45 AM'],
    ];
}

// Fetch messages for selected conversation
$messages = [];
if ($conv_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT cm.id, cm.user_id, u.username, cm.message as content, 
                   DATE_FORMAT(cm.created_at, '%h:%i %p') as timestamp,
                   'read' as status
            FROM chat_messages cm
            LEFT JOIN users u ON cm.user_id = u.id
            WHERE cm.conversation_id = ?
            ORDER BY cm.created_at ASC
        ");
        $stmt->execute([$conv_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Use sample data if database fails
        $messages = [
            ['id' => 1, 'user_id' => 1, 'content' => 'Hey, how are you?', 'timestamp' => '10:30 AM', 'status' => 'read'],
            ['id' => 2, 'user_id' => 2, 'content' => 'Great! Working on my latest chapter.', 'timestamp' => '10:32 AM', 'status' => 'read'],
            ['id' => 3, 'user_id' => 1, 'content' => 'That sounds amazing!', 'timestamp' => '10:35 AM', 'status' => 'read'],
            ['id' => 4, 'user_id' => 2, 'content' => 'Thanks for the feedback!', 'timestamp' => '10:40 AM', 'status' => 'read'],
        ];
    }
}

$page_title = 'Chat - Scroll Novels';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Chat Page Styling */
body {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    color: #1f2937;
}

.dark body {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #e2e8f0;
}

.chat-container {
    height: calc(100vh - 200px);
    min-height: 500px;
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    display: flex;
    border: 1px solid #e5e7eb;
    margin: 1rem auto;
    max-width: 1200px;
}

.dark .chat-container {
    background: #1e293b;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
    border-color: #334155;
}

/* Sidebar - Conversations */
.chat-sidebar {
    width: 320px;
    background: #f8fafc;
    border-right: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}

.dark .chat-sidebar {
    background: #0f172a;
    border-color: #334155;
}

.chat-sidebar-header {
    padding: 1.25rem;
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.dark .chat-sidebar-header {
    border-color: #334155;
}

.chat-sidebar-header h2 {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0;
    color: white;
}

.chat-sidebar-header small {
    display: block;
    color: rgba(255,255,255,0.8);
    margin-top: 0.25rem;
    font-size: 0.875rem;
}

.conversation-list {
    overflow-y: auto;
    flex: 1;
}

.conversation-item {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.dark .conversation-item {
    border-color: #334155;
}

.conversation-item:hover {
    background: #ecfdf5;
}

.dark .conversation-item:hover {
    background: #334155;
}

.conversation-item.active {
    background: #d1fae5;
    border-left: 4px solid #10b981;
}

.dark .conversation-item.active {
    background: #064e3b;
}

.conversation-item-content {
    flex: 1;
    min-width: 0;
}

.conversation-item-name {
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 0.25rem;
}

.dark .conversation-item-name {
    color: #f1f5f9;
}

.conversation-item-preview {
    font-size: 0.875rem;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dark .conversation-item-preview {
    color: #94a3b8;
}

.conversation-item-time {
    font-size: 0.75rem;
    color: #94a3b8;
    white-space: nowrap;
    margin-left: 0.5rem;
}

.unread-badge {
    background: #10b981;
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.25rem 0.5rem;
    border-radius: 999px;
    margin-left: 0.5rem;
}

/* Chat Main Area */
.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: white;
}

.dark .chat-main {
    background: #1e293b;
}

.chat-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e2e8f0;
    background: #f9fafb;
}

.dark .chat-header {
    border-color: #334155;
    background: #0f172a;
}

.chat-header h3 {
    font-size: 1.125rem;
    font-weight: 700;
    margin: 0;
    color: #0f172a;
}

.dark .chat-header h3 {
    color: #f1f5f9;
}

.chat-header-status {
    font-size: 0.875rem;
    color: #10b981;
    margin-top: 0.25rem;
}

.messages-container {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    background: #fafafa;
}

.dark .messages-container {
    background: #1e293b;
}

.message {
    display: flex;
    max-width: 70%;
    animation: slideIn 0.3s ease;
}

.message.sent {
    margin-left: auto;
}

.message.received {
    margin-right: auto;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.message-bubble {
    padding: 0.875rem 1.25rem;
    border-radius: 1rem;
    max-width: 100%;
    word-wrap: break-word;
    font-size: 0.95rem;
    line-height: 1.5;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.message.sent .message-bubble {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border-bottom-right-radius: 0.25rem;
}

.message.received .message-bubble {
    background: white;
    color: #1f2937;
    border: 1px solid #e5e7eb;
    border-bottom-left-radius: 0.25rem;
}

.dark .message.received .message-bubble {
    background: #334155;
    color: #f1f5f9;
    border-color: #475569;
}

.message-time {
    font-size: 0.75rem;
    margin-top: 0.25rem;
    color: #9ca3af;
    text-align: right;
}

.message.received .message-time {
    text-align: left;
}

/* Empty State */
.empty-state {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    color: #64748b;
    padding: 2rem;
}

.dark .empty-state {
    color: #94a3b8;
}

.empty-state-icon {
    font-size: 4rem;
    margin-bottom: 1.5rem;
}

.empty-state-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.dark .empty-state-title {
    color: #f1f5f9;
}

/* Input Area */
.input-area {
    padding: 1rem 1.25rem;
    border-top: 1px solid #e2e8f0;
    background: white;
    flex-shrink: 0;
}

.dark .input-area {
    border-color: #334155;
    background: #0f172a;
}

.input-area form {
    display: flex;
    gap: 0.75rem;
}

.input-area input {
    flex: 1;
    padding: 0.875rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 0.75rem;
    background: white;
    color: #1f2937;
    font-size: 0.95rem;
    transition: all 0.2s ease;
}

.dark .input-area input {
    border-color: #475569;
    background: #1e293b;
    color: #f1f5f9;
}

.input-area input:focus {
    outline: none;
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
}

.input-area input::placeholder {
    color: #9ca3af;
}

.dark .input-area input::placeholder {
    color: #64748b;
}

.input-area button {
    padding: 0.875rem 1.5rem;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    border-radius: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.input-area button:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.input-area button:active {
    transform: translateY(0);
}

/* Scrollbar */
::-webkit-scrollbar {
    width: 6px;
}

::-webkit-scrollbar-track {
    background: transparent;
}

::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.dark ::-webkit-scrollbar-thumb {
    background: #475569;
}

::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Responsive */
@media (max-width: 768px) {
    .chat-container {
        height: calc(100vh - 150px);
        flex-direction: column;
        margin: 0.5rem;
        border-radius: 12px;
    }

    .chat-sidebar {
        width: 100%;
        max-height: 200px;
        border-right: none;
        border-bottom: 1px solid #e2e8f0;
    }

    .message {
        max-width: 85%;
    }
}

@media (max-width: 480px) {
    .message {
        max-width: 90%;
    }

    .message-bubble {
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
    }
}
</style>

<div class="chat-container">
    <!-- Conversations Sidebar -->
    <div class="chat-sidebar">
        <div class="chat-sidebar-header">
            <h2>Messages</h2>
            <small><?php echo htmlspecialchars($user['username']); ?></small>
        </div>

        <div class="conversation-list">
            <?php foreach ($conversations as $conv): ?>
                <div class="conversation-item <?= $conv_id === $conv['id'] ? 'active' : '' ?>" 
                     onclick="location.href='?conv=<?= $conv['id'] ?>'">
                    <div class="conversation-item-content">
                        <div class="conversation-item-name"><?= htmlspecialchars($conv['name']) ?></div>
                        <div class="conversation-item-preview"><?= htmlspecialchars($conv['last_message']) ?></div>
                    </div>
                    <div style="display: flex; align-items: center; flex-shrink: 0;">
                        <div class="conversation-item-time"><?= $conv['timestamp'] ?? date('h:i A', strtotime($conv['created_at'] ?? 'now')) ?></div>
                        <?php if ($conv['unread'] > 0): ?>
                            <span class="unread-badge"><?= $conv['unread'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Chat Main -->
    <div class="chat-main">
        <?php if ($conv_id): ?>
            <!-- Chat Header -->
            <div class="chat-header">
                <h3><?= htmlspecialchars($conversations[0]['name'] ?? 'Chat') ?></h3>
                <div class="chat-header-status">Active now</div>
            </div>

            <!-- Messages -->
            <div class="messages-container" id="messages">
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?= $msg['user_id'] === 1 ? 'sent' : 'received' ?>">
                        <div>
                            <div class="message-bubble"><?= htmlspecialchars($msg['content']) ?></div>
                            <div class="message-time"><?= date('H:i', strtotime($msg['timestamp'] ?? $msg['created_at'] ?? 'now')) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Input Area -->
            <div class="input-area">
                <form id="send-form">
                    <input type="text" id="messageInput" placeholder="Type a message..." required>
                    <button type="submit">Send</button>
                </form>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-state-icon">💬</div>
                <div class="empty-state-title">Select a conversation</div>
                <p>Choose from your messages to start chatting</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="/js/main-utils.js"></script>
<script>
// Create new conversation
async function createConversation(otherUserId) {
    try {
        const response = await fetch('/api/chat.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ other_user_id: otherUserId })
        });
        
        const data = await response.json();
        if (data.success) {
            window.location.href = '?conv=' + data.id;
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Error creating conversation:', error);
        alert('Error creating conversation');
    }
}

// Send message
document.getElementById('send-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const conv_id = new URLSearchParams(window.location.search).get('conv');
    const messageText = document.getElementById('messageInput').value.trim();
    
    if (!conv_id || !messageText) {
        alert('Please select a conversation first');
        return;
    }
    
    try {
        const response = await fetch('/api/chat.php?action=send_message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                conversation_id: parseInt(conv_id),
                message: messageText
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('messageInput').value = '';
            // Reload messages to show new message
            setTimeout(() => location.reload(), 500);
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Error sending message:', error);
        alert('Error sending message');
    }
});

// Load conversations on page load
window.addEventListener('load', async function() {
    try {
        const response = await fetch('/api/chat.php?action=get_conversations');
        const data = await response.json();
        
        if (data.success && data.data.length > 0) {
            console.log('Loaded conversations:', data.data);
            // Could update UI dynamically here
        }
    } catch (error) {
        console.error('Error loading conversations:', error);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
