// /admin/js/stories.js - Story management module
(() => {
  const tbody = document.getElementById('storiesBody');
  const perPage = 20;
  let currentPage = 1;

  async function loadStories(page = 1) {
    currentPage = page;
    tbody.innerHTML = `<tr><td colspan="7" class="muted center">Loading stories…</td></tr>`;

    const params = {
      q: document.getElementById('storySearch').value || '',
      page,
      per_page: perPage
    };

    try {
      const data = await qs('/admin/ajax/get_stories.php', params);
      if (!data || !Array.isArray(data.stories)) {
        tbody.innerHTML = `<tr><td colspan="7" class="muted center">No stories found</td></tr>`;
        return;
      }

      tbody.innerHTML = data.stories.map(s => `
        <tr>
          <td>${s.id}</td>
          <td>${escapeHtml(s.title)}</td>
          <td>${escapeHtml(s.author || '—')}</td>
          <td>${s.chapters || 0}</td>
          <td>${s.views || 0}</td>
          <td>${statusBadge(s.status)}</td>
          <td class="actions">
            <button class="btn btn-ghost" onclick="editStory(${s.id})">Edit</button>
            <button class="btn btn-danger-outline" onclick="deleteStory(${s.id})">Delete</button>
          </td>
        </tr>
      `).join('');
    } catch (err) {
      console.error(err);
      tbody.innerHTML = `<tr><td colspan="7" class="muted center">Error loading stories</td></tr>`;
    }
  }

  window.editStory = function(id) {
    alert('Edit story #' + id);
  };

  window.deleteStory = async function(id) {
    if (!confirm('Delete story #' + id + '?')) return;
    const res = await post('/admin/ajax/delete_story.php', { id });
    alert(res.message || 'Done');
    if (res.ok) loadStories(currentPage);
  };

  window.loadStories = loadStories;
  document.getElementById('btnSearchStories').addEventListener('click', () => loadStories(1));
  document.getElementById('storySearch').addEventListener('keydown', e => { if (e.key === 'Enter') loadStories(1); });

  loadStories(1);
})();
