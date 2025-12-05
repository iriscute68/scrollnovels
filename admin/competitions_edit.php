<?php
// admin/competitions_edit.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once dirname(__DIR__) . '/includes/db_migrations.php';

// Ensure competitions table has correct schema
try {
    // Make sure the status column supports 'published'
    $pdo->exec("ALTER TABLE competitions MODIFY COLUMN status ENUM('draft', 'published', 'closed') DEFAULT 'draft'");
} catch (Exception $e) {
    // Column may already have this - ignore error
    error_log('Alter competitions status column: ' . $e->getMessage());
}

// Check admin permission - support multiple session types
if (!is_admin() && empty($_SESSION['admin_id']) && empty($_SESSION['admin_user'])) {
    header('Location: /admin/admin.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: admin.php?page=competitions');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ?");
$stmt->execute([$id]);
$competition = $stmt->fetch();
if (!$competition) {
    header('Location: /admin/competitions.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cannot edit closed competitions
    if ($competition['status'] === 'closed') {
        $errors[] = 'Cannot edit a closed competition.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $rules = trim($_POST['rules'] ?? '');
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;
        $prize = trim($_POST['prize'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        $max_entries = intval($_POST['max_entries'] ?? 1);
        $auto_win_by = $_POST['auto_win_by'] ?? 'none';
        $min_chapters = intval($_POST['min_chapters'] ?? 0);
        $min_words = intval($_POST['min_words'] ?? 0);

        // Validation
        if (!$title) $errors[] = 'Competition title is required.';
        if (!$start_date) $errors[] = 'Start date is required.';
        if (!$end_date) $errors[] = 'End date is required.';
        if ($start_date && $end_date && strtotime($start_date) >= strtotime($end_date)) {
            $errors[] = 'Start date must be before end date.';
        }

        if (!$errors) {
            try {
                // Handle cover image upload
                $cover_image = $competition['cover_image'];
                if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === 0) {
                    $upload_dir = __DIR__ . '/../uploads/competitions/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $filename = time() . '_' . preg_replace('/[^a-z0-9._-]/i', '', basename($_FILES['cover_image']['name']));
                    $target_path = $upload_dir . $filename;
                    if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $target_path)) {
                        $cover_image = 'competitions/' . $filename;
                    }
                }

                $u = $pdo->prepare(
                    "UPDATE competitions SET " .
                    "title=?, description=?, rules=?, start_date=?, end_date=?, prize=?, status=?, cover_image=?, " .
                    "max_entries=?, auto_win_by=?, min_chapters=?, min_words=?, updated_at=NOW() " .
                    "WHERE id = ?"
                );
                $u->execute([
                    $title, $description, $rules, $start_date, $end_date, $prize, $status, $cover_image,
                    $max_entries, $auto_win_by, $min_chapters, $min_words, $id
                ]);

                // Log action
                $adminId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
                $log = $pdo->prepare("INSERT INTO admin_activity (admin_id, action, meta, created_at) VALUES (?, ?, ?, NOW())");
                $log->execute([$adminId, 'edit_competition', json_encode(['id' => $id, 'title' => $title])]);

                header('Location: admin.php?page=competitions');
                exit;
            } catch (Exception $e) {
                error_log('competition_edit error: ' . $e->getMessage());
                $errors[] = 'Database error. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/inc/header.php';
?>
<div class="container" style="max-width: 800px; margin: 0 auto;">
    <div class="card" style="margin-top: 20px;">
        <h1>Edit Competition</h1>

        <?php if ($errors): ?>
            <div class="card" style="background: rgba(200, 50, 50, 0.1); border: 1px solid #c83232; color: #ff6b6b; padding: 12px; margin-bottom: 16px; border-radius: 8px;">
                <strong>Errors:</strong>
                <ul style="margin: 8px 0 0 20px;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($competition['status'] === 'closed'): ?>
            <div class="card" style="background: rgba(100, 100, 100, 0.1); border: 1px solid #999; color: var(--muted); padding: 12px; margin-bottom: 16px; border-radius: 8px;">
                <strong>⚠️ This competition is closed.</strong> Editing is disabled to preserve historical data.
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" <?php if ($competition['status'] === 'closed') echo 'style="pointer-events: none; opacity: 0.6;"'; ?>>
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Competition Title <span style="color: #c83232;">*</span></label>
                <input type="text" name="title" required value="<?= htmlspecialchars($competition['title']) ?>" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);" <?php if ($competition['status'] === 'closed') echo 'disabled'; ?>>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Description</label>
                <textarea name="description" id="compDescription" rows="4" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory); font-family: inherit;" <?php if ($competition['status'] === 'closed') echo 'disabled'; ?>><?= htmlspecialchars($competition['description']) ?></textarea>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Rules</label>
                <textarea name="rules" id="compRules" rows="6" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory); font-family: inherit;" <?php if ($competition['status'] === 'closed') echo 'disabled'; ?>><?= htmlspecialchars($competition['rules']) ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 600;">Start Date <span style="color: #c83232;">*</span></label>
                    <input type="datetime-local" name="start_date" required value="<?= date('Y-m-d\TH:i', strtotime($competition['start_date'])) ?>" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);" <?php if ($competition['status'] === 'closed') echo 'disabled'; ?>>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 600;">End Date <span style="color: #c83232;">*</span></label>
                    <input type="datetime-local" name="end_date" required value="<?= date('Y-m-d\TH:i', strtotime($competition['end_date'])) ?>" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);" <?php if ($competition['status'] === 'closed') echo 'disabled'; ?>>
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Prize Information</label>
                <input type="text" name="prize" value="<?= htmlspecialchars($competition['prize']) ?>" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);" <?php if ($competition['status'] === 'closed') echo 'disabled'; ?>>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Cover Image</label>
                <?php if ($competition['cover_image']): ?>
                    <div style="margin-bottom: 8px; color: var(--muted); font-size: 14px;">Current: <?= htmlspecialchars($competition['cover_image']) ?></div>
                <?php endif; ?>
                <input type="file" name="cover_image" accept="image/*" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);" <?php if ($competition['status'] === 'closed') echo 'disabled'; ?>>
                <small style="color: var(--muted);">Leave empty to keep current image</small>
            </div>

            <hr style="border: none; border-top: 1px solid rgba(212, 175, 55, 0.1); margin: 24px 0;">

            <h3 style="font-size: 16px; color: var(--gold); margin-bottom: 12px;">Story Requirements</h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 600;">Minimum Chapters</label>
                    <input type="number" name="min_chapters" min="0" value="<?= (int)$competition['min_chapters'] ?>" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);" <?php if ($competition['status'] === 'closed') echo 'disabled'; ?>>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 600;">Minimum Word Count</label>
                    <input type="number" name="min_words" min="0" value="<?= (int)$competition['min_words'] ?>" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);" <?php if ($competition['status'] === 'closed') echo 'disabled'; ?>>
                </div>
            </div>

            <hr style="border: none; border-top: 1px solid rgba(212, 175, 55, 0.1); margin: 24px 0;">

            <h3 style="font-size: 16px; color: var(--gold); margin-bottom: 12px;">Auto-Winner Criteria</h3>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Winner Selection Method</label>
                <select name="auto_win_by" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);" <?php if ($competition['status'] === 'closed') echo 'disabled'; ?>>
                    <option value="none" <?= $competition['auto_win_by'] === 'none' ? 'selected' : '' ?>>Manual (Admin selects winner)</option>
                    <option value="views" <?= $competition['auto_win_by'] === 'views' ? 'selected' : '' ?>>Most Views</option>
                    <option value="votes" <?= $competition['auto_win_by'] === 'votes' ? 'selected' : '' ?>>Most Votes</option>
                    <option value="ratings" <?= $competition['auto_win_by'] === 'ratings' ? 'selected' : '' ?>>Highest Average Rating</option>
                    <option value="completion" <?= $competition['auto_win_by'] === 'completion' ? 'selected' : '' ?>>First Completed Story</option>
                </select>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Max Entries Per User</label>
                <input type="number" name="max_entries" min="1" value="<?= (int)$competition['max_entries'] ?>" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);" <?php if ($competition['status'] === 'closed') echo 'disabled'; ?>>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Status</label>
                <select name="status" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);">
                    <option value="draft" <?= $competition['status'] === 'draft' ? 'selected' : '' ?>>Draft (Not visible to users)</option>
                    <option value="published" <?= $competition['status'] === 'published' ? 'selected' : '' ?>>Published (Live)</option>
                    <option value="closed" <?= $competition['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <button type="submit" class="btn btn-gold" style="flex: 1; padding: 12px; font-weight: 600;" <?php if ($competition['status'] === 'closed') echo 'disabled'; ?>>Save Changes</button>
                <a href="/admin/competitions.php" class="btn" style="flex: 1; padding: 12px; text-align: center; text-decoration: none; font-weight: 600;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<!-- Quill WYSIWYG Editor (Free, no API key) -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
['compDescription', 'compRules'].forEach(function(id) {
    const textarea = document.getElementById(id);
    if (textarea) {
        const editorDiv = document.createElement('div');
        editorDiv.id = 'quill-' + id;
        editorDiv.style.minHeight = '200px';
        editorDiv.innerHTML = textarea.value;
        textarea.style.display = 'none';
        textarea.parentNode.insertBefore(editorDiv, textarea);
        
        const q = new Quill('#quill-' + id, {
            theme: 'snow',
            modules: { toolbar: [['bold', 'italic', 'underline'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], ['link'], ['clean']] }
        });
        
        // Sync on form submit
        textarea.closest('form').addEventListener('submit', function() {
            textarea.value = q.root.innerHTML;
        });
    }
});
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
