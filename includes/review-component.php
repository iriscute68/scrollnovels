<?php
/**
 * includes/review-component.php
 * Professional review system component
 * Place this before the comments section in any story/chapter page
 */
if (!function_exists('site_url')) {
    function site_url($path = '') { 
        return '/scrollnovels' . ($path ? '/' . ltrim($path, '/') : ''); 
    }
}
?>

<style>
/* Professional Star Rating System */
.review-box {
    background: #ffffff;
    dark: #1f2937;
    padding: 24px;
    border-radius: 16px;
    max-width: 100%;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    margin-bottom: 24px;
}

.dark .review-box {
    background: #111827;
    border-color: #374151;
}

.review-box h3 {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 12px;
    color: #111827;
}

.dark .review-box h3 {
    color: #f3f4f6;
}

.rating-stars {
    display: flex;
    gap: 12px;
    margin: 16px 0;
    cursor: pointer;
}

.rating-stars svg {
    width: 40px;
    height: 40px;
    fill: #d1d5db;
    transition: all 0.25s ease;
    filter: drop-shadow(0 0 0px rgba(255, 200, 80, 0));
}

.rating-stars svg:hover {
    transform: scale(1.1);
    fill: url(#gradHover);
    filter: drop-shadow(0 0 6px rgba(255, 200, 80, 0.3));
}

.rating-stars svg.filled {
    fill: url(#grad);
    filter: drop-shadow(0 0 6px rgba(255, 200, 80, 0.6));
    transform: scale(1);
}

.rating-display {
    font-size: 0.875rem;
    color: #6b7280;
    margin-top: 8px;
    font-weight: 500;
}

.dark .rating-display {
    color: #d1d5db;
}

textarea.review-textarea {
    width: 100%;
    min-height: 120px;
    border-radius: 12px;
    border: 1px solid #d1d5db;
    padding: 12px;
    margin-top: 12px;
    margin-bottom: 12px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    font-size: 0.95rem;
    resize: vertical;
    background: #fff;
    color: #111827;
    transition: border-color 0.2s;
}

.dark textarea.review-textarea {
    background: #374151;
    border-color: #4b5563;
    color: #f3f4f6;
}

textarea.review-textarea:focus {
    outline: none;
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.review-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.review-buttons button {
    padding: 10px 20px;
    border: none;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

#submit-review, #update-review {
    background: #10b981;
    color: white;
}

#submit-review:hover, #update-review:hover {
    background: #059669;
}

#delete-review {
    background: #ef4444;
    color: white;
}

#delete-review:hover {
    background: #dc2626;
}

.review-message {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 12px;
    font-size: 0.9rem;
    display: none;
}

.review-message.success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #6ee7b7;
    display: block;
}

.dark .review-message.success {
    background: #064e3b;
    color: #d1fae5;
    border-color: #10b981;
}

.review-message.error {
    background: #fee2e2;
    color: #7f1d1d;
    border: 1px solid #fca5a5;
    display: block;
}

.dark .review-message.error {
    background: #7f1d1d;
    color: #fee2e2;
    border-color: #ef4444;
}

.existing-review {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.dark .existing-review {
    background: #064e3b;
    border-color: #10b981;
}

.existing-review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.your-review-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #047857;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.dark .your-review-label {
    color: #6ee7b7;
}

.existing-review-text {
    color: #1f2937;
    font-size: 0.95rem;
    margin: 8px 0;
    line-height: 1.5;
}

.dark .existing-review-text {
    color: #f3f4f6;
}

.review-meta {
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 8px;
}

.dark .review-meta {
    color: #d1d5db;
}
</style>

<div class="review-box">
    <h3>⭐ Rate This Story</h3>
    
    <div id="reviewMessage" class="review-message"></div>
    
    <!-- Display existing review if user is logged in -->
    <?php if ($isLoggedIn && $userId): ?>
        <div id="existingReview" class="existing-review" style="display:none;">
            <div class="existing-review-header">
                <span class="your-review-label">✓ Your Review</span>
                <div id="existingStars" class="rating-stars" style="margin:0;"></div>
            </div>
            <p id="existingReviewText" class="existing-review-text"></p>
            <p class="review-meta">Last updated: <span id="existingReviewDate"></span></p>
        </div>

        <form id="reviewForm">
            <input type="hidden" name="story_id" value="<?= (int)$bookId ?>">
            
            <!-- Star Rating -->
            <label style="display:block; margin-bottom:8px; font-weight:600; color:#1f2937;">
                <span class="dark" style="color:#f3f4f6;">Your Rating:</span>
            </label>
            <div class="rating-stars" id="ratingStars">
                <svg data-value="1" viewBox="0 0 24 24">
                    <defs>
                        <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#ffe08a"/>
                            <stop offset="100%" stop-color="#f4b400"/>
                        </linearGradient>
                        <linearGradient id="gradHover" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#fff4cc"/>
                            <stop offset="100%" stop-color="#ffd700"/>
                        </linearGradient>
                    </defs>
                    <path d="M12 2l3.1 6.26L22 9.27l-5 4.87L18.2 21 12 17.77 5.8 21 7 14.14 2 9.27l6.9-1.01L12 2z"/>
                </svg>
                <svg data-value="2" viewBox="0 0 24 24">
                    <defs>
                        <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#ffe08a"/>
                            <stop offset="100%" stop-color="#f4b400"/>
                        </linearGradient>
                        <linearGradient id="gradHover" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#fff4cc"/>
                            <stop offset="100%" stop-color="#ffd700"/>
                        </linearGradient>
                    </defs>
                    <path d="M12 2l3.1 6.26L22 9.27l-5 4.87L18.2 21 12 17.77 5.8 21 7 14.14 2 9.27l6.9-1.01L12 2z"/>
                </svg>
                <svg data-value="3" viewBox="0 0 24 24">
                    <defs>
                        <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#ffe08a"/>
                            <stop offset="100%" stop-color="#f4b400"/>
                        </linearGradient>
                        <linearGradient id="gradHover" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#fff4cc"/>
                            <stop offset="100%" stop-color="#ffd700"/>
                        </linearGradient>
                    </defs>
                    <path d="M12 2l3.1 6.26L22 9.27l-5 4.87L18.2 21 12 17.77 5.8 21 7 14.14 2 9.27l6.9-1.01L12 2z"/>
                </svg>
                <svg data-value="4" viewBox="0 0 24 24">
                    <defs>
                        <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#ffe08a"/>
                            <stop offset="100%" stop-color="#f4b400"/>
                        </linearGradient>
                        <linearGradient id="gradHover" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#fff4cc"/>
                            <stop offset="100%" stop-color="#ffd700"/>
                        </linearGradient>
                    </defs>
                    <path d="M12 2l3.1 6.26L22 9.27l-5 4.87L18.2 21 12 17.77 5.8 21 7 14.14 2 9.27l6.9-1.01L12 2z"/>
                </svg>
                <svg data-value="5" viewBox="0 0 24 24">
                    <defs>
                        <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#ffe08a"/>
                            <stop offset="100%" stop-color="#f4b400"/>
                        </linearGradient>
                        <linearGradient id="gradHover" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#fff4cc"/>
                            <stop offset="100%" stop-color="#ffd700"/>
                        </linearGradient>
                    </defs>
                    <path d="M12 2l3.1 6.26L22 9.27l-5 4.87L18.2 21 12 17.77 5.8 21 7 14.14 2 9.27l6.9-1.01L12 2z"/>
                </svg>
            </div>
            <div class="rating-display" id="ratingDisplay">Select a rating</div>

            <!-- Review Text -->
            <label style="display:block; margin-top:16px; margin-bottom:8px; font-weight:600; color:#1f2937;">
                <span class="dark" style="color:#f3f4f6;">Your Review (Optional):</span>
            </label>
            <textarea class="review-textarea" name="review_text" placeholder="Write your review..."></textarea>

            <!-- Buttons -->
            <div class="review-buttons">
                <button type="submit" id="submit-review">Submit Review</button>
                <button type="button" id="update-review" style="display:none;">Update Review</button>
                <button type="button" id="delete-review" style="display:none;">Delete Review</button>
            </div>
        </form>
    <?php else: ?>
        <p style="color:#6b7280; font-style:italic;">
            <a href="<?= site_url('/pages/login.php') ?>" style="color:#10b981; font-weight:600; text-decoration:none;">Login</a> 
            to rate this story and share your review!
        </p>
    <?php endif; ?>
</div>

<script>
<?php if ($isLoggedIn && $userId): ?>
let selectedRating = 0;

// Initialize stars
document.querySelectorAll("#ratingStars svg").forEach(star => {
    star.addEventListener("click", () => {
        selectedRating = parseInt(star.dataset.value);
        highlightStars(selectedRating);
    });
});

function highlightStars(count) {
    document.querySelectorAll("#ratingStars svg").forEach(s => {
        s.classList.toggle("filled", parseInt(s.dataset.value) <= count);
    });
    const labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
    document.getElementById('ratingDisplay').textContent = count > 0 ? labels[count] + ' (' + count + '★)' : 'Select a rating';
}

// Load existing review
function loadExistingReview() {
    fetch('<?= site_url('/api/get-review.php') ?>?story_id=<?= (int)$bookId ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.review) {
                const review = data.review;
                selectedRating = review.rating;
                highlightStars(review.rating);
                document.querySelector('textarea.review-textarea').value = review.review_text || '';
                
                // Show existing review display
                document.getElementById('existingReview').style.display = 'block';
                document.getElementById('existingReviewText').textContent = review.review_text || '(No text)';
                document.getElementById('existingReviewDate').textContent = new Date(review.updated_at || review.created_at).toLocaleDateString();
                
                // Update button states
                document.getElementById('submit-review').style.display = 'none';
                document.getElementById('update-review').style.display = 'inline-block';
                document.getElementById('delete-review').style.display = 'inline-block';
                
                // Show existing stars for reference
                highlightExistingStars(review.rating);
            }
        })
        .catch(e => console.error('Error loading review:', e));
}

function highlightExistingStars(count) {
    document.querySelectorAll("#existingStars svg").forEach(s => {
        if (!s.parentElement.querySelector('svg')) return;
        s.classList.toggle("filled", parseInt(s.dataset.value) <= count);
    });
}

// Create SVGs for existing review display
const existingStarsContainer = document.getElementById('existingStars');
for (let i = 1; i <= 5; i++) {
    const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    svg.setAttribute("viewBox", "0 0 24 24");
    svg.setAttribute("data-value", i);
    svg.style.width = "20px";
    svg.style.height = "20px";
    svg.style.fill = "#d1d5db";
    svg.innerHTML = `
        <defs>
            <linearGradient id="gradExisting" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#ffe08a"/>
                <stop offset="100%" stop-color="#f4b400"/>
            </linearGradient>
        </defs>
        <path d="M12 2l3.1 6.26L22 9.27l-5 4.87L18.2 21 12 17.77 5.8 21 7 14.14 2 9.27l6.9-1.01L12 2z"/>
    `;
    existingStarsContainer.appendChild(svg);
}

// Form submission
document.getElementById('reviewForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    if (selectedRating < 1 || selectedRating > 5) {
        showMessage('Please select a rating', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('story_id', <?= (int)$bookId ?>);
    formData.append('rating', selectedRating);
    formData.append('review_text', document.querySelector('textarea.review-textarea').value);
    
    try {
        const response = await fetch('<?= site_url('/api/submit-review.php') ?>', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            showMessage('✓ ' + data.message, 'success');
            setTimeout(loadExistingReview, 500);
        } else {
            showMessage('✗ ' + data.error, 'error');
        }
    } catch (e) {
        showMessage('Error: ' + e.message, 'error');
    }
});

// Update review
document.getElementById('update-review').addEventListener('click', async (e) => {
    e.preventDefault();
    if (!selectedRating) {
        showMessage('Please select a rating', 'error');
        return;
    }
    document.getElementById('reviewForm').dispatchEvent(new Event('submit'));
});

// Delete review
document.getElementById('delete-review').addEventListener('click', async (e) => {
    e.preventDefault();
    if (!confirm('Delete your review? This cannot be undone.')) return;
    
    try {
        const reviewId = await getReviewId();
        const response = await fetch('<?= site_url('/api/delete-review.php') ?>', {
            method: 'POST',
            body: new FormData(Object.assign(new FormData(), {story_id: <?= (int)$bookId ?>, review_id: reviewId}))
        });
        // Simpler approach - just post the review_id
        const fd = new FormData();
        fd.append('review_id', await getReviewId());
        
        const r = await fetch('<?= site_url('/api/delete-review.php') ?>', { method: 'POST', body: fd });
        const data = await r.json();
        
        if (data.success) {
            showMessage('✓ Review deleted', 'success');
            selectedRating = 0;
            document.querySelector('textarea.review-textarea').value = '';
            highlightStars(0);
            document.getElementById('existingReview').style.display = 'none';
            document.getElementById('submit-review').style.display = 'inline-block';
            document.getElementById('update-review').style.display = 'none';
            document.getElementById('delete-review').style.display = 'none';
        } else {
            showMessage('✗ ' + data.error, 'error');
        }
    } catch (e) {
        showMessage('Error: ' + e.message, 'error');
    }
});

async function getReviewId() {
    const response = await fetch('<?= site_url('/api/get-review.php') ?>?story_id=<?= (int)$bookId ?>');
    const data = await response.json();
    return data.review ? data.review.id : null;
}

function showMessage(msg, type) {
    const msgBox = document.getElementById('reviewMessage');
    msgBox.textContent = msg;
    msgBox.className = 'review-message ' + type;
    msgBox.style.display = 'block';
    setTimeout(() => {
        msgBox.style.display = 'none';
    }, 5000);
}

// Load review on page load
loadExistingReview();
<?php endif; ?>
</script>
