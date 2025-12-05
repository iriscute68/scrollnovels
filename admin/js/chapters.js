// /admin/js/chapters.js - Chapter management
(() => {
  const tbody = document.getElementById('chaptersTbody');
  const perPage = 25;

  async function loadChapters(page = 1) {
    tbody.innerHTML = `<tr><td colspan="7" class="muted center">Loading chapters…</td></tr>`;

    try {
      const params = {
        q: document.getElementById('chapterSearch').value || '',
        page,
        per_page: perPage
      };
      const data = await qs('/admin/ajax/get_chapters.php', params);
      if (!data || !Array.isArray(data.chapters)) {
        tbody.innerHTML = `<tr><td colspan="7" class="muted center">No chapters</td></tr>`;
        return;
      }

      tbody.innerHTML = data.chapters.map(c => `
        <tr>
          <td>${c.id}</td>
          <td>${escapeHtml(c.story_title)}</td>
          <td>${escapeHtml(c.title)}</td>
          <td>${c.word_count || 0}</td>
          <td>${c.views || 0}</td>
          <td>${c.published_at ? c.published_at.substring(0, 10) : '—'}</td>
          <td class="actions">
            <button class="btn btn-ghost" onclick="editChapter(${c.id})">Edit</button>
          </td>
        </tr>
      `).join('');
    } catch (err) {
      console.error(err);
      tbody.innerHTML = `<tr><td colspan="7" class="muted center">Error</td></tr>`;
    }
  }

  window.editChapter = function(id) {
    alert('Edit chapter #' + id);
  };

  window.loadChapters = loadChapters;
  document.getElementById('btnSearchChapters').addEventListener('click', () => loadChapters(1));
  document.getElementById('chapterSearch').addEventListener('keydown', e => { if (e.key === 'Enter') loadChapters(1); });

  loadChapters(1);
})();
