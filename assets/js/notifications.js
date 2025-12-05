// assets/notifications.js - Frontend notifications system

const notifBell = document.getElementById('notifBell');
const notifPanel = document.getElementById('notifPanel');
const notifList = document.getElementById('notifList');
const notifCount = document.getElementById('notifCount');
const markAllRead = document.getElementById('markAllRead');
const tickerInner = document.getElementById('tickerInner');

let lastNotifId = 0;

async function fetchNotifications() {
  try {
    const res = await fetch('/api/notifications_fetch.php?limit=10');
    const data = await res.json();
    updatePanel(data.items);
    updateBadge(data.unread);
  } catch (e) {
    console.error('Fetch error:', e);
  }
}

function updateBadge(count) {
  if (count > 0) {
    notifCount.textContent = count;
    notifCount.classList.remove('hidden');
    notifCount.classList.add('glow-pulse');
  } else {
    notifCount.classList.add('hidden');
    notifCount.classList.remove('glow-pulse');
  }
}

function formatTime(ts) {
  const d = new Date(ts);
  return d.toLocaleString();
}

function updatePanel(items) {
  if (!notifList) return;
  notifList.innerHTML = '';
  items.forEach(item => {
    lastNotifId = Math.max(lastNotifId, item.id);
    const el = document.createElement('div');
    el.className = 'p-3 bg-[#0f0820] border border-[#D4AF37]/10 rounded hover:bg-[#160b2a] transition-colors cursor-pointer';
    el.innerHTML = `
      <div class="flex items-start gap-3">
        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-[#D4AF37] to-[#4B0082] flex items-center justify-center text-[#120A2A] font-bold">${iconForType(item.type)}</div>
        <div class="flex-1">
          <div class="flex justify-between items-center">
            <div class="text-[#D4AF37] font-semibold">${escapeHtml(item.title)}</div>
            <div class="text-xs text-[#8B7D6B]">${formatTime(item.created_at)}</div>
          </div>
          <div class="text-[#C4B5A0] text-sm mt-1">${escapeHtml(item.body || '')}</div>
        </div>
      </div>
    `;
    el.onclick = () => { markAsRead(item.id); if (item.url) window.location = item.url; };
    notifList.appendChild(el);
  });
}

function iconForType(type) {
  const icons = {
    'comment': '💬',
    'like': '❤️',
    'announcement': '📢',
    'payout': '💸',
    'competition': '🏆',
    'follow': '🔔',
    'report': '⚠️',
    'appeal': '📋'
  };
  return icons[type] || '🔔';
}

function escapeHtml(s) {
  return (s || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

async function markAsRead(id) {
  try {
    await fetch('/api/notifications_mark_read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${id}`
    });
    fetchNotifications();
  } catch (e) {
    console.error('Mark read error:', e);
  }
}

if (markAllRead) {
  markAllRead.onclick = async () => {
    try {
      await fetch('/api/notifications_mark_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `all=1`
      });
      fetchNotifications();
    } catch (e) {
      console.error('Mark all error:', e);
    }
  };
}

if (notifBell) {
  notifBell.addEventListener('click', (e) => {
    e.stopPropagation();
    if (notifPanel.classList.contains('hidden')) {
      notifPanel.classList.remove('hidden');
      notifPanel.style.animation = 'scaleIn 160ms ease-out';
    } else {
      notifPanel.classList.add('hidden');
    }
  });

  document.addEventListener('click', () => {
    if (notifPanel && !notifPanel.classList.contains('hidden')) {
      notifPanel.classList.add('hidden');
    }
  });
}

// SSE connection
if (!!window.EventSource) {
  const es = new EventSource('/inc/notifications_sse.php?last_id=' + lastNotifId);

  es.addEventListener('notification', function(e) {
    try {
      const payload = JSON.parse(e.data);
      fetchNotifications();
    } catch (e) {
      console.error('Parse error:', e);
    }
  });

  es.addEventListener('announcement', function(e) {
    try {
      const a = JSON.parse(e.data);
      if (a.show_on_ticker) {
        addTickerItem(a.title);
      }
      fetchNotifications();
    } catch (e) {
      console.error('Announcement parse error:', e);
    }
  });
}

function addTickerItem(text) {
  if (!tickerInner) return;
  const node = document.createElement('span');
  node.textContent = ` ${text}  —  `;
  tickerInner.appendChild(node);
  tickerInner.style.animation = 'none';
  void tickerInner.offsetWidth;
  tickerInner.style.animation = '';
}

// Initial load
fetchNotifications();

// Refresh every minute
setInterval(fetchNotifications, 60000);
