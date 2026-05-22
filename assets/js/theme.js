(function () {
  const storageKey = 'raspored-theme';
  const root = document.documentElement;
  const media = window.matchMedia('(prefers-color-scheme: light)');

  function storedTheme() {
    const value = localStorage.getItem(storageKey);
    return value === 'light' || value === 'dark' ? value : '';
  }

  function systemTheme() {
    return media.matches ? 'light' : 'dark';
  }

  function activeTheme() {
    return storedTheme() || systemTheme();
  }

  function applyTheme(theme) {
    root.dataset.theme = theme;
    root.style.colorScheme = theme;
    updateThemeColor(theme);
    document
      .querySelectorAll('[data-theme-toggle]')
      .forEach((button) => {
        const next = theme === 'light' ? 'dark' : 'light';
        button.dataset.themeState = theme;
        button.setAttribute('aria-label', `Tema: ${theme}. Prebaci na ${next}.`);
        button.setAttribute('title', `Tema: ${theme}`);
        button.textContent = theme === 'light' ? '☀️' : '🌙';
      });
  }

  function toggleTheme() {
    const next = activeTheme() === 'light' ? 'dark' : 'light';
    localStorage.setItem(storageKey, next);
    applyTheme(next);
  }

  function updateThemeColor(theme) {
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
      meta.setAttribute('content', theme === 'light' ? '#f7f8fb' : '#0f0f1a');
    }
  }

  applyTheme(activeTheme());

  document.addEventListener('DOMContentLoaded', () => {
    applyTheme(activeTheme());
    document
      .querySelectorAll('[data-theme-toggle]')
      .forEach((button) => button.addEventListener('click', toggleTheme));
  });

  media.addEventListener('change', () => {
    if (!storedTheme()) {
      applyTheme(systemTheme());
    }
  });
})();
