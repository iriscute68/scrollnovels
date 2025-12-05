<?php
// admin/competition_judging.php - Judging dashboard for competitions
if (session_status() === PHP_SESSION_NONE) session_start();

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

// Check admin permission - support multiple session types
if (!is_admin() && empty($_SESSION['admin_id']) && empty($_SESSION['admin_user'])) {
    header('Location: admin.php');
    exit;
}

$competition_id = intval($_GET['comp_id'] ?? $_GET['id'] ?? 0);
if (!$competition_id) {
    header('Location: admin.php?page=competitions');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ?");
$stmt->execute([$competition_id]);
$competition = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$competition) { 
    http_response_code(404); 
    echo "Competition not found"; 
    exit; 
}

// Fetch entries with judge scores
$entries_stmt = $pdo->prepare("
    SELECT e.id, e.story_id, e.user_id, e.submitted_at, e.status,
           s.title as story_title,
           u.username as author_name,
           COALESCE(AVG(js.score), 0) as avg_score,
           COUNT(DISTINCT js.judge_id) as judge_count
    FROM competition_entries e
    JOIN stories s ON s.id = e.story_id
    JOIN users u ON u.id = e.user_id
    LEFT JOIN judge_scores js ON js.entry_id = e.id
    WHERE e.competition_id = ?
    GROUP BY e.id
    ORDER BY e.submitted_at DESC
");
$entries_stmt->execute([$competition_id]);
$entries_list = $entries_stmt->fetchAll(PDO::FETCH_ASSOC);

// Count stats
$scored = 0;
foreach ($entries_list as $e) {
    if ($e['judge_count'] > 0) $scored++;
}

$adminId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Competition Judging - <?= htmlspecialchars($competition['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #1a1a2e; color: #e0e0e0; }
        .card { background: #16213e; border-color: #0f3460; }
        .table { color: #e0e0e0; }
        .table-dark { background: #0f3460; }
        .badge-success { background: #28a745; }
        .badge-warning { background: #ffc107; color: #000; }
        .badge-danger { background: #dc3545; }
        .btn-ghost { background: transparent; border: 1px solid #6c757d; color: #e0e0e0; }
        .btn-ghost:hover { background: #6c757d; color: #fff; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">⚖️ Judging Dashboard — <?= htmlspecialchars($competition['title']) ?></h1>
        <a href="admin.php?page=competitions" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Competitions</a>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card p-3 text-center" style="background: #f8f9fa;">
                <small style="font-size: 1.1rem; font-weight: 600; color: #333; display: block; margin-bottom: 10px;">Total Entries</small>
                <h3 style="color: #333; margin: 0;"><?= count($entries_list) ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 text-center" style="background: #f8f9fa;">
                <small style="font-size: 1.1rem; font-weight: 600; color: #333; display: block; margin-bottom: 10px;">Scored</small>
                <h3 style="color: #333; margin: 0;"><?= $scored ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 text-center" style="background: #f8f9fa;">
                <small style="font-size: 1.1rem; font-weight: 600; color: #333; display: block; margin-bottom: 10px;">Pending</small>
                <h3 style="color: #333; margin: 0;"><?= count($entries_list) - $scored ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3 text-center" style="background: #f8f9fa;">
                <small style="font-size: 1.1rem; font-weight: 600; color: #333; display: block; margin-bottom: 10px;">Average Score</small>
                <h3 style="color: #333; margin: 0;">
                    <?php
                    $total_score = array_sum(array_map(fn($e) => $e['avg_score'], $entries_list));
                    $avg = !empty($entries_list) ? $total_score / count($entries_list) : 0;
                    echo number_format($avg, 1);
                    ?>
                </h3>
            </div>
        </div>
    </div>

    <!-- Entries Table -->
    <div class="card p-4">
        <h5 class="mb-3">Competition Entries</h5>
        
        <?php if (empty($entries_list)): ?>
            <div class="alert alert-info">No entries submitted for this competition yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Story</th>
                            <th>Author</th>
                            <th class="text-center">Avg Score</th>
                            <th class="text-center">Judge Scores</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries_list as $entry): ?>
                            <tr>
                                <td>
                                    <a href="/scrollnovels/pages/story.php?id=<?= $entry['story_id'] ?>" target="_blank" class="text-info">
                                        <?= htmlspecialchars($entry['story_title']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($entry['author_name']) ?></td>
                                <td class="text-center">
                                    <?php if ($entry['avg_score'] > 0): ?>
                                        <strong><?= number_format($entry['avg_score'], 2) ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?= $entry['judge_count'] ?> received</span>
                                </td>
                                <td>
                                    <?php 
                                    $statusClass = 'bg-warning';
                                    if ($entry['status'] === 'approved') {
                                        $statusClass = 'bg-success';
                                    } elseif ($entry['status'] === 'disqualified') {
                                        $statusClass = 'bg-danger';
                                    }
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><?= ucfirst($entry['status']) ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-ghost" onclick="openScoreModal(<?= $entry['id'] ?>, <?= json_encode($entry['story_title']) ?>)">
                                        <i class="fas fa-star"></i> Add Score
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Scoring Modal -->
<div class="modal fade" id="scoreModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="background: #16213e; border-color: #0f3460;">
            <div class="modal-header border-0">
                <h5 class="modal-title">Submit Judge Score</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="scoreEntryId">
                <p id="scoreStoryName" class="text-muted mb-4"></p>
                
                <div class="mb-3">
                    <label class="form-label">Score (1-10)</label>
                    <input type="number" id="scoreValue" class="form-control bg-dark text-light border-secondary" min="1" max="10" step="0.5" placeholder="7.5">
                </div>

                <div class="mb-3">
                    <label class="form-label">Comment</label>
                    <textarea id="scoreComment" class="form-control bg-dark text-light border-secondary" rows="3" placeholder="Judge feedback (optional)..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitScore()">Submit Score</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const currentAdminId = <?= $adminId ?>;
let scoreModal;

document.addEventListener('DOMContentLoaded', function() {
    scoreModal = new bootstrap.Modal(document.getElementById('scoreModal'));
});

function openScoreModal(entryId, storyName) {
    document.getElementById('scoreEntryId').value = entryId;
    document.getElementById('scoreStoryName').textContent = storyName;
    document.getElementById('scoreValue').value = '';
    document.getElementById('scoreComment').value = '';
    scoreModal.show();
}

async function submitScore() {
    const entryId = document.getElementById('scoreEntryId').value;
    const score = parseFloat(document.getElementById('scoreValue').value);
    const comment = document.getElementById('scoreComment').value;

    if (!score || score < 1 || score > 10) {
        alert('Please enter a score between 1 and 10');
        return;
    }

    try {
        const res = await fetch('ajax/submit_judge_score.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                entry_id: entryId, 
                judge_id: currentAdminId, 
                score: score, 
                comment: comment 
            })
        });

        const data = await res.json();
        if (data.ok || data.success) {
            alert('Score submitted successfully');
            scoreModal.hide();
            location.reload();
        } else {
            alert('Error: ' + (data.message || data.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Error submitting score: ' + e.message);
    }
}
</script>
</body>
</html>
