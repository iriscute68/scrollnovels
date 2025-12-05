// assets/js/achievements.js - Achievement system JavaScript
(() => {
  // Initialize achievement tooltips and interactions
  document.addEventListener('DOMContentLoaded', () => {
    // Add hover effects to achievement cards
    const cards = document.querySelectorAll('.achievement-card');
    
    cards.forEach(card => {
      card.addEventListener('mouseenter', function() {
        this.classList.add('active');
      });
      
      card.addEventListener('mouseleave', function() {
        this.classList.remove('active');
      });

      // Add touch support for mobile
      card.addEventListener('touchstart', function(e) {
        this.classList.toggle('active');
        e.preventDefault();
      });
    });

    // Load additional achievement data via AJAX if needed
    loadAchievementStats();
  });

  // Fetch and display achievement statistics
  async function loadAchievementStats() {
    try {
      const response = await fetch('/ajax/get_achievement_stats.php', {
        credentials: 'same-origin'
      });
      const data = await response.json();
      
      if (data && data.stats) {
        updateStatsDisplay(data.stats);
      }
    } catch (error) {
      console.warn('Could not load achievement stats:', error);
    }
  }

  // Update stats cards with latest data
  function updateStatsDisplay(stats) {
    const unlockedEl = document.getElementById('achievement-unlocked-count');
    const totalEl = document.getElementById('achievement-total-count');
    const progressEl = document.getElementById('achievement-progress-percent');

    if (unlockedEl) unlockedEl.textContent = stats.unlocked || 0;
    if (totalEl) totalEl.textContent = stats.total || 0;
    if (progressEl) progressEl.textContent = stats.progress_percent || 0;
  }

  // Trigger achievement unlock animation
  window.triggerAchievementUnlock = function(achievementId, name, icon) {
    // Show notification toast
    showToast(`🎉 Achievement Unlocked: ${name}`, 'success', 5000);
    
    // Animate the achievement card if visible
    const card = document.querySelector(`[data-achievement-id="${achievementId}"]`);
    if (card) {
      card.classList.add('unlocked');
      card.classList.remove('locked');
      card.style.animation = 'pulse 0.5s ease-in-out';
    }
  };

  // Helper: Show notification toast
  window.showToast = function(message, type = 'info', duration = 3000) {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    toast.style.cssText = `
      position: fixed;
      bottom: 24px;
      right: 24px;
      padding: 16px 20px;
      background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
      color: white;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      font-weight: 500;
      z-index: 9999;
      animation: slideIn 0.3s ease-out;
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
      toast.style.animation = 'slideOut 0.3s ease-out';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  };

  // Add CSS animations
  const style = document.createElement('style');
  style.textContent = `
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }
    @keyframes slideIn {
      from { transform: translateX(400px); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
      from { transform: translateX(0); opacity: 1; }
      to { transform: translateX(400px); opacity: 0; }
    }
  `;
  document.head.appendChild(style);

  // Filter achievements by category/status
  window.filterAchievements = function(filter = 'all') {
    const cards = document.querySelectorAll('.achievement-card');
    
    cards.forEach(card => {
      let show = false;
      
      switch(filter) {
        case 'unlocked':
          show = card.classList.contains('unlocked');
          break;
        case 'locked':
          show = card.classList.contains('locked');
          break;
        default:
          show = true;
      }
      
      card.style.display = show ? '' : 'none';
    });
  };
})();
