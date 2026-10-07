'use strict';
(() => {
  const script = document.currentScript;
  if (!script?.dataset.sourceRoot) return;
  const root = script.dataset.sourceRoot;
  const base = new URL('../../', script.src);
  const ownPath = new URL(script.src).pathname;
  const escape = value => value.replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));

  function source() {
    for (const frame of new Error().stack?.split('\n') || []) {
      const location = frame.match(/(https?:\/\/[^\s)]+):(\d+):(\d+)\)?$/);
      if (!location) continue;
      const url = new URL(location[1]);
      if (url.origin !== base.origin || url.pathname === ownPath || !url.pathname.startsWith(base.pathname + 'assets/js/')) continue;
      return { file: root + '/' + decodeURIComponent(url.pathname.slice(base.pathname.length)), line: Number(location[2]), column: Number(location[3]) };
    }
    return null;
  }

  window.HelpdeskLocator = {
    attributes() {
      const found = source();
      return found ? ` data-php-file="${escape(found.file)}" data-php-line="${found.line}" data-php-column="${found.column}"` : '';
    },
    createElement(...args) {
      const element = document.createElement(...args);
      const found = source();
      if (found) {
        element.dataset.phpFile = found.file;
        element.dataset.phpLine = String(found.line);
        element.dataset.phpColumn = String(found.column);
      }
      return element;
    },
  };
})();
