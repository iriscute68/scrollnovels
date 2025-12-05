<?php
// pages/admin/guides.php - Admin Guides Manager
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

requireLogin();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . site_url('/'));
    exit;
}

// Ensure guides table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS guides (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    content LONGTEXT,
    media JSON NULL,
    status ENUM('draft','published') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$message = '';
$message_type = '';

try {
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $content = $_POST['content'] ?? '';
        $status = $_POST['status'] ?? 'draft';
        $media = isset($_POST['media']) ? json_decode($_POST['media'], true) : [];
        if (!$slug) {
            $slug = preg_replace('/[^a-z0-9-]/','-', strtolower($title));
            $slug = preg_replace('/-+/','-', $slug);
            $slug = trim($slug, '-');
        }
        $media_json = json_encode($media ?? []);
        if ($id) {
            $stmt = $pdo->prepare('UPDATE guides SET title=?, slug=?, content=?, media=?, status=? WHERE id=?');
            $stmt->execute([$title, $slug, $content, $media_json, $status, $id]);
            $message = 'Guide updated';
        } else {
            $stmt = $pdo->prepare('INSERT INTO guides (title, slug, content, media, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$title, $slug, $content, $media_json, $status]);
            $message = 'Guide created';
        }
        $message_type = 'success';
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM guides WHERE id=?')->execute([$id]);
            $message = 'Guide deleted';
            $message_type = 'success';
        }
    } elseif ($action === 'publish') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE guides SET status='published' WHERE id=?")->execute([$id]);
            $message = 'Guide published';
            $message_type = 'success';
        }
    }
} catch (Exception $e) {
    $message = 'Error: ' . $e->getMessage();
    $message_type = 'error';
}

$guides = $pdo->query('SELECT * FROM guides ORDER BY updated_at DESC')->fetchAll();

$page_title = 'Admin: Guides';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<main class="max-w-6xl mx-auto px-4 py-10">
    <h1 class="text-3xl font-bold mb-6">Guides Manager</h1>
    <?php if ($message): ?>
    <div class="mb-4 p-3 rounded <?= $message_type==='success'?'bg-green-100':'bg-red-100' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <section class="mb-8">
        <h2 class="text-xl font-semibold mb-3">Create / Edit</h2>
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="title" class="block text-sm font-medium">Title</label>
                    <input id="title" name="title" class="w-full px-3 py-2 border rounded" required>
                </div>
                <div>
                    <label for="slug" class="block text-sm font-medium">Slug</label>
                    <input id="slug" name="slug" class="w-full px-3 py-2 border rounded" placeholder="auto-generated if blank">
                </div>
            </div>
            <div class="mt-3">
                <label for="content" class="block text-sm font-medium">Content (HTML allowed)</label>
                <textarea id="content" name="content" rows="8" class="w-full px-3 py-2 border rounded"></textarea>
            </div>
            <div class="mt-3">
                <label for="status" class="block text-sm font-medium">Status</label>
                <select id="status" name="status" class="w-full px-3 py-2 border rounded">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                </select>
            </div>
            <div class="mt-4">
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded">Save</button>
            </div>
        </form>
    </section>

    <section>
        <h2 class="text-xl font-semibold mb-3">Existing Guides</h2>
        <?php if (empty($guides)): ?>
            <p>No guides yet.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($guides as $g): ?>
                <div class="p-4 border rounded">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="font-semibold"><?= htmlspecialchars($g['title']) ?> <span class="text-xs px-2 py-1 rounded bg-gray-100"><?= htmlspecialchars($g['status']) ?></span></h3>
                            <p class="text-xs text-gray-600">Updated: <?= htmlspecialchars($g['updated_at']) ?></p>
                        </div>
                        <div class="flex gap-2">
                            <form method="POST">
                                <input type="hidden" name="action" value="publish">
                                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                                <button class="px-3 py-1 bg-blue-600 text-white rounded">Publish</button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Delete this guide?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                                <button class="px-3 py-1 bg-red-600 text-white rounded">Delete</button>
                            </form>
                        </div>
                    </div>
                    <div class="mt-3 text-sm text-gray-800">
                        <?= $g['content'] ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
