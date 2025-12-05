<?php
require_once 'inc/header.php';
$activeTab = 'announcements';
require_once 'inc/sidebar.php';

// Check admin auth
if (!isAdminLoggedIn()) {
    http_response_code(403);
    exit('Forbidden');
}
?>
<div class="container my-4">
    <h1>Announcements</h1>
    <form id="announceForm" method="post" action="<?= rtrim(SITE_URL, '/') ?>/api/save-announcement.php">
        <input type="hidden" name="id" id="ann-id">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div class="mb-2">
            <input name="title" id="ann-title" class="form-control" placeholder="Title">
        </div>
        <div class="mb-2">
            <input name="slug" id="ann-slug" class="form-control" placeholder="Optional slug">
        </div>
        <div class="mb-2">
            <textarea name="content" id="ann-content" class="form-control" rows="4" placeholder="Content (HTML allowed)"></textarea>
        </div>
        <div class="mb-2">
            <input name="link" id="ann-link" class="form-control" placeholder="Optional link">
        </div>
        <button class="btn btn-primary" type="submit">Save Announcement</button>
    </form>

    <hr>
    <h3>Existing</h3>
    <?php
    $rows = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();
    foreach ($rows as $r): ?>
        <div class="card mb-2">
            <div class="card-body">
                <h5><?= htmlspecialchars($r['title']) ?> <?php if (!$r['is_active']): ?><span class="badge bg-secondary">inactive</span><?php else: ?><span class="badge bg-success">active</span><?php endif; ?></h5>
                <p><?= nl2br(htmlspecialchars(substr($r['content'],0,240))) ?></p>
                <div class="small">
                    <a href="#" class="edit-ann me-2" data-id="<?= $r['id'] ?>" data-title="<?= htmlspecialchars($r['title'], ENT_QUOTES) ?>" data-slug="<?= htmlspecialchars($r['slug'], ENT_QUOTES) ?>" data-content="<?= htmlspecialchars($r['content'], ENT_QUOTES) ?>" data-link="<?= htmlspecialchars($r['link'], ENT_QUOTES) ?>">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="#" class="toggle-ann me-2" data-id="<?= $r['id'] ?>" data-active="<?= $r['is_active'] ? '1' : '0' ?>">
                        <i class="fas <?= $r['is_active'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                        <?= $r['is_active'] ? 'Deactivate' : 'Activate' ?>
                    </a>
                    <a href="#" class="delete-ann text-danger" data-id="<?= $r['id'] ?>">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
document.querySelectorAll('.edit-ann').forEach(a=>a.addEventListener('click', function(e){
    e.preventDefault();
    document.getElementById('ann-id').value = this.dataset.id;
    document.getElementById('ann-title').value = this.dataset.title;
    document.getElementById('ann-slug').value = this.dataset.slug;
    document.getElementById('ann-content').value = this.dataset.content;
    document.getElementById('ann-link').value = this.dataset.link;
    window.scrollTo(0,0);
}));

    document.querySelectorAll('.toggle-ann').forEach(a=>a.addEventListener('click', async function(e){
    e.preventDefault();
    const id = this.dataset.id;
    const fd = new FormData();
    fd.append('id', id);
    fd.append('csrf', '<?= csrf_token() ?>');
    const res = await fetch('<?= rtrim(SITE_URL, '/') ?>/api/toggle-announcement.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const json = await res.json();
    alert(json.status + ': ' + (json.is_active ? 'activated' : 'deactivated'));
    location.reload();
}));

    document.querySelectorAll('.delete-ann').forEach(a=>a.addEventListener('click', async function(e){
    e.preventDefault();
    if (!confirm('Delete this announcement?')) return;
    const id = this.dataset.id;
    const fd = new FormData();
    fd.append('id', id);
    fd.append('csrf', '<?= csrf_token() ?>');
    const res = await fetch('<?= rtrim(SITE_URL, '/') ?>/api/delete-announcement.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const json = await res.json();
    alert(json.status || 'Deleted');
    location.reload();
}));

document.getElementById('announceForm').addEventListener('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(this);
    // ensure csrf is present
    if (!fd.get('csrf')) fd.append('csrf', '<?= csrf_token() ?>');
    const res = await fetch(this.action, { method: 'POST', body: fd, credentials: 'same-origin' });
    const json = await res.json();
    alert('Saved: '+(json.status||'ok'));
    location.reload();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
