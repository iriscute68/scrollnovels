<?php
// ads.php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireLogin();
if (!hasRole('admin')) {
    header("Location: dashboard.php");
    exit;
}

$stories = $pdo->query("SELECT id, title FROM stories WHERE status = 'published'")->fetchAll();
$ads = $pdo->query("
    SELECT a.*, s.title as story_title
    FROM ads a
    LEFT JOIN stories s ON a.story_id = s.id
    ORDER BY a.created_at DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ads Management - Scroll Novels</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php @include dirname(__DIR__) . '/includes/navbar.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Ads Management</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adModal">
            <i class="fas fa-plus"></i> New Ad
        </button>
    </div>

    <!-- Ads Table -->
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Content</th>
                    <th>Placement</th>
                    <th>Story</th>
                    <th>Status</th>
                    <th>Dates</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ads as $ad): ?>
                <tr>
                    <td><?= $ad['id'] ?></td>
                    <td><?= htmlspecialchars(substr($ad['content'], 0, 50)) ?>...</td>
                    <td><span class="badge bg-info"><?= ucfirst($ad['placement']) ?></span></td>
                    <td><?= $ad['story_title'] ? htmlspecialchars($ad['story_title']) : 'All' ?></td>
                    <td>
                        <span class="badge bg-<?= $ad['status'] == 'active' ? 'success' : ($ad['status'] == 'pending' ? 'warning' : 'secondary') ?>">
                            <?= ucfirst($ad['status']) ?>
                        </span>
                    </td>
                    <td><?= date('M j', strtotime($ad['start_date'])) ?> - <?= date('M j, Y', strtotime($ad['end_date'])) ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary edit-ad" data-ad='<?= json_encode($ad) ?>'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" action="<?= rtrim(SITE_URL, '/') ?>/api/delete-ad.php" class="d-inline">
                            <input type="hidden" name="ad_id" value="<?= $ad['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete ad?')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="adModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="adForm">
                <div class="modal-header">
                    <h5 class="modal-title">Create / Edit Ad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="ad_id">
                    <div class="mb-3">
                        <label>Content (HTML allowed)</label>
                        <textarea name="content" id="ad_content" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Placement</label>
                            <select name="placement" id="ad_placement" class="form-select" required>
                                <option value="featured">Featured</option>
                                <option value="sidebar">Sidebar</option>
                                <option value="top">Top Banner</option>
                                <option value="bottom">Bottom Banner</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Target Story (optional)</label>
                            <select name="story_id" id="ad_story" class="form-select">
                                <option value="">