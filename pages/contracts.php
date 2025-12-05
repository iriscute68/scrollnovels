<?php
// contracts.php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$contracts = $pdo->prepare("
    SELECT c.*, u.username AS admin_name
    FROM contracts c
    LEFT JOIN users u ON c.admin_id = u.id
    WHERE c.author_id = ?
    ORDER BY c.created_at DESC
");
$contracts->execute([$user_id]);
$contracts = $contracts->fetchAll();
?>
<?php
    $page_title = 'My Contracts - Scroll Novels';
    $page_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
    require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <h2>My Contracts</h2>

    <?php foreach ($contracts as $c): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <strong><?= htmlspecialchars($c['title']) ?></strong>
                <span class="badge bg-<?= 
                    $c['status'] == 'signed' ? 'success' : 
                    ($c['status'] == 'pending' ? 'warning' : 'secondary')
                ?>"><?= ucfirst($c['status']) ?></span>
            </div>
            <div class="card-body">
                <p><strong>Royalty:</strong> <?= $c['royalty_rate'] ?>%</p>
                <p><strong>Milestones:</strong> <?= implode(', ', json_decode($c['milestones'], true)) ?> chapters</p>
                <div class="border p-3 mb-3" style="max-height:200px;overflow:auto;">
                    <?= $c['terms'] ?>
                </div>

                <?php if ($c['status'] == 'pending'): ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#signModal<?= $c['id'] ?>">
                        Sign Contract
                    </button>
                <?php elseif ($c['status'] == 'signed'): ?>
                    <a href="/api/contract-pdf.php?id=<?= $c['id'] ?>" class="btn btn-secondary">Download PDF</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sign Modal -->
        <div class="modal fade" id="signModal<?= $c['id'] ?>">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= rtrim(SITE_URL, '/') ?>/api/sign-contract.php" method="POST">
                        <div class="modal-header">
                            <h5>Sign Contract</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="contract_id" value="<?= $c['id'] ?>">
                            <p>Draw your signature below:</p>
                            <canvas id="canvas<?= $c['id'] ?>" width="300" height="150" style="border:1px solid #ccc;"></canvas>
                            <input type="hidden" name="signature" id="sig<?= $c['id'] ?>">
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-secondary" onclick="clearSig(<?= $c['id'] ?>)">Clear</button>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success">Confirm & Sign</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
<?php foreach ($contracts as $c): if ($c['status'] == 'pending'): ?>
    const canvas<?= $c['id'] ?> = document.getElementById('canvas<?= $c['id'] ?>');
    const ctx<?= $c['id'] ?> = canvas<?= $c['id'] ?>.getContext('2d');
    let drawing = false;

    canvas<?= $c['id'] ?>.addEventListener('mousedown', e => { drawing = true; ctx<?= $c['id'] ?>.beginPath(); });
    canvas<?= $c['id'] ?>.addEventListener('mousemove', e => {
        if (drawing) {
            ctx<?= $c['id'] ?>.lineTo(e.offsetX, e.offsetY);
            ctx<?= $c['id'] ?>.stroke();
        }
    });
    canvas<?= $c['id'] ?>.addEventListener('mouseup', () => { drawing = false; saveSig(<?= $c['id'] ?>); });

    function clearSig(id) {
        ctx<?= $c['id'] ?>.clearRect(0, 0, canvas<?= $c['id'] ?>.width, canvas<?= $c['id'] ?>.height);
        document.getElementById('sig<?= $c['id'] ?>').value = '';
    }

    function saveSig(id) {
        document.getElementById('sig<?= $c['id'] ?>').value = canvas<?= $c['id'] ?>.toDataURL();
    }
<?php endif; endforeach; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
