<div class="container">
  <h1 class="page-title">Monetization & Payments</h1>
  <div class="card">
    <div class="table-actions">
      <input id="txSearch" placeholder="Search transactions..." type="search">
      <button id="btnSearchTx" class="btn btn-ghost">Search</button>
      <button id="btnExportTx" class="btn btn-ghost">Export CSV</button>
    </div>
    <div class="table-wrap">
      <table id="txTable">
        <thead>
          <tr><th>TX ID</th><th>Donor</th><th>Author</th><th>Amount</th><th>Date</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody id="txTbody">Loading…</tbody>
      </table>
    </div>
    <div class="pagination" id="txPagination"></div>
  </div>
</div>

<script src="/admin/js/monetization.js" defer></script>
