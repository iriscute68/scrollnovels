// Tailwind Dark Mode Theme Manager
(function() {
  const htmlElement = document.documentElement;
  const themeKey = 'scroll-novels-theme';
  const defaultTheme = 'light';

  // Initialize theme from localStorage and apply
  function initTheme() {
    const savedTheme = localStorage.getItem(themeKey) || defaultTheme;
    applyTheme(savedTheme);
  }

  // Apply theme by toggling 'dark' class on <html>
  function applyTheme(theme) {
    if (theme === 'dark') {
      htmlElement.classList.add('dark');
    } else {
      htmlElement.classList.remove('dark');
    }
    localStorage.setItem(themeKey, theme);
  }

  // Toggle between light and dark themes
  window.toggleTheme = function() {
    const isDarkMode = htmlElement.classList.contains('dark');
    const newTheme = isDarkMode ? 'light' : 'dark';
    applyTheme(newTheme);
  }

  // Initialize on page load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTheme);
  } else {
    initTheme();
  }
})();
