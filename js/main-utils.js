// Global Utilities - main.js

/**
 * COMPREHENSIVE SCROLL NOVELS APPLICATION CLASS
 * Full implementation of all critical functionality
 */

class ScrollNovelsApp {
  constructor() {
    this.users = {}
    this.books = {}
    this.chapters = {}
    this.comments = {}
    this.init()
  }

  init() {
    this.setupEventListeners()
    this.loadData()
  }

  setupEventListeners() {
    // Navigation
    document.addEventListener("click", (e) => this.handleNavigation(e))

    // Reading controls
    const fontControls = document.querySelectorAll("[data-font-control]")
    fontControls.forEach((control) => {
      control.addEventListener("change", (e) => this.handleFontChange(e))
    })

    // Comment voting
    document.addEventListener("click", (e) => {
      if (e.target.closest("[data-vote-button]")) {
        this.handleCommentVote(e)
      }
    })

    // Follow author
    document.addEventListener("click", (e) => {
      if (e.target.closest("[data-follow-button]")) {
        this.handleFollowAuthor(e)
      }
    })

    // Bookmark
    document.addEventListener("click", (e) => {
      if (e.target.closest("[data-bookmark-button]")) {
        this.handleBookmark(e)
      }
    })
  }

  handleNavigation(e) {
    const link = e.target.closest('a[href^="/"]')
    if (link && !e.ctrlKey && !e.metaKey) {
      const href = link.getAttribute("href")
      if (href) {
        window.location.href = href
      }
    }
  }

  handleFontChange(e) {
    const fontSize = e.target.value || 16
    const content = document.querySelector(".reading-text, article")
    if (content) {
      content.style.fontSize = fontSize + "px"
      localStorage.setItem("fontSize", fontSize)
    }
  }

  handleCommentVote(e) {
    const button = e.target.closest("[data-vote-button]")
    const type = button.getAttribute("data-vote-type")
    const count = Number.parseInt(button.textContent)

    button.classList.toggle("voted")
    const newCount = button.classList.contains("voted") ? count + 1 : count - 1
    button.textContent = (type === "like" ? "❤️ " : "👎 ") + newCount
  }

  handleFollowAuthor(e) {
    const button = e.target.closest("[data-follow-button]")
    button.classList.toggle("following")
    button.textContent = button.classList.contains("following") ? "Following" : "Follow Author"

    // Save to localStorage
    const authorId = button.getAttribute("data-author-id")
    const following = JSON.parse(localStorage.getItem("following") || "[]")
    if (button.classList.contains("following")) {
      following.push(authorId)
    } else {
      const idx = following.indexOf(authorId)
      if (idx > -1) following.splice(idx, 1)
    }
    localStorage.setItem("following", JSON.stringify(following))
  }

  handleBookmark(e) {
    const button = e.target.closest("[data-bookmark-button]")
    button.classList.toggle("bookmarked")
    button.textContent = button.classList.contains("bookmarked") ? "🔖 Bookmarked" : "🔖 Bookmark"

    const bookId = button.getAttribute("data-book-id")
    const bookmarks = JSON.parse(localStorage.getItem("bookmarks") || "[]")
    if (button.classList.contains("bookmarked")) {
      if (!bookmarks.includes(bookId)) bookmarks.push(bookId)
    } else {
      const idx = bookmarks.indexOf(bookId)
      if (idx > -1) bookmarks.splice(idx, 1)
    }
    localStorage.setItem("bookmarks", JSON.stringify(bookmarks))
  }

  loadData() {
    // Load user preferences
    const savedFontSize = localStorage.getItem("fontSize")
    if (savedFontSize) {
      const content = document.querySelector(".reading-text, article")
      if (content) content.style.fontSize = savedFontSize + "px"
    }

    // Restore bookmarks
    const bookmarks = JSON.parse(localStorage.getItem("bookmarks") || "[]")
    bookmarks.forEach((bookId) => {
      const btn = document.querySelector(`[data-bookmark-button][data-book-id="${bookId}"]`)
      if (btn) {
        btn.classList.add("bookmarked")
        btn.textContent = "🔖 Bookmarked"
      }
    })

    // Restore following status
    const following = JSON.parse(localStorage.getItem("following") || "[]")
    following.forEach((authorId) => {
      const btn = document.querySelector(`[data-follow-button][data-author-id="${authorId}"]`)
      if (btn) {
        btn.classList.add("following")
        btn.textContent = "Following"
      }
    })
  }
}

// Initialize app
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    window.scrollNovelsApp = new ScrollNovelsApp()
  })
} else {
  window.scrollNovelsApp = new ScrollNovelsApp()
}

/**
 * FORM VALIDATION & SUBMISSION
 */

// Validate form using HTML5 validation
function validateForm(formId) {
  const form = document.getElementById(formId)
  if (!form) return false
  return form.checkValidity()
}

// Submit form with fetch and error handling
function submitForm(formId, callback) {
  const form = document.getElementById(formId)
  if (!form) return

  const formData = new FormData(form)
  fetch(form.action || '#', {
    method: form.method || 'POST',
    body: formData
  })
    .then(response => response.json())
    .then(data => {
      if (callback) callback(data)
    })
    .catch(error => {
      console.error('Form submission error:', error)
      showAlert('Failed to submit form', 'error')
    })
}

// Show alert notification
function showAlert(message, type = 'success') {
  const alertDiv = document.createElement('div')
  alertDiv.className = `alert alert-${type}`
  alertDiv.textContent = message
  document.body.appendChild(alertDiv)
  setTimeout(() => alertDiv.remove(), 3000)
}

// Auto-save draft to localStorage
function autoSaveDraft(textareaId, storageKey) {
  const textarea = document.getElementById(textareaId)
  if (!textarea) return

  // Save on input
  textarea.addEventListener('input', () => {
    localStorage.setItem(storageKey, textarea.value)
  })

  // Restore draft on load
  const draft = localStorage.getItem(storageKey)
  if (draft) {
    textarea.value = draft
  }
}

/**
 * THEME MANAGEMENT
 */

// Theme toggle
function toggleTheme() {
  const html = document.documentElement
  const isDark = html.classList.contains('dark')

  if (isDark) {
    html.classList.remove('dark')
    localStorage.setItem('theme', 'light')
  } else {
    html.classList.add('dark')
    localStorage.setItem('theme', 'dark')
  }
}

// Initialize theme on page load
function initTheme() {
  const theme = localStorage.getItem('theme') || 'light'
  if (theme === 'dark') {
    document.documentElement.classList.add('dark')
  }
}

/**
 * API & NOTIFICATIONS
 */

// API helper with error handling
async function apiCall(endpoint, method = 'GET', data = null) {
  const options = {
    method,
    headers: { 'Content-Type': 'application/json' },
  }

  if (data) {
    options.body = JSON.stringify(data)
  }

  const response = await fetch(endpoint, options)
  return await response.json()
}

// Show notification toast
function showNotification(message, type = 'success') {
  const notification = document.createElement('div')
  notification.className = `notification notification-${type}`
  notification.textContent = message
  document.body.appendChild(notification)

  setTimeout(() => {
    notification.remove()
  }, 3000)
}

/**
 * CHAT FUNCTIONALITY
 */

let lastMsgId = 0
let polling = null

// Initialize chat form
function initChat() {
  const sendForm = document.getElementById('send-form')
  if (!sendForm) return

  sendForm.addEventListener('submit', async function(e) {
    e.preventDefault()
    const messageInput = document.getElementById('messageInput')
    if (messageInput && messageInput.value.trim()) {
      const messageDiv = document.createElement('div')
      messageDiv.className = 'flex justify-end'
      messageDiv.innerHTML = `
        <div class="max-w-xs px-4 py-3 rounded-lg bg-emerald-600 text-white rounded-br-none">
            <p class="text-sm">${messageInput.value}</p>
            <p class="text-xs mt-1 text-emerald-100">${new Date().toLocaleTimeString()}</p>
        </div>
      `
      const messagesDiv = document.getElementById('messages')
      if (messagesDiv) {
        messagesDiv.appendChild(messageDiv)
      }
      messageInput.value = ''
    }
  })
}

// Start polling for auto-scroll
function startPolling() {
  if (polling) clearInterval(polling)
  polling = setInterval(() => {
    const messagesDiv = document.getElementById('messages')
    if (messagesDiv) {
      messagesDiv.scrollTop = messagesDiv.scrollHeight
    }
  }, 2000)
}

/**
 * INITIALIZATION
 */

// Initialize on document ready
document.addEventListener('DOMContentLoaded', () => {
  initTheme()
  initChat()
  startPolling()
})

