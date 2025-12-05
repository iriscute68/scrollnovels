// assets/js/book.js - Likes/comments + reader nav (merged; no leaks)
document.addEventListener('DOMContentLoaded', () => {
    console.log('Book reader loaded');

    // Start Reading -> go to first chapter's reader
    const startBtn = document.getElementById('startReadingBtn');
    if (startBtn) {
        startBtn.addEventListener('click', () => {
            const first = document.querySelector('.readChapterBtn');
            if (first) {
                const id = first.dataset.chapterId;
                window.location.href = `${window.SITE_URL}/pages/reader.php?chapter_id=${id}`;
            } else {
                // fallback to story page
                const sid = startBtn.dataset.storyId;
                window.location.href = `${window.SITE_URL}/pages/book.php?id=${sid}`;
            }
        });
    }

    // Donate -> open donate page for this story
    const donateBtn = document.getElementById('donateOpenBtn');
    if (donateBtn) {
        donateBtn.addEventListener('click', () => {
            const sid = document.getElementById('startReadingBtn')?.dataset.storyId;
            if (sid) window.location.href = `${window.SITE_URL}/pages/donate.php?story_id=${sid}`;
            else window.location.href = `${window.SITE_URL}/pages/donate.php`;
        });
    }

    // Like button (uses api/like.php expecting JSON)
    const likeBtn = document.getElementById('likeBtn');
    if (likeBtn) {
        likeBtn.addEventListener('click', async () => {
            const storyId = document.getElementById('startReadingBtn')?.dataset.storyId;
            if (!storyId) return;
            try {
                const res = await fetch(`${window.SITE_URL}/api/like.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ story_id: storyId })
                });
                const data = await res.json();
                if (data.count !== undefined) {
                    document.getElementById('likeCount').textContent = data.count;
                    likeBtn.classList.toggle('btn-like', data.liked);
                }
            } catch (e) {
                console.error(e);
            }
        });
    }

    // Library (add/remove)
    const libBtn = document.getElementById('libraryBtn');
    if (libBtn) {
        libBtn.addEventListener('click', async () => {
            const storyId = document.getElementById('startReadingBtn')?.dataset.storyId;
            if (!storyId) return;
            try {
                const res = await fetch(`${window.SITE_URL}/api/add_library.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ story_id: storyId })
                });
                const data = await res.json();
                if (data.inLibrary !== undefined) {
                    libBtn.textContent = data.inLibrary ? 'In Library' : 'Add to Library';
                    libBtn.classList.toggle('btn-emerald', data.inLibrary);
                    libBtn.classList.toggle('btn-outline', !data.inLibrary);
                }
            } catch (e) {
                console.error(e);
            }
        });
    }

    // Read chapter buttons
    document.querySelectorAll('.readChapterBtn').forEach(b => {
        b.addEventListener('click', () => {
            const id = b.dataset.chapterId;
            if (id) window.location.href = `${window.SITE_URL}/pages/reader.php?chapter_id=${id}`;
        });
    });

    // Post review
    const postReviewBtn = document.getElementById('postReviewBtn');
    if (postReviewBtn) {
        postReviewBtn.addEventListener('click', async () => {
            const storyId = document.getElementById('startReadingBtn')?.dataset.storyId;
            const content = document.getElementById('reviewContent')?.value || '';
            const rating = document.getElementById('reviewRating')?.value || 5;
            if (!storyId) return;
            try {
                const res = await fetch(`${window.SITE_URL}/api/post_review.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ story_id: storyId, rating: rating, content: content })
                });
                const data = await res.json();
                if (data.success && data.review) {
                    // prepend simple review card
                    const list = document.getElementById('reviewList');
                    const node = document.createElement('div');
                    node.className = 'review-card';
                    node.innerHTML = `<div class="flex-between"><strong>${escapeHtml(data.review.username || 'You')}</strong><span class="muted small">${new Date(data.review.created_at).toLocaleDateString()}</span></div><div class="muted small">Rating: ${data.review.rating} / 5</div><p>${escapeHtml(data.review.content || '')}</p>`;
                    if (list) list.prepend(node);
                    // clear form
                    document.getElementById('reviewContent').value = '';
                }
            } catch (e) {
                console.error(e);
            }
        });
    }

    // Reader nav (keyboard: arrow keys for chapters) - keep original behavior if nav exists
    document.addEventListener('keydown', e => {
        const current = document.querySelector('nav a.bg-emerald-600');
        if (e.key === 'ArrowLeft' && current && current.previousElementSibling) {
            current.previousElementSibling.click();
        } else if (e.key === 'ArrowRight' && current && current.nextElementSibling) {
            current.nextElementSibling.click();
        }
    });

    // Webtoon zoom (if images)
    const images = document.querySelectorAll('.webtoon-gallery img');
    images.forEach(img => {
        img.addEventListener('click', () => {
            img.style.transform = img.style.transform === 'scale(1.5)' ? 'scale(1)' : 'scale(1.5)';
        });
    });

    // simple escape for injected text
    function escapeHtml(s) {
        return String(s).replace(/[&<>\"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    }
});