<?php
// admin/blog_create.php - Create blog posts/announcements
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Check login - support both user_id and admin_user session keys
$logged_in = isset($_SESSION['user_id']) || isset($_SESSION['admin_user']) || isset($_SESSION['admin_id']);
if (!$logged_in) {
    header('Location: /scrollnovels/pages/login.php');
    exit;
}

// Check admin role
$user_id = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0;
if ($user_id) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user || !in_array($user['role'] ?? '', ['admin', 'super_admin', 'moderator'])) {
        echo "Access denied. Admin role required.";
        exit;
    }
}

try {
    $pdo->exec("
        ALTER TABLE announcements 
        ADD COLUMN IF NOT EXISTS featured_image VARCHAR(500),
        ADD COLUMN IF NOT EXISTS featured_image_alt VARCHAR(255),
        ADD COLUMN IF NOT EXISTS featured_image_url VARCHAR(500),
        ADD COLUMN IF NOT EXISTS is_blog TINYINT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS external_links JSON,
        ADD COLUMN IF NOT EXISTS is_featured TINYINT DEFAULT 0
    ");
} catch (Exception $e) {
    // Columns may already exist
}

$blog_id = intval($_GET['id'] ?? 0);
$blog_data = null;
$external_links = [];

// Load existing blog if editing
if ($blog_id) {
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->execute([$blog_id]);
    $blog_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$blog_data) {
        die('Blog post not found');
    }
    
    // Parse external links if they exist
    if (!empty($blog_data['external_links'])) {
        $external_links = json_decode($blog_data['external_links'], true) ?? [];
    }
}

$page_title = $blog_id ? 'Edit Blog Post' : 'Create Blog Post or Announcement';
$type = $_GET['type'] ?? 'blog';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #e2e8f0; }
        .card { background: #1e293b; border-radius: 8px; border: 1px solid #334155; }
        .input-field { background: #0f172a; border: 1px solid #334155; color: #e2e8f0; padding: 10px; border-radius: 6px; }
        .input-field:focus { border-color: #3b82f6; outline: none; }
        .btn { padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 500; }
        .btn-primary { background: #3b82f6; color: white; border: none; }
        .btn-primary:hover { background: #2563eb; }
        .btn-ghost { background: transparent; color: #94a3b8; border: 1px solid #334155; }
        .btn-ghost:hover { background: #1e293b; }
        .ql-toolbar { background: #1e293b; border-color: #334155 !important; }
        .ql-container { background: #0f172a; border-color: #334155 !important; min-height: 300px; }
        .ql-editor { color: #e2e8f0; }
        .ql-snow .ql-stroke { stroke: #94a3b8; }
        .ql-snow .ql-fill { fill: #94a3b8; }
        .ql-snow .ql-picker { color: #94a3b8; }
    </style>
</head>
<body class="min-h-screen">
<div class="max-w-4xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold"><?= $page_title ?></h1>
        <a href="/scrollnovels/admin/admin.php?page=announcements" class="btn btn-ghost">← Back</a>
    </div>

    <form id="blogForm" class="space-y-6">
        <input type="hidden" name="id" value="<?= $blog_id ?>">

        <!-- Title -->
        <div class="card p-4">
            <label class="block text-sm font-medium mb-2">Title *</label>
            <input type="text" name="title" class="input-field w-full" 
                value="<?= htmlspecialchars($blog_data['title'] ?? '') ?>"
                placeholder="Enter blog post title" required>
        </div>

        <!-- Type Selection -->
        <div class="card p-4">
            <label class="block text-sm font-medium mb-2">Type *</label>
            <select name="type" class="input-field w-full" required>
                <option value="announcement" <?= $type === 'announcement' ? 'selected' : '' ?>>📢 Announcement</option>
                <option value="blog" <?= $type === 'blog' ? 'selected' : '' ?>>📝 Blog Post</option>
            </select>
        </div>

        <!-- Level/Priority (for announcements) -->
        <div class="card p-4">
                <label class="block text-sm font-medium mb-2">Priority Level *</label>
                <select name="level" class="input-field w-full" required>
                    <option value="info" <?= ($blog_data['level'] ?? '') === 'info' ? 'selected' : '' ?>>📰 Info</option>
                    <option value="notice" <?= ($blog_data['level'] ?? '') === 'notice' ? 'selected' : '' ?>>📢 Notice</option>
                    <option value="alert" <?= ($blog_data['level'] ?? '') === 'alert' ? 'selected' : '' ?>>⚠️ Alert</option>
                    <option value="system" <?= ($blog_data['level'] ?? '') === 'system' ? 'selected' : '' ?>>⚙️ System</option>
                </select>
            </div>

            <!-- Featured Image -->
            <div class="card p-4">
                <label class="block text-sm font-medium mb-2">Featured Image</label>
                <div class="flex gap-2 mb-3">
                    <input type="text" name="featured_image" class="input-field flex-1" 
                        value="<?= htmlspecialchars($blog_data['featured_image'] ?? '') ?>"
                        placeholder="Image URL">
                    <button type="button" class="btn btn-secondary" onclick="triggerImageUpload()">📤 Upload</button>
                </div>
                <input type="file" id="imageUpload" style="display:none" accept="image/*" onchange="handleImageUpload(event)">
                
                <?php if (!empty($blog_data['featured_image'])): ?>
                    <img src="<?= htmlspecialchars($blog_data['featured_image']) ?>" alt="Featured" style="max-width: 300px; margin-top: 10px; border-radius: 8px;">
                <?php endif; ?>
            </div>

            <!-- Summary/Excerpt -->
            <div class="card p-4">
                <label class="block text-sm font-medium mb-2">Summary/Excerpt</label>
                <textarea name="summary" class="input-field w-full" rows="3" 
                    placeholder="Brief summary of your post..."><?= htmlspecialchars($blog_data['summary'] ?? '') ?></textarea>
            </div>

            <!-- Content -->
            <div class="card p-4">
                <label class="block text-sm font-medium mb-2">Content *</label>
                <div style="margin-bottom: 10px;">
                    <button type="button" class="btn btn-sm btn-secondary mr-2" onclick="insertLink()">🔗 Add Link</button>
                    <button type="button" class="btn btn-sm btn-secondary mr-2" onclick="insertImage()">🖼️ Add Image</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="insertCode()">💻 Add Code</button>
                </div>
                <textarea name="content" id="contentArea" class="input-field w-full" rows="15" 
                    placeholder="Write your blog post content here... (HTML & Markdown supported)"
                    required><?= htmlspecialchars($blog_data['content'] ?? '') ?></textarea>
            </div>

            <!-- External Links Section -->
            <div class="card p-4">
                <label class="block text-sm font-medium mb-3">📌 External Links</label>
                <div id="linksList" style="margin-bottom: 15px;">
                    <?php foreach ($external_links as $idx => $link): ?>
                        <div style="padding: 10px; background: #f5f5f5; border-radius: 6px; margin-bottom: 8px; display: flex; gap: 8px;">
                            <input type="text" class="input-field flex-1" placeholder="Link Text" value="<?= htmlspecialchars($link['text'] ?? '') ?>" onchange="updateLink(<?= $idx ?>, 'text', this.value)">
                            <input type="url" class="input-field flex-1" placeholder="URL" value="<?= htmlspecialchars($link['url'] ?? '') ?>" onchange="updateLink(<?= $idx ?>, 'url', this.value)">
                            <button type="button" class="btn btn-sm btn-danger" onclick="removeLink(<?= $idx ?>)">✕</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline" onclick="addLink()">+ Add Link</button>
            </div>

            <!-- Publication Settings -->
            <div class="card p-4 space-y-3">
                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="show_on_ticker" class="w-4 h-4"
                            <?= ($blog_data['show_on_ticker'] ?? false) ? 'checked' : '' ?>>
                        <span class="text-sm">Show in announcement ticker</span>
                    </label>
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_pinned" class="w-4 h-4"
                            <?= ($blog_data['is_pinned'] ?? false) ? 'checked' : '' ?>>
                        <span class="text-sm">Pin to top</span>
                    </label>
                </div>

                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_featured" class="w-4 h-4"
                            <?= ($blog_data['is_featured'] ?? false) ? 'checked' : '' ?>>
                        <span class="text-sm">Featured post</span>
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Active From</label>
                    <input type="datetime-local" name="active_from" class="input-field w-full"
                        value="<?= $blog_data['active_from'] ?? '' ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Active Until</label>
                    <input type="datetime-local" name="active_until" class="input-field w-full"
                        value="<?= $blog_data['active_until'] ?? '' ?>">
                </div>
            </div>

            <!-- Preview -->
            <div class="card p-4">
                <h3 class="font-semibold mb-3">Preview</h3>
                <div id="preview" class="p-4 bg-card/50 border border-border rounded min-h-[200px] dark:bg-gray-800">
                    <p class="text-muted-foreground">Preview will appear here</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 sticky bottom-0 bg-background/95 p-4 border-t border-border">
                <button type="button" onclick="previewBlog()" class="btn btn-ghost">Preview</button>
                <button type="submit" class="btn btn-primary ml-auto">
                    <?= $blog_id ? 'Update' : 'Publish' ?>
                </button>
                <a href="/admin/blog.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</main>

<script>
let linksList = <?= json_encode($external_links) ?>;

function insertLink() {
    const textarea = document.getElementById('contentArea');
    const selectedText = textarea.value.substring(textarea.selectionStart, textarea.selectionEnd) || 'Link text';
    const url = prompt('Enter URL:', 'https://');
    if (url) {
        const markdown = `[${selectedText}](${url})`;
        insertAtCursor(markdown);
    }
}

function insertImage() {
    const url = prompt('Enter image URL:', 'https://');
    const alt = prompt('Enter alt text:', '');
    if (url) {
        const markdown = `![${alt}](${url})`;
        insertAtCursor(markdown);
    }
}

function insertCode() {
    const textarea = document.getElementById('contentArea');
    const selectedText = textarea.value.substring(textarea.selectionStart, textarea.selectionEnd) || 'code here';
    const codeBlock = `\`\`\`\n${selectedText}\n\`\`\``;
    insertAtCursor(codeBlock);
}

function insertAtCursor(text) {
    const textarea = document.getElementById('contentArea');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    textarea.value = textarea.value.substring(0, start) + text + textarea.value.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + text.length;
    textarea.focus();
}

function triggerImageUpload() {
    document.getElementById('imageUpload').click();
}

function handleImageUpload(e) {
    // In a real app, upload to server and get URL
    const file = e.target.files[0];
    const reader = new FileReader();
    reader.onload = (event) => {
        document.querySelector('[name="featured_image"]').value = event.target.result;
    };
    reader.readAsDataURL(file);
}

function addLink() {
    const listDiv = document.getElementById('linksList');
    const idx = linksList.length;
    linksList.push({text: '', url: ''});
    
    const div = document.createElement('div');
    div.style.cssText = 'padding: 10px; background: #f5f5f5; border-radius: 6px; margin-bottom: 8px; display: flex; gap: 8px;';
    div.innerHTML = `
        <input type="text" class="input-field" style="flex: 1;" placeholder="Link Text" onchange="updateLink(${idx}, 'text', this.value)">
        <input type="url" class="input-field" style="flex: 1;" placeholder="URL" onchange="updateLink(${idx}, 'url', this.value)">
        <button type="button" class="btn btn-sm btn-danger" onclick="removeLink(${idx})">✕</button>
    `;
    listDiv.appendChild(div);
}

function updateLink(idx, field, value) {
    if (linksList[idx]) linksList[idx][field] = value;
}

function removeLink(idx) {
    linksList.splice(idx, 1);
    document.getElementById('linksList').children[idx]?.remove();
}

function previewBlog() {
    const title = document.querySelector('[name="title"]').value;
    const content = document.querySelector('[name="content"]').value;
    const level = document.querySelector('[name="level"]').value;
    const image = document.querySelector('[name="featured_image"]').value;
    
    const preview = document.getElementById('preview');
    let html = `<div style="background: white; padding: 20px; border-radius: 8px; color: #333;">`;
    
    if (image) {
        html += `<img src="${image}" alt="Featured" style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 8px; margin-bottom: 15px;">`;
    }
    
    html += `
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
            <span>${level === 'info' ? '📰' : level === 'notice' ? '📢' : level === 'alert' ? '⚠️' : '⚙️'}</span>
            <h4 style="font-weight: bold; margin: 0;">${title || 'Untitled'}</h4>
        </div>
        <div style="color: #666; line-height: 1.6;">${content ? content.substring(0, 300) + '...' : 'No content yet'}</div>
    </div>`;
    
    preview.innerHTML = html;
}

document.getElementById('blogForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // Sync Quill content before getting values
    if (typeof window.syncBlogQuill === 'function') {
        window.syncBlogQuill();
    }
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // Convert checkboxes to boolean
    data.show_on_ticker = formData.has('show_on_ticker') ? 1 : 0;
    data.is_pinned = formData.has('is_pinned') ? 1 : 0;
    data.is_featured = formData.has('is_featured') ? 1 : 0;
    data.is_blog = data.type === 'blog' ? 1 : 0;
    data.external_links = JSON.stringify(linksList);

    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Publishing...';
    submitBtn.disabled = true;

    try {
        const res = await fetch('/scrollnovels/admin/ajax/save_blog_post.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await res.json();
        if (result.ok) {
            alert(result.message || 'Published successfully!');
            window.location.href = '/scrollnovels/admin/admin.php?page=announcements';
        } else {
            alert('Error: ' + (result.message || 'Unknown error'));
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    } catch (err) {
        alert('Network error: ' + err.message);
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
});

// Auto-preview on change
['title', 'content', 'level', 'featured_image'].forEach(field => {
    const el = document.querySelector(`[name="${field}"]`);
    if (el) el.addEventListener('change', previewBlog);
});
</script>

<!-- Quill WYSIWYG Editor -->
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
const blogTextarea = document.getElementById('contentArea');
if (blogTextarea) {
    const editorDiv = document.createElement('div');
    editorDiv.id = 'quill-blog-editor';
    editorDiv.style.minHeight = '300px';
    editorDiv.innerHTML = blogTextarea.value;
    blogTextarea.style.display = 'none';
    blogTextarea.parentNode.insertBefore(editorDiv, blogTextarea);
    
    const quillBlog = new Quill('#quill-blog-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'align': [] }],
                ['link', 'image', 'code-block'],
                ['clean']
            ]
        }
    });
    
    window.syncBlogQuill = function() {
        blogTextarea.value = quillBlog.root.innerHTML;
    };
}
</script>
</div>
</body>
</html>
