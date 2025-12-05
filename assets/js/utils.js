// assets/js/utils.js - Shared fetch + toast (merged; no leaks)
function apiFetch(endpoint, options = {}) {
    const defaults = {
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('input[name="csrf"]')?.value || ''  // From forms
        },
        credentials: 'same-origin'  // Cookies/sessions
    };
    // Use SITE_URL (set by server-side header include) so requests respect the /scrollnovels base path
    const base = window.SITE_URL || window.location.origin;
    return fetch(`${base}/api/${endpoint}`, { ...defaults, ...options });
}

function toast(msg, type = 'info') {
    const div = document.createElement('div');
    div.className = `fixed top-4 right-4 p-4 rounded shadow-lg ${type === 'error' ? 'bg-red-500' : 'bg-emerald-500'} text-white`;
    div.textContent = msg;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 3000);
}

// Export for use
window.apiFetch = apiFetch;
window.toast = toast;