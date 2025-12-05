<div class="container">
  <h1 class="page-title">Overview</h1>

  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-label">Total Users</div>
      <div class="kpi-value" id="kpi-users">—</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Total Authors</div>
      <div class="kpi-value" id="kpi-authors">—</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Total Stories</div>
      <div class="kpi-value" id="kpi-stories">—</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Total Chapters</div>
      <div class="kpi-value" id="kpi-chapters">—</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Revenue (MTD)</div>
      <div class="kpi-value" id="kpi-revenue">—</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Pending Withdrawals</div>
      <div class="kpi-value" id="kpi-withdrawals">—</div>
    </div>
  </div>

  <div class="grid-2">
    <section class="card" id="activityCard">
      <h2>Recent Activity</h2>
      <div id="activityList">Loading…</div>
    </section>

    <section class="card" id="systemHealthCard">
      <h2>Server Health</h2>
      <div id="systemHealth">Loading…</div>
    </section>
  </div>

  <section class="card">
    <h2>Recent Payments</h2>
    <div id="recentPayments">Loading…</div>
  </section>
</div>

<script>
  // fetch KPIs and fill UI
  fetch('/admin/ajax/get_kpis.php').then(r => r.json()).then(data => {
    if (!data.error) {
      document.getElementById('kpi-users').textContent = (data.total_users || 0).toLocaleString();
      document.getElementById('kpi-authors').textContent = (data.total_authors || 0).toLocaleString();
      document.getElementById('kpi-stories').textContent = (data.total_stories || 0).toLocaleString();
      document.getElementById('kpi-chapters').textContent = (data.total_chapters || 0).toLocaleString();
      document.getElementById('kpi-revenue').textContent = '$' + Number(data.revenue_mtd || 0).toFixed(2);
      document.getElementById('kpi-withdrawals').textContent = '$' + Number(data.pending_withdrawals || 0).toFixed(2);

      const act = document.getElementById('activityList');
      act.innerHTML = (data.recent_activity || []).map(a => `<div class="activity-row">${a}</div>`).join('');
      document.getElementById('recentPayments').innerHTML = data.recent_payments_html || '<p class="muted">No recent payments</p>';
    }
  }).catch(e => console.error(e));

  fetch('/admin/ajax/get_system_status.php').then(r => r.json()).then(s => {
    if (!s.error) {
      document.getElementById('systemHealth').innerHTML = `
        <div>CPU: ${s.cpu || 0}%</div>
        <div>RAM: ${s.ram?.used_mb || 0}MB / ${s.ram?.total_mb || 0}MB (${s.ram?.percent || 0}%)</div>
        <div>Disk: ${s.disk?.used_gb || 0}GB / ${s.disk?.total_gb || 0}GB (${s.disk?.percent || 0}%)</div>
      `;
    }
  }).catch(e => console.error(e));
</script>
