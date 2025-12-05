<?php
// admin/entry_review.php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();

$entry_id = intval($_GET['id'] ?? 0);
if (!$entry_id) {
    header('Location: /admin/competitions.php');
    exit;
}

// Load entry + book + user + competition
$stmt = $pdo->prepare(
    "SELECT ce.*, b.title AS book_title, b.user_id AS book_user_id, b.synopsis, b.genre, " .
    "       u.username AS author_name, c.title AS competition_title, c.id AS competition_id, c.status AS competition_status " .
    "FROM competition_entries ce " .
    "JOIN books b ON b.id = ce.book_id " .
    "JOIN users u ON u.id = ce.user_id " .
    "JOIN competitions c ON c.id = ce.competition_id " .
    "WHERE ce.id = ?"
);
$stmt->execute([$entry_id]);
$entry = $stmt->fetch();
if (!$entry) {
    header('Location: /admin/competitions.php');
    exit;
}

$message = '';
$message_type = '';

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'approve' || $action === 'disqualify') {
        $new_status = $action === 'approve' ? 'approved' : 'disqualified';
        $u = $pdo->prepare("UPDATE competition_entries SET status = ? WHERE id = ?");
        $u->execute([$new_status, $entry_id]);

        // Log admin action
        $log = $pdo->prepare("INSERT INTO admin_activity (admin_id, action, meta, created_at) VALUES (?, ?, ?, NOW())");
        $log->execute([current_user_id(), 'entry_' . $action, json_encode(['entry_id' => $entry_id, 'competition_id' => $entry['competition_id']])]);

        $message = 'Entry ' . $new_status . ' successfully.';
        $message_type = 'success';

        // Refresh entry data
        $stmt->execute([$entry_id]);
        $entry = $stmt->fetch();
    }

    if ($action === 'flag' && !empty($_POST['reason'])) {
        $reason = trim($_POST['reason']);
        $pdo->prepare("INSERT INTO admin_activity (admin_id, action, meta, created_at) VALUES (?, ?, ?, NOW())")->execute([
            current_user_id(),
            'flag_entry',
            json_encode(['entry_id' => $entry_id, 'reason' => $reason])
        ]);

        $message = 'Entry flagged for review.';
        $message_type = 'info';
    }
}

require_once __DIR__ . '/../inc/header.php';
?>
<div class="container" style="max-width: 900px; margin: 0 auto;">
    <div class="card" style="margin-top: 20px;">
        <h1>Review Entry — #<?= $entry_id ?></h1>

        <?php if ($message): ?>
            <div class="card" style="background: rgba(<?= $message_type === 'success' ? '76, 200, 100' : '100, 150, 200' ?>, 0.1); border: 1px solid <?= $message_type === 'success' ? '#4cc864' : '#6496c8' ?>; color: <?= $message_type === 'success' ? '#4cc864' : '#6496c8' ?>; padding: 12px; margin-bottom: 16px; border-radius: 8px;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div style="margin-bottom: 24px;">
            <h2 style="color: var(--gold); margin-bottom: 8px;"><?= htmlspecialchars($entry['book_title']) ?></h2>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <p><strong>Author:</strong> <?= htmlspecialchars($entry['author_name']) ?> <span style="color: var(--muted);">(user id <?= (int)$entry['user_id'] ?>)</span></p>
                    <p><strong>Competition:</strong> <?= htmlspecialchars($entry['competition_title']) ?></p>
                    <p><strong>Genre:</strong> <?= htmlspecialchars($entry['genre'] ?? 'N/A') ?></p>
                </div>
                <div style="background: rgba(212, 175, 55, 0.05); padding: 12px; border-radius: 8px; border-left: 3px solid var(--gold);">
                    <p><strong>Status:</strong></p>
                    <div style="font-size: 18px; font-weight: 700; color: <?= $entry['status'] === 'approved' ? '#4cc864' : ($entry['status'] === 'disqualified' ? '#ff6b6b' : '#ffd700') ?>;">
                        <?= htmlspecialchars(ucfirst($entry['status'])) ?>
                    </div>
                    <p style="color: var(--muted); font-size: 12px; margin-top: 8px;">Submitted: <?= date('M j, Y H:i', strtotime($entry['submitted_at'])) ?></p>
                </div>
            </div>
        </div>

        <hr style="border: none; border-top: 1px solid rgba(212, 175, 55, 0.1); margin: 24px 0;">

        <h3 style="color: var(--gold); margin-bottom: 12px;">Synopsis</h3>
        <div class="card" style="background: rgba(15, 8, 32, 0.5); padding: 16px; border-left: 3px solid rgba(212, 175, 55, 0.3); margin-bottom: 24px;">
            <?= nl2br(htmlspecialchars($entry['synopsis'])) ?>
        </div>

        <hr style="border: none; border-top: 1px solid rgba(212, 175, 55, 0.1); margin: 24px 0;">

        <h3 style="color: var(--gold); margin-bottom: 16px;">Admin Actions</h3>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px;">
            <form method="post">
                <input type="hidden" name="action" value="approve">
                <button class="btn btn-gold" type="submit" style="width: 100%; padding: 12px; font-weight: 600;">✓ Approve Entry</button>
            </form>

            <form method="post" onsubmit="return confirm('Are you sure you want to disqualify this entry?');">
                <input type="hidden" name="action" value="disqualify">
                <button class="btn" type="submit" style="width: 100%; padding: 12px; font-weight: 600; background: rgba(200, 50, 50, 0.2); color: #ff6b6b;">✗ Disqualify Entry</button>
            </form>
        </div>

        <hr style="border: none; border-top: 1px solid rgba(212, 175, 55, 0.1); margin: 24px 0;">

        <h3 style="color: var(--gold); margin-bottom: 12px;">Flag for Review</h3>
        <form method="post" style="margin-bottom: 24px;">
            <input type="hidden" name="action" value="flag">
            <div style="margin-bottom: 12px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Reason for flagging</label>
                <textarea name="reason" rows="3" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory); font-family: inherit;" placeholder="Explain why you're flagging this entry..."></textarea>
            </div>
            <button class="btn" type="submit" style="padding: 10px 16px;">⚠️ Flag Entry</button>
        </form>

        <div style="display: flex; gap: 12px; margin-top: 24px;">
            <a href="/admin/competition_entries.php?id=<?= (int)$entry['competition_id'] ?>" class="btn" style="padding: 10px 16px; text-decoration: none;">← Back to Entries</a>
            <a href="/admin/competitions.php" class="btn" style="padding: 10px 16px; text-decoration: none;">← Back to Competitions</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
