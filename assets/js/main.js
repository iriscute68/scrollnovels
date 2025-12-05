// assets/js/main.js
document.addEventListener('DOMContentLoaded', function () {
  // Announcement toggle
  const annToggle = document.getElementById('ann-toggle');
  const annPanel = document.getElementById('ann-panel');
  if (annToggle && annPanel) {
    annToggle.addEventListener('click', () => {
      const visible = annPanel.style.display === 'block';
      annPanel.style.display = visible ? 'none' : 'block';
      annPanel.setAttribute('aria-hidden', visible ? 'true' : 'false');
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
      if (!annPanel.contains(e.target) && !annToggle.contains(e.target)) {
        annPanel.style.display = 'none';
      }
    });
  }

  // Copy link button (example for blog post page)
  document.querySelectorAll('.copy-link-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const url = window.location.href;
      try {
        await navigator.clipboard.writeText(url);
        btn.innerText = 'Copied!';
        setTimeout(() => btn.innerText = 'Copy Link', 2000);
      } catch (err) {
        console.error('copy failed', err);
      }
    });
  });

  // Simple featured carousel controls (prev/next)
  const carouselPrev = document.querySelector('.carousel-prev');
  const carouselNext = document.querySelector('.carousel-next');
  const carouselItems = document.querySelectorAll('.featured-item');
  let carouselIndex = 0;
  function showCarousel(idx) {
    carouselItems.forEach((it, i) => it.style.display = (i === idx ? 'block' : 'none'));
  }
  if (carouselItems.length) showCarousel(0);
  if (carouselPrev) carouselPrev.addEventListener('click', () => { carouselIndex = (carouselIndex - 1 + carouselItems.length) % carouselItems.length; showCarousel(carouselIndex); });
  if (carouselNext) carouselNext.addEventListener('click', () => { carouselIndex = (carouselIndex + 1) % carouselItems.length; showCarousel(carouselIndex); });

  // Submit new book for competition (example modal form)
  const submitBookForm = document.getElementById('submit-book-form');
  if (submitBookForm) {
    submitBookForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.target;
      const data = {
        title: form.title.value.trim(),
        synopsis: form.synopsis.value.trim(),
        genre: form.genre.value,
        competition_id: form.competition_id.value
      };
      if (!data.title || !data.synopsis) {
        alert('Title and synopsis are required');

  // Fallback: ensure clicking on cards (group/book-card) on touch devices follows inner anchor
  document.addEventListener('click', function(e) {
    try {
      const el = e.target;
      const card = el.closest('.group, .book-card');
      if (!card) return;
      // If the actual clicked element is an anchor or inside an anchor, let it proceed
      if (el.closest('a')) return;
      const a = card.querySelector('a[href]');
      if (a && a.getAttribute('href')) {
        // Allow other handlers to run first; then navigate
        setTimeout(() => { window.location.href = a.href; }, 10);
      }
    } catch (err) {
      console.error(err);
    }
  });
        return;
      }
      form.querySelector('button[type=submit]').disabled = true;
      try {
        const res = await fetch('/api/api_submit_book.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) {
          // show success UI, e.g. replace modal content
          alert('Book submitted! Good luck in the competition.');
          window.location.reload();
        } else {
          alert('Error: ' + (json.error || 'Unknown'));
        }
      } catch (err) {
        console.error(err);
        alert('Server error');
      } finally {
        form.querySelector('button[type=submit]').disabled = false;
      }
    });
  }
});
