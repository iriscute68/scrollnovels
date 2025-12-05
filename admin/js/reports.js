// /admin/js/reports.js - Reports management
(() => {
  const tbody = document.getElementById('reportsTbody');

  async function loadReports(page = 1) {
    tbody.innerHTML = `<tr><td colspan="7" class="muted center">Loading…</td></tr>`;

    try {
      const params = {
        q: document.getElementById('reportSearch').value || '',
        status: document.getElementById('filterReportStatus').value || '',
        page,
        per_page: 20
      };
      const data = await qs('/admin/ajax/get_reports.php', params);
      if (!data || !Array.isArray(data.reports)) {
        tbody.innerHTML = `<tr><td colspan="7" class="muted center">No reports</td></tr>`;
        return;
      }

      tbody.innerHTML = data.reports.map(r => `
        <tr>
          <td>${r.id}</td>
          <td>${escapeHtml(r.type)}</td>
          <td>${escapeHtml(r.reported_user || '—')}</td>
          <td>${escapeHtml((r.reason || '').substring(0, 50))}...</td>
          <td>${statusBadge(r.status)}</td>
          <td>${r.created_at ? r.created_at.substring(0, 10) : '—'}</td>
          <td class="actions">
            <button class="btn btn-ghost" onclick="viewReport(${r.id})">View</button>
          </td>
        </tr>
      `).join('');
    } catch (err) {
      console.error(err);
      tbody.innerHTML = `<tr><td colspan="7" class="muted center">Error</td></tr>`;
    }
  }

  window.viewReport = function(id) {
    alert('View report #' + id);
  };

  window.loadReports = loadReports;
  document.getElementById('btnSearchReports').addEventListener('click', () => loadReports(1));
  document.getElementById('reportSearch').addEventListener('keydown', e => { if (e.key === 'Enter') loadReports(1); });

  loadReports(1);
})();
