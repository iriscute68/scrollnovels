/**
 * Writer Analytics System
 * Tracks comments, reviews, and library saves in real-time
 * 
 * Usage:
 * - Include in writer dashboard
 * - Initialize: window.writerAnalytics = new WriterAnalytics()
 * - Add new activity: writerAnalytics.addComment(data), addReview(data), addLibrarySave(data)
 */

class WriterAnalytics {
  constructor() {
    this.commentsList = document.getElementById('comments-list')
    this.reviewsList = document.getElementById('reviews-list')
    this.savesList = document.getElementById('saves-list')
    this.pollInterval = 10000 // Poll every 10 seconds
    this.initEventListeners()
    this.startPolling()
    console.log('[WriterAnalytics] System initialized')
  }

  /**
   * Initialize event listeners for interactions
   */
  initEventListeners() {
    document.addEventListener('click', (e) => {
      const commentItem = e.target.closest('.comment-item')
      if (commentItem) {
        this.handleCommentInteraction(commentItem)
      }

      const reviewItem = e.target.closest('.review-item')
      if (reviewItem) {
        this.handleReviewInteraction(reviewItem)
      }

      const saveItem = e.target.closest('.save-item')
      if (saveItem) {
        this.handleSaveInteraction(saveItem)
      }
    })
  }

  /**
   * Handle comment item click
   */
  handleCommentInteraction(element) {
    const commentId = element.getAttribute('data-id')
    console.log('[WriterAnalytics] Viewing comment:', commentId)

    // Add highlight effect
    element.style.background = 'rgba(16, 185, 129, 0.1)'
    setTimeout(() => {
      element.style.background = ''
    }, 500)

    // Show notification
    const username = element.querySelector('.font-semibold')?.textContent || 'User'
    this.showNotification(`Comment from ${username}`)
  }

  /**
   * Handle review item click
   */
  handleReviewInteraction(element) {
    const reviewId = element.getAttribute('data-id')
    console.log('[WriterAnalytics] Viewing review:', reviewId)

    // Add highlight effect
    element.style.background = 'rgba(251, 191, 36, 0.1)'
    setTimeout(() => {
      element.style.background = ''
    }, 500)

    // Show notification with rating
    const rating = element.querySelector('.rating-stars')?.textContent || ''
    this.showNotification(`Review: ${rating}`)
  }

  /**
   * Handle save item click
   */
  handleSaveInteraction(element) {
    const saveId = element.getAttribute('data-id')
    console.log('[WriterAnalytics] Viewing save:', saveId)

    // Add highlight effect
    element.style.background = 'rgba(59, 130, 246, 0.1)'
    setTimeout(() => {
      element.style.background = ''
    }, 500)

    // Show notification
    const username = element.querySelector('.font-semibold')?.textContent || 'User'
    this.showNotification(`${username} saved your story!`)
  }

  /**
   * Start polling for new activity
   */
  startPolling() {
    setInterval(() => {
      this.fetchNewActivity()
    }, this.pollInterval)
  }

  /**
   * Fetch new activity from server
   */
  async fetchNewActivity() {
    try {
      // In a real application, this would call an API endpoint
      // GET /api/writer/activity
      console.log('[WriterAnalytics] Polling for new activity...')
    } catch (error) {
      console.error('[WriterAnalytics] Error fetching activity:', error)
    }
  }

  /**
   * Show notification toast
   */
  showNotification(message) {
    const notification = document.createElement('div')
    notification.className =
      'fixed bottom-4 right-4 bg-emerald-600 text-white px-4 py-3 rounded-lg shadow-lg z-50 animate-slideInUp'
    notification.textContent = message
    document.body.appendChild(notification)

    setTimeout(() => {
      notification.style.opacity = '0'
      notification.style.transform = 'translateY(20px)'
      notification.style.transition = 'all 300ms ease'
      setTimeout(() => notification.remove(), 300)
    }, 3000)
  }

  /**
   * Add a new comment to the list
   * 
   * @param {Object} comment - Comment data
   * @param {number} comment.id - Comment ID
   * @param {string} comment.username - User's display name
   * @param {string} comment.handle - User's handle (@username)
   * @param {string} comment.comment - Comment text
   * @param {string} comment.storyTitle - Title of the story
   * @param {string} comment.time - Relative time (e.g., "2h ago")
   */
  addComment(comment) {
    if (!this.commentsList) {
      console.warn('[WriterAnalytics] Comments list element not found')
      return
    }

    const commentHtml = `
      <div class="comment-item p-4 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition border-b border-gray-200 dark:border-gray-700" data-id="${comment.id}">
        <div class="flex items-start justify-between mb-2">
          <div>
            <p class="font-semibold text-gray-900 dark:text-white">${this.escapeHtml(comment.username)}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">${this.escapeHtml(comment.handle)}</p>
          </div>
          <span class="text-xs bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 px-2 py-1 rounded whitespace-nowrap font-medium">${comment.time}</span>
        </div>
        <p class="text-sm text-gray-700 dark:text-gray-300 mb-2">${this.escapeHtml(comment.comment)}</p>
        <p class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">On: ${this.escapeHtml(comment.storyTitle)}</p>
      </div>
    `

    this.commentsList.insertAdjacentHTML('afterbegin', commentHtml)
    this.showNotification(`New comment from ${comment.username}`)
  }

  /**
   * Add a new review to the list
   * 
   * @param {Object} review - Review data
   * @param {number} review.id - Review ID
   * @param {string} review.username - Reviewer's display name
   * @param {string} review.handle - Reviewer's handle
   * @param {number} review.rating - Rating (1-5)
   * @param {string} review.text - Review text
   * @param {string} review.storyTitle - Story title
   * @param {string} review.time - Relative time
   */
  addReview(review) {
    if (!this.reviewsList) {
      console.warn('[WriterAnalytics] Reviews list element not found')
      return
    }

    const stars = '★'.repeat(review.rating) + '☆'.repeat(5 - review.rating)
    const reviewHtml = `
      <div class="review-item p-4 hover:bg-yellow-50 dark:hover:bg-yellow-900/20 transition border-b border-gray-200 dark:border-gray-700" data-id="${review.id}">
        <div class="flex items-start justify-between mb-2">
          <div>
            <p class="font-semibold text-gray-900 dark:text-white">${this.escapeHtml(review.username)}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">${this.escapeHtml(review.handle)}</p>
          </div>
          <span class="text-xs bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 px-2 py-1 rounded whitespace-nowrap font-medium">${review.time}</span>
        </div>
        <div class="text-yellow-500 dark:text-yellow-400 text-sm mb-2 rating-stars font-semibold">${stars}</div>
        <p class="text-sm text-gray-700 dark:text-gray-300 mb-2">${this.escapeHtml(review.text)}</p>
        <p class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">On: ${this.escapeHtml(review.storyTitle)}</p>
      </div>
    `

    this.reviewsList.insertAdjacentHTML('afterbegin', reviewHtml)
    this.showNotification(`New ${review.rating}-star review from ${review.username}`)
  }

  /**
   * Add a new library save to the list
   * 
   * @param {Object} save - Save data
   * @param {number} save.id - Save ID
   * @param {string} save.username - User's display name
   * @param {string} save.handle - User's handle
   * @param {string} save.storyTitle - Story title
   * @param {string} save.time - Relative time
   */
  addLibrarySave(save) {
    if (!this.savesList) {
      console.warn('[WriterAnalytics] Saves list element not found')
      return
    }

    const saveHtml = `
      <div class="save-item p-4 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition border-b border-gray-200 dark:border-gray-700" data-id="${save.id}">
        <div class="flex items-start justify-between mb-2">
          <div>
            <p class="font-semibold text-gray-900 dark:text-white">${this.escapeHtml(save.username)}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">${this.escapeHtml(save.handle)}</p>
          </div>
          <span class="text-xs bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 px-2 py-1 rounded whitespace-nowrap font-medium">${save.time}</span>
        </div>
        <p class="text-sm text-gray-700 dark:text-gray-300">
          Saved <span class="font-semibold text-emerald-600 dark:text-emerald-400">${this.escapeHtml(save.storyTitle)}</span> to library
        </p>
      </div>
    `

    this.savesList.insertAdjacentHTML('afterbegin', saveHtml)
    this.showNotification(`${save.username} saved your story!`)
  }

  /**
   * Update statistics counters
   * 
   * @param {Object} stats - Statistics object
   * @param {number} stats.commentToday - Comments today
   * @param {number} stats.reviewToday - Reviews today
   * @param {number} stats.saveToday - Saves today
   * @param {number} stats.avgRating - Average rating
   */
  updateStats(stats) {
    const elementsMap = {
      'comments-today': 'commentToday',
      'reviews-today': 'reviewToday',
      'saves-today': 'saveToday',
      'avg-rating': 'avgRating',
    }

    for (const [elementId, statKey] of Object.entries(elementsMap)) {
      const element = document.getElementById(elementId)
      if (element && stats[statKey] !== undefined) {
        element.textContent = stats[statKey]
      }
    }
  }

  /**
   * Escape HTML to prevent XSS
   */
  escapeHtml(text) {
    const div = document.createElement('div')
    div.textContent = text
    return div.innerHTML
  }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('comments-list') || 
      document.getElementById('reviews-list') || 
      document.getElementById('saves-list')) {
    window.writerAnalytics = new WriterAnalytics()
  }
})
