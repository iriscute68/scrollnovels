<?php // admin/pages/support.php ?>
<h4>Support Tickets</h4>

<?php
$ticket_id = (int)($_GET['ticket'] ?? 0);

// Ensure replies table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS support_ticket_replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        admin_id INT NOT NULL,
        reply_text LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (ticket_id),
        FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Table may already exist
}

// If viewing a specific ticket
if ($ticket_id) {
    $ticket = $pdo->prepare("SELECT st.*, u.username, u.email FROM support_tickets st JOIN users u ON st.user_id = u.id WHERE st.id = ?");
    $ticket->execute([$ticket_id]);
    $ticket = $ticket->fetch();
    
    if (!$ticket) {
        echo "<div class='alert alert-warning'>Ticket not found.</div>";
    } else {
?>
    <div class="mb-3">
        <a href="admin.php?page=support" class="btn btn-secondary">← Back to List</a>
    </div>
    
    <div class="card mb-3">
        <div class="card-header">
            <h5>#<?= $ticket['id'] ?> - <?= htmlspecialchars($ticket['subject']) ?></h5>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <p><strong>User:</strong> <?= htmlspecialchars($ticket['username']) ?> (<?= htmlspecialchars($ticket['email']) ?>)</p>
                    <p><strong>Category:</strong> <?= htmlspecialchars($ticket['category']) ?></p>
                    <p><strong>Priority:</strong> <span class="badge bg-<?= $ticket['priority'] === 'urgent' ? 'danger' : ($ticket['priority'] === 'high' ? 'warning' : 'info') ?>"><?= htmlspecialchars($ticket['priority']) ?></span></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Status:</strong> <span class="badge bg-warning"><?= htmlspecialchars($ticket['status']) ?></span></p>
                    <p><strong>Created:</strong> <?= date('M d, Y H:i', strtotime($ticket['created_at'])) ?></p>
                    <p><strong>Updated:</strong> <?= date('M d, Y H:i', strtotime($ticket['updated_at'])) ?></p>
                </div>
            </div>
            
            <hr>
            
            <div class="mb-3">
                <h6>Original Message:</h6>
                <div class="p-3 bg-light rounded">
                    <?= nl2br(htmlspecialchars($ticket['message'] ?? $ticket['description'] ?? 'No message')) ?>
                </div>
            </div>
            
            <hr>
            
            <!-- Replies Section -->
            <div class="mb-4">
                <h6>Replies:</h6>
                <div id="replies-section" style="max-height: 400px; overflow-y: auto; margin-bottom: 15px;">
                    <?php
                    $replies = $pdo->prepare("
                        SELECT sr.*, u.username 
                        FROM support_ticket_replies sr 
                        JOIN users u ON sr.admin_id = u.id 
                        WHERE sr.ticket_id = ? 
                        ORDER BY sr.created_at ASC
                    ");
                    $replies->execute([$ticket_id]);
                    $replies = $replies->fetchAll();
                    
                    if (empty($replies)) {
                        echo '<p class="text-muted">No replies yet.</p>';
                    } else {
                        foreach ($replies as $reply) {
                            ?>
                            <div class="p-3 mb-2 bg-light rounded border-left border-primary" style="border-left: 4px solid #007bff;">
                                <strong><?= htmlspecialchars($reply['username']) ?></strong> 
                                <small class="text-muted"><?= date('M d, Y H:i', strtotime($reply['created_at'])) ?></small>
                                <div class="mt-2">
                                    <?= nl2br(htmlspecialchars($reply['reply_text'])) ?>
                                </div>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
            </div>
            
            <!-- Add Reply Form -->
            <div class="mb-3 p-3 bg-white border rounded">
                <h6>Add Reply:</h6>
                <form method="POST" action="<?= site_url('/api/admin/add-ticket-reply.php') ?>" onsubmit="return handleReplySubmit(event)">
                    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
                    <textarea name="reply_text" class="form-control" rows="4" required placeholder="Type your reply here..."></textarea>
                    <div class="mt-2">
                        <button type="submit" class="btn btn-primary">Send Reply</button>
                    </div>
                </form>
            </div>
            
            <hr>
            
            <div class="mb-3">
                <h6>Admin Actions:</h6>
                <form method="POST" class="d-flex gap-2" style="display: inline-flex;">
                    <select name="status" class="form-select" style="width: auto;">
                        <option value="">Change Status...</option>
                        <option value="open">Open</option>
                        <option value="in_progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                    <button type="submit" name="action" value="update_status" class="btn btn-primary">Update</button>
                    <button type="submit" name="action" value="delete" class="btn btn-danger" onclick="return confirm('Delete this ticket?')">Delete</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function handleReplySubmit(e) {
        e.preventDefault();
        const ticket_id = <?= $ticket_id ?>;
        const reply_text = document.querySelector('[name="reply_text"]').value;
        
        fetch('<?= site_url('/api/admin/add-ticket-reply.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'ticket_id=' + ticket_id + '&reply_text=' + encodeURIComponent(reply_text)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Reply sent successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to send reply'));
            }
        })
        .catch(err => alert('Error: ' + err.message));
        
        return false;
    }
    </script>
    
    <?php
        // Handle form actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            
            if ($action === 'update_status' && !empty($_POST['status'])) {
                $pdo->prepare("UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$_POST['status'], $ticket_id]);
                echo "<div class='alert alert-success'>Ticket status updated.</div>";
            } elseif ($action === 'delete') {
                $pdo->prepare("DELETE FROM support_tickets WHERE id = ?")->execute([$ticket_id]);
                header("Location: " . site_url('/admin/admin.php?page=support'));
                exit;
            }
        }
    ?>
<?php
    }
} else {
    // List all tickets
    $tickets = $pdo->query("SELECT st.*, u.username FROM support_tickets st JOIN users u ON st.user_id = u.id WHERE st.status IN ('open', 'in_progress', 'pending') ORDER BY st.created_at DESC LIMIT 50")->fetchAll();
?>
<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr><th>ID</th><th>User</th><th>Subject</th><th>Category</th><th>Status</th><th>Priority</th><th>Created</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $t): ?>
                <tr>
                    <td>#<?= $t['id'] ?></td>
                    <td><?= htmlspecialchars($t['username']) ?></td>
                    <td><?= htmlspecialchars(substr($t['subject'], 0, 40)) ?></td>
                    <td><?= htmlspecialchars($t['category']) ?></td>
                    <td><span class="badge bg-warning"><?= htmlspecialchars($t['status']) ?></span></td>
                    <td><span class="badge bg-<?= $t['priority'] === 'urgent' ? 'danger' : ($t['priority'] === 'high' ? 'warning' : 'info') ?>"><?= htmlspecialchars($t['priority']) ?></span></td>
                    <td><?= date('M d', strtotime($t['created_at'])) ?></td>
                    <td>
                        <a href="<?= site_url('/admin/admin.php?page=support&ticket=' . $t['id']) ?>" class="btn btn-sm btn-info">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php } ?>
