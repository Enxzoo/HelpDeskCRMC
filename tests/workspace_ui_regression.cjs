const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const cssom = require('rrweb-cssom');

module.exports = ({ pages, registrationFixture }) => {
  let checks = 0;
  const check = (condition, message) => { assert.ok(condition, message); checks++; };
  const publicRoot = path.resolve(__dirname, '../public');
  const parsedStyles = new Set();
  const workspaceSource = fs.readFileSync(path.join(publicRoot, 'assets/js/workspace.js'), 'utf8');
  const profileSource = fs.readFileSync(path.join(publicRoot, 'assets/js/student_profile.js'), 'utf8');
  for (const [page, html] of Object.entries(pages)) {
    const dom = new JSDOM(html, { url: 'http://helpdesk.test/' + page, runScripts: 'outside-only' });
    const { window } = dom;
    const { document } = window;
    const $ = id => document.getElementById(id);
    try {
      const ids = [...document.querySelectorAll('[id]')].map(node => node.id);
      check(new Set(ids).size === ids.length, page + ' has duplicate element IDs.');
      const styles = [...document.querySelectorAll('link[rel=stylesheet]')].map(node => node.getAttribute('href'));
      const studentPage = page.startsWith('student_profile.php') || page === 'dashboard_student.php';
      check(styles.some(href => href.includes('shared/tokens.css')), page + ' omitted the shared palette.');
      if (studentPage) {
        check(styles.some(href => href.includes('student/sidebar.css')) && styles.some(href => href.includes('student/profile.css')), page + ' omitted the original student layout.');
        check(!styles.some(href => href.includes('shared/workspace.css') || href.includes('shared/base.css') || href.includes('accounts/')), page + ' loaded the replacement workspace layout.');
        check(styles.some(href => href.includes('fonts.googleapis.com') && href.includes('Inter') && !href.includes('Jakarta')), page + ' changed the original student font.');
      } else {
        check(styles.some(href => href.includes('shared/forms.css')), page + ' omitted the shared form controls.');
        const font = page === 'register.php' ? 'Inter' : 'Jakarta';
        check(styles.some(href => href.includes('fonts.googleapis.com') && href.includes(font)), page + ' omitted its existing page font.');
      }
      for (const node of document.querySelectorAll('link[rel=stylesheet],script[src],img[src]')) {
        const reference = node.getAttribute('href') || node.getAttribute('src');
        if (/^https?:/.test(reference)) continue;
        const filename = path.resolve(publicRoot, decodeURIComponent(reference.split('?')[0]));
        check(filename.startsWith(publicRoot + path.sep) && fs.existsSync(filename), page + ' has a missing asset: ' + reference);
        if (filename.endsWith('.css') && !parsedStyles.has(filename)) {
          cssom.parse(fs.readFileSync(filename, 'utf8'));
          parsedStyles.add(filename);
        }
        if (filename.endsWith('.css')) {
          const style = document.createElement('style');
          style.textContent = fs.readFileSync(filename, 'utf8');
          document.head.append(style);
        }
      }
      const search = document.querySelector('.search-field input');
      if (search) {
        const style = window.getComputedStyle(search);
        check(style.borderTopWidth === '0px' && style.marginTop === '0px', page + ' applied nested form chrome inside the search field.');
      }
      const sidebar = $('workspaceSidebar');
      if (sidebar) {
        const mobile = { matches: false, addEventListener(type, callback) { this.onChange = callback; } };
        window.matchMedia = () => mobile;
        window.HTMLDialogElement.prototype.showModal = function () { this.setAttribute('open', ''); };
        window.HTMLDialogElement.prototype.close = function () { this.removeAttribute('open'); };
        window.eval(workspaceSource);
        const menu = $('workspaceMenuToggle');
        const backdrop = $('workspaceBackdrop');
        check(!sidebar.inert && backdrop.hidden, page + ' hid desktop navigation.');
        const current = sidebar.querySelector('[aria-current=page]');
        check(current && sidebar.querySelectorAll('[aria-current=page]').length === 1, page + ' has no unique active navigation.');
        mobile.matches = true; mobile.onChange();
        check(sidebar.inert && menu.getAttribute('aria-expanded') === 'false', page + ' left mobile navigation focusable when closed.');
        menu.click();
        check(sidebar.classList.contains('open') && !backdrop.hidden && !sidebar.inert, page + ' cannot open mobile navigation.');
        check(menu.getAttribute('aria-expanded') === 'true' && document.activeElement === current, page + ' lost navigation focus/state.');
        const items = [...sidebar.querySelectorAll('a[href],button:not(:disabled)')];
        items.at(-1).focus();
        const tab = new window.KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
        document.activeElement.dispatchEvent(tab);
        check(tab.defaultPrevented && document.activeElement === items[0], page + ' lets focus leave the open drawer.');
        items[0].dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true, cancelable: true }));
        check(document.activeElement === items.at(-1), page + ' failed reverse drawer focus.');
        document.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape' }));
        check(sidebar.inert && backdrop.hidden && document.activeElement === menu, page + ' cannot close navigation with Escape.');
        menu.click(); backdrop.click();
        check(!sidebar.classList.contains('open') && !document.body.classList.contains('navigation-open'), page + ' backdrop failed to release scroll lock.');
        menu.click(); $('workspaceClose').click();
        check(backdrop.hidden && document.activeElement === menu, page + ' close button failed.');
        menu.click(); mobile.matches = false; mobile.onChange();
        check(!sidebar.inert && backdrop.hidden && !sidebar.classList.contains('open'), page + ' failed desktop resize.');
        const logout = sidebar.querySelector('[data-workspace-logout]');
        check(logout.querySelector('input[name=_csrf_token]') && logout.method === 'post' && logout.getAttribute('action') === 'logout.php', page + ' omitted secure sign-out.');
        const submit = () => logout.dispatchEvent(new window.Event('submit', { cancelable: true }));
        check(!submit() && $('workspaceLogout').open, page + ' bypassed sign-out confirmation.');
        $('workspaceStay').click();
        check(!$('workspaceLogout').open && !logout.dataset.confirmed, page + ' cannot cancel sign-out.');
        submit();
        let submitted = false;
        logout.requestSubmit = () => { submitted = submit(); };
        $('workspaceSignOut').click();
        check(submitted && !$('workspaceLogout').open, page + ' confirmed sign-out did not submit.');
      }
      if (page === 'register.php') {
        window.HTMLDialogElement.prototype.showModal = function () { this.setAttribute('open', ''); };
        window.HTMLDialogElement.prototype.close = function () { this.removeAttribute('open'); };
        window.eval(profileSource);
        const form = $('studentRegistration');
        check(window.getComputedStyle($('password')).paddingRight === '48px', 'Password toggle overlaps the input text.');
        check(document.querySelector('.login-card') && form.closest('.register-container'), 'Registration does not reuse the sign-in card layout.');
        check(document.querySelector('header .navbar') && document.querySelector('.navbar-cta a[href="login.php"]'), 'Registration does not reuse the public sign-in navigation.');
        const sections = [...form.querySelectorAll('fieldset')];
        check(sections.length === 3 && sections[2].nextElementSibling.classList.contains('registration-agreements'), 'Registration agreements are not after account security.');
        check(form.querySelectorAll('[name=email]').length === 1 && $('email').closest('[data-registration-step]').dataset.registrationStep === '2', 'Registration email is not exclusively in the security step.');
        const agreements = [...form.querySelectorAll('.registration-agreements input[type=checkbox]')];
        check(agreements.length === 2 && agreements.every(input => input.required && !input.checked), 'Registration agreements are missing, optional, or preselected.');
        check(form.querySelector('a[href="terms.php"]') && form.querySelector('a[href="student_privacy.php"]'), 'Registration omitted the legal document links.');
        const toggles = [...form.querySelectorAll('[data-password-toggle]')];
        check(toggles.length === 2, 'Registration is missing a password visibility control.');
        for (const toggle of toggles) {
          const input = $(toggle.dataset.passwordToggle);
          check(toggle.type === 'button' && toggle.getAttribute('aria-controls') === input.id, 'Password visibility control can submit the form or targets the wrong field.');
          toggle.click();
          check(input.type === 'text' && toggle.getAttribute('aria-label') === 'Hide password', 'Password toggle cannot reveal the password.');
          toggle.click();
          check(input.type === 'password' && toggle.getAttribute('aria-label') === 'Show password', 'Password toggle cannot hide the password.');
        }
        for (const [key, value] of Object.entries(registrationFixture)) {
          const field = form.elements[key];
          if (!field) continue;
          if (field.type === 'checkbox') field.checked = true;
          else field.value = value;
          field.dispatchEvent(new window.Event('change'));
        }
        check(form.checkValidity(), 'Registration fixture cannot complete the form.');
        for (const input of agreements) {
          input.checked = false;
          check(!form.checkValidity(), 'Registration can bypass ' + input.name + '.');
          input.checked = true;
        }
        check(form.dispatchEvent(new window.Event('submit', { cancelable: true })) && !document.querySelector('#registrationReview'), 'Registration still requires an extra approval/review step.');
      }
    } finally { window.close(); }
  }
  console.log('Workspace UI regression assertions passed: ' + checks);
  return checks;
};
