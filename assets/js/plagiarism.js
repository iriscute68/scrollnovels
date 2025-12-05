// assets/js/plagiarism.js - Plagiarism detection UI and AJAX handlers
(() => {
  const tbody = document.getElementById('plTbody');
  const searchInput = document.getElementById('plSearch');
  const statusSelect = document.getElementById('plStatus');

  async function api(path, opts = {}) {
    const res = await fetch('/admin/ajax/' + path, { 
      credentials: 'same-origin', 
      ...opts 
    });
    return res.json();
  }

  function escape(s) {
    return String(s || '').replace(/[&<>"']/g, m => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    })[m]);
  }

  function statusBadge(s) {
    const classes = {
      'open': 'badge-warning',
      'resolved': 'badge-success',
      'ignored': 'badge-muted'
    };
    const label = s.charAt(0).toUpperCase() + s.slice(1);
    return `<span class="badge ${classes[s] || ''}">` + label + '</span>';
  }

  // Main: Load and display plagiarism reports
  window.fetchScans = async function() {
    tbody.innerHTML = `<tr><td colspan="8" class="muted center">Loading reports…</td></tr>`;
    
    const q = searchInput.value || '';
    const st = statusSelect.value || '';
    const data = await api(`get_plagiarism_reports.php?q=${encodeURIComponent(q)}&status=${encodeURIComponent(st)}`);
    
    if (!data || !Array.isArray(data.reports)) {
      tbody.innerHTML = `<tr><td colspan="8" class="muted center">No reports found</td></tr>`;
      return;
    }

    tbody.innerHTML = data.reports.map(r => `
      <tr class="hover:bg-card/50 border-b border-border">
        <td class="p-3">${r.id}</td>
        <td class="p-3">
          <div class="font-medium">${escape(r.story_title)}</div>
          <div class="text-xs text-muted-foreground">Ch ${r.chapter_number} • ${escape(r.chapter_title)}</div>
        </td>
        <td class="p-3">${escape(r.author || 'Unknown')}</td>
        <td class="p-3"><strong>${r.score}%</strong></td>
        <td class="p-3">${r.match_count || 0}</td>
        <td class="p-3">${statusBadge(r.status)}</td>
        <td class="p-3 text-sm text-muted-foreground">${r.created_at}</td>
        <td class="p-3 flex gap-1">
          <button class="btn btn-ghost btn-sm" onclick="openCompare(${r.id})">View</button>
          <button class="btn btn-danger-outline btn-sm" onclick="markReport(${r.id}, 'ignored')">Ignore</button>
          <button class="btn btn-ghost btn-sm" onclick="downloadReport(${r.id})">Export</button>
        </td>
      </tr>
    `).join('');
  };

  // Open comparison modal with full report details
  window.openCompare = async function(reportId) {
    const j = await api('get_plagiarism_report.php?id=' + reportId);
    
    if (!j || !j.report) {
      alert('Report not found');
      return;
    }

    const r = j.report;

    // Populate modal header
    document.getElementById('plCompareTitle').textContent = 
      `Report #${r.id} — ${escape(r.story_title)} (Ch ${r.chapter_number})`;

    // Highlight matched text in left pane
    document.getElementById('plChapterText').innerHTML = highlightMatches(r.text, r.matches);

    // Show matches/sources in right pane
    document.getElementById('plMatches').innerHTML = buildMatchesHtml(r.matches);

    // Show modal
    document.getElementById('plCompareModal').classList.remove('hidden');
    if (document.getElementById('modalBackdrop')) {
      document.getElementById('modalBackdrop').classList.remove('hidden');
    }

    // Bind apply action handler
    document.getElementById('plApplyAction').onclick = async function() {
      const action = document.getElementById('plActionSelect').value;
      if (action === 'no_action') {
        alert('Select an action');
        return;
      }
      
      const note = prompt('Optional note for log:') || '';
      const res = await fetch('/admin/ajax/resolve_plagiarism.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: reportId, action, note })
      });
      
      const rr = await res.json();
      alert(rr.message || 'Done');
      closeCompare();
      fetchScans();
    };
  };

  // Close compare modal
  window.closeCompare = function() {
    document.getElementById('plCompareModal').classList.add('hidden');
    if (document.getElementById('modalBackdrop')) {
      document.getElementById('modalBackdrop').classList.add('hidden');
    }
  };

  // Highlight matched text snippets in chapter text
  function highlightMatches(text, matches) {
    if (!matches || !matches.length) {
      return '<pre class="whitespace-pre-wrap text-sm">' + escape(text) + '</pre>';
    }

    let out = escape(text);
    // Sort by longest first to avoid nested replacement issues
    const sorted = [...(matches || [])].sort((a, b) => 
      (b.snippet?.length || 0) - (a.snippet?.length || 0)
    );

    sorted.forEach(m => {
      if (m.snippet) {
        const esc = escape(m.snippet);
        out = out.split(esc).join(
          `<mark style="background-color: rgba(255, 165, 0, 0.3);">${esc}</mark>`
        );
      }
    });

    return `<pre class="whitespace-pre-wrap text-sm">${out}</pre>`;
  }

  // Build HTML for matches/sources display
  function buildMatchesHtml(matches) {
    if (!matches || !matches.length) {
      return '<p class="text-muted-foreground">No matches found</p>';
    }

    return matches.map((m, i) => `
      <div class="mb-3 p-2 border border-border rounded">
        <div class="flex justify-between items-center mb-1">
          <strong>Source #${i + 1}</strong>
          <span class="text-xs font-semibold text-orange-500">${m.score}%</span>
        </div>
        <div class="text-xs text-muted-foreground mb-1">
          ${escape(m.source_title || m.source_url || 'Unknown Source')}
        </div>
        <div class="text-sm p-2 bg-card/50 rounded mb-2">
          ${escape(m.snippet || '')}
        </div>
        ${m.source_url ? `<a href="${escape(m.source_url)}" target="_blank" class="text-xs text-blue-500 hover:underline">
          View source →
        </a>` : ''}
      </div>
    `).join('');
  }

  // Manual scan modal - open
  window.openManualScan = function() {
    document.getElementById('plManualModal').classList.remove('hidden');
    if (document.getElementById('modalBackdrop')) {
      document.getElementById('modalBackdrop').classList.remove('hidden');
    }
  };

  // Manual scan modal - close
  window.closeManualScan = function() {
    document.getElementById('plManualModal').classList.add('hidden');
    if (document.getElementById('modalBackdrop')) {
      document.getElementById('modalBackdrop').classList.add('hidden');
    }
  };

  // Submit manual scan
  window.submitManualScan = async function() {
    const id = document.getElementById('manualChapterId').value.trim();
    const url = document.getElementById('manualCompareUrl').value.trim();
    
    if (!id) {
      alert('Enter a chapter ID');
      return;
    }

    const res = await fetch('/admin/ajax/run_scan.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ chapter_id: id, compare_url: url })
    });

    const j = await res.json();
    alert(j.message || 'Scan queued');
    closeManualScan();
    fetchScans();
  };

  // Mark report as ignored/resolved
  window.markReport = async function(id, status) {
    if (!confirm(`Mark this report as ${status}?`)) return;

    const res = await fetch('/admin/ajax/resolve_plagiarism.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, action: status, note: 'batch action' })
    });

    const j = await res.json();
    alert(j.message || 'Done');
    fetchScans();
  };

  // Download/export single report
  window.downloadReport = function(id) {
    location.href = '/admin/ajax/export_single_plagiarism.php?id=' + id;
  };

  // Event bindings
  document.getElementById('btnSearchPl')?.addEventListener('click', fetchScans);
  document.getElementById('btnClearPl')?.addEventListener('click', () => {
    searchInput.value = '';
    statusSelect.value = '';
    fetchScans();
  });
  document.getElementById('btnExportPlagiarism')?.addEventListener('click', () => {
    location.href = '/admin/ajax/export_plagiarism_csv.php';
  });

  // Batch scan all recent chapters
  window.runFullScan = async function() {
    if (!confirm('Queue plagiarism scans for the 200 most recent chapters?\n\nThis may take a while to process.')) {
      return;
    }

    const res = await fetch('/admin/ajax/run_full_scan.php', {
      method: 'POST',
      credentials: 'same-origin'
    });

    const j = await res.json();
    alert(j.message || 'Batch scan started');
    fetchScans();
  };

  // Initial load
  fetchScans();
})();
