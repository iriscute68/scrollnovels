<?php
// blog/create.php - Blog post editor with Quill WYSIWYG blocks
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is admin or author
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: ' . site_url('/pages/login.php'));
    exit;
}

$post = null;
$post_id = intval($_GET['id'] ?? 0);
if ($post_id) {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND user_id = ?");
    $stmt->execute([$post_id, $userId]);
    $post = $stmt->fetch();
    if (!$post) {
        header('Location: ' . site_url('/blog/'));
        exit;
    }
}

$categories = ['Update', 'Event', 'Announcement', 'Patch Note', 'Community', 'Dev Log', 'Spotlight'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $post ? 'Edit Post' : 'New Blog Post' ?> - Scroll Novels</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    
    <link rel="stylesheet" href="<?= asset_url('css/theme.css') ?>">
</head>
<body class="bg-gray-900 text-white">

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="max-w-4xl mx-auto p-6 mt-16">
    <h1 class="text-3xl font-bold mb-6"><?= $post ? 'Edit Post' : 'Create New Blog Post' ?></h1>

    <form id="postForm" class="space-y-6">
        <!-- Title -->
        <div>
            <label class="block text-sm font-medium mb-1">Post Title</label>
            <input type="text" id="title" name="title" placeholder="Enter post title..." required
                   value="<?= htmlspecialchars($post['title'] ?? '') ?>"
                   class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-gold">
            <small class="text-gray-500">Slug will auto-generate: <span id="slugPreview"><?= htmlspecialchars($post['slug'] ?? 'post-title') ?></span></small>
        </div>

        <!-- Category & Tags -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Category</label>
                <select id="category" name="category" class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= ($post['category'] ?? '') === $cat ? 'selected' : '' ?>>
                            <?= $cat ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Tags (comma separated)</label>
                <input type="text" id="tags" name="tags" placeholder="news, update, release"
                       value="<?= htmlspecialchars($post['tags'] ?? '') ?>"
                       class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-gold">
            </div>
        </div>

        <!-- Excerpt -->
        <div>
            <label class="block text-sm font-medium mb-1">Excerpt (Summary)</label>
            <textarea id="excerpt" name="excerpt" placeholder="Brief summary..." rows="2"
                      class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-gold">
<?= htmlspecialchars($post['excerpt'] ?? '') ?></textarea>
        </div>

        <!-- Cover Image -->
        <div>
            <label class="block text-sm font-medium mb-1">Cover Image</label>
            <input type="file" id="cover_upload" accept="image/*"
                   class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-400">
            <input type="hidden" id="cover_image" name="cover_image" value="<?= htmlspecialchars($post['cover_image'] ?? '') ?>">
            <?php if ($post && $post['cover_image']): ?>
                <div class="mt-2"><img src="<?= htmlspecialchars($post['cover_image']) ?>" alt="Cover" class="max-w-xs rounded-lg"></div>
            <?php endif; ?>
        </div>

        <!-- Content Editor (Quill) -->
        <div>
            <label class="block text-sm font-medium mb-1">Content</label>
            <div id="editor" style="height: 400px;" class="bg-white text-black rounded-lg"></div>
            <input type="hidden" id="content" name="content" value="<?= htmlspecialchars($post['content'] ?? '') ?>">
        </div>

        <!-- Status & Actions -->
        <div class="flex gap-4 justify-between">
            <div>
                <label class="text-sm font-medium">
                    <input type="radio" name="status" value="draft" <?= ($post['status'] ?? 'draft') === 'draft' ? 'checked' : '' ?> required>
                    Save as Draft
                </label>
                <label class="text-sm font-medium ml-4">
                    <input type="radio" name="status" value="published" <?= ($post['status'] ?? '') === 'published' ? 'checked' : '' ?> required>
                    Publish
                </label>
            </div>
            <div class="space-x-2">
                <button type="button" id="preview" class="px-6 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg">Preview</button>
                <button type="submit" class="px-6 py-2 bg-gold text-midnight font-bold rounded-lg hover:bg-yellow-400">
                    <?= $post ? 'Update Post' : 'Create Post' ?>
                </button>
                <a href="<?= site_url('/blog/') ?>" class="px-6 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg inline-block">Cancel</a>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// Initialize Quill editor
const quill = new Quill('#editor', {
    theme: 'snow',
    modules: {
        toolbar: [
            ['bold', 'italic', 'underline'],
            ['link', 'blockquote', 'code-block'],
            [{ 'header': 1 }, { 'header': 2 }],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            ['clean']
        ]
    }
});

// Load existing content
<?php if ($post && $post['content']): ?>
quill.root.innerHTML = <?= json_encode($post['content']) ?>;
<?php endif; ?>

// Auto-generate slug from title
document.getElementById('title').addEventListener('keyup', function() {
    const slug = this.value.toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    document.getElementById('slugPreview').textContent = slug || 'post-title';
});

// Cover image upload
document.getElementById('cover_upload').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    
    const formData = new FormData();
    formData.append('image', file);
    
    try {
        const res = await fetch('<?= site_url('/api/upload_image.php') ?>', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('cover_image').value = data.url;
        } else {
            alert('Upload failed: ' + (data.error || 'Unknown error'));
        }
    } catch (err) {
        console.error(err);
        alert('Upload error: ' + err.message);
    }
});

// Form submission
document.getElementById('postForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(document.getElementById('postForm'));
    formData.set('content', quill.root.innerHTML);
    formData.set('post_id', <?= $post_id ?>);
    
    try {
        const res = await fetch('<?= site_url('/api/save_post.php') ?>', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            window.location = '<?= site_url('/blog/') ?>';
        } else {
            alert('Error: ' + (data.error || 'Failed to save'));
        }
    } catch (err) {
        console.error(err);
        alert('Save error: ' + err.message);
    }
});

// Preview
document.getElementById('preview').addEventListener('click', () => {
    const form = new FormData(document.getElementById('postForm'));
    form.set('content', quill.root.innerHTML);
    
    const previewWindow = window.open('<?= site_url('/blog/preview.php') ?>', 'preview', 'width=900,height=700');
    
    // Send data to preview window
    setTimeout(() => {
        previewWindow.document.body.innerHTML = `
            <div style="padding: 20px; max-width: 800px; margin: 0 auto;">
                <h1>${form.get('title')}</h1>
                <p><strong>${form.get('category')}</strong> | ${form.get('tags')}</p>
                <hr>
                <div>${form.get('content')}</div>
            </div>
        `;
    }, 500);
});
</script>

</body>
</html>
