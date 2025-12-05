/**
 * ACHIEVEMENTS & POINTS SYSTEM
 * Comprehensive system for tracking user achievements, points, and comments
 */

class AchievementsSystem {
  constructor(config = {}) {
    this.apiBase = config.apiBase || "/api"
    this.userId = config.userId || "1"
    this.pollInterval = config.pollInterval || 10000
    this.init()
  }

  init() {
    console.log("[Achievements System] Initializing...")
    this.loadAchievements()
    this.loadUserPoints()
    this.loadComments()
    this.attachEventListeners()
    this.startPolling()
  }

  // ============================
  // POINTS SYSTEM
  // ============================

  async trackPoints(action, details = {}) {
    try {
      const response = await fetch(`${this.apiBase}/achievements/track-points.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          user_id: this.userId,
          action,
          ...details,
        }),
      })

      const data = await response.json()
      if (data.success) {
        this.showNotification(`+${data.data.points_added} points for ${action}`, "success")

        if (data.data.new_achievements && data.data.new_achievements.length > 0) {
          for (let achievement of data.data.new_achievements) {
            this.showAchievementNotification(achievement)
          }
        }

        this.loadUserPoints()
        this.loadAchievements()
        return data.data
      } else {
        this.showNotification(data.message, "error")
      }
    } catch (error) {
      console.error("[Achievements] Track points error:", error)
      this.showNotification("Error tracking points", "error")
    }
  }

  async loadUserPoints() {
    try {
      const response = await fetch(`${this.apiBase}/achievements/get-user-achievements.php?user_id=${this.userId}`)
      const data = await response.json()

      if (data.success) {
        const pointsData = data.data.user_points
        this.updatePointsDisplay(pointsData)
      }
    } catch (error) {
      console.error("[Achievements] Load points error:", error)
    }
  }

  updatePointsDisplay(points) {
    const display = document.querySelector(".points-value.balance")
    if (display) {
      display.textContent = points.balance.toLocaleString()
    }

    const earned = document.querySelector(".points-value.earned-total")
    if (earned) {
      earned.textContent = points.total_earned.toLocaleString()
    }
  }

  // ============================
  // ACHIEVEMENTS SYSTEM
  // ============================

  async loadAchievements() {
    try {
      const response = await fetch(`${this.apiBase}/achievements/get-user-achievements.php?user_id=${this.userId}`)
      const data = await response.json()

      if (data.success) {
        this.renderAchievements(data.data.achievements)
      }
    } catch (error) {
      console.error("[Achievements] Load error:", error)
    }
  }

  renderAchievements(achievements) {
    const container = document.querySelector(".achievements-grid")
    if (!container) return

    container.innerHTML = achievements
      .map(
        (achievement) => `
            <div class="achievement-card ${achievement.earned ? "earned" : ""}" 
                 title="${achievement.description}">
                <div class="earned-badge">🏆</div>
                <div class="achievement-icon">${achievement.icon}</div>
                <div class="achievement-name">${achievement.name}</div>
                <div class="achievement-description">${achievement.description}</div>
                <div class="achievement-points">+${achievement.points_reward} pts</div>
                <div class="achievement-status ${achievement.earned ? "earned" : "locked"}">
                    ${
                      achievement.earned
                        ? `✓ Earned on ${new Date(achievement.earned_at).toLocaleDateString()}`
                        : "Locked"
                    }
                </div>
            </div>
        `,
      )
      .join("")
  }

  showAchievementNotification(achievement) {
    const container = document.querySelector("[data-notification-container]") || document.body
    const notification = document.createElement("div")
    notification.className = "notification success"
    notification.innerHTML = `
            <div class="notification-message">🏆 Achievement Unlocked! ${achievement.name} - +${achievement.points_reward} points</div>
        `
    container.insertBefore(notification, container.firstChild)
    setTimeout(() => notification.remove(), 5000)
  }

  // ============================
  // COMMENTS SYSTEM
  // ============================

  async loadComments(postId = null) {
    if (!postId) return

    try {
      const response = await fetch(`${this.apiBase}/forum/get-comments.php?post_id=${postId}&limit=50`)
      const data = await response.json()

      if (data.success) {
        this.renderComments(data.data.comments)
      }
    } catch (error) {
      console.error("[Comments] Load error:", error)
    }
  }

  renderComments(comments) {
    const container = document.querySelector(".comments-list")
    if (!container) return

    if (comments.length === 0) {
      container.innerHTML = '<p style="color: #94a3b8; text-align: center;">No comments yet. Be the first!</p>'
      return
    }

    container.innerHTML = comments
      .map(
        (comment) => `
            <div class="comment-item">
                <div class="comment-header">
                    <span class="comment-author">${this.escapeHtml(comment.user_name)}</span>
                    <span class="comment-time">${comment.created_at_formatted}</span>
                </div>
                <div class="comment-content">${this.escapeHtml(comment.content)}</div>
                <div style="display: flex; gap: 16px; padding-top: 12px; border-top: 1px solid #334155;">
                    <button class="comment-action-btn" onclick="achievementsSystem.likeComment(${comment.id})">
                        ❤️ Like
                    </button>
                    <button class="comment-action-btn" onclick="achievementsSystem.replyComment(${comment.id})">
                        💬 Reply
                    </button>
                </div>
            </div>
        `,
      )
      .join("")
  }

  async addComment(postId, content) {
    if (!content.trim()) {
      this.showNotification("Comment cannot be empty", "error")
      return
    }

    try {
      const response = await fetch(`${this.apiBase}/forum/add-comment.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          post_id: postId,
          user_id: this.userId,
          user_name: document.querySelector("[data-user-name]")?.textContent || "Anonymous",
          content,
        }),
      })

      const data = await response.json()
      if (data.success) {
        this.showNotification("Comment posted! +5 points", "success")
        this.loadComments(postId)
        this.trackPoints("comment", { reference: data.data.comment_id })
        return true
      } else {
        this.showNotification(data.message, "error")
      }
    } catch (error) {
      console.error("[Comments] Add error:", error)
      this.showNotification("Error posting comment", "error")
    }
  }

  likeComment(commentId) {
    this.showNotification("❤️ You liked this comment", "success")
    this.trackPoints("comment_like", { reference: commentId })
  }

  replyComment(commentId) {
    const form = document.querySelector(".comment-form textarea")
    if (form) {
      form.focus()
      form.value = `@comment-${commentId} `
    }
  }

  // ============================
  // FORM HANDLERS
  // ============================

  attachEventListeners() {
    const form = document.querySelector(".comment-form")
    if (form) {
      const textarea = form.querySelector("textarea")
      const submitBtn = form.querySelector(".comment-btn")

      if (textarea && submitBtn) {
        textarea.addEventListener("input", (e) => {
          const count = e.target.value.length
          const counter = form.querySelector(".char-count")
          if (counter) counter.textContent = `${count}/5000`

          submitBtn.disabled = count === 0 || count > 5000
        })

        submitBtn.addEventListener("click", () => {
          const postId = document.querySelector("[data-post-id]")?.dataset.postId
          if (postId) {
            this.addComment(postId, textarea.value)
            textarea.value = ""
            textarea.dispatchEvent(new Event("input"))
          }
        })
      }
    }
  }

  // ============================
  // POLLING
  // ============================

  startPolling() {
    setInterval(() => {
      this.loadUserPoints()
      this.loadAchievements()
    }, this.pollInterval)
  }

  // ============================
  // UTILITIES
  // ============================

  showNotification(message, type = "info") {
    const container = document.querySelector("[data-notification-container]") || document.body
    const notification = document.createElement("div")
    notification.className = `notification ${type}`
    notification.innerHTML = `<div class="notification-message">${message}</div>`
    container.insertBefore(notification, container.firstChild)
    setTimeout(() => notification.remove(), 4000)
  }

  escapeHtml(text) {
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    }
    return text.replace(/[&<>"']/g, (m) => map[m])
  }
}

// Initialization
document.addEventListener("DOMContentLoaded", () => {
  window.achievementsSystem = new AchievementsSystem({
    userId: document.querySelector("[data-user-id]")?.dataset.userId || "1",
    apiBase: "/api",
  })

  console.log("[Achievements System] Ready")
})
