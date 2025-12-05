<?php
/**
 * includes/components/review-widget.php - Display and manage reviews
 * Usage: include 'includes/components/review-widget.php' with $bookId set
 */

if (!isset($bookId)) {
    return; // Book ID must be set
}

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
?>

<div class="review-widget mt-12 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
    <h2 class="text-3xl font-bold text-gray-800 dark:text-white mb-8">📚 Reviews & Ratings</h2>

    <?php if ($isLoggedIn): ?>
        <!-- Review Form -->
        <div class="mb-8 p-6 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg">
            <h3 class="text-xl font-semibold text-gray-800 dark:text-white mb-4">Share Your Thoughts</h3>

            <form id="reviewForm" class="space-y-4">
                <!-- Star Rating -->
                <div class="mb-4">
                    <label class="block text-gray-700 dark:text-gray-300 font-semibold mb-2">Your Rating</label>
                    <div id="rating" data-rating="0" class="flex gap-1 cursor-pointer mb-2">
                        <span data-value="1" class="star text-4xl transition cursor-pointer">★</span>
                        <span data-value="2" class="star text-4xl transition cursor-pointer">★</span>
                        <span data-value="3" class="star text-4xl transition cursor-pointer">★</span>
                        <span data-value="4" class="star text-4xl transition cursor-pointer">★</span>
                        <span data-value="5" class="star text-4xl transition cursor-pointer">★</span>
                    </div>
                    <input type="hidden" id="rating_value" name="rating" value="0">
                </div>

                <!-- Review Text -->
                <div>
                    <label class="block text-gray-700 dark:text-gray-300 font-semibold mb-2">Your Review</label>
                    <textarea id="review_text" name="review_text" placeholder="What did you think of this story?" 
                              class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white"
                              rows="5"></textarea>
                </div>

                <!-- Submit -->
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-4 rounded-lg transition">
                    ✓ Submit Review
                </button>

                <div id="reviewMessage" class="mt-2"></div>
            </form>
        </div>

        <style>
        #rating .star {
            color: #d1d5db;
            font-size: 2.5rem;
        }

        #rating .star.hovered,
        #rating .star.selected {
            color: #fbbf24;
        }
        </style>

        <script>
        // Star rating handler
        const ratingDiv = document.getElementById('rating');
        const ratingInput = document.getElementById('rating_value');
        const stars = ratingDiv.querySelectorAll('.star');
        let currentRating = 0;

        stars.forEach(star => {
            star.addEventListener('mouseover', function() {
                const hoverValue = parseInt(this.getAttribute('data-value'));
                stars.forEach(s => {
                    s.classList.toggle('hovered', parseInt(s.getAttribute('data-value')) <= hoverValue);
                });
            });

            star.addEventListener('mouseout', function() {
                stars.forEach(s => s.classList.remove('hovered'));
                updateStars(currentRating);
            });

            star.addEventListener('click', function() {
                currentRating = parseInt(this.getAttribute('data-value'));
                ratingInput.value = currentRating;
                updateStars(currentRating);
            });
        });

        function updateStars(rating) {
            stars.forEach(s => {
                const val = parseInt(s.getAttribute('data-value'));
                s.classList.toggle('selected', val <= rating);
            });
        }

        // Submit review
        document.getElementById('reviewForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const rating = parseInt(ratingInput.value);
            const reviewText = document.getElementById('review_text').value;
            const msgDiv = document.getElementById('reviewMessage');

            if (!rating) {
                msgDiv.innerHTML = '<p class="text-red-600">Please select a rating</p>';
                return;
            }

            try {
                const response = await fetch('/api/review.php?action=store', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        book_id: {{ $bookId }},
                        rating: rating,
                        review_text: reviewText
                    })
                });

                const data = await response.json();

                if (data.success) {
                    msgDiv.innerHTML = '<p class="text-emerald-600 font-semibold">✅ ' + data.message + '</p>';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    msgDiv.innerHTML = '<p class="text-red-600">❌ ' + data.error + '</p>';
                }
            } catch (error) {
                msgDiv.innerHTML = '<p class="text-red-600">Error: ' + error.message + '</p>';
            }
        });
        </script>

    <?php else: ?>
        <div class="mb-8 p-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg text-center">
            <p class="text-gray-700 dark:text-gray-300 mb-3">Please <a href="/pages/login.php" class="text-blue-600 hover:underline font-semibold">login</a> to leave a review.</p>
        </div>
    <?php endif; ?>

    <!-- Reviews List -->
    <div id="reviewsList" class="mt-8 space-y-4">
        <p class="text-gray-500 dark:text-gray-400 text-center py-8">Loading reviews...</p>
    </div>

    <script>
    // Load reviews
    async function loadReviews() {
        try {
            const response = await fetch(`/api/review.php?action=list&book_id={{ $bookId }}&limit=10`);
            const data = await response.json();
            const container = document.getElementById('reviewsList');

            if (!data.success || !data.reviews || data.reviews.length === 0) {
                container.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-center py-8">No reviews yet. Be the first!</p>';
                return;
            }

            const html = data.reviews.map(review => `
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-3">
                            ${review.profile_image ? `<img src="${review.profile_image}" class="w-10 h-10 rounded-full">` : '<div class="w-10 h-10 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">👤</div>'}
                            <div>
                                <p class="font-semibold text-gray-800 dark:text-white">${review.username}</p>
                                <p class="text-sm text-gray-500">${new Date(review.created_at).toLocaleDateString()}</p>
                            </div>
                        </div>
                        <div class="text-xl">
                            ${'★'.repeat(review.rating)}<span style="color: #d1d5db;">${'★'.repeat(5-review.rating)}</span>
                        </div>
                    </div>
                    <p class="text-gray-700 dark:text-gray-300">${review.review_text || '(No text review)'}</p>
                </div>
            `).join('');

            container.innerHTML = html;
        } catch (error) {
            document.getElementById('reviewsList').innerHTML = '<p class="text-red-600">Error loading reviews</p>';
        }
    }

    loadReviews();
    </script>
</div>
