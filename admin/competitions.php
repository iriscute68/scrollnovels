<?php
// admin/competitions.php - Manage competitions
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireLogin();

if (!hasRole('admin')) {
    header("Location: ../pages/dashboard.php");
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $rules = trim($_POST['rules'] ?? '');
        $prize = trim($_POST['prize'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $max_entries = (int)($_POST['max_entries'] ?? 1);
        $status = $_POST['status'] ?? 'draft';
        $min_chapters = (int)($_POST['min_chapters'] ?? 0);
        $min_words = (int)($_POST['min_words'] ?? 0);
        $auto_win_by = $_POST['auto_win_by'] ?? 'none';
        
        if (empty($title)) {
            $_SESSION['error'] = 'Competition title is required';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO competitions (title, description, rules, prize, start_date, end_date, max_entries, status, min_chapters, min_words, auto_win_by, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $adminId = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0;
                $stmt->execute([$title, $description, $rules, $prize, $start_date, $end_date, $max_entries, $status, $min_chapters, $min_words, $auto_win_by, $adminId]);
                $_SESSION['success'] = 'Competition created successfully';
            } catch (Exception $e) {
                error_log('Competition create error: ' . $e->getMessage());
                $_SESSION['error'] = 'Failed to create competition';
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $rules = trim($_POST['rules'] ?? '');
        $prize = trim($_POST['prize'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $max_entries = (int)($_POST['max_entries'] ?? 1);
        $status = $_POST['status'] ?? 'draft';
        $min_chapters = (int)($_POST['min_chapters'] ?? 0);
        $min_words = (int)($_POST['min_words'] ?? 0);
        $auto_win_by = $_POST['auto_win_by'] ?? 'none';
        
        if (empty($title)) {
            $_SESSION['error'] = 'Competition title is required';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE competitions 
                    SET title = ?, description = ?, rules = ?, prize = ?, start_date = ?, end_date = ?, max_entries = ?, status = ?, min_chapters = ?, min_words = ?, auto_win_by = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $rules, $prize, $start_date, $end_date, $max_entries, $status, $min_chapters, $min_words, $auto_win_by, $id]);
                $_SESSION['success'] = 'Competition updated successfully';
            } catch (Exception $e) {
                error_log('Competition update error: ' . $e->getMessage());
                $_SESSION['error'] = 'Failed to update competition';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM competitions WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = 'Competition deleted';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Failed to delete competition';
        }
    }
    
    header("Location: competitions.php");
    exit;
}

// Ensure table exists with correct schema
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS competitions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description LONGTEXT,
        rules LONGTEXT,
        prize VARCHAR(255),
        start_date DATETIME,
        end_date DATETIME,
        max_entries INT DEFAULT 1,
        auto_win_by ENUM('none','views','votes','ratings') DEFAULT 'none',
        min_chapters INT DEFAULT 0,
        min_words INT DEFAULT 0,
        cover_image VARCHAR(255),
        prize_info JSON NULL,
        requirements_json JSON NULL,
        created_by INT NULL,
        status ENUM('draft', 'published', 'closed') DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (status),
        INDEX (start_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Try to alter existing table to add 'published' status if it doesn't exist
    // This is safe - if column already has it, it won't error
    try {
        $pdo->exec("ALTER TABLE competitions MODIFY COLUMN status ENUM('draft', 'published', 'closed') DEFAULT 'draft'");
    } catch (Exception $e) {
        // Column may already have this - ignore error
    }
} catch (Exception $e) {
    error_log('Competitions table creation error: ' . $e->getMessage());
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$whereSql = '';

if ($filter !== 'all') {
    $whereSql = " WHERE status = '" . $pdo->quote($filter) . "'";
}

// Fetch competitions
try {
    $sql = "SELECT * FROM competitions" . $whereSql . " ORDER BY start_date DESC LIMIT 200";
    $competitions = $pdo->query($sql)->fetchAll();
} catch (Exception $e) {
    $competitions = [];
}

// Get counts
try {
    $draft_count = $pdo->query("SELECT COUNT(*) FROM competitions WHERE status = 'draft'")->fetchColumn();
    $published_count = $pdo->query("SELECT COUNT(*) FROM competitions WHERE status = 'published'")->fetchColumn();
    $closed_count = $pdo->query("SELECT COUNT(*) FROM competitions WHERE status = 'closed'")->fetchColumn();
} catch (Exception $e) {
    $draft_count = $published_count = $closed_count = 0;
}

$edit_mode = false;
$edit_comp = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ?");
        $stmt->execute([$id]);
        $edit_comp = $stmt->fetch();
        $edit_mode = true;
    } catch (Exception $e) {}
}

include dirname(__DIR__) . '/includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Manage Competitions</h2>
        </div>
        <div class="col-md-6 text-end">
            <a href="admin.php" class="btn btn-secondary">Back to Admin</a>
        </div>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); endif; ?>
    
    <?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); endif; ?>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h5><?= $draft_count ?></h5>
                    <small>Draft</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5><?= $published_count ?></h5>
                    <small>Published</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-secondary">
                <div class="card-body">
                    <h5><?= $closed_count ?></h5>
                    <small>Closed</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5><?= $edit_mode ? 'Edit Competition' : 'Create New Competition' ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="<?= $edit_mode ? 'update' : 'create' ?>">
                        <?php if ($edit_mode): ?>
                        <input type="hidden" name="id" value="<?= $edit_comp['id'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($edit_comp['title'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($edit_comp['description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Rules</label>
                            <textarea name="rules" class="form-control" rows="3"><?= htmlspecialchars($edit_comp['rules'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Prize Information</label>
                            <input type="text" name="prize" class="form-control" value="<?= htmlspecialchars($edit_comp['prize'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Start Date *</label>
                            <input type="datetime-local" name="start_date" class="form-control" required value="<?= $edit_comp && $edit_comp['start_date'] ? date('Y-m-d\TH:i', strtotime($edit_comp['start_date'])) : '' ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">End Date *</label>
                            <input type="datetime-local" name="end_date" class="form-control" required value="<?= $edit_comp && $edit_comp['end_date'] ? date('Y-m-d\TH:i', strtotime($edit_comp['end_date'])) : '' ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Minimum Chapters</label>
                            <input type="number" name="min_chapters" class="form-control" min="0" value="<?= $edit_comp['min_chapters'] ?? 0 ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Minimum Word Count</label>
                            <input type="number" name="min_words" class="form-control" min="0" value="<?= $edit_comp['min_words'] ?? 0 ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Winner Selection Method</label>
                            <select name="auto_win_by" class="form-control">
                                <option value="none" <?= ($edit_comp['auto_win_by'] ?? 'none') === 'none' ? 'selected' : '' ?>>Manual (Admin selects)</option>
                                <option value="views" <?= ($edit_comp['auto_win_by'] ?? 'none') === 'views' ? 'selected' : '' ?>>Most Views</option>
                                <option value="votes" <?= ($edit_comp['auto_win_by'] ?? 'none') === 'votes' ? 'selected' : '' ?>>Most Votes</option>
                                <option value="ratings" <?= ($edit_comp['auto_win_by'] ?? 'none') === 'ratings' ? 'selected' : '' ?>>Highest Rating</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Max Entries Per User</label>
                            <input type="number" name="max_entries" class="form-control" min="1" value="<?= $edit_comp['max_entries'] ?? 1 ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status *</label>
                            <select name="status" class="form-control" required>
                                <option value="draft" <?= ($edit_comp['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft (Hidden)</option>
                                <option value="published" <?= ($edit_comp['status'] ?? 'draft') === 'published' ? 'selected' : '' ?>>Published (Visible)</option>
                                <option value="closed" <?= ($edit_comp['status'] ?? 'draft') === 'closed' ? 'selected' : '' ?>>Closed (Archived)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100"><?= $edit_mode ? 'Update' : 'Create' ?></button>
                        <?php if ($edit_mode): ?>
                        <a href="competitions.php" class="btn btn-secondary w-100 mt-2">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- Filters -->
            <div class="mb-3">
                <a href="?filter=all" class="btn btn-outline-secondary <?= $filter === 'all' ? 'active' : '' ?>">All</a>
                <a href="?filter=draft" class="btn btn-outline-info <?= $filter === 'draft' ? 'active' : '' ?>">Draft</a>
                <a href="?filter=published" class="btn btn-outline-success <?= $filter === 'published' ? 'active' : '' ?>">Published</a>
                <a href="?filter=closed" class="btn btn-outline-secondary <?= $filter === 'closed' ? 'active' : '' ?>">Closed</a>
            </div>

            <!-- Competitions Table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Title</th>
                            <th>Created By</th>
                            <th>Entries</th>
                            <th>Status</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($competitions as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['title']) ?></strong></td>
                            <td>
                                <?php
                                    if ($c['created_by']) {
                                        $userStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                                        $userStmt->execute([$c['created_by']]);
                                        $creator = $userStmt->fetch();
                                        echo htmlspecialchars($creator['username'] ?? 'Unknown');
                                    } else {
                                        echo 'System';
                                    }
                                ?>
                            </td>
                            <td>
                                <?php
                                    $entryStmt = $pdo->prepare("SELECT COUNT(*) FROM competition_entries WHERE competition_id = ?");
                                    $entryStmt->execute([$c['id']]);
                                    $entryCount = $entryStmt->fetchColumn();
                                    echo $entryCount . ($c['max_entries'] > 0 ? ' / ' . $c['max_entries'] : '');
                                ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $c['status'] === 'published' ? 'success' : ($c['status'] === 'draft' ? 'warning' : 'secondary') ?>">
                                    <?= ucfirst($c['status']) ?>
                                </span>
                            </td>
                            <td><small><?= $c['start_date'] ? date('M d, Y', strtotime($c['start_date'])) : 'Not set' ?></small></td>
                            <td><small><?= $c['end_date'] ? date('M d, Y', strtotime($c['end_date'])) : 'Not set' ?></small></td>
                            <td>
                                <a href="/admin/competitions_edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this competition?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (empty($competitions)): ?>
            <div class="alert alert-info">No competitions found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
