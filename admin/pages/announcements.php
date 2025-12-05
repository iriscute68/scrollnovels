<?php
// admin/pages/announcements.php - Enhanced with create/update/delete
$announcements = $pdo->query("
    SELECT a.*, 
           (SELECT COUNT(*) FROM announcement_reads WHERE announcement_id = a.id) as read_count
    FROM announcements a
    ORDER BY a.created_at DESC
    LIMIT 100
")->fetchAll();
?>

<div class="row mb-3">
    <div class="col-md-6">
        <h3>Announcements Management</h3>
    </div>
    <div class="col-md-6 text-end">
        <button class="btn btn-success me-2" onclick="showCreateForm()"><i class="fas fa-plus"></i> Quick Create</button>
        <a class="btn btn-primary" href="<?= site_url('/admin/blog_create.php?type=announcement') ?>"><i class="fas fa-edit"></i> Full Editor</a>
    </div>
</div>

<!-- Create/Edit Form Modal-like Section -->
<div id="announcement-form" style="display: none; margin-bottom: 30px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
    <h5 id="form-title">Create New Announcement</h5>
    <form id="announcement-save-form" method="POST">
        <input type="hidden" id="announcement-id" value="">
        
        <div class="mb-3">
            <label class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="announcement-title" required maxlength="255">
        </div>

        <div class="mb-3">
            <label class="form-label">Content <span class="text-danger">*</span></label>
            <div class="btn-group mb-2" role="group" aria-label="Formatting">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatSel('b')"><strong>B</strong></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatSel('i')"><em>I</em></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatSel('u')"><u>U</u></button>
            </div>
            <textarea class="form-control" id="announcement-content" rows="8" required placeholder="You can use basic HTML such as <b>, <i>, <u>"></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Link (Optional)</label>
            <input type="url" class="form-control" id="announcement-link" placeholder="https://...">
        </div>

        <div class="mb-3">
            <label class="form-label">Image URL (Optional)</label>
            <input type="url" class="form-control" id="announcement-image" placeholder="https://...">
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Announcement</button>
            <button type="button" class="btn btn-secondary" onclick="cancelForm()"><i class="fas fa-times"></i> Cancel</button>
        </div>
    </form>
</div>

<!-- Announcements List -->
<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Title</th>
                <th>Content Preview</th>
                <th>Views</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($announcements as $ann): ?>
            <tr>
                <td><?= htmlspecialchars($ann['title']) ?></td>
                <td><?= htmlspecialchars(substr($ann['content'], 0, 60)) ?>...</td>
                <td><span class="badge bg-info"><?= $ann['read_count'] ?></span></td>
                <td><?= date('M d, Y', strtotime($ann['created_at'])) ?></td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick="editAnnouncement(<?= $ann['id'] ?>, '<?= htmlspecialchars($ann['title'], ENT_QUOTES) ?>', '<?= htmlspecialchars($ann['content'], ENT_QUOTES) ?>', '<?= htmlspecialchars($ann['link'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($ann['image'] ?? '', ENT_QUOTES) ?>')"><i class="fas fa-edit"></i> Edit</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteAnnouncement(<?= $ann['id'] ?>)"><i class="fas fa-trash"></i> Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function showCreateForm() {
    document.getElementById('announcement-form').style.display = 'block';
    document.getElementById('form-title').textContent = 'Create New Announcement';
    document.getElementById('announcement-id').value = '';
    document.getElementById('announcement-save-form').reset();
}

function cancelForm() {
    document.getElementById('announcement-form').style.display = 'none';
}

function editAnnouncement(id, title, content, link, image) {
    document.getElementById('announcement-form').style.display = 'block';
    document.getElementById('form-title').textContent = 'Edit Announcement';
    document.getElementById('announcement-id').value = id;
    document.getElementById('announcement-title').value = title;
    document.getElementById('announcement-content').value = content;
    document.getElementById('announcement-link').value = link;
    document.getElementById('announcement-image').value = image;
}

async function deleteAnnouncement(id) {
    if (!confirm('Are you sure you want to delete this announcement?')) return;
    
    try {
        const response = await fetch('/api/admin-announcements.php?action=delete_announcement', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        
        const data = await response.json();
        if (data.success) {
            alert('Announcement deleted successfully');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error deleting announcement');
    }
}

document.getElementById('announcement-save-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Sync Quill content before getting value
    if (typeof syncQuillContent === 'function') {
        syncQuillContent();
    }
    
    const id = document.getElementById('announcement-id').value;
    const data = {
        title: document.getElementById('announcement-title').value,
        content: document.getElementById('announcement-content').value,
        link: document.getElementById('announcement-link').value || null,
        image: document.getElementById('announcement-image').value || null
    };
    
    if (id) {
        data.id = parseInt(id);
    }
    
    const action = id ? 'update_announcement' : 'create_announcement';
    
    try {
        const response = await fetch('/api/admin-announcements.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        if (result.success) {
            alert(id ? 'Announcement updated successfully' : 'Announcement created successfully');
            location.reload();
        } else {
            alert('Error: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error saving announcement');
    }
});
</script>

<script>
function formatSel(tag) {
    const ta = document.getElementById('announcement-content');
    if (!ta) return;
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const val = ta.value;
    const before = val.substring(0, start);
    const sel = val.substring(start, end);
    const after = val.substring(end);
    const open = '<' + tag + '>';
    const close = '</' + tag + '>';
    ta.value = before + open + sel + close + after;
    // restore selection inside tags
    const newStart = start + open.length;
    const newEnd = newStart + sel.length;
    ta.focus();
    ta.setSelectionRange(newStart, newEnd);
}
</script>

<!-- Quill WYSIWYG Editor (Free, no API key) -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
// Replace textarea with Quill editor
const contentTextarea = document.getElementById('announcement-content');
if (contentTextarea) {
    const editorDiv = document.createElement('div');
    editorDiv.id = 'quill-editor';
    editorDiv.innerHTML = contentTextarea.value;
    contentTextarea.style.display = 'none';
    contentTextarea.parentNode.insertBefore(editorDiv, contentTextarea);
    
    const quill = new Quill('#quill-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link', 'image'],
                ['clean']
            ]
        }
    });
    
    // Sync Quill content to hidden textarea before form actions
    window.syncQuillContent = function() {
        contentTextarea.value = quill.root.innerHTML;
    };
}
</script>
