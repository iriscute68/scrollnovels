// /admin/js/users.js - User management module
(() => {
  const tbody = document.getElementById('usersBody');
  const pagination = document.getElementById('usersPagination');
  const searchInput = document.getElementById('userSearch');
  const filterRole = document.getElementById('filterRole');
  const filterStatus = document.getElementById('filterStatus');
  const perPage = 20;
  let currentPage = 1;

  async function loadUsers(page = 1) {
    currentPage = page;
    tbody.innerHTML = `<tr><td colspan="9" class="muted center">Loading users…</td></tr>`;

    const params = {
      q: searchInput.value || '',
      page,
      per_page: perPage
    };

    try {
      const data = await qs('/admin/ajax/get_users.php', params);
      if (!data || !Array.isArray(data.users)) {
        tbody.innerHTML = `<tr><td colspan="9" class="muted center">No users found</td></tr>`;
        return;
      }

      tbody.innerHTML = data.users.map(u => `
        <tr>
          <td>${u.id}</td>
          <td>${escapeHtml(u.username)}</td>
          <td>${escapeHtml(u.email)}</td>
          <td>${u.role || 'user'}</td>
          <td>${statusBadge(u.status)}</td>
          <td>0</td>
          <td>${u.created_at ? u.created_at.substring(0, 10) : '—'}</td>
          <td>${u.last_login ? u.last_login.substring(0, 10) : '—'}</td>
          <td class="actions">
            <button class="btn btn-ghost" onclick="viewUser(${u.id})">View</button>
            <button class="btn btn-danger-outline" onclick="banUser(${u.id})">Ban</button>
          </td>
        </tr>
      `).join('');

      renderPagination(data.total, page);
    } catch (err) {
      console.error(err);
      tbody.innerHTML = `<tr><td colspan="9" class="muted center">Error loading users</td></tr>`;
    }
  }

  function renderPagination(total, page) {
    const pages = Math.ceil(total / perPage) || 1;
    if (pages <= 1) {
      pagination.innerHTML = '';
      return;
    }
    let html = '';
    const start = Math.max(1, page - 2);
    const end = Math.min(pages, page + 2);
    if (page > 1) html += `<button class="btn btn-ghost" onclick="loadUsers(${page - 1})">Prev</button>`;
    for (let i = start; i <= end; i++) {
      html += `<button class="btn ${i === page ? 'btn-active' : ''}" onclick="loadUsers(${i})">${i}</button>`;
    }
    if (page < pages) html += `<button class="btn btn-ghost" onclick="loadUsers(${page + 1})">Next</button>`;
    pagination.innerHTML = html;
  }

  window.viewUser = function(id) {
    alert('View user #' + id);
  };

  window.banUser = async function(id) {
    if (!confirm('Ban user #' + id + '?')) return;
    const res = await post('/admin/ajax/ban_user.php', { id, action: 'ban' });
    alert(res.message || 'Done');
    if (res.ok) loadUsers(currentPage);
  };

  window.loadUsers = loadUsers;
  document.getElementById('btnSearch').addEventListener('click', () => loadUsers(1));
  searchInput.addEventListener('keydown', e => { if (e.key === 'Enter') loadUsers(1); });

  loadUsers(1);
})();
