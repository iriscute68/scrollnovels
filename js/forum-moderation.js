// Forum Moderation System - Complete Implementation
const moderationData = {
  currentPost: null,
  posts: [],
  warnings: {},
}

document.addEventListener("DOMContentLoaded", () => {
  loadPosts()
  startModerationPolling()
})

async function loadPosts(statusFilter = "") {
  try {
    let url = "/api/forum/get_posts.php"
    if (statusFilter) {
      url += "?status=" + statusFilter
    }

    const response = await fetch(url)
    const data = await response.json()

    if (response.ok) {
      moderationData.posts = data.posts
      renderPostsTable()
    }
  } catch (error) {
    console.error("Error loading posts:", error)
    showNotification("Failed to load posts", "error")
  }
}

function renderPostsTable() {
  const tbody = document.getElementById("postsTable")

  if (moderationData.posts.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="p-4 text-center text-gray-400">No posts found</td></tr>'
    return
  }

  tbody.innerHTML = moderationData.posts
    .map(
      (post) => {
        const encodedContent = post.content.substring(0, 50).replace(/'/g, "\\'")
        const encodedAuthor = post.author.replace(/'/g, "\\'")
        return `
        <tr class="border-b border-gray-700 hover:bg-gray-700 transition">
            <td class="p-4">#${post.id}</td>
            <td class="p-4">${post.author}</td>
            <td class="p-4 max-w-xs truncate">${encodedContent}...</td>
            <td class="p-4">
                <span class="status-badge status-${post.status}">${post.status.toUpperCase()}</span>
            </td>
            <td class="p-4 text-sm text-gray-400">${new Date(post.created_at).toLocaleDateString()}</td>
            <td class="p-4">
                <button onclick="openModerationModal(${post.id}, ${JSON.stringify(post.content)}, ${JSON.stringify(post.author)})" 
                        class="btn-primary text-sm">
                    Moderate
                </button>
            </td>
        </tr>
    `}
    )
    .join("")
}

function openModerationModal(postId, content, author) {
  moderationData.currentPost = postId

  document.getElementById("modalPostId").textContent = postId
  document.getElementById("modalContent").textContent = content
  document.getElementById("modalAuthor").textContent = author

  // Reset form
  document.getElementById("moderationAction").value = ""
  document.getElementById("moderationReason").value = ""
  document.getElementById("editedContent").value = content

  openModal("moderationModal")
}

document.addEventListener("change", (e) => {
  if (e.target.id === "moderationAction") {
    const editDiv = document.getElementById("editContentDiv")
    if (e.target.value === "edit") {
      editDiv.style.display = "block"
    } else {
      editDiv.style.display = "none"
    }
  }
})

async function submitModeration() {
  const action = document.getElementById("moderationAction").value
  const reason = document.getElementById("moderationReason").value
  const notes = document.getElementById("moderationNotes").value
  const content = document.getElementById("editedContent").value

  if (!action || !reason) {
    showNotification("Please select action and provide reason", "error")
    return
  }

  try {
    const payload = {
      post_id: moderationData.currentPost,
      action: action,
      reason: reason,
      notes: notes,
    }

    if (action === "edit") {
      payload.content = content
    }

    const response = await fetch("/api/forum/moderate_post.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })

    const data = await response.json()

    if (response.ok) {
      showNotification(`Post ${action}d successfully! User notified.`, "success")

      // Log moderation action
      logModerationAction(action, reason)

      closeModal("moderationModal")
      loadPosts(document.getElementById("statusFilter")?.value || "")
    } else {
      showNotification(data.error || "Moderation action failed", "error")
    }
  } catch (error) {
    console.error("Error:", error)
    showNotification("Error applying moderation", "error")
  }
}

async function deleteComment(commentId, postId) {
  if (!confirm("Are you sure you want to delete this comment?")) {
    return
  }

  try {
    const response = await fetch("/api/forum/delete_comment.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ comment_id: commentId, post_id: postId }),
    })

    const data = await response.json()

    if (response.ok) {
      showNotification("Comment deleted", "success")
      loadComments(postId)
    }
  } catch (error) {
    showNotification("Error deleting comment", "error")
  }
}

async function editComment(commentId, newContent) {
  try {
    const response = await fetch("/api/forum/edit_comment.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ comment_id: commentId, content: newContent }),
    })

    const data = await response.json()

    if (response.ok) {
      showNotification("Comment updated", "success")
    }
  } catch (error) {
    showNotification("Error editing comment", "error")
  }
}

function logModerationAction(action, reason) {
  const log = {
    action: action,
    reason: reason,
    timestamp: new Date().toLocaleString(),
    postId: moderationData.currentPost,
  }

  const logs = JSON.parse(localStorage.getItem("moderationLogs") || "[]")
  logs.push(log)
  localStorage.setItem("moderationLogs", JSON.stringify(logs))
}

async function loadUserWarnings(userId) {
  try {
    const response = await fetch(`/api/admin/get_user_warnings.php?user_id=${userId}`)
    const data = await response.json()

    if (response.ok) {
      moderationData.warnings[userId] = data.warnings
      displayWarnings(userId, data.warnings)
    }
  } catch (error) {
    console.error("Error loading warnings:", error)
  }
}

function displayWarnings(userId, warnings) {
  const container = document.getElementById(`warnings-${userId}`)
  if (!container) return

  container.innerHTML = warnings
    .map(
      (warning) => `
        <div class="warning-card p-3 rounded mb-2 ${
          warning.severity === "permanent_ban"
            ? "bg-red-900/30 border-red-500"
            : warning.severity === "temporary_ban"
              ? "bg-orange-900/30 border-orange-500"
              : "bg-yellow-900/30 border-yellow-500"
        } border">
            <div class="flex justify-between">
                <span class="font-semibold text-white">${warning.reason}</span>
                <span class="text-xs text-gray-400">${new Date(warning.created_at).toLocaleDateString()}</span>
            </div>
            <p class="text-xs text-gray-300 mt-1">Severity: ${warning.severity.replace("_", " ").toUpperCase()}</p>
        </div>
    `,
    )
    .join("")
}

function startModerationPolling() {
  setInterval(() => {
    loadPosts(document.getElementById("statusFilter")?.value || "")
  }, 10000)
}

// Modal utilities
function openModal(modalId) {
  const modal = document.getElementById(modalId)
  if (modal) modal.classList.remove("hidden")
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId)
  if (modal) modal.classList.add("hidden")
}

function showNotification(message, type = "success") {
  const notification = document.createElement("div")
  notification.className = `fixed top-4 right-4 p-4 rounded ${
    type === "success" ? "bg-emerald-500" : "bg-red-500"
  } text-white z-50`
  notification.textContent = message
  document.body.appendChild(notification)

  setTimeout(() => {
    notification.remove()
  }, 3000)
}

// Assuming loadComments is defined elsewhere
async function loadComments(postId) {
  try {
    const response = await fetch(`/api/forum/get_comments.php?post_id=${postId}`)
    const data = await response.json()

    if (response.ok) {
      console.log("Comments loaded:", data.comments)
    }
  } catch (error) {
    console.error("Error loading comments:", error)
  }
}
