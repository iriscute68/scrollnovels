<?php
// admin/pages/guides.php - Guide Page Management

// Ensure guide_pages table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS guide_pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(255) UNIQUE NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            content LONGTEXT,
            order_index INT DEFAULT 0,
            published TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $content = $_POST['content'] ?? '';
        $order_index = (int)($_POST['order_index'] ?? 0);
        $published = isset($_POST['published']) ? 1 : 0;
        
        // Auto-generate slug if empty
        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
        }
        
        if (empty($title)) {
            $message = 'Title is required';
            $messageType = 'danger';
        } else {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare("INSERT INTO guide_pages (title, slug, description, content, order_index, published) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $slug, $description, $content, $order_index, $published]);
                    $message = 'Guide page created successfully!';
                    $messageType = 'success';
                } else {
                    $stmt = $pdo->prepare("UPDATE guide_pages SET title = ?, slug = ?, description = ?, content = ?, order_index = ?, published = ? WHERE id = ?");
                    $stmt->execute([$title, $slug, $description, $content, $order_index, $published, $id]);
                    $message = 'Guide page updated successfully!';
                    $messageType = 'success';
                }
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM guide_pages WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Guide page deleted successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error deleting guide page';
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'seed_defaults') {
        // Seed default guides from pages/guides.php
        $defaultGuides = [
            ['slug' => 'getting-started', 'title' => '🚀 Getting Started', 'description' => 'Everything you need to know to start using Scroll Novels', 'order_index' => 1],
            ['slug' => 'writing-guide', 'title' => '✍️ Writing Your Story', 'description' => 'Tips and best practices for writing on Scroll Novels', 'order_index' => 2],
            ['slug' => 'community-guidelines', 'title' => '📜 Community Guidelines', 'description' => 'Rules and guidelines for a safe and respectful community', 'order_index' => 3],
            ['slug' => 'faq', 'title' => '❓ Frequently Asked Questions', 'description' => 'Answers to common questions about Scroll Novels', 'order_index' => 4],
            ['slug' => 'competitions', 'title' => '🏆 Competitions & Events', 'description' => 'Learn how to participate in writing competitions', 'order_index' => 5],
            ['slug' => 'points-rewards', 'title' => '⭐ Points & Rewards', 'description' => 'Understanding the points system and how to earn rewards', 'order_index' => 6],
        ];
        
        $seeded = 0;
        foreach ($defaultGuides as $guide) {
            try {
                // Check if already exists
                $check = $pdo->prepare("SELECT id FROM guide_pages WHERE slug = ?");
                $check->execute([$guide['slug']]);
                if (!$check->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO guide_pages (slug, title, description, content, order_index, published) VALUES (?, ?, ?, '', ?, 1)");
                    $stmt->execute([$guide['slug'], $guide['title'], $guide['description'], $guide['order_index']]);
                    $seeded++;
                }
            } catch (Exception $e) {}
        }
        $message = "Seeded $seeded default guide templates. Edit each guide to add content.";
        $messageType = 'success';
    }
}

// Fetch all guides
$guides = $pdo->query("SELECT * FROM guide_pages ORDER BY order_index ASC, created_at DESC")->fetchAll();

// Check if editing
$editGuide = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM guide_pages WHERE id = ?");
    $stmt->execute([$editId]);
    $editGuide = $stmt->fetch();
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3><i class="fas fa-book-reader"></i> Guide Pages Management</h3>
    </div>
    <div class="col-md-6 text-end">
        <?php if (empty($guides)): ?>
        <form method="post" class="d-inline me-2">
            <input type="hidden" name="action" value="seed_defaults">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-seedling"></i> Seed Default Guides
            </button>
        </form>
        <?php endif; ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#guideModal" onclick="resetForm()">
            <i class="fas fa-plus"></i> Create Guide Page
        </button>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Guides Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Order</th>
                        <th>Title</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($guides)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No guide pages found. Create one to get started!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($guides as $guide): ?>
                        <tr>
                            <td><?= $guide['order_index'] ?></td>
                            <td><strong><?= htmlspecialchars($guide['title']) ?></strong></td>
                            <td><code><?= htmlspecialchars($guide['slug']) ?></code></td>
                            <td>
                                <?php if ($guide['published']): ?>
                                    <span class="badge bg-success">Published</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($guide['updated_at'])) ?></td>
                            <td>
                                <a href="<?= site_url('/pages/guides.php?slug=' . urlencode($guide['slug'])) ?>" 
                                   class="btn btn-sm btn-info" target="_blank" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button class="btn btn-sm btn-warning" onclick="editGuide(<?= htmlspecialchars(json_encode($guide)) ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Delete this guide page?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $guide['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Guide Editor Modal -->
<div class="modal fade" id="guideModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Create Guide Page</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="guideId" value="">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="guideTitle" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">URL Slug</label>
                                <input type="text" name="slug" id="guideSlug" class="form-control" placeholder="auto-generated">
                                <small class="text-muted">Leave empty to auto-generate from title</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="guideDescription" class="form-control" rows="2" placeholder="Brief description for the guide"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea name="content" id="guideContent" class="form-control" rows="15" placeholder="Guide content (HTML supported)"></textarea>
                        <small class="text-muted">You can use HTML formatting in the content</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Display Order</label>
                                <input type="number" name="order_index" id="guideOrder" class="form-control" value="0" min="0">
                                <small class="text-muted">Lower numbers appear first</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check mt-2">
                                    <input type="checkbox" name="published" id="guidePublished" class="form-check-input" checked>
                                    <label class="form-check-label" for="guidePublished">Published</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Guide Page
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('modalTitle').textContent = 'Create Guide Page';
    document.getElementById('formAction').value = 'create';
    document.getElementById('guideId').value = '';
    document.getElementById('guideTitle').value = '';
    document.getElementById('guideSlug').value = '';
    document.getElementById('guideDescription').value = '';
    document.getElementById('guideContent').value = '';
    document.getElementById('guideOrder').value = '0';
    document.getElementById('guidePublished').checked = true;
}

function editGuide(guide) {
    document.getElementById('modalTitle').textContent = 'Edit Guide Page';
    document.getElementById('formAction').value = 'update';
    document.getElementById('guideId').value = guide.id;
    document.getElementById('guideTitle').value = guide.title;
    document.getElementById('guideSlug').value = guide.slug;
    document.getElementById('guideDescription').value = guide.description || '';
    document.getElementById('guideContent').value = guide.content || '';
    document.getElementById('guideOrder').value = guide.order_index || 0;
    document.getElementById('guidePublished').checked = guide.published == 1;
    
    // Update Quill editor content if it exists
    if (window.quillGuide) {
        window.quillGuide.root.innerHTML = guide.content || '';
    }
    
    new bootstrap.Modal(document.getElementById('guideModal')).show();
}

// Auto-generate slug from title
document.getElementById('guideTitle').addEventListener('input', function() {
    const slugField = document.getElementById('guideSlug');
    if (!slugField.value) {
        // Only auto-generate if slug is empty
        slugField.placeholder = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    }
});
</script>

<!-- Quill WYSIWYG Editor (Free, no API key) -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
const guideTextarea = document.getElementById('guideContent');
if (guideTextarea) {
    const editorDiv = document.createElement('div');
    editorDiv.id = 'quill-guide-editor';
    editorDiv.style.minHeight = '300px';
    editorDiv.innerHTML = guideTextarea.value;
    guideTextarea.style.display = 'none';
    guideTextarea.parentNode.insertBefore(editorDiv, guideTextarea);
    
    window.quillGuide = new Quill('#quill-guide-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'align': [] }],
                ['link', 'image', 'code-block'],
                ['clean']
            ]
        }
    });
    
    // Sync content before form submit
    guideTextarea.closest('form').addEventListener('submit', function() {
        guideTextarea.value = quillGuide.root.innerHTML;
    });
}
</script>
