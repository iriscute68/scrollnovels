// /admin/js/admin.js - Shared utilities for admin panel
document.addEventListener('DOMContentLoaded', () => {
  // Sidebar toggle
  const toggle = document.getElementById('toggleSidebar');
  if (toggle) {
    toggle.addEventListener('click', () => {
      const sidebar = document.querySelector('.sidebar');
      sidebar.classList.toggle('open');
    });
  }

  // Global search
  const gsearch = document.getElementById('globalSearch');
  if (gsearch) {
    let debounceTimeout;
    gsearch.addEventListener('input', (e) => {
      clearTimeout(debounceTimeout);
      debounceTimeout = setTimeout(() => {
        const q = e.target.value.trim();
        if (q.length > 2) {
          fetch('/admin/ajax/global_search.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(console.log)
            .catch(console.error);
        }
      }, 350);
    });
  }
});

// Utilities
window.escapeHtml = function(s) {
  return String(s || '').replace(/[&<>"']/g, m => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  })[m]);
};

window.qs = async function(url, params = {}) {
  const q = new URLSearchParams(params);
  const res = await fetch(`${url}?${q.toString()}`, { credentials: 'same-origin' });
  return res.json();
};

window.post = async function(url, body) {
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return res.json();
};

// Status badge helper
window.statusBadge = function(status) {
  const badges = {
    'active': 'badge badge-success',
    'pending': 'badge badge-warning',
    'completed': 'badge badge-success',
    'visible': 'badge badge-success',
    'hidden': 'badge badge-danger',
    'suspended': 'badge badge-warning',
    'banned': 'badge badge-danger',
    'open': 'badge badge-warning',
    'resolved': 'badge badge-success',
  };
  const cls = badges[status] || 'badge';
  return `<span class="${cls}">${escapeHtml(status)}</span>`;
};
