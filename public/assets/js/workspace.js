'use strict';
(() => {
  const sidebar = document.getElementById('workspaceSidebar');
  const menu = document.getElementById('workspaceMenuToggle');
  const backdrop = document.getElementById('workspaceBackdrop');
  if (sidebar && menu && backdrop) {
    const mobile = window.matchMedia('(max-width: 900px)');
    const toggle = open => {
      sidebar.classList.toggle('open', open);
      sidebar.inert = mobile.matches && !open;
      backdrop.hidden = !open;
      menu.setAttribute('aria-expanded', String(open));
      menu.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
      document.body.classList.toggle('navigation-open', open);
    };
    menu.addEventListener('click', () => {
      const open = !sidebar.classList.contains('open');
      toggle(open);
      if (open) sidebar.querySelector('a[aria-current="page"]')?.focus();
    });
    backdrop.addEventListener('click', () => { toggle(false); menu.focus(); });
    document.getElementById('workspaceClose')?.addEventListener('click', () => { toggle(false); menu.focus(); });
    document.addEventListener('keydown', event => {
      if (!mobile.matches || !sidebar.classList.contains('open') || document.querySelector('dialog[open]')) return;
      if (event.key === 'Escape') {
        toggle(false);
        menu.focus();
      }
      if (event.key === 'Tab') {
        const items = [...sidebar.querySelectorAll('a[href],button:not(:disabled)')];
        const first = items[0];
        const last = items.at(-1);
        if ((event.shiftKey && document.activeElement === first) || (!event.shiftKey && document.activeElement === last)) {
          event.preventDefault();
          (event.shiftKey ? last : first).focus();
        }
      }
    });
    mobile.addEventListener('change', () => {
      const wasOpen = sidebar.classList.contains('open');
      toggle(false);
      if (mobile.matches && (wasOpen || sidebar.contains(document.activeElement))) menu.focus();
    });
    toggle(false);
  }

  const logout = document.querySelector('[data-workspace-logout]');
  const dialog = document.getElementById('workspaceLogout');
  if (logout && dialog && typeof dialog.showModal === 'function') {
    logout.addEventListener('submit', event => {
      if (logout.dataset.confirmed === 'true') return;
      event.preventDefault();
      dialog.showModal();
    });
    document.getElementById('workspaceStay').addEventListener('click', () => dialog.close());
    document.getElementById('workspaceSignOut').addEventListener('click', () => {
      logout.dataset.confirmed = 'true';
      dialog.close();
      logout.requestSubmit();
    });
  }
})();
