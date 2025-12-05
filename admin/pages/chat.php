<?php
// admin/pages/chat.php - Chat Management
$conversations = [];
try {
    $stmt = $pdo->query("
        SELECT cc.id, cc.created_at,
               u1.username as user1_name,
               u2.username as user2_name,
               (SELECT COUNT(*) FROM chat_messages WHERE conversation_id = cc.id) as message_count,
               (SELECT message FROM chat_messages WHERE conversation_id = cc.id ORDER BY created_at DESC LIMIT 1) as last_message
        FROM chat_conversations cc
        LEFT JOIN users u1 ON cc.user1_id = u1.id
        LEFT JOIN users u2 ON cc.user2_id = u2.id
        ORDER BY cc.created_at DESC
        LIMIT 100
    ");
    $conversations = $stmt->fetchAll();
} catch (Exception $e) {
    // Table might not exist yet
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3>Chat Conversations</h3>
    </div>
    <div class="col-md-6 text-end">
        <button class="btn btn-primary" onclick="showCreateConversation()"><i class="fas fa-plus"></i> Create Conversation</button>
    </div>
</div>

<!-- Create Conversation Form -->
<div id="create-conversation-form" style="display: none; margin-bottom: 30px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
    <h5>Create New Conversation</h5>
    <form id="conversation-form" method="POST">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">User 1 <span class="text-danger">*</span></label>
                    <select class="form-control" id="user1" required>
                        <option value="">Select User...</option>
                        <?php 
                        $users = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll();
                        foreach ($users as $user): 
                        ?>
                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">User 2 <span class="text-danger">*</span></label>
                    <select class="form-control" id="user2" required>
                        <option value="">Select User...</option>
                        <?php 
                        foreach ($users as $user): 
                        ?>
                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Create</button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('create-conversation-form').style.display = 'none'"><i class="fas fa-times"></i> Cancel</button>
        </div>
    </form>
</div>

<!-- Conversations List -->
<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>User 1</th>
                <th>User 2</th>
                <th>Messages</th>
                <th>Last Message</th>
                <th>Started</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($conversations)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted">No conversations yet</td>
                </tr>
            <?php else: foreach ($conversations as $conv): ?>
                <tr>
                    <td><?= htmlspecialchars($conv['user1_name'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars($conv['user2_name'] ?? 'Unknown') ?></td>
                    <td><span class="badge bg-info"><?= $conv['message_count'] ?></span></td>
                    <td><?= htmlspecialchars(substr($conv['last_message'] ?? 'No messages', 0, 50)) ?>...</td>
                    <td><?= date('M d, Y H:i', strtotime($conv['created_at'])) ?></td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="viewConversation(<?= $conv['id'] ?>)"><i class="fas fa-eye"></i> View</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteConversation(<?= $conv['id'] ?>)"><i class="fas fa-trash"></i> Delete</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- View Conversation Modal -->
<div id="conversation-viewer" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; overflow: auto;">
    <div style="background: white; margin: 20px auto; border-radius: 8px; width: 90%; max-width: 800px; padding: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h5>Conversation Messages</h5>
            <button onclick="document.getElementById('conversation-viewer').style.display = 'none'" class="btn btn-close"></button>
        </div>
        <div id="messages-container" style="max-height: 500px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; border-radius: 4px; margin-bottom: 15px;"></div>
    </div>
</div>

<script>
function showCreateConversation() {
    document.getElementById('create-conversation-form').style.display = 'block';
}

async function viewConversation(conversationId) {
    try {
        const response = await fetch(`/api/chat.php?action=get_messages&conversation_id=${conversationId}`);
        const data = await response.json();
        
        if (data.success) {
            const container = document.getElementById('messages-container');
            container.innerHTML = '';
            
            if (data.data.length === 0) {
                container.innerHTML = '<p class="text-muted">No messages in this conversation</p>';
            } else {
                data.data.forEach(msg => {
                    const div = document.createElement('div');
                    div.style.marginBottom = '10px';
                    div.innerHTML = `
                        <strong>${htmlEscape(msg.username || 'Unknown')}</strong> <small class="text-muted">${msg.created_at}</small><br>
                        <p>${htmlEscape(msg.message)}</p>
                    `;
                    container.appendChild(div);
                });
            }
            
            document.getElementById('conversation-viewer').style.display = 'block';
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error loading conversation');
    }
}

async function deleteConversation(conversationId) {
    if (!confirm('Delete this conversation and all its messages?')) return;
    
    try {
        // Delete all messages first, then conversation
        // For now just notify admin to handle manually
        alert('Conversation deletion would be handled by admin API endpoint');
    } catch (error) {
        console.error('Error:', error);
    }
}

function htmlEscape(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

document.getElementById('conversation-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const user1 = document.getElementById('user1').value;
    const user2 = document.getElementById('user2').value;
    
    if (!user1 || !user2) {
        alert('Please select both users');
        return;
    }
    
    if (user1 === user2) {
        alert('Cannot create conversation between same user');
        return;
    }
    
    try {
        const response = await fetch('/api/chat.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ other_user_id: user2 })
        });
        
        const data = await response.json();
        if (data.success) {
            alert('Conversation created successfully');
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error creating conversation');
    }
});
</script>
