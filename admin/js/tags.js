// /admin/js/tags.js - Tags and genres management
(() => {
  const tbody = document.getElementById('tagsTbody');

  async function loadTags() {
    tbody.innerHTML = `<tr><td colspan="3" class="muted center">Loading…</td></tr>`;

    try {
      const params = { q: document.getElementById('tagSearch').value || '' };
      const data = await qs('/admin/ajax/get_tags.php', params);
      if (!data || !Array.isArray(data.tags)) {
        tbody.innerHTML = `<tr><td colspan="3" class="muted center">No tags</td></tr>`;
        return;
      }

      tbody.innerHTML = data.tags.map(t => `
        <tr>
          <td>${escapeHtml(t.name)}</td>
          <td>${t.count || 0}</td>
          <td class="actions">
            <button class="btn btn-ghost" onclick="editTag(${t.id})">Edit</button>
            <button class="btn btn-danger-outline" onclick="deleteTag(${t.id})">Delete</button>
          </td>
        </tr>
      `).join('');
    } catch (err) {
      console.error(err);
      tbody.innerHTML = `<tr><td colspan="3" class="muted center">Error</td></tr>`;
    }
  }

  window.editTag = function(id) {
    alert('Edit tag #' + id);
  };

  window.deleteTag = async function(id) {
    if (!confirm('Delete tag?')) return;
    const res = await post('/admin/ajax/delete_tag.php', { id });
    alert(res.message || 'Done');
    if (res.ok) loadTags();
  };

  window.openCreateTagModal = function() {
    alert('Create tag modal - to be implemented');
  };

  window.loadTags = loadTags;
  document.getElementById('btnSearchTags').addEventListener('click', () => loadTags());

  loadTags();
})();
