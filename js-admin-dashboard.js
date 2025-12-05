// js/admin-dashboard.js - Admin Dashboard Functionality

const API_BASE = '/api/v1';
let currentSection = 'dashboard';
let authToken = localStorage.getItem('authToken');

// Navigation
document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', (e) => {
        if (link.href.includes('/logout')) return;
        
        e.preventDefault();
        const section = link.dataset.section;
        
        document.querySelectorAll('.admin-section').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        
        document.getElementById(section).classList.add('active');
        link.classList.add('active');
        
        currentSection = section;
        
        // Load section data
        if (section === 'dashboard') loadDashboard();
        if (section === 'patreon') loadPatreonLinks();
        if (section === 'ledger') loadPointsLedger();
        if (section === 'support') loadBookSupport();
        if (section === 'leaderboards') loadLeaderboards();
    });
});

// ==========================
// Dashboard Section
// ==========================

async function loadDashboard() {
    try {
        const response = await fetch(`${API_BASE}/admin/dashboard`, {
            headers: { 'Authorization': `Bearer ${authToken}` }
        });
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            
            // Update stats
            document.getElementById('totalPointsDistributed').textContent = 
                formatNumber(data.overview.total_points_distributed);
            document.getElementById('patreonDistributed').textContent = 
                formatNumber(data.overview.patreon_distributed);
            document.getElementById('totalSpent').textContent = 
                formatNumber(data.overview.total_spent);
            document.getElementById('activePatrons').textContent = 
                formatNumber(data.patreon.active_patrons);
            document.getElementById('patronsRewarded').textContent = 
                formatNumber(data.patreon.monthly_rewards.users_rewarded);
            document.getElementById('activeUsers').textContent = 
                formatNumber(data.active_users);
            
            // Update top books table
            const tbody = document.getElementById('topBooksTable');
            tbody.innerHTML = data.top_books.map((book, idx) => `
                <tr>
                    <td>#${idx + 1}</td>
                    <td>
                        <a href="/book/${book.slug}" target="_blank">${book.title}</a>
                    </td>
                    <td>${book.supporter_count}</td>
                    <td>${formatNumber(book.total_support)}</td>
                    <td>
                        <button class="btn btn-secondary" onclick="viewBook('${book.id}')">View</button>
                    </td>
                </tr>
            `).join('');
        }
    } catch (err) {
        console.error('Failed to load dashboard:', err);
        showStatus('Failed to load dashboard', 'error');
    }
}

// ==========================
// Patreon Links Section
// ==========================

let patreonPage = 1;

async function loadPatreonLinks() {
    try {
        const response = await fetch(
            `${API_BASE}/admin/patreon-links?limit=50&offset=${(patreonPage - 1) * 50}`,
            { headers: { 'Authorization': `Bearer ${authToken}` } }
        );
        const result = await response.json();
        
        if (result.success) {
            const tbody = document.getElementById('patreonLinksTable');
            tbody.innerHTML = result.data.map(link => `
                <tr>
                    <td>${link.username || 'N/A'}</td>
                    <td>${link.email || 'N/A'}</td>
                    <td>${link.tier_name || 'None'}</td>
                    <td>
                        <span class="badge ${link.active ? 'badge-success' : 'badge-danger'}">
                            ${link.patron_status || 'inactive'}
                        </span>
                    </td>
                    <td>$${(link.pledge_amount_cents / 100).toFixed(2)}</td>
                    <td>${link.last_reward_date ? new Date(link.last_reward_date).toLocaleDateString() : 'Never'}</td>
                    <td>${link.support_count || 0}</td>
                    <td>
                        <button class="btn btn-danger" onclick="unlinkPatreon('${link.id}')">Unlink</button>
                    </td>
                </tr>
            `).join('');
            
            // Update pagination
            updatePagination('patreonPagination', result.pagination, (page) => {
                patreonPage = page;
                loadPatreonLinks();
            });
        }
    } catch (err) {
        console.error('Failed to load Patreon links:', err);
        showStatus('Failed to load Patreon links', 'error');
    }
}

async function unlinkPatreon(linkId) {
    const reason = prompt('Enter reason for unlinking:');
    if (!reason) return;
    
    showConfirmModal('Unlink Patreon', 
        `Unlink Patreon account? (Reason: ${reason})`,
        async () => {
            try {
                const response = await fetch(`${API_BASE}/admin/patreon-links/${linkId}/unlink`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${authToken}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ reason })
                });
                
                if (response.ok) {
                    showStatus('Patreon account unlinked', 'success');
                    loadPatreonLinks();
                }
            } catch (err) {
                showStatus('Failed to unlink account', 'error');
            }
        }
    );
}

document.getElementById('filterActiveBtn')?.addEventListener('click', function() {
    this.textContent = this.dataset.active === 'true' ? 'Show All' : 'Show Active Only';
    this.dataset.active = this.data.active === 'true' ? 'false' : 'true';
    patreonPage = 1;
    loadPatreonLinks();
});

// ==========================
// Points Ledger Section
// ==========================

let ledgerPage = 1;

async function loadPointsLedger() {
    const userId = document.getElementById('filterUserId').value;
    const type = document.getElementById('filterType').value;
    const from = document.getElementById('filterFrom').value;
    const to = document.getElementById('filterTo').value;
    
    try {
        const params = new URLSearchParams({
            limit: 100,
            offset: (ledgerPage - 1) * 100,
            ...(userId && { user: userId }),
            ...(type && { type }),
            ...(from && { from }),
            ...(to && { to })
        });
        
        const response = await fetch(`${API_BASE}/admin/points-transactions?${params}`, {
            headers: { 'Authorization': `Bearer ${authToken}` }
        });
        const result = await response.json();
        
        if (result.success) {
            const tbody = document.getElementById('ledgerTable');
            tbody.innerHTML = result.data.map(tx => `
                <tr>
                    <td>${tx.username || tx.user_id}</td>
                    <td><span class="badge badge-${getBadgeClass(tx.type)}">${tx.type}</span></td>
                    <td>${tx.source}</td>
                    <td class="${tx.delta > 0 ? 'positive' : 'negative'}">
                        ${tx.delta > 0 ? '+' : ''}${formatNumber(tx.delta)}
                    </td>
                    <td>${formatNumber(tx.balance_after)}</td>
                    <td>${new Date(tx.created_at).toLocaleDateString()}</td>
                </tr>
            `).join('');
            
            updatePagination('ledgerPagination', result.pagination, (page) => {
                ledgerPage = page;
                loadPointsLedger();
            });
        }
    } catch (err) {
        console.error('Failed to load ledger:', err);
        showStatus('Failed to load ledger', 'error');
    }
}

document.getElementById('filterLedgerBtn')?.addEventListener('click', () => {
    ledgerPage = 1;
    loadPointsLedger();
});

document.getElementById('exportCsvBtn')?.addEventListener('click', async () => {
    const from = document.getElementById('filterFrom').value;
    const to = document.getElementById('filterTo').value;
    const type = document.getElementById('filterType').value;
    
    try {
        const response = await fetch(`${API_BASE}/admin/points-transactions/export`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${authToken}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ from, to, type })
        });
        
        if (response.ok) {
            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `points-ledger-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            showStatus('CSV exported successfully', 'success');
        }
    } catch (err) {
        showStatus('Failed to export CSV', 'error');
    }
});

// ==========================
// Book Support Section
// ==========================

let supportPage = 1;

async function loadBookSupport() {
    const bookId = document.getElementById('filterBookId').value;
    const userId = document.getElementById('filterSupportUserId').value;
    
    try {
        const params = new URLSearchParams({
            limit: 50,
            offset: (supportPage - 1) * 50,
            ...(bookId && { book_id: bookId }),
            ...(userId && { user_id: userId })
        });
        
        const response = await fetch(`${API_BASE}/admin/book-support?${params}`, {
            headers: { 'Authorization': `Bearer ${authToken}` }
        });
        const result = await response.json();
        
        if (result.success) {
            const tbody = document.getElementById('supportTable');
            tbody.innerHTML = result.data.map(support => `
                <tr>
                    <td>${support.username || support.user_id}</td>
                    <td><a href="/book/${support.slug}" target="_blank">${support.title}</a></td>
                    <td>${support.points}</td>
                    <td><span class="badge badge-${support.point_type}">${support.point_type}</span></td>
                    <td>${support.multiplier}x</td>
                    <td>${formatNumber(support.effective_points)}</td>
                    <td>${new Date(support.created_at).toLocaleDateString()}</td>
                    <td>
                        <button class="btn btn-danger" onclick="reverseSupport('${support.id}')">Reverse</button>
                    </td>
                </tr>
            `).join('');
            
            updatePagination('supportPagination', result.pagination, (page) => {
                supportPage = page;
                loadBookSupport();
            });
        }
    } catch (err) {
        console.error('Failed to load book support:', err);
        showStatus('Failed to load book support', 'error');
    }
}

document.getElementById('filterSupportBtn')?.addEventListener('click', () => {
    supportPage = 1;
    loadBookSupport();
});

async function reverseSupport(supportId) {
    const reason = prompt('Enter reason for reversal:');
    if (!reason) return;
    
    showConfirmModal('Reverse Support', 
        `Reverse this support event? (Reason: ${reason})`,
        async () => {
            try {
                const response = await fetch(`${API_BASE}/admin/book-support/${supportId}/reverse`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${authToken}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ reason })
                });
                
                if (response.ok) {
                    showStatus('Support reversed successfully', 'success');
                    loadBookSupport();
                }
            } catch (err) {
                showStatus('Failed to reverse support', 'error');
            }
        }
    );
}

// ==========================
// Leaderboards Section
// ==========================

async function loadLeaderboards() {
    try {
        const response = await fetch(`${API_BASE}/admin/leaderboards/config`, {
            headers: { 'Authorization': `Bearer ${authToken}` }
        });
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('freeMultiplier').value = result.data.free_multiplier;
            document.getElementById('premiumMultiplier').value = result.data.premium_multiplier;
            document.getElementById('patreonMultiplier').value = result.data.patreon_multiplier;
            document.getElementById('decayPercentage').value = result.data.decay_percentage;
            document.getElementById('expiryWeeks').value = result.data.decay_weeks;
        }
    } catch (err) {
        console.error('Failed to load leaderboards config:', err);
    }
}

document.getElementById('updateConfigBtn')?.addEventListener('click', async () => {
    showConfirmModal('Update Configuration',
        'Update leaderboard configuration? This will affect future point calculations.',
        async () => {
            try {
                const response = await fetch(`${API_BASE}/admin/leaderboards/config`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${authToken}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        free_multiplier: parseFloat(document.getElementById('freeMultiplier').value),
                        premium_multiplier: parseFloat(document.getElementById('premiumMultiplier').value),
                        patreon_multiplier: parseFloat(document.getElementById('patreonMultiplier').value),
                        decay_percentage: parseInt(document.getElementById('decayPercentage').value),
                        decay_weeks: parseInt(document.getElementById('expiryWeeks').value)
                    })
                });
                
                if (response.ok) {
                    showStatus('Configuration updated successfully', 'success');
                }
            } catch (err) {
                showStatus('Failed to update configuration', 'error');
            }
        }
    );
});

document.getElementById('regenerateBtn')?.addEventListener('click', async () => {
    showConfirmModal('Regenerate Leaderboards',
        'This will recalculate rankings for all time periods. Continue?',
        async () => {
            try {
                const response = await fetch(`${API_BASE}/admin/leaderboards/regenerate`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${authToken}` }
                });
                const result = await response.json();
                
                if (result.success) {
                    showStatus(`Regenerated ${result.data.records} ranking records`, 'success');
                }
            } catch (err) {
                showStatus('Failed to regenerate leaderboards', 'error');
            }
        }
    );
});

// ==========================
// Utility Functions
// ==========================

function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num);
}

function getBadgeClass(type) {
    if (type === 'patreon_reward') return 'success';
    if (type === 'spent') return 'warning';
    if (type === 'refund') return 'danger';
    return 'default';
}

function updatePagination(elementId, pagination, onPageChange) {
    const paginationDiv = document.getElementById(elementId);
    const totalPages = Math.ceil(pagination.total / pagination.limit);
    const currentPage = pagination.offset / pagination.limit + 1;
    
    let html = '';
    
    if (currentPage > 1) {
        html += `<button onclick="this.onclick = null; arguments[0].target.dispatchEvent(new CustomEvent('pageChange', { detail: 1 }))">« First</button>`;
        html += `<button onclick="this.onclick = null; arguments[0].target.dispatchEvent(new CustomEvent('pageChange', { detail: ${currentPage - 1} }))">‹ Previous</button>`;
    }
    
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        html += `<button ${i === currentPage ? 'class="active"' : ''} onclick="this.onclick = null; arguments[0].target.dispatchEvent(new CustomEvent('pageChange', { detail: ${i} }))">${i}</button>`;
    }
    
    if (currentPage < totalPages) {
        html += `<button onclick="this.onclick = null; arguments[0].target.dispatchEvent(new CustomEvent('pageChange', { detail: ${currentPage + 1} }))">Next ›</button>`;
        html += `<button onclick="this.onclick = null; arguments[0].target.dispatchEvent(new CustomEvent('pageChange', { detail: ${totalPages} }))">Last »</button>`;
    }
    
    paginationDiv.innerHTML = html;
    
    paginationDiv.addEventListener('pageChange', (e) => {
        onPageChange(e.detail);
    });
}

function showStatus(message, type) {
    const statusEl = document.getElementById('statusMessage');
    if (!statusEl) return;
    
    statusEl.textContent = message;
    statusEl.className = `status-message ${type}`;
    
    setTimeout(() => {
        statusEl.className = 'status-message';
    }, 5000);
}

function showConfirmModal(title, message, onConfirm) {
    const modal = document.getElementById('confirmModal');
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    
    modal.classList.add('active');
    
    const okBtn = document.getElementById('confirmOkBtn');
    const cancelBtn = document.getElementById('confirmCancelBtn');
    
    const handleConfirm = async () => {
        await onConfirm();
        closeConfirmModal();
    };
    
    const closeConfirmModal = () => {
        modal.classList.remove('active');
        okBtn.removeEventListener('click', handleConfirm);
        cancelBtn.removeEventListener('click', closeConfirmModal);
    };
    
    okBtn.addEventListener('click', handleConfirm);
    cancelBtn.addEventListener('click', closeConfirmModal);
}

// Load dashboard on page load
window.addEventListener('load', () => {
    if (!authToken) {
        window.location.href = '/login';
        return;
    }
    loadDashboard();
});
