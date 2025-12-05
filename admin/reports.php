<?php
// admin/reports.php - View and manage story reports
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireLogin();

if (!hasRole('admin')) {
    header("Location: ../pages/dashboard.php");
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $report_id = (int)($_POST['id'] ?? 0);
    
    if ($action === 'update_status') {
        $status = $_POST['status'] ?? 'pending';
        try {
            $stmt = $pdo->prepare("UPDATE story_reports SET status = ? WHERE id = ?");
            $stmt->execute([$status, $report_id]);
        } catch (Exception $e) {}
    } elseif ($action === 'delete_report') {
        try {
            $stmt = $pdo->prepare("DELETE FROM story_reports WHERE id = ?");
            $stmt->execute([$report_id]);
        } catch (Exception $e) {}
    } elseif ($action === 'remove_story') {
        // Get story id from report
        $stmt = $pdo->prepare("SELECT story_id FROM story_reports WHERE id = ?");
        $stmt->execute([$report_id]);
        $report = $stmt->fetch();
        
        if ($report) {
            // Delete the story
            $deleteStmt = $pdo->prepare("DELETE FROM stories WHERE id = ?");
            $deleteStmt->execute([$report['story_id']]);
            
            // Update report status
            $updateStmt = $pdo->prepare("UPDATE story_reports SET status = 'resolved' WHERE id = ?");
            $updateStmt->execute([$report_id]);
        }
    } elseif ($action === 'warn_author') {
        $message = $_POST['message'] ?? 'Your story has been reported. Please review our guidelines.';
        $stmt = $pdo->prepare("
            SELECT reporter_id FROM story_reports WHERE id = ?
        ");
        $stmt->execute([$report_id]);
        // In a real system, you'd send a notification/email here
        
        try {
            $updateStmt = $pdo->prepare("UPDATE story_reports SET status = 'warned' WHERE id = ?");
            $updateStmt->execute([$report_id]);
        } catch (Exception $e) {}
    }
    
    header("Location: reports.php");
    exit;
}

// Get filter
$filter = $_GET['filter'] ?? 'pending';
$whereSql = '';

if ($filter === 'pending') {
    $whereSql = " WHERE sr.status = 'pending'";
} elseif ($filter === 'resolved') {
    $whereSql = " WHERE sr.status = 'resolved'";
} elseif ($filter === 'warned') {
    $whereSql = " WHERE sr.status = 'warned'";
}

// Ensure table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS story_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        story_id INT NOT NULL,
        reporter_id INT NOT NULL,
        reason VARCHAR(255) NOT NULL,
        description LONGTEXT,
        status ENUM('pending', 'resolved', 'warned') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
        FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (status),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {}

// Fetch reports with story and user info
try {
    $sql = "
        SELECT sr.*, s.title as story_title, s.author_id,
               u.username as reporter_name, u.email as reporter_email,
               au.username as author_name
        FROM story_reports sr
        JOIN stories s ON sr.story_id = s.id
        JOIN users u ON sr.reporter_id = u.id
        JOIN users au ON s.author_id = au.id
    " . $whereSql . " ORDER BY sr.created_at DESC LIMIT 200";
    
    $reports = $pdo->query($sql)->fetchAll();
} catch (Exception $e) {
    $reports = [];
}

// Get counts
try {
    $pending_count = $pdo->query("SELECT COUNT(*) FROM story_reports WHERE status = 'pending'")->fetchColumn();
    $resolved_count = $pdo->query("SELECT COUNT(*) FROM story_reports WHERE status = 'resolved'")->fetchColumn();
    $warned_count = $pdo->query("SELECT COUNT(*) FROM story_reports WHERE status = 'warned'")->fetchColumn();
    $total_count = $pending_count + $resolved_count + $warned_count;
} catch (Exception $e) {
    $pending_count = $resolved_count = $warned_count = $total_count = 0;
}

include dirname(__DIR__) . '/includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Story Reports</h2>
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
                    <small>Total Reports</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-danger">
                <div class="card-body">
                    <h5><?= $pending_count ?></h5>
                    <small>Pending</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h5><?= $warned_count ?></h5>
                    <small>Warned</small>
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
        <a href="?filter=pending" class="btn btn-outline-danger <?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="?filter=warned" class="btn btn-outline-warning <?= $filter === 'warned' ? 'active' : '' ?>">Warned</a>
        <a href="?filter=resolved" class="btn btn-outline-success <?= $filter === 'resolved' ? 'active' : '' ?>">Resolved</a>
    </div>

    <!-- Reports Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Story</th>
                    <th>Author</th>
                    <th>Reporter</th>
                    <th>Reason</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $r): ?>
                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><strong><?= htmlspecialchars(substr($r['story_title'], 0, 30)) ?></strong></td>
                    <td><?= htmlspecialchars($r['author_name']) ?></td>
                    <td><?= htmlspecialchars($r['reporter_name']) ?> <br><small><?= htmlspecialchars($r['reporter_email']) ?></small></td>
                    <td><?= htmlspecialchars($r['reason']) ?></td>
                    <td><small><?= date('M d, Y', strtotime($r['created_at'])) ?></small></td>
                    <td>
                        <span class="badge bg-<?= $r['status'] === 'pending' ? 'danger' : ($r['status'] === 'warned' ? 'warning' : 'success') ?>">
                            <?= ucfirst($r['status']) ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#reportModal" onclick="viewReport(<?= json_encode($r) ?>)">View</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (empty($reports)): ?>
    <div class="alert alert-info">No reports found.</div>
    <?php endif; ?>
</div>

<!-- Report Detail Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Report #<span id="reportId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label"><strong>Story:</strong></label>
                        <p id="reportStory"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><strong>Author:</strong></label>
                        <p id="reportAuthor"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label"><strong>Reporter:</strong></label>
                        <p id="reportReporter"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><strong>Status:</strong></label>
                        <p id="reportStatus"></p>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Reason:</strong></label>
                    <p id="reportReason"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Description:</strong></label>
                    <div class="border p-3 bg-light" id="reportDescription" style="max-height: 200px; overflow-y: auto;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <form method="POST" class="d-inline" id="reportActionForm">
                    <input type="hidden" name="id" id="reportIdInput">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-warning" name="action" value="warn_author" onclick="return confirm('Send warning to author?')">Warn Author</button>
                    <button type="button" class="btn btn-danger" onclick="if(confirm('Delete this story? This cannot be undone!')) deleteStory()">Remove Story</button>
                    <button type="button" class="btn btn-success" onclick="markResolved()">Mark Resolved</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function viewReport(report) {
    document.getElementById('reportId').textContent = report.id;
    document.getElementById('reportStory').textContent = report.story_title;
    document.getElementById('reportAuthor').textContent = report.author_name;
    document.getElementById('reportReporter').innerHTML = report.reporter_name + ' <br><a href="mailto:' + report.reporter_email + '">' + report.reporter_email + '</a>';
    document.getElementById('reportStatus').innerHTML = '<span class="badge bg-' + (report.status === 'pending' ? 'danger' : (report.status === 'warned' ? 'warning' : 'success')) + '">' + report.status.charAt(0).toUpperCase() + report.status.slice(1) + '</span>';
    document.getElementById('reportReason').textContent = report.reason;
    document.getElementById('reportDescription').textContent = report.description || 'No additional description provided.';
    document.getElementById('reportIdInput').value = report.id;
}

function markResolved() {
    const form = document.getElementById('reportActionForm');
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'update_status';
    form.appendChild(actionInput);
    
    const statusInput = document.createElement('input');
    statusInput.type = 'hidden';
    statusInput.name = 'status';
    statusInput.value = 'resolved';
    form.appendChild(statusInput);
    
    form.submit();
}

function deleteStory() {
    const form = document.getElementById('reportActionForm');
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'remove_story';
    form.appendChild(actionInput);
    
    form.submit();
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
