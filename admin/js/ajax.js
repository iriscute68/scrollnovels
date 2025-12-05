// admin/js/ajax.js
async function postJSON(url, data) {
  const resp = await fetch(url, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(data)
  });
  return resp.json();
}

// Delete story
async function deleteStory(id) {
  if (!confirm('Are you sure you want to delete this story? This action cannot be undone.')) return;
  const res = await postJSON('ajax/delete_story.php', { id });
  if (res.success) {
    showNotification('Story deleted successfully!', 'success');
    setTimeout(() => location.reload(), 1500);
  } else {
    showNotification(res.error || 'Failed to delete story', 'danger');
  }
}

// Toggle user status
async function toggleUser(id) {
  if (!confirm('Toggle user status?')) return;
  const res = await postJSON('ajax/toggle_user.php', { id });
  if (res.success) {
    showNotification('User status updated!', 'success');
    setTimeout(() => location.reload(), 1500);
  } else {
    showNotification(res.error || 'Failed to toggle user', 'danger');
  }
}

// Approve story
async function approveStory(id) {
  if (!confirm('Approve this story for publishing?')) return;
  const res = await postJSON('ajax/approve_story.php', { id });
  if (res.success) {
    showNotification('Story approved and published!', 'success');
    setTimeout(() => location.reload(), 1500);
  } else {
    showNotification(res.error || 'Failed to approve story', 'danger');
  }
}

// Export CSV
async function exportCSV(type) {
  window.location.href = `ajax/export_csv.php?type=${type}`;
}
