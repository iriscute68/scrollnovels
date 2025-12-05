// admin/js/main.js
function confirmAction(msg, cb) {
  if (confirm(msg)) cb();
}

function toggleDark() {
  document.documentElement.classList.toggle('dark');
  localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

function showNotification(message, type = 'info') {
  const alertClass = type === 'success' ? 'alert-success' : type === 'danger' ? 'alert-danger' : 'alert-info';
  const alertHtml = `<div class="alert ${alertClass}">${message}</div>`;
  const container = document.querySelector('main') || document.body;
  container.insertAdjacentHTML('afterbegin', alertHtml);
  
  setTimeout(() => {
    const alert = container.querySelector('.alert');
    if (alert) alert.remove();
  }, 3000);
}

function formatNumber(num) {
  return new Intl.NumberFormat('en-US').format(num);
}

document.addEventListener('DOMContentLoaded', function() {
  // Auto-remove alerts after 5 seconds
  document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => el.remove(), 5000);
  });
});
