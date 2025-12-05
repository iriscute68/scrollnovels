<?php
// admin/support-tickets.php - View and manage support tickets
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireLogin();

if (!hasRole('admin')) {
    header("Location: ../pages/dashboard.php");
    exit;
}

$action = $_GET['action'] ?? '';
$ticket_id = (int)($_GET['id'] ?? 0);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ticket_id = (int)($_POST['id'] ?? 0);
    
    if ($action === 'mark_read') {
        try {
            $stmt = $pdo->prepare("UPDATE contacts SET status = 'read' WHERE id = ?");
            $stmt->execute([$ticket_id]);
        } catch (Exception $e) {}
    } elseif ($action === 'mark_resolved') {
        try {
            $stmt = $pdo->prepare("UPDATE contacts SET status = 'resolved' WHERE id = ?");
            $stmt->execute([$ticket_id]);
        } catch (Exception $e) {}
    } elseif ($action === 'delete') {
        try {
            $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ?");
            $stmt->execute([$ticket_id]);
        } catch (Exception $e) {}
    }
    
    header("Location: support-tickets.php");
    exit;
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$whereSql = '';
if ($filter === 'new') {
    $whereSql = " WHERE status = 'new'";
} elseif ($filter === 'read') {
    $whereSql = " WHERE status = 'read'";
} elseif ($filter === 'resolved') {
    $whereSql = " WHERE status = 'resolved'";
}

try {
    $stmt = $pdo->query("CREATE TABLE IF NOT EXISTS contacts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('new', 'read', 'resolved') DEFAULT 'new',
        INDEX (status),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {}

// Fetch tickets
try {
    $sql = "SELECT * FROM contacts" . $whereSql . " ORDER BY created_at DESC LIMIT 100";
    $tickets = $pdo->query($sql)->fetchAll();
} catch (Exception $e) {
    $tickets = [];
}

// Get counts
try {
    $new_count = $pdo->query("SELECT COUNT(*) FROM contacts WHERE status = 'new'")->fetchColumn();
    $read_count = $pdo->query("SELECT COUNT(*) FROM contacts WHERE status = 'read'")->fetchColumn();
    $resolved_count = $pdo->query("SELECT COUNT(*) FROM contacts WHERE status = 'resolved'")->fetchColumn();
    $total_count = $new_count + $read_count + $resolved_count;
} catch (Exception $e) {
    $new_count = $read_count = $resolved_count = $total_count = 0;
}

include dirname(__DIR__) . '/includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Support Tickets</h2>
        </div>
        <div class="col-md-6 text-end">
            <a href="admin.php" class="btn btn-secondary">Back to Admin</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h5><?= $total_count ?></h5>
                    <small>Total Tickets</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-danger">
                <div class="card-body">
                    <h5><?= $new_count ?></h5>
                    <small>New</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h5><?= $read_count ?></h5>
                    <small>Read</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5><?= $resolved_count ?></h5>
                    <small>Resolved</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="btn-group mb-4" role="group">
        <a href="?filter=all" class="btn btn-outline-primary <?= $filter === 'all' ? 'active' : '' ?>">All</a>
        <a href="?filter=new" class="btn btn-outline-danger <?= $filter === 'new' ? 'active' : '' ?>">New</a>
        <a href="?filter=read" class="btn btn-outline-warning <?= $filter === 'read' ? 'active' : '' ?>">Read</a>
        <a href="?filter=resolved" class="btn btn-outline-success <?= $filter === 'resolved' ? 'active' : '' ?>">Resolved</a>
    </div>

    <!-- Tickets Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Subject</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $t): ?>
                <tr>
                    <td><?= $t['id'] ?></td>
                    <td><?= htmlspecialchars($t['name']) ?></td>
                    <td><a href="mailto:<?= htmlspecialchars($t['email']) ?>"><?= htmlspecialchars($t['email']) ?></a></td>
                    <td>
                        <strong><?= htmlspecialchars(substr($t['subject'], 0, 40)) ?></strong>
                        <?php if (strlen($t['subject']) > 40): ?>...<?php endif; ?>
                    </td>
                    <td><small><?= date('M d, Y H:i', strtotime($t['created_at'])) ?></small></td>
                    <td>
                        <span class="badge bg-<?= $t['status'] === 'new' ? 'danger' : ($t['status'] === 'read' ? 'warning' : 'success') ?>">
                            <?= ucfirst($t['status']) ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#ticketModal" onclick="viewTicket(<?= $t['id'] ?>, <?= json_encode($t) ?>)">View</button>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button class="btn btn-sm btn-danger" onclick="return confirm('Delete this ticket?')">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (empty($tickets)): ?>
    <div class="alert alert-info">No support tickets found.</div>
    <?php endif; ?>
</div>

<!-- Ticket Detail Modal -->
<div class="modal fade" id="ticketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Support Ticket #<span id="ticketId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Name:</label>
                    <p id="ticketName"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email:</label>
                    <p id="ticketEmail"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Subject:</label>
                    <p id="ticketSubject"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Message:</label>
                    <div class="border p-3 bg-light" id="ticketMessage" style="max-height: 300px; overflow-y: auto;"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Status:</label>
                    <p id="ticketStatus"></p>
                </div>
            </div>
            <div class="modal-footer">
                <form method="POST" class="d-inline" id="statusForm">
                    <input type="hidden" name="action" id="actionInput">
                    <input type="hidden" name="id" id="ticketIdInput">
                    <button type="submit" class="btn btn-primary" id="markReadBtn">Mark as Read</button>
                    <button type="submit" class="btn btn-success" id="markResolvedBtn">Mark as Resolved</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function viewTicket(id, ticket) {
    document.getElementById('ticketId').textContent = id;
    document.getElementById('ticketName').textContent = ticket.name;
    document.getElementById('ticketEmail').innerHTML = '<a href="mailto:' + ticket.email + '">' + ticket.email + '</a>';
    document.getElementById('ticketSubject').textContent = ticket.subject;
    document.getElementById('ticketMessage').textContent = ticket.message;
    document.getElementById('ticketStatus').textContent = ticket.status.charAt(0).toUpperCase() + ticket.status.slice(1);
    document.getElementById('ticketIdInput').value = id;
    
    // Update button onclick handlers
    document.getElementById('markReadBtn').onclick = function(e) {
        e.preventDefault();
        document.getElementById('actionInput').value = 'mark_read';
        document.getElementById('statusForm').submit();
    };
    
    document.getElementById('markResolvedBtn').onclick = function(e) {
        e.preventDefault();
        document.getElementById('actionInput').value = 'mark_resolved';
        document.getElementById('statusForm').submit();
    };
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
