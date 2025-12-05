// Admin Ticket Management - Complete Implementation
const adminTicketData = {
  currentTicket: null,
  tickets: [],
  replies: [],
}

document.addEventListener("DOMContentLoaded", () => {
  loadAdminTickets()
  startAdminPolling()
})

async function loadAdminTickets(statusFilter = "", priorityFilter = "") {
  try {
    let url = "/api/admin/get_all_tickets.php"
    const params = new URLSearchParams()

    if (statusFilter) params.append("status", statusFilter)
    if (priorityFilter) params.append("priority", priorityFilter)

    if (params.toString()) {
      url += "?" + params.toString()
    }

    const response = await fetch(url)
    const data = await response.json()

    if (response.ok) {
      adminTicketData.tickets = data.tickets
      renderAdminTicketsTable()
    }
  } catch (error) {
    console.error("Error loading tickets:", error)
  }
}

function renderAdminTicketsTable() {
  const tbody = document.getElementById("ticketsTable")

  if (adminTicketData.tickets.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-gray-400">No tickets</td></tr>'
    return
  }

  tbody.innerHTML = adminTicketData.tickets
    .map(
      (ticket) => `
        <tr class="border-b border-gray-700 hover:bg-gray-700 transition cursor-pointer" 
            onclick="openAdminTicketModal(${ticket.id})">
            <td class="p-4">#${ticket.id}</td>
            <td class="p-4">${ticket.subject}</td>
            <td class="p-4">${ticket.user_name}</td>
            <td class="p-4">
                <span class="priority-badge priority-${ticket.priority}">${ticket.priority.toUpperCase()}</span>
            </td>
            <td class="p-4">
                <span class="status-badge status-${ticket.status}">${ticket.status.toUpperCase()}</span>
            </td>
            <td class="p-4 text-sm text-gray-400">${new Date(ticket.created_at).toLocaleDateString()}</td>
            <td class="p-4">
                <button onclick="openAdminTicketModal(${ticket.id}); event.stopPropagation();" class="btn-primary text-xs">
                    Reply
                </button>
            </td>
        </tr>
    `,
    )
    .join("")
}

async function openAdminTicketModal(ticketId) {
  try {
    const response = await fetch(`/api/admin/get_ticket_details.php?id=${ticketId}`)
    const data = await response.json()

    if (response.ok) {
      adminTicketData.currentTicket = ticketId
      const ticket = data.ticket

      document.getElementById("modalTicketId").textContent = ticketId
      document.getElementById("modalSubject").textContent = ticket.subject
      document.getElementById("modalUser").textContent = ticket.user_name
      document.getElementById("modalStatus").value = ticket.status
      document.getElementById("modalPriority").textContent = ticket.priority.toUpperCase()
      document.getElementById("modalMessage").textContent = ticket.description

      // Load replies
      renderAdminReplies(data.replies)

      openModal("ticketModal")
    }
  } catch (error) {
    console.error("Error:", error)
    showNotification("Failed to load ticket", "error")
  }
}

function renderAdminReplies(replies) {
  const container = document.getElementById("adminReplies")

  container.innerHTML = replies
    .map(
      (reply) => `
        <div class="p-3 rounded ${reply.is_admin_reply ? "bg-emerald-900/30 border-l-4 border-emerald-500" : "bg-gray-700"}">
            <div class="flex justify-between mb-1">
                <span class="font-semibold text-white">${reply.is_admin_reply ? "👨‍💼 Admin Response" : "👤 User"}</span>
                <span class="text-xs text-gray-400">${new Date(reply.created_at).toLocaleDateString()}</span>
            </div>
            <p class="text-white text-sm">${reply.message}</p>
        </div>
    `,
    )
    .join("")
}

async function submitAdminReply(event) {
  event.preventDefault()

  const message = document.getElementById("adminReplyText").value
  const newStatus = document.getElementById("modalStatus").value

  try {
    const response = await fetch("/api/admin/reply_ticket_admin.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        ticket_id: adminTicketData.currentTicket,
        message: message,
        status: newStatus,
      }),
    })

    const data = await response.json()

    if (response.ok) {
      showNotification("Reply sent! User notified.", "success")
      document.getElementById("adminReplyText").value = ""
      openAdminTicketModal(adminTicketData.currentTicket)
    } else {
      showNotification(data.error || "Failed to send reply", "error")
    }
  } catch (error) {
    console.error("Error:", error)
    showNotification("Error sending reply", "error")
  }
}

async function updateTicketStatus() {
  const newStatus = document.getElementById("modalStatus").value

  try {
    const response = await fetch("/api/admin/update_ticket_status.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        ticket_id: adminTicketData.currentTicket,
        status: newStatus,
      }),
    })

    const data = await response.json()

    if (response.ok) {
      showNotification("Ticket status updated", "success")
      loadAdminTickets(document.getElementById("statusFilter")?.value || "", document.getElementById("priorityFilter")?.value || "")
    }
  } catch (error) {
    showNotification("Error updating status", "error")
  }
}

function filterAdminTickets() {
  const status = document.getElementById("statusFilter").value
  const priority = document.getElementById("priorityFilter").value
  loadAdminTickets(status, priority)
}

function startAdminPolling() {
  setInterval(() => {
    loadAdminTickets(document.getElementById("statusFilter")?.value || "", document.getElementById("priorityFilter")?.value || "")
  }, 30000)
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
