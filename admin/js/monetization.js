// /admin/js/monetization.js - Monetization & payments
(() => {
  const tbody = document.getElementById('txTbody');

  async function loadTransactions(page = 1) {
    tbody.innerHTML = `<tr><td colspan="7" class="muted center">Loading…</td></tr>`;

    try {
      const params = {
        q: document.getElementById('txSearch').value || '',
        page,
        per_page: 20
      };
      const data = await qs('/admin/ajax/get_transactions.php', params);
      if (!data || !Array.isArray(data.transactions)) {
        tbody.innerHTML = `<tr><td colspan="7" class="muted center">No transactions</td></tr>`;
        return;
      }

      tbody.innerHTML = data.transactions.map(tx => `
        <tr>
          <td>${escapeHtml(tx.tx_id)}</td>
          <td>${escapeHtml(tx.donor || '—')}</td>
          <td>${escapeHtml(tx.recipient || '—')}</td>
          <td class="text-green">$${Number(tx.amount || 0).toFixed(2)}</td>
          <td>${tx.created_at ? tx.created_at.substring(0, 10) : '—'}</td>
          <td>${statusBadge(tx.status)}</td>
          <td class="actions">
            <button class="btn btn-ghost" onclick="viewTx('${escapeHtml(tx.tx_id)}')">View</button>
          </td>
        </tr>
      `).join('');
    } catch (err) {
      console.error(err);
      tbody.innerHTML = `<tr><td colspan="7" class="muted center">Error</td></tr>`;
    }
  }

  window.viewTx = function(id) {
    alert('View transaction: ' + id);
  };

  window.loadTransactions = loadTransactions;
  document.getElementById('btnSearchTx').addEventListener('click', () => loadTransactions(1));
  document.getElementById('txSearch').addEventListener('keydown', e => { if (e.key === 'Enter') loadTransactions(1); });

  loadTransactions(1);
})();
