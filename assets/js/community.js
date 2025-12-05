// assets/js/community.js - Modals/search/replies (merged; debounce)
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('search-input');
    const categoryFilter = document.getElementById('category-filter');
    const list = document.getElementById('threads-list');
    const loadMore = document.getElementById('load-more');

    // Debounce search/filter
    let timeout;
    function refreshList() {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            const cat = categoryFilter.value;
            const q = searchInput.value;
            apiFetch(`discussions/list.php?category=${cat}&search=${encodeURIComponent(q)}&limit=20&offset=0`)
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        list.innerHTML = data.threads.map(t => `
                            <article class="bg-gray-800 p-4 rounded border">
                                <h3><a href="${window.SITE_URL}/pages/thread.php?slug=${t.slug}" class="text-xl font-semibold hover:text-emerald-400">${t.title}</a></h3>
                                <p class="text-gray-300">${t.content.substring(0, 100)}...</p>
                                <div class="text-sm text-gray-500 mt-2">
                                    By ${t.author_name} • ${t.reply_count} replies • ${formatTimeAgo(t.created_at)}
                                </div>
                            </article>
                        `).join('') || '<p class="text-center text-gray-400">No threads found.</p>';
                    }
                });
        }, 300);
    }

    searchInput?.addEventListener('input', refreshList);
    categoryFilter?.addEventListener('change', refreshList);

    // Load more (pag offset += limit)
    if (loadMore) {
        let offset = 20;
        loadMore.addEventListener('click', () => {
            apiFetch(`discussions/list.php?offset=${offset}`)
                .then(r => r.json())
                .then(data => {
                    if (data.ok && data.threads.length) {
                        const html = data.threads.map(t => `
                            <article class="bg-gray-800 p-4 rounded border">
                                <h3><a href="${window.SITE_URL}/pages/thread.php?slug=${t.slug}" class="text-xl font-semibold hover:text-emerald-400">${t.title}</a></h3>
                                <p class="text-gray-300">${t.content.substring(0, 100)}...</p>
                                <div class="text-sm text-gray-500 mt-2">
                                    By ${t.author_name} • ${t.reply_count} replies • ${formatTimeAgo(t.created_at)}
                                </div>
                            </article>
                        `).join('');
                        list.insertAdjacentHTML('beforeend', html);
                        offset += 20;
                    } else {
                        loadMore.style.display = 'none';
                    }
                });
        });
    }

    // Reply form (per thread; stub for /thread/slug)
    const replyForms = document.querySelectorAll('.reply-form');
    replyForms.forEach(form => {
        form.addEventListener('submit', async e => {
            e.preventDefault();
            const fd = new FormData(form);
            const res = await apiFetch('discussions/reply.php', {method: 'POST', body: fd});
            const data = await res.json();
            if (data.ok) {
                toast('Reply posted!');
                location.reload();
            } else {
                toast(data.error, 'error');
            }
        });
    });

    function formatTimeAgo(dateStr) {
        return new Date(dateStr).toLocaleDateString();  // Expand to time_ago if JS lib
    }
});