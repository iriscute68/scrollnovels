<?php
// admin/pages/reports.php
$reports = $pdo->query("
    SELECT r.*, u.username, 
           CASE 
               WHEN r.story_id IS NOT NULL THEN 'Story'
               WHEN r.chapter_id IS NOT NULL THEN 'Chapter'
               WHEN r.comment_id IS NOT NULL THEN 'Comment'
               ELSE 'Other'
           END as type
    FROM content_reports r
    LEFT JOIN users u ON r.reported_by = u.id
    ORDER BY r.created_at DESC
    LIMIT 100
")->fetchAll();
?>

<div class="row mb-3">
    <div class="col">
        <h3>Content Reports</h3>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Type</th>
                <th>Reported By</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reports as $report): ?>
            <tr>
                <td><span class="badge bg-warning"><?= $report['type'] ?></span></td>
                <td><?= htmlspecialchars($report['username']) ?></td>
                <td><?= substr(htmlspecialchars($report['reason']), 0, 40) ?>...</td>
                <td>
                    <?php if ($report['status'] === 'pending'): ?>
                        <span class="badge bg-warning">Pending</span>
                    <?php elseif ($report['status'] === 'resolved'): ?>
                        <span class="badge bg-success">Resolved</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Dismissed</span>
                    <?php endif; ?>
                </td>
                <td><?= date('M d, Y', strtotime($report['created_at'])) ?></td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="viewReport(<?= $report['id'] ?>)"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-sm btn-success" onclick="resolveReport(<?= $report['id'] ?>)"><i class="fas fa-check"></i></button>
                    <button class="btn btn-sm btn-secondary" onclick="dismissReport(<?= $report['id'] ?>)"><i class="fas fa-times"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Report Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="reportBody"></div>
        </div>
    </div>
</div>

<script>
function viewReport(id) {
    fetch('/api/admin/get-report.php?id=' + id)
        .then(r => r.json())
        .then(d => {
            document.getElementById('reportBody').innerHTML = `
                <p><strong>Reported By:</strong> ${d.username}</p>
                <p><strong>Reason:</strong> ${d.reason}</p>
                <p><strong>Description:</strong></p>
                <p>${d.description}</p>
                <p><strong>Date:</strong> ${d.created_at}</p>
            `;
            new bootstrap.Modal(document.getElementById('reportModal')).show();
        });
}

function resolveReport(id) {
    if (confirm('Mark this report as resolved?')) {
        fetch('/api/admin/resolve-report.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id, status: 'resolved'})
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) location.reload();
            else alert('Error: ' + d.error);
        });
    }
}

function dismissReport(id) {
    if (confirm('Dismiss this report?')) {
        fetch('/api/admin/resolve-report.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id, status: 'dismissed'})
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) location.reload();
            else alert('Error: ' + d.error);
        });
    }
}
</script>
