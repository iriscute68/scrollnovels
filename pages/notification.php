<?php
// notifications.php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Mark all as read
if (isset($_GET['mark_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$user_id]);
    header("Location: notifications.php");
    exit;
}

// Fetch notifications
$stmt = $pdo->prepare("
    SELECT n.*, u.username AS actor_name
    FROM notifications n
    LEFT JOIN users u ON n.actor_id = u.id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 100
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

$unread_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_count->execute([$user_id]);
$unread_count = $unread_count->fetchColumn();
?>

<?php
    $page_title = 'Notifications - Scroll Novels';
    $page_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">'
        . '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">'
        . '<style>.notification{border-left:4px solid #007bff}.notification.unread{background:#f8f9fa;border-left-color:#28a745}.notification .icon{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center}.notification .time{font-size:.8rem}</style>';

    require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Notifications <?= $unread_count ? "<span class='badge bg-danger'>$unread_count</span>" : '' ?></h3>
        <?php if ($unread_count): ?>
            <a href="?mark_read=1" class="btn btn-sm btn-outline-success">Mark All Read</a>
        <?php endif; ?>
    </div>

    <div id="notifications-list">
        <?php foreach ($notifications as $n): ?>
            <div class="card mb-2 notification <?= $n['is_read'] ? '' : 'unread' ?>">
                <div class="card-body d-flex">
                    <?php
                        $icon_class = 'bg-secondary';
                        $icon = 'fa-bell';
                        
                        if ($n['type'] == 'chapter') {
                            $icon_class = 'bg-info';
                            $icon = 'fa-book';
                        }
                        elseif ($n['type'] == 'comment') {
                            $icon_class = 'bg-primary';
                            $icon = 'fa-comment';
                        }
                        elseif ($n['type'] == 'comment_like') {
                            $icon_class = 'bg-danger';
                            $icon = 'fa-heart';
                        }
                        elseif ($n['type'] == 'comment_reply') {
                            $icon_class = 'bg-warning';
                            $icon = 'fa-reply';
                        }
                        elseif ($n['type'] == 'like') {
                            $icon_class = 'bg-danger';
                            $icon = 'fa-heart';
                        }
                        elseif ($n['type'] == 'reply') {
                            $icon_class = 'bg-warning';
                            $icon = 'fa-reply';
                        }
                        elseif ($n['type'] == 'donation') {
                            $icon_class = 'bg-success';
                            $icon = 'fa-dollar-sign';
                        }
                    ?>
                    <div class="icon me-3 text-white <?= $icon_class ?>">
                        <i class="fas <?= $icon ?>"></i>
                    </div>
                    <div class="flex-grow-1">
                        <p class="mb-1">
                            <strong><?= htmlspecialchars($n['actor_name'] ?? 'System') ?></strong>
                            <?= htmlspecialchars($n['message']) ?>
                        </p>
                        <!-- Show notification type badge for comment notifications -->
                        <div class="mb-1">
                            <?php if ($n['type'] == 'comment_like'): ?>
                                <span class="badge bg-danger">❤️ Like on Comment</span>
                            <?php elseif ($n['type'] == 'comment_reply'): ?>
                                <span class="badge bg-warning text-dark">💬 Reply to Comment</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted time">
                            <?= time_ago($n['created_at']) ?>
                        </small>
                    </div>
                    <?php if (!$n['is_read']): ?>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input mark-read" data-id="<?= $n['id'] ?>">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($notifications)): ?>
        <p class="text-center text-muted">No notifications yet.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
// Mark single as read
$(document).on('change', '.mark-read', function() {
    const id = $(this).data('id');
    $.post('/api/mark-notification.php', { id: id });
    $(this).closest('.notification').removeClass('unread');
    updateBadge();
});

// Poll for new
let lastId = <?= $notifications ? $notifications[0]['id'] : 0 ?>;
setInterval(() => {
    $.get('/api/get-notifications.php?since=' + lastId, data => {
        if (data.html) {
            $('#notifications-list').prepend(data.html);
            lastId = data.last_id;
            updateBadge();
        }
    });
}, 10000);

function updateBadge() {
    $.get('/api/unread-count.php', count => {
        const badge = $('.badge.bg-danger');
        if (count > 0) {
            badge.text(count).show();
        } else {
            badge.hide();
        }
    });
}
</script>
</body>
</html>

<?php
// time_ago provided by includes/functions.php
?>
