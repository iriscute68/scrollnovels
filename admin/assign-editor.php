<?php
// admin/assign-editor.php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();
if (!hasRole('admin')) die("Access denied");

$stories = $pdo->query("SELECT s.id, s.title, u.username FROM stories s JOIN users u ON s.author_id = u.id WHERE s.status = 'pending'")->fetchAll();
// Fetch users that have the 'editor' role via user_roles
$editors = $pdo->query("SELECT u.id, u.username FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON ur.role_id = r.id WHERE r.name = 'editor'")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $story_id = (int)$_POST['story_id'];
    $editor_id = (int)$_POST['editor_id'];

    $stmt = $pdo->prepare("INSERT INTO editor_assignments (story_id, editor_id, assigned_by) VALUES (?, ?, ?)");
    $stmt->execute([$story_id, $editor_id, $_SESSION['user_id']]);

    // Notify editor
    require_once '../includes/functions.php';
    notify($pdo, $editor_id, $_SESSION['user_id'], 'assignment', "You have been assigned to review a story", "/editor.php");

    header("Location: assign-editor.php?success=1");
}
?>

<!DOCTYPE html>
<html><head><title>Assign Editor</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body>
<?php include '../includes/navbar.php'; ?>
<div class="container mt-4">
    <h2>Assign Editor to Story</h2>
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Editor assigned!</div>
    <?php endif; ?>

    <form method="POST" class="row g-3">
        <div class="col-md-6">
            <label>Story</label>
            <select name="story_id" class="form-select" required>
                <option value="">Select Story</option>
                <?php foreach ($stories as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['title']) ?> by <?= $s['username'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label>Editor</label>
            <select name="editor_id" class="form-select" required>
                <option value="">Select Editor</option>
                <?php foreach ($editors as $e): ?>
                    <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Assign</button>
        </div>
    </form>

    <hr>
    <h4>Pending Assignments</h4>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Story</th><th>Author</th><th>Editor</th><th>Status</th></tr></thead>
            <tbody>
                <?php
                $assignments = $pdo->query("
                    SELECT ea.*, s.title, u1.username as author, u2.username as editor
                    FROM editor_assignments ea
                    JOIN stories s ON ea.story_id = s.id
                    JOIN users u1 ON s.author_id = u1.id
                    JOIN users u2 ON ea.editor_id = u2.id
                ")->fetchAll();
                foreach ($assignments as $a): ?>
                <tr>
                    <td><a href="<?= rtrim(SITE_URL, '/') ?>/pages/book.php?id=<?= urlencode($a['story_id']) ?>"><?= htmlspecialchars($a['title']) ?></a></td>
                    <td><?= $a['author'] ?></td>
                    <td><?= $a['editor'] ?></td>
                    <td><span class="badge bg-<?= $a['status'] == 'approved' ? 'success' : ($a['status'] == 'rejected' ? 'danger' : 'warning') ?>">
                        <?= ucfirst($a['status']) ?>
                    </span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body></html>