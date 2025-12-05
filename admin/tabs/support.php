<?php
// Use the full-featured support messages management code
require_once __DIR__ . '/../support-messages.php';
?>

<div class="container">
  <h1 class="page-title">Support Tickets</h1>
  <div class="card">
    <div class="table-actions">
      <input id="ticketSearch" placeholder="Search tickets..." type="search">
      <button id="btnSearchTickets" class="btn btn-ghost">Search</button>
    </div>
    <div class="table-wrap">
      <table id="ticketsTable">
        <thead>
          <tr><th>ID</th><th>User</th><th>Subject</th><th>Status</th><th>Created</th><th>Actions</th></tr>
        </thead>
        <tbody id="ticketsTbody">Loading...</tbody>
      </table>
    </div>
  </div>
</div>
