// Competition Management System - Complete Implementation
const competitionData = {
  currentCompetition: null,
  competitions: [],
  entries: [],
}

document.addEventListener("DOMContentLoaded", () => {
  loadCompetitions()
})

async function loadCompetitions() {
  try {
    const response = await fetch("/api/competitions/get_competitions.php")
    const data = await response.json()

    if (response.ok) {
      competitionData.competitions = data.competitions
      renderCompetitionsList()
    }
  } catch (error) {
    console.error("Error loading competitions:", error)
    showNotification("Failed to load competitions", "error")
  }
}

function renderCompetitionsList() {
  const container = document.getElementById("competitionsList")

  if (competitionData.competitions.length === 0) {
    container.innerHTML = '<p class="text-gray-400">No competitions created yet</p>'
    return
  }

  container.innerHTML = competitionData.competitions
    .map(
      (comp) => `
        <div class="bg-gray-800 border border-gray-700 p-4 rounded">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <h3 class="text-lg font-bold text-white">${comp.title}</h3>
                    <p class="text-sm text-gray-400">${comp.category} • Status: <span class="text-emerald-400">${comp.status}</span></p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-emerald-400">${comp.entry_count}/${comp.entry_limit} entries</p>
                    <button onclick="viewCompetitionDetails(${comp.id})" class="btn-primary text-xs mt-2">
                        Manage
                    </button>
                </div>
            </div>
            <p class="text-xs text-gray-500">
                Ends: ${new Date(comp.end_date).toLocaleDateString()} • Prize: $${comp.prize_pool}
            </p>
        </div>
    `,
    )
    .join("")
}

async function submitCompetition(event) {
  event.preventDefault()

  const title = document.getElementById("compTitle")?.value
  const description = document.getElementById("compDescription")?.value
  const category = document.getElementById("compCategory")?.value
  const startDate = document.getElementById("compStartDate")?.value
  const endDate = document.getElementById("compEndDate")?.value
  const entryLimit = document.getElementById("compEntryLimit")?.value
  const prizePool = document.getElementById("compPrizePool")?.value
  const rules = document.getElementById("compRules")?.value
  const status = document.getElementById("compStatus")?.value

  try {
    const response = await fetch("/api/competitions/create_competition.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        title,
        description,
        category,
        start_date: startDate,
        end_date: endDate,
        entry_limit: entryLimit,
        prize_pool: prizePool,
        rules,
        status,
      }),
    })

    const data = await response.json()

    if (response.ok) {
      showNotification("Competition created successfully!", "success")
      event.target.reset()
      loadCompetitions()
    } else {
      showNotification(data.errors ? data.errors[0] : "Failed to create competition", "error")
    }
  } catch (error) {
    console.error("Error:", error)
    showNotification("Error creating competition", "error")
  }
}

async function viewCompetitionDetails(compId) {
  try {
    const response = await fetch(`/api/competitions/get_entries.php?competition_id=${compId}`)
    const data = await response.json()

    if (response.ok) {
      competitionData.currentCompetition = compId
      competitionData.entries = data.entries
      renderCompetitionEntries(data.entries)
    }
  } catch (error) {
    console.error("Error:", error)
    showNotification("Failed to load entries", "error")
  }
}

function renderCompetitionEntries(entries) {
  const container = document.createElement("div")
  container.className = "mt-6 max-h-96 overflow-y-auto space-y-2"

  if (entries.length === 0) {
    container.innerHTML = '<p class="text-gray-400">No entries yet</p>'
  } else {
    container.innerHTML = entries
      .map(
        (entry) => {
          const statusBadge = entry.status === "submitted" 
            ? `
              <input type="number" id="score-${entry.id}" min="0" max="100" placeholder="Score" class="w-16 bg-gray-600 p-1 rounded text-white text-xs">
              <button onclick="scoreEntry(${entry.id})" class="btn-primary text-xs">Score</button>
            `
            : `
              <span class="text-emerald-400 text-xs font-semibold">Score: ${entry.score}/100</span>
            `
          
          return `
            <div class="bg-gray-700 p-3 rounded flex justify-between items-center">
                <div>
                    <p class="font-semibold text-white">${entry.story_title}</p>
                    <p class="text-xs text-gray-400">by ${entry.author}</p>
                </div>
                <div class="flex gap-2 items-center">
                    ${statusBadge}
                    <button onclick="rejectEntry(${entry.id})" class="btn-danger text-xs">Reject</button>
                </div>
            </div>
          `
        }
      )
      .join("")
  }

  return container
}

async function scoreEntry(entryId) {
  const score = document.getElementById(`score-${entryId}`)?.value

  if (!score || score < 0 || score > 100) {
    showNotification("Please enter a valid score (0-100)", "error")
    return
  }

  try {
    const response = await fetch("/api/competitions/score_entry.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ entry_id: entryId, score }),
    })

    const data = await response.json()

    if (response.ok) {
      showNotification("Entry scored successfully!", "success")
      viewCompetitionDetails(competitionData.currentCompetition)
    }
  } catch (error) {
    showNotification("Error scoring entry", "error")
  }
}

async function rejectEntry(entryId) {
  if (!confirm("Reject this entry?")) {
    return
  }

  try {
    const response = await fetch("/api/competitions/reject_entry.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ entry_id: entryId }),
    })

    const data = await response.json()

    if (response.ok) {
      showNotification("Entry rejected", "success")
      viewCompetitionDetails(competitionData.currentCompetition)
    }
  } catch (error) {
    showNotification("Error rejecting entry", "error")
  }
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
