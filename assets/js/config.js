/**
 * Basic runtime configuration that mirrors the Sneat starter behaviour.
 * It exposes a predictable global object so the Twig templates and any
 * third-party widgets can read palette details without importing modules.
 */
(function () {
  const THEME = {
    version: '1.0.0',
    mode: 'light',
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
  root.dataset.theme = THEME.mode;

  Object.entries(THEME.colors).forEach(([name, value]) => {
    root.style.setProperty(`--hb-${name}`, value);
  });
})();
