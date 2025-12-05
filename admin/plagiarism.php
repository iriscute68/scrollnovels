<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin', 'moderator'])) {
    header('Location: ../pages/login.php');
    exit;
}
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/db.php';

$page_title = 'Plagiarism Reports - Admin';
?>

<main class="flex-1">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-emerald-700 dark:text-emerald-400 mb-6">🔍 Plagiarism Reports</h1>

        <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg p-4 shadow border border-emerald-200 dark:border-emerald-900">
            <div class="flex gap-4 items-center flex-wrap">
                <input type="text" id="searchInput" placeholder="Search reports..." class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 dark:text-white flex-1 min-w-200">
                <select id="statusFilter" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 dark:text-white">
                    <option value="">All Statuses</option>
                    <option value="open">Open</option>
                    <option value="resolved">Resolved</option>
                    <option value="ignored">Ignored</option>
                </select>
                <button onclick="loadReports()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium">Search</button>
            </div>
        </div>

        <div id="reportsList" class="space-y-4">
            <p class="text-gray-500 dark:text-gray-400">Loading reports...</p>
        </div>
    </div>
</main>

<script>
async function loadReports() {
    const q = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    
    const resp = await fetch('<?= site_url('/admin/ajax/get_plagiarism_reports.php') ?>?q=' + encodeURIComponent(q) + '&status=' + encodeURIComponent(status));
    const data = await resp.json();
    
    const list = document.getElementById('reportsList');
    if (!data.reports || data.reports.length === 0) {
        list.innerHTML = '<p class="text-gray-500 dark:text-gray-400">No reports found.</p>';
        return;
    }
    
    list.innerHTML = data.reports.map(r => `
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-start gap-4 flex-wrap">
                <div class="flex-1">
                    <h3 class="font-bold text-gray-900 dark:text-white mb-1">${r.chapter_title || 'Unknown'}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Story: ${r.story_title || 'Unknown'}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Author: ${r.author || 'Unknown'}</p>
                    <p class="text-sm mt-2"><strong>Score:</strong> ${(r.score * 100).toFixed(1)}%</p>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <button onclick="resolveReport(${r.id}, 'mark_resolved')" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-xs font-medium">✓ Resolved</button>
                    <button onclick="resolveReport(${r.id}, 'ignored')" class="px-3 py-1 bg-gray-600 hover:bg-gray-700 text-white rounded text-xs font-medium">- Ignore</button>
                    <button onclick="resolveReport(${r.id}, 'delete_chapter')" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-medium">🗑 Delete</button>
                </div>
            </div>
        </div>
    `).join('');
}

async function resolveReport(id, action) {
    const resp = await fetch('<?= site_url('/admin/ajax/resolve_plagiarism.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, action })
    });
    const data = await resp.json();
    
    if (data.ok) {
        loadReports();
    } else {
        alert('Error: ' + (data.message || 'Unknown'));
    }
}

loadReports();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
