<?php
// admin/pages/dashboard.php - Overview

$activities = $pdo->query("
    SELECT 'story' AS type, s.title as title, u.username, s.created_at
    FROM stories s JOIN users u ON s.author_id = u.id
    UNION ALL
    SELECT 'donation', CONCAT('Donation $', d.amount), u.username, d.created_at
    FROM donations d JOIN users u ON d.user_id = u.id
    UNION ALL
    SELECT 'thread', t.title, u.username, t.created_at
    FROM forum_topics t JOIN users u ON t.author_id = u.id
    ORDER BY created_at DESC LIMIT 15
")->fetchAll();
?>

<h4>Dashboard Overview</h4>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Recent Activity</h5>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <div class="list-group list-group-flush">
                    <?php foreach ($activities as $act): ?>
                        <div class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <strong><?= htmlspecialchars($act['username']) ?></strong>
                                <small><?= date('M d, H:i', strtotime($act['created_at'])) ?></small>
                            </div>
                            <p class="mb-1 small"><?= htmlspecialchars(substr($act['title'], 0, 60)) ?></p>
                            <small class="text-muted"><?= ucfirst($act['type']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Quick Stats</h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-6">New Users (Today)</dt>
                    <dd class="col-sm-6"><?= $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?></dd>

                    <dt class="col-sm-6">New Stories (Today)</dt>
                    <dd class="col-sm-6"><?= $pdo->query("SELECT COUNT(*) FROM stories WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?></dd>

                    <dt class="col-sm-6">Pending Reviews</dt>
                    <dd class="col-sm-6"><?= $pdo->query("SELECT COUNT(*) FROM stories WHERE status = 'pending'")->fetchColumn() ?></dd>

                    <dt class="col-sm-6">Active Reports</dt>
                    <dd class="col-sm-6"><?= $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'open'")->fetchColumn() ?></dd>

                    <dt class="col-sm-6">Support Tickets</dt>
                    <dd class="col-sm-6"><?= $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open', 'pending')")->fetchColumn() ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>
