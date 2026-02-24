/**
 * Lightweight behaviour glue for the Sneat-derived pages.
 * Handles menu toggles, password visibility toggles, and provides
 * a tiny event bus for page-specific widgets.
 */
(function () {
  const ready = (cb) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', cb, { once: true });
    } else {
      cb();
    }
  };

  const togglePasswordVisibility = () => {
    document.querySelectorAll('.form-password-toggle').forEach((wrapper) => {
      const input = wrapper.querySelector('input[type="password"], input[data-hb-password]');
      const trigger = wrapper.querySelector('.input-group-text');

      if (!input || !trigger) {
        return;
      }

      trigger.addEventListener('click', () => {
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        const icon = trigger.querySelector('i');
        if (icon) {
          icon.classList.toggle('bx-hide', !isPassword);
          icon.classList.toggle('bx-show', isPassword);
        }
      });
    });
  };

  const bindMenuToggle = () => {
    const toggler = document.querySelector('[data-hb-toggle="layout-menu"]');
    if (!toggler) {
      return;
    }

    const layout = document.querySelector('.layout-menu');
    if (!layout) {
      return;
    }

    toggler.addEventListener('click', () => {
      layout.classList.toggle('active');
    });
  };

  const bindSidebarSubmenuToggles = () => {
    document.querySelectorAll('.menu-link.menu-toggle').forEach((toggleLink) => {
      toggleLink.addEventListener('click', (event) => {
        event.preventDefault();
        const parent = toggleLink.closest('.menu-item');
        const submenu = parent ? parent.querySelector(':scope > .menu-sub') : null;

        if (!parent || !submenu) {
          return;
        }

        const isOpen = parent.classList.contains('open');
        parent.classList.toggle('open', !isOpen);
        submenu.style.display = isOpen ? '' : 'block';
      });
    });
  };

  ready(() => {
    togglePasswordVisibility();
    bindMenuToggle();
    bindSidebarSubmenuToggles();
  });
})();
