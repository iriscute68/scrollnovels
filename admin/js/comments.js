// /admin/js/comments.js - Comment management
(() => {
  const tbody = document.getElementById('commentsTbody');

  async function loadComments(page = 1) {
    tbody.innerHTML = `<tr><td colspan="7" class="muted center">Loading…</td></tr>`;

    try {
      const params = {
        q: document.getElementById('commentSearch').value || '',
        page,
        per_page: 20
      };
      const data = await qs('/admin/ajax/get_comments.php', params);
      if (!data || !Array.isArray(data.comments)) {
        tbody.innerHTML = `<tr><td colspan="7" class="muted center">No comments</td></tr>`;
        return;
      }

      tbody.innerHTML = data.comments.map(c => `
        <tr>
          <td>${c.id}</td>
          <td>${escapeHtml(c.story_title || '—')}</td>
          <td>${escapeHtml(c.username || '—')}</td>
          <td>${escapeHtml((c.text || '').substring(0, 100))}...</td>
          <td>${c.created_at ? c.created_at.substring(0, 10) : '—'}</td>
          <td>${statusBadge(c.status)}</td>
          <td class="actions">
            <button class="btn btn-ghost" onclick="viewComment(${c.id})">View</button>
          </td>
        </tr>
      `).join('');
    } catch (err) {
      console.error(err);
      tbody.innerHTML = `<tr><td colspan="7" class="muted center">Error</td></tr>`;
    }
  }

  window.viewComment = function(id) {
    alert('View comment #' + id);
  };

  window.loadComments = loadComments;
  document.getElementById('btnSearchComments').addEventListener('click', () => loadComments(1));
  document.getElementById('commentSearch').addEventListener('keydown', e => { if (e.key === 'Enter') loadComments(1); });

  loadComments(1);
})();
