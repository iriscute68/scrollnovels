<?php
// admin/pages/competitions.php
$competitions = $pdo->query("
    SELECT c.*, u.username, COUNT(DISTINCT ce.id) as entry_count
    FROM competitions c
    LEFT JOIN users u ON c.created_by = u.id
    LEFT JOIN competition_entries ce ON c.id = ce.competition_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
")->fetchAll();
?>

<?php if (isset($_GET['created'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle"></i> Competition created successfully!
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle"></i> Competition deleted successfully!
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3>Manage Competitions</h3>
    </div>
    <div class="col-md-6 text-end">
        <a href="competitions_create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Create Competition</a>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Title</th>
                <th>Created By</th>
                <th>Entries</th>
                <th>Status</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($competitions as $comp): ?>
            <tr>
                <td><?= htmlspecialchars($comp['title']) ?></td>
                <td><?= htmlspecialchars($comp['username']) ?></td>
                <td><span class="badge bg-info"><?= $comp['entry_count'] ?></span></td>
                <td>
                    <?php
                        $status = $comp['status'] ?? 'draft';
                        if ($status === 'published') {
                            echo '<span class="badge bg-success">Published</span>';
                        } elseif ($status === 'draft') {
                            echo '<span class="badge bg-warning">Draft</span>';
                        } elseif ($status === 'closed') {
                            echo '<span class="badge bg-secondary">Closed</span>';
                        } else {
                            echo '<span class="badge bg-info">' . htmlspecialchars($status) . '</span>';
                        }
                    ?>
                </td>
                <td><?= date('M d, Y', strtotime($comp['start_date'])) ?></td>
                <td><?= date('M d, Y', strtotime($comp['end_date'])) ?></td>
                <td>
                    <a href="competitions_edit.php?id=<?= $comp['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                    <a href="competition_judging.php?id=<?= $comp['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-gavel"></i></a>
                    <button class="btn btn-sm btn-danger" onclick="deleteCompetition(<?= $comp['id'] ?>)"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function deleteCompetition(id) {
    if (confirm('Delete this competition? This action cannot be undone.')) {
        fetch('competitions_delete.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: id})
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                location.reload();
            } else {
                alert('Error: ' + d.error);
            }
        });
    }
}
</script>
