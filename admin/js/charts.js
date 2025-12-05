// admin/js/charts.js
document.addEventListener('DOMContentLoaded', function() {
  // Initialize charts if Chart.js is available
  if (typeof Chart === 'undefined') return;
  
  // Example chart for views
  const viewsCtx = document.getElementById('chart-views');
  if (viewsCtx) {
    new Chart(viewsCtx, {
      type: 'line',
      data: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
          label: 'Story Views',
          data: [4200, 3800, 5200, 4800, 6200, 7100, 5900],
          fill: false,
          borderColor: '#7c3aed',
          backgroundColor: 'rgba(124, 58, 237, 0.05)',
          tension: 0.4,
          borderWidth: 2,
          pointBackgroundColor: '#7c3aed',
          pointBorderColor: '#fff',
          pointRadius: 5,
          pointHoverRadius: 7
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: {
              color: '#eceff1',
              font: { size: 12 }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#9aa4b2' },
            grid: { color: '#1f2937' }
          },
          y: {
            ticks: { color: '#9aa4b2' },
            grid: { color: '#1f2937' }
          }
        }
      }
    });
  }

  // User distribution pie chart
  const usersCtx = document.getElementById('chart-users');
  if (usersCtx) {
    new Chart(usersCtx, {
      type: 'doughnut',
      data: {
        labels: ['Active', 'Suspended', 'Banned'],
        datasets: [{
          data: [85, 10, 5],
          backgroundColor: ['#10b981', '#f59e0b', '#ef4444']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: {
              color: '#eceff1',
              font: { size: 12 }
            }
          }
        }
      }
    });
  }

  // Revenue bar chart
  const revenueCtx = document.getElementById('chart-revenue');
  if (revenueCtx) {
    new Chart(revenueCtx, {
      type: 'bar',
      data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [{
          label: 'Donations (GHS)',
          data: [1200, 1900, 1500, 2200, 1800, 2400],
          backgroundColor: '#06b6d4',
          borderRadius: 5
        }]
      },
      options: {
        indexAxis: 'x',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: {
              color: '#eceff1',
              font: { size: 12 }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#9aa4b2' },
            grid: { color: '#1f2937' }
          },
          y: {
            ticks: { color: '#9aa4b2' },
            grid: { color: '#1f2937' }
          }
        }
      }
    });
  }
});
