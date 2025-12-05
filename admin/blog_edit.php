<?php
// admin/blog_edit.php - Edit existing blog post
session_start();
require_once __DIR__ . '/inc/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: blog_list.php');
    exit;
}

// Fetch post
$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    header('Location: blog_list.php');
    exit;
}

$categories = ['Update', 'Event', 'Announcement', 'Patch Note', 'Community', 'Dev Log', 'Spotlight', 'Tutorial', 'Story Spotlight'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Blog Post - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.0/dist/quill.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.0/dist/quill.snow.css" rel="stylesheet">
    <style>
        body { background: #0f0820; color: #F5F0E8; }
        .card { background: #1a0f3a; border: 1px solid rgba(212,175,55,0.1); }
        .btn-gold { background: #D4AF37; color: #120A2A; }
        .btn-gold:hover { background: #e0c158; }
        .ql-editor { background: #0f0820; color: #F5F0E8; min-height: 400px; }
        .ql-toolbar { background: #1a0f3a; border: 1px solid rgba(212,175,55,0.1); }
        input, textarea, select { background: #0f0820 !important; color: #F5F0E8 !important; border: 1px solid rgba(212,175,55,0.2) !important; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="max-w-4xl mx-auto p-6 mt-16">
    <h1 class="text-4xl font-bold text-yellow-400 mb-8">✏️ Edit Blog Post</h1>

    <form id="postForm" class="space-y-6">
        <input type="hidden" name="id" value="<?= $post['id'] ?>" />

        <!-- Title -->
        <div class="card p-6 rounded-lg">
            <label class="block text-sm font-semibold mb-2">Title *</label>
            <input type="text" name="title" id="title" placeholder="Enter blog post title" 
                   class="w-full px-4 py-2 rounded" required 
                   value="<?= htmlspecialchars($post['title']) ?>"
                   oninput="updateSlug()" />
        </div>

        <!-- Slug -->
        <div class="card p-6 rounded-lg">
            <label class="block text-sm font-semibold mb-2">Slug (URL)</label>
            <input type="text" name="slug" id="slug" placeholder="auto-generated-from-title" 
                   class="w-full px-4 py-2 rounded text-gray-500"
                   value="<?= htmlspecialchars($post['slug']) ?>"
                   readonly />
            <p class="text-xs text-gray-500 mt-2">Auto-generated from title</p>
        </div>

        <!-- Category & Tags -->
        <div class="grid grid-cols-2 gap-6">
            <div class="card p-6 rounded-lg">
                <label class="block text-sm font-semibold mb-2">Category</label>
                <select name="category" class="w-full px-4 py-2 rounded">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $post['category'] === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="card p-6 rounded-lg">
                <label class="block text-sm font-semibold mb-2">Tags (comma-separated)</label>
                <input type="text" name="tags" placeholder="e.g. update, feature, fix" 
                       class="w-full px-4 py-2 rounded"
                       value="<?= htmlspecialchars($post['tags'] ?? '') ?>" />
            </div>
        </div>

        <!-- Excerpt -->
        <div class="card p-6 rounded-lg">
            <label class="block text-sm font-semibold mb-2">Excerpt (Brief Summary)</label>
            <textarea name="excerpt" placeholder="Brief summary of the post (max 500 chars)" 
                      class="w-full px-4 py-2 rounded" rows="3" maxlength="500"><?= htmlspecialchars($post['excerpt'] ?? '') ?></textarea>
        </div>

        <!-- Cover Image -->
        <div class="card p-6 rounded-lg">
            <label class="block text-sm font-semibold mb-2">Cover Image</label>
            <div class="flex gap-4 items-center">
                <input type="file" name="cover_image" id="coverInput" accept="image/*" class="flex-1" />
                <img id="coverPreview" src="<?= htmlspecialchars($post['cover_image'] ?? '') ?>" 
                     alt="Cover preview" class="w-32 h-32 rounded object-cover <?= $post['cover_image'] ? '' : 'hidden' ?>" />
            </div>
        </div>

        <!-- Rich Text Editor -->
        <div class="card p-6 rounded-lg">
            <label class="block text-sm font-semibold mb-2">Content *</label>
            <div id="editor"></div>
        </div>

        <!-- Status -->
        <div class="card p-6 rounded-lg">
            <label class="block text-sm font-semibold mb-2">Status</label>
            <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="status" value="draft" <?= $post['status'] === 'draft' ? 'checked' : '' ?> />
                    <span>Draft (Private)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="status" value="published" <?= $post['status'] === 'published' ? 'checked' : '' ?> />
                    <span>Published (Public)</span>
                </label>
            </div>
        </div>

        <!-- Meta -->
        <div class="card p-6 rounded-lg">
            <p class="text-sm text-gray-400">
                <strong>Views:</strong> <?= (int)$post['views'] ?> | 
                <strong>Created:</strong> <?= date('M d, Y H:i', strtotime($post['created_at'])) ?> | 
                <strong>Updated:</strong> <?= date('M d, Y H:i', strtotime($post['updated_at'])) ?>
            </p>
        </div>

        <!-- Buttons -->
        <div class="flex gap-4">
            <button type="submit" name="action" value="save_draft" class="btn-gold px-6 py-2 rounded font-semibold">💾 Save Changes</button>
            <button type="submit" name="action" value="publish" class="bg-green-600 hover:bg-green-700 px-6 py-2 rounded font-semibold">🚀 Publish</button>
            <a href="blog_list.php" class="px-6 py-2 rounded bg-gray-700 hover:bg-gray-600">Cancel</a>
        </div>
    </form>
</div>

<script>
// Initialize Quill editor with existing content
const quill = new Quill('#editor', {
    theme: 'snow',
    modules: {
        toolbar: [
            ['bold', 'italic', 'underline', 'strike'],
            ['blockquote', 'code-block'],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            [{ 'header': [1, 2, 3, false] }],
            ['link', 'image'],
            ['clean']
        ]
    }
});

// Load existing content
quill.root.innerHTML = <?= json_encode($post['content']) ?>;

// Auto-generate slug from title
function updateSlug() {
    const title = document.getElementById('title').value;
    const slug = title
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_]+/g, '-')
        .replace(/^-+|-+$/g, '');
    document.getElementById('slug').value = slug || 'untitled';
}

// Preview cover image
document.getElementById('coverInput').addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = (evt) => {
            document.getElementById('coverPreview').src = evt.target.result;
            document.getElementById('coverPreview').classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }
});

// Form submission
document.getElementById('postForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData();
    formData.append('id', document.querySelector('[name="id"]').value);
    formData.append('title', document.querySelector('[name="title"]').value);
    formData.append('slug', document.getElementById('slug').value);
    formData.append('category', document.querySelector('[name="category"]').value);
    formData.append('tags', document.querySelector('[name="tags"]').value);
    formData.append('excerpt', document.querySelector('[name="excerpt"]').value);
    formData.append('content', quill.root.innerHTML);
    formData.append('status', document.querySelector('[name="status"]:checked').value);
    
    // Handle cover image upload
    const coverFile = document.getElementById('coverInput').files[0];
    if (coverFile) {
        formData.append('cover_image', coverFile);
    }

    try {
        const response = await fetch('api/blog_save.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message || 'Blog post updated successfully!');
            window.location.href = 'blog_list.php';
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
});
</script>
</body>
</html>
