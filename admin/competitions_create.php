<?php
// admin/competitions_create.php
session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db_migrations.php';

// Check admin auth
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . site_url('/pages/login.php'));
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !in_array($user['role'], ['admin', 'super_admin', 'moderator'])) {
    header('Location: ' . site_url('/'));
    exit;
}

$currentUserId = $_SESSION['user_id'];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    $cash_prize = floatval($_POST['cash_prize'] ?? 0);
    $featured = isset($_POST['featured']) ? 1 : 0;
    $badge = trim($_POST['badge'] ?? '');

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
            $cover_image = null;
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === 0) {
                $upload_dir = dirname(__DIR__) . '/uploads/competitions/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $filename = time() . '_' . preg_replace('/[^a-z0-9._-]/i', '', basename($_FILES['cover_image']['name']));
                $target_path = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $target_path)) {
                    $cover_image = '/uploads/competitions/' . $filename;
                }
            }
            
            // Build prize_info JSON
            $prize_info = json_encode([
                'cash_prize' => $cash_prize,
                'featured' => $featured,
                'badge' => $badge
            ]);
            
            // Build requirements JSON
            $requirements_json = json_encode([
                'min_chapters' => $min_chapters,
                'min_words' => $min_words,
                'max_entries' => $max_entries
            ]);

            $stmt = $pdo->prepare("
                INSERT INTO competitions 
                (title, description, rules, start_date, end_date, prize, status, cover_image, 
                 max_entries, auto_win_by, min_chapters, min_words, prize_info, requirements_json, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $title, $description, $rules, $start_date, $end_date, $prize, $status, $cover_image,
                $max_entries, $auto_win_by, $min_chapters, $min_words, $prize_info, $requirements_json, $currentUserId
            ]);

            $comp_id = $pdo->lastInsertId();
            
            // Log action
            try {
                $log = $pdo->prepare("INSERT INTO admin_action_logs (actor_id, action, target_type, target_id, created_at) VALUES (?, 'create_competition', 'competition', ?, NOW())");
                $log->execute([$currentUserId, $comp_id]);
            } catch (Exception $e) {}

            header('Location: admin.php?page=competitions&created=1');
            exit;
        } catch (Exception $e) {
            error_log('competition_create error: ' . $e->getMessage());
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$page_title = 'Create Competition';
require_once __DIR__ . '/header.php';
?>
<div class="container" style="max-width: 800px; margin: 0 auto;">
    <div class="card" style="margin-top: 20px;">
        <h1>Create New Competition</h1>

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

        <form method="post" enctype="multipart/form-data">
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Competition Title <span style="color: #c83232;">*</span></label>
                <input type="text" name="title" required class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);" placeholder="e.g., Winter Writing Challenge 2025">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Description</label>
                <textarea name="description" id="compDescription" rows="4" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory); font-family: inherit;" placeholder="Tell writers what this competition is about..."></textarea>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Rules</label>
                <textarea name="rules" id="compRules" rows="6" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory); font-family: inherit;" placeholder="Competition rules and requirements..."></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 600;">Start Date <span style="color: #c83232;">*</span></label>
                    <input type="datetime-local" name="start_date" required class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 600;">End Date <span style="color: #c83232;">*</span></label>
                    <input type="datetime-local" name="end_date" required class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);">
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Prize Information</label>
                <input type="text" name="prize" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);" placeholder="e.g., $500 cash prize or Featured placement">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Cover Image</label>
                <input type="file" name="cover_image" accept="image/*" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);">
                <small style="color: var(--muted);">Required for published competitions</small>
            </div>

            <hr style="border: none; border-top: 1px solid rgba(212, 175, 55, 0.1); margin: 24px 0;">

            <h3 style="font-size: 16px; color: var(--gold); margin-bottom: 12px;">Story Requirements</h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 600;">Minimum Chapters</label>
                    <input type="number" name="min_chapters" min="0" value="0" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 600;">Minimum Word Count</label>
                    <input type="number" name="min_words" min="0" value="0" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);">
                </div>
            </div>

            <hr style="border: none; border-top: 1px solid rgba(212, 175, 55, 0.1); margin: 24px 0;">

            <h3 style="font-size: 16px; color: var(--gold); margin-bottom: 12px;">Auto-Winner Criteria</h3>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Winner Selection Method</label>
                <select name="auto_win_by" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);">
                    <option value="none">Manual (Admin selects winner)</option>
                    <option value="views">Most Views</option>
                    <option value="votes">Most Votes</option>
                    <option value="ratings">Highest Average Rating</option>
                    <option value="completion">First Completed Story</option>
                </select>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Max Entries Per User</label>
                <input type="number" name="max_entries" min="1" value="1" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Status</label>
                <select name="status" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);">
                    <option value="draft">Draft (Not visible to users)</option>
                    <option value="active">Active (Live)</option>
                    <option value="upcoming">Upcoming</option>
                </select>
            </div>

            <hr style="border: none; border-top: 1px solid rgba(212, 175, 55, 0.1); margin: 24px 0;">

            <h3 style="font-size: 16px; color: var(--gold); margin-bottom: 12px;">Prize Details</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 600;">Cash Prize ($)</label>
                    <input type="number" name="cash_prize" min="0" step="0.01" value="0" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 600;">Winner Badge</label>
                    <input type="text" name="badge" class="input" style="width: 100%; padding: 10px; border: 1px solid rgba(212, 175, 55, 0.2); border-radius: 8px; background: rgba(15, 8, 32, 0.8); color: var(--ivory);" placeholder="e.g., Winner 2025">
                </div>
            </div>
            
            <div style="margin-bottom: 16px;">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="featured" value="1">
                    <span>Featured Spot on Homepage</span>
                </label>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <button type="submit" class="btn btn-gold" style="flex: 1; padding: 12px; font-weight: 600; background: #10b981; color: white; border: none; border-radius: 8px; cursor: pointer;">Create Competition</button>
                <a href="admin.php?page=competitions" class="btn" style="flex: 1; padding: 12px; text-align: center; text-decoration: none; font-weight: 600; background: #6b7280; color: white; border-radius: 8px;">Cancel</a>
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

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
