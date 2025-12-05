<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['tag_name'])) {
    $name = trim($_POST['tag_name']);
    try {
        $stmt = $pdo->prepare('INSERT INTO tags (name, created_at) VALUES (?, NOW())');
        $stmt->execute([$name]);
        $msg = "Tag created: $name";
    } catch (Exception $e) {
        $msg = 'Error creating tag: ' . $e->getMessage();
    }
}

$tags = [];
try { $tags = $pdo->query('SELECT * FROM tags ORDER BY name')->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e) { $tags = []; }
?>
<main class="admin-main">
    <?php require __DIR__ . '/topbar.php'; ?>
    <h1 class="text-2xl font-bold mb-4">Tags</h1>
    <?php if (!empty($msg)): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="POST" class="mb-6">
        <label class="block mb-2">Create Tag</label>
        <input name="tag_name" class="input" required placeholder="Tag name">
        <button class="btn btn-primary mt-2">Create</button>
    </form>

    <div>
        <h3 class="font-semibold mb-2">Existing Tags</h3>
        <div class="space-y-2">
            <?php foreach($tags as $t): ?>
                <div class="p-2 bg-white dark:bg-gray-800 rounded">#<?= htmlspecialchars($t['name']) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>
<?php
// admin/tags.php - Manage story tags
require_once 'inc/header.php';
$activeTab = 'tags';
require_once 'inc/sidebar.php';

// Ensure tags table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tags (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) UNIQUE NOT NULL,
        slug VARCHAR(100) UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // table may exist
}

// Handle tag creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if ($name) {
            try {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
                $stmt = $pdo->prepare("INSERT INTO tags (name, slug, description) VALUES (?, ?, ?)");
                $stmt->execute([$name, $slug, $description]);
                $success = "Tag created successfully";
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $tag_id = (int)($_POST['tag_id'] ?? 0);
        if ($tag_id) {
            try {
                $pdo->prepare("DELETE FROM tags WHERE id = ?")->execute([$tag_id]);
                $success = "Tag deleted successfully";
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch tags
$tags = $pdo->query("SELECT * FROM tags ORDER BY name")->fetchAll() ?? [];
?>

<main class="flex-1 p-6 ml-64">
  <div class="mb-6">
    <h2 class="text-2xl font-bold">🏷️ Story Tags</h2>
    <p class="text-gray-400">Manage available tags for stories</p>
  </div>

  <?php if (isset($success)): ?>
    <div class="mb-4 p-4 bg-green-900/30 border border-green-600 text-green-400 rounded-lg">✓ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  
  <?php if (isset($error)): ?>
    <div class="mb-4 p-4 bg-red-900/30 border border-red-600 text-red-400 rounded-lg">✕ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Create Tag Form -->
  <div class="card mb-6">
    <h3 class="text-lg font-bold mb-4">Create New Tag</h3>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="create">
      <div>
        <label class="block text-sm font-medium mb-2">Tag Name</label>
        <input type="text" name="name" required placeholder="e.g. Action, Romance, Fantasy" class="w-full px-3 py-2 bg-[#0f1113] border border-[#2a2d31] rounded text-white">
      </div>
      <div>
        <label class="block text-sm font-medium mb-2">Description (optional)</label>
        <textarea name="description" rows="2" placeholder="Brief description..." class="w-full px-3 py-2 bg-[#0f1113] border border-[#2a2d31] rounded text-white"></textarea>
      </div>
      <button type="submit" class="px-4 py-2 btn btn-primary">Create Tag</button>
    </form>
  </div>

  <!-- Tags Table -->
  <div class="card">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-[#1f2937]">
          <th class="text-left p-3">Name</th>
          <th class="text-left p-3">Slug</th>
          <th class="text-left p-3">Description</th>
          <th class="text-left p-3">Created</th>
          <th class="text-left p-3">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($tags)): ?>
          <tr>
            <td colspan="5" class="p-3 text-center text-gray-500">No tags yet</td>
          </tr>
        <?php else: ?>
          <?php foreach ($tags as $tag): ?>
            <tr class="border-b border-[#1f2937] hover:bg-[#0f1113]">
              <td class="p-3 font-semibold"><?= htmlspecialchars($tag['name']) ?></td>
              <td class="p-3 text-gray-400"><?= htmlspecialchars($tag['slug']) ?></td>
              <td class="p-3 text-gray-400"><?= htmlspecialchars(substr($tag['description'] ?? '', 0, 50)) ?></td>
              <td class="p-3 text-gray-400"><?= date('M d, Y', strtotime($tag['created_at'])) ?></td>
              <td class="p-3">
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="tag_id" value="<?= $tag['id'] ?>">
                  <button type="submit" class="px-2 py-1 btn btn-danger text-xs" onclick="return confirm('Delete this tag?')">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<?php require_once 'inc/footer.php'; ?>
