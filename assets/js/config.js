/**
 * Basic runtime configuration that mirrors the Sneat starter behaviour.
 * It exposes a predictable global object so the Twig templates and any
 * third-party widgets can read palette details without importing modules.
 */
(function () {
  const saved = localStorage.getItem('hb-theme') || 'light';

  const THEME = {
    version: '1.0.0',
    mode: saved,
    colors: {
      primary: '#696cff',
      secondary: '#8592a3',
      success: '#71dd37',
      info: '#03c3ec',
      warning: '#ffab00',
      danger: '#ff3e1d',
      dark: '#233446'
    }
  };

  window.HomeBalanceTheme = THEME;

  const root = document.documentElement;
  root.dataset.bsTheme = THEME.mode;
  root.dataset.theme = THEME.mode;

  Object.entries(THEME.colors).forEach(([name, value]) => {
    root.style.setProperty(`--hb-${name}`, value);
  });

  function bindThemeToggle() {
    const toggle = document.getElementById('theme-toggle');
    const icon   = document.getElementById('theme-icon');
    if (!toggle || toggle._hbThemeBound) return;
    toggle._hbThemeBound = true;

    function applyTheme(mode) {
      root.dataset.bsTheme = mode;
      root.dataset.theme   = mode;
      localStorage.setItem('hb-theme', mode);
      if (window.HomeBalanceTheme) window.HomeBalanceTheme.mode = mode;
      if (icon) {
        icon.className = mode === 'dark'
          ? 'icon-base bx bx-sun icon-md'
          : 'icon-base bx bx-moon icon-md';
      }
    }

    // Sync icon to the already-applied theme
    applyTheme(root.dataset.bsTheme || saved);

    toggle.addEventListener('click', (e) => {
      e.preventDefault();
      const next = root.dataset.bsTheme === 'dark' ? 'light' : 'dark';
      applyTheme(next);
    });
  }

  // Standard page load
  document.addEventListener('DOMContentLoaded', bindThemeToggle);

  // Turbo navigations (soft navigation — DOMContentLoaded doesn't re-fire)
  document.addEventListener('turbo:load',   bindThemeToggle);
  document.addEventListener('turbo:render', bindThemeToggle);
})();
