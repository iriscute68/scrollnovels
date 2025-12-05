<?php
// pages/support.php - Support Center
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$page_title = 'Support Center';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - ScrollNovels</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white">
    <!-- Navigation -->
    <nav class="bg-gray-800 border-b border-gray-700">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <a href="<?= site_url('/pages/dashboard.php') ?>" class="text-gray-400 hover:text-white transition">← Back to Dashboard</a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-6xl mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold mb-2">Support Center</h1>
            <p class="text-gray-400">Get help with your account, report issues, or ask questions</p>
        </div>

        <!-- Tabs Navigation -->
        <div class="flex gap-4 mb-6 border-b border-gray-700">
            <button class="tab-btn active" data-tab="tickets">📋 My Tickets</button>
            <button class="tab-btn" data-tab="create">✏️ Create Ticket</button>
        </div>

        <!-- Tabs Content Container -->
        <div class="tab-content">
            <!-- My Tickets Tab -->
            <div id="tickets-tab" class="tab-panel">
                <div class="space-y-4">
                    <div class="flex gap-2 mb-4">
                        <button class="status-filter-btn active" data-status="">All</button>
                        <button class="status-filter-btn" data-status="open">Open</button>
                        <button class="status-filter-btn" data-status="in_progress">In Progress</button>
                        <button class="status-filter-btn" data-status="resolved">Resolved</button>
                        <button class="status-filter-btn" data-status="closed">Closed</button>
                    </div>
                    <div id="tickets-list" class="space-y-3">
                        <div class="text-center py-8 text-gray-500">Loading tickets...</div>
                    </div>
                </div>
            </div>

            <!-- Create Ticket Tab -->
            <div id="create-tab" class="tab-panel" style="display: none;">
                <div class="bg-gray-800 rounded-lg p-6 max-w-2xl">
                    <form id="create-ticket-form" class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold mb-2">Subject *</label>
                            <input type="text" name="subject" required placeholder="Brief description of your issue"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                            <p class="text-xs text-gray-500 mt-1">Minimum 5 characters</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2">Category *</label>
                            <select name="category" required class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <option value="">-- Select Category --</option>
                                <option value="bug">🐛 Bug Report</option>
                                <option value="feature">✨ Feature Request</option>
                                <option value="payment">💳 Payment Issue</option>
                                <option value="account">👤 Account Help</option>
                                <option value="content">📚 Content Question</option>
                                <option value="other">❓ Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2">Priority *</label>
                            <select name="priority" required class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">🚨 Urgent</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2">Description *</label>
                            <textarea name="description" required placeholder="Please provide as much detail as possible..." rows="6"
                                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"></textarea>
                            <p class="text-xs text-gray-500 mt-1">Minimum 10 characters. Include error messages if applicable.</p>
                        </div>

                        <div id="form-message" style="display: none;" class="p-4 rounded-lg text-sm"></div>

                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg transition">
                            ✓ Submit Ticket
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Ticket Detail Modal -->
    <div id="ticket-modal" style="display: none;" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4">
        <div class="bg-gray-800 rounded-lg p-6 max-w-2xl w-full max-h-96 overflow-y-auto border border-gray-700">
            <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-700">
                <h2 id="modal-title" class="text-xl font-bold">Ticket Details</h2>
                <button onclick="closeTicketModal()" class="text-gray-400 hover:text-white text-2xl">✕</button>
            </div>

            <div id="modal-body" class="mb-4">
                <!-- Loaded dynamically -->
            </div>

            <div class="border-t border-gray-700 pt-4">
                <h3 class="font-semibold mb-3">Messages</h3>
                <div id="replies-list" class="space-y-3 mb-4 max-h-48 overflow-y-auto">
                    <!-- Replies loaded here -->
                </div>

                <div id="reply-form-container" class="border-t border-gray-700 pt-4">
                    <form id="reply-form" class="space-y-2">
                        <textarea name="message" placeholder="Type your reply..." rows="3"
                            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"></textarea>
                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 rounded-lg transition">
                            Post Reply
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentFilter = '';
        let currentTicketId = null;

        // Initialize
        loadTickets();

        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tabName = this.dataset.tab;
                
                // Update active button
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Show/hide panels
                document.querySelectorAll('.tab-panel').forEach(panel => {
                    panel.style.display = 'none';
                });
                document.getElementById(tabName + '-tab').style.display = 'block';
            });
        });

        // Status filter buttons
        document.querySelectorAll('.status-filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.status-filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.status;
                loadTickets();
            });
        });

        // Load tickets
        function loadTickets() {
            const url = '<?= site_url('/api/get-support-tickets.php') ?>' + (currentFilter ? '?status=' + currentFilter : '');
            
            fetch(url, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.tickets) {
                        renderTickets(data.tickets);
                    } else {
                        showTicketsError('Failed to load tickets');
                    }
                })
                .catch(e => {
                    console.error(e);
                    showTicketsError('Network error');
                });
        }

        // Render tickets
        function renderTickets(tickets) {
            const list = document.getElementById('tickets-list');
            
            if (!tickets || tickets.length === 0) {
                list.innerHTML = '<div class="text-center py-8 text-gray-500">No tickets found</div>';
                return;
            }

            list.innerHTML = tickets.map(t => `
                <div class="bg-gray-800 border border-gray-700 rounded-lg p-4 cursor-pointer hover:bg-gray-750 transition" onclick="viewTicket(${t.id})">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h3 class="font-semibold">#${t.id} - ${escapeHtml(t.subject)}</h3>
                            <p class="text-sm text-gray-400">${escapeHtml(t.category)}</p>
                        </div>
                        <div class="flex gap-2">
                            <span class="px-2 py-1 rounded text-xs font-semibold bg-yellow-900/30 text-yellow-300">
                                ${escapeHtml(t.priority)}
                            </span>
                            <span class="px-2 py-1 rounded text-xs font-semibold bg-blue-900/30 text-blue-300">
                                ${escapeHtml(t.status)}
                            </span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center text-sm text-gray-400">
                        <span>${t.reply_count || 0} replies</span>
                        <span>${new Date(t.updated_at).toLocaleDateString()}</span>
                    </div>
                </div>
            `).join('');
        }

        // View ticket
        function viewTicket(ticketId) {
            currentTicketId = ticketId;
            
            fetch('<?= site_url('/api/get-support-tickets.php') ?>?ticket_id=' + ticketId, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.ticket) {
                        const ticket = data.ticket;
                        document.getElementById('modal-title').textContent = '#' + ticket.id + ' - ' + escapeHtml(ticket.subject);
                        
                        document.getElementById('modal-body').innerHTML = `
                            <div class="space-y-3 text-sm">
                                <div><span class="text-gray-400">Category:</span> <strong>${escapeHtml(ticket.category)}</strong></div>
                                <div><span class="text-gray-400">Priority:</span> <strong>${escapeHtml(ticket.priority)}</strong></div>
                                <div><span class="text-gray-400">Status:</span> <strong>${escapeHtml(ticket.status)}</strong></div>
                                <div><span class="text-gray-400">Created:</span> <strong>${new Date(ticket.created_at).toLocaleString()}</strong></div>
                                <hr class="border-gray-700 my-3">
                                <div class="text-gray-300">${escapeHtml(ticket.description)}</div>
                            </div>
                        `;

                        // Load replies
                        document.getElementById('replies-list').innerHTML = '';
                        if (data.replies && data.replies.length > 0) {
                            document.getElementById('replies-list').innerHTML = data.replies.map(r => `
                                <div class="bg-gray-700 rounded p-3 text-sm">
                                    <div class="font-semibold text-emerald-400">${escapeHtml(r.username || 'Admin')}</div>
                                    <div class="text-xs text-gray-400 mb-1">${new Date(r.created_at).toLocaleString()}</div>
                                    <div class="text-gray-200">${escapeHtml(r.message)}</div>
                                </div>
                            `).join('');
                        }

                        document.getElementById('ticket-modal').style.display = 'flex';
                    }
                })
                .catch(e => {
                    console.error(e);
                    alert('Failed to load ticket details');
                });
        }

        // Close modal
        function closeTicketModal() {
            document.getElementById('ticket-modal').style.display = 'none';
            currentTicketId = null;
        }

        // Create ticket form
        document.getElementById('create-ticket-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const data = {
                subject: this.subject.value.trim(),
                description: this.description.value.trim(),
                category: this.category.value,
                priority: this.priority.value
            };

            if (data.subject.length < 5) {
                showFormMessage('Subject must be at least 5 characters', 'error');
                return;
            }

            if (data.description.length < 10) {
                showFormMessage('Description must be at least 10 characters', 'error');
                return;
            }

            fetch('<?= site_url('/api/create-support-ticket.php') ?>', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showFormMessage('✓ Ticket #' + data.ticket_id + ' created successfully!', 'success');
                    this.reset();
                    
                    // Reload tickets
                    setTimeout(() => {
                        loadTickets();
                        document.querySelectorAll('.tab-btn')[0].click();
                    }, 1500);
                } else {
                    showFormMessage(data.error || 'Failed to create ticket', 'error');
                }
            })
            .catch(e => {
                console.error(e);
                showFormMessage('Network error: ' + e.message, 'error');
            });
        });

        // Show message
        function showFormMessage(msg, type) {
            const div = document.getElementById('form-message');
            div.textContent = msg;
            div.style.display = 'block';
            div.className = 'p-4 rounded-lg text-sm ' + (type === 'success' 
                ? 'bg-emerald-900/30 text-emerald-300 border border-emerald-600'
                : 'bg-red-900/30 text-red-300 border border-red-600');
            
            setTimeout(() => {
                div.style.display = 'none';
            }, 5000);
        }

        function showTicketsError(msg) {
            const list = document.getElementById('tickets-list');
            list.innerHTML = '<div class="text-center py-8 text-red-400">' + escapeHtml(msg) + '</div>';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modal on outside click
        document.getElementById('ticket-modal').addEventListener('click', function(e) {
            if (e.target === this) closeTicketModal();
        });
    </script>

    <style>
        .tab-btn {
            padding: 0.75rem 1rem;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
        }

        .tab-btn.active {
            color: #10b981;
            border-bottom-color: #10b981;
        }

        .status-filter-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #4b5563;
            background-color: #374151;
            color: #d1d5db;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .status-filter-btn.active {
            background-color: #10b981;
            color: white;
            border-color: #10b981;
        }

        .status-filter-btn:hover {
            background-color: #4b5563;
        }
    </style>
</body>
</html>

