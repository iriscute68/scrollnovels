<?php
/**
 * Book Settings Management
 * Configure individual book settings, metadata, publishing options
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireLogin();

// Admin check
$isAdmin = isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1;
if (!$isAdmin && !isset($_SESSION['admin_id'])) {
    header("Location: " . site_url('/pages/dashboard.php'));
    exit;
}

$book_id = $_GET['id'] ?? 0;

if (!$book_id) {
    die('Book ID required');
}

// Fetch book
$stmt = $pdo->prepare("SELECT * FROM stories WHERE id = ?");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if (!$book) {
    die('Book not found');
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'update_settings') {
            $stmt = $pdo->prepare("
                UPDATE stories 
                SET 
                    status = ?,
                    is_adult = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $_POST['status'] ?? 'draft',
                $_POST['is_adult'] ?? 0,
                $book_id
            ]);
            
            echo json_encode(['success' => $result, 'message' => 'Settings updated']);
            
        } elseif ($_POST['action'] === 'reset_stats') {
            $stmt = $pdo->prepare("UPDATE stories SET views = 0 WHERE id = ?");
            $result = $stmt->execute([$book_id]);
            
            echo json_encode(['success' => $result, 'message' => 'Stats reset']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?= htmlspecialchars($book['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0f0f12;
            --bg-secondary: #141418;
            --border-color: #22222a;
            --text-primary: #e6e7ea;
            --text-secondary: #9aa0a6;
            --accent: #6366f1;
        }

        body {
            background: linear-gradient(135deg, var(--bg-primary) 0%, #0b0b0d 100%);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container-fluid {
            padding: 30px;
            max-width: 1000px;
        }

        .back-link {
            color: var(--accent);
            text-decoration: none;
            margin-bottom: 20px;
            display: inline-block;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .settings-panel {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
        }

        .panel-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 10px 15px;
            border-radius: 6px;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(0, 0, 0, 0.3);
            border-color: var(--accent);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }

        .form-check {
            padding: 10px 0;
        }

        .form-check-input {
            width: 20px;
            height: 20px;
            border: 1px solid var(--border-color);
            background: rgba(0, 0, 0, 0.2);
            cursor: pointer;
        }

        .form-check-input:checked {
            background: var(--accent);
            border-color: var(--accent);
        }

        .btn-save {
            width: 100%;
            padding: 10px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn-save:hover {
            background: #4f46e5;
        }

        .btn-danger {
            width: 100%;
            padding: 10px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .stat-item:last-child {
            border-bottom: none;
        }

        .stat-label {
            color: var(--text-secondary);
        }

        .stat-value {
            font-weight: 600;
            color: var(--accent);
        }

        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <a href="book_editor.php?id=<?= $book_id ?>" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Editor
        </a>

        <h1 style="margin-bottom: 30px;">⚙️ Book Settings</h1>

        <div class="settings-grid">
            <!-- Publication Settings -->
            <div class="settings-panel">
                <div class="panel-title">
                    <i class="fas fa-paper-plane"></i> Publication Settings
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select id="bookStatus" class="form-select">
                        <option value="draft" <?= $book['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="pending" <?= $book['status'] === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                        <option value="published" <?= $book['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="hiatus" <?= $book['status'] === 'hiatus' ? 'selected' : '' ?>>On Hiatus</option>
                        <option value="rejected" <?= $book['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>

                <div class="form-group">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="isAdult" <?= $book['is_adult'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isAdult">
                            Adult Content (18+)
                        </label>
                    </div>
                </div>

                <button class="btn-save" onclick="saveSettings()">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </div>

            <!-- Statistics -->
            <div class="settings-panel">
                <div class="panel-title">
                    <i class="fas fa-bar-chart"></i> Statistics
                </div>

                <div class="stat-item">
                    <span class="stat-label">Views</span>
                    <span class="stat-value"><?= number_format($book['views'] ?? 0) ?></span>
                </div>

                <div class="stat-item">
                    <span class="stat-label">Created</span>
                    <span class="stat-value"><?= date('M d, Y', strtotime($book['created_at'])) ?></span>
                </div>

                <div class="stat-item">
                    <span class="stat-label">Last Updated</span>
                    <span class="stat-value"><?= date('M d, Y', strtotime($book['updated_at'])) ?></span>
                </div>

                <button class="btn-danger" onclick="resetStats()">
                    <i class="fas fa-redo"></i> Reset Statistics
                </button>
            </div>

            <!-- Access & Sharing -->
            <div class="settings-panel">
                <div class="panel-title">
                    <i class="fas fa-share-alt"></i> Access & Sharing
                </div>

                <div class="form-group">
                    <label class="form-label">Book URL</label>
                    <input type="text" class="form-control" readonly value="/books/<?= htmlspecialchars($book['slug']) ?>">
                </div>

                <div class="form-group">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="allowComments" checked>
                        <label class="form-check-label" for="allowComments">
                            Allow Comments
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="allowSharing" checked>
                        <label class="form-check-label" for="allowSharing">
                            Allow Sharing
                        </label>
                    </div>
                </div>
            </div>

            <!-- Advanced Options -->
            <div class="settings-panel">
                <div class="panel-title">
                    <i class="fas fa-sliders-h"></i> Advanced Options
                </div>

                <div class="form-group">
                    <label class="form-label">Reading Age</label>
                    <select class="form-select">
                        <option>General Audiences</option>
                        <option>Teen (13+)</option>
                        <option>Mature (16+)</option>
                        <option>Adults Only (18+)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Language</label>
                    <select class="form-select">
                        <option>English</option>
                        <option>Spanish</option>
                        <option>French</option>
                        <option>German</option>
                        <option>Other</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <script>
        function saveSettings() {
            const formData = new FormData();
            formData.append('action', 'update_settings');
            formData.append('status', document.getElementById('bookStatus').value);
            formData.append('is_adult', document.getElementById('isAdult').checked ? 1 : 0);

            fetch('book_settings.php?id=<?= $book_id ?>', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Settings saved!');
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }

        function resetStats() {
            if (!confirm('Are you sure you want to reset all statistics?')) return;

            const formData = new FormData();
            formData.append('action', 'reset_stats');

            fetch('book_settings.php?id=<?= $book_id ?>', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Statistics reset!');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
    </script>
</body>
</html>
