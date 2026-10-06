const assert = require('node:assert/strict');
const path = require('node:path');

module.exports = async ({ pages, baseUrl, fixture }) => {
  if (process.env.HELPDESK_PROFILE_VISUAL !== '1') return 0;
  const { chromium } = require(process.env.HELPDESK_PLAYWRIGHT_PATH || 'playwright');
  const browser = await chromium.launch({ headless: true,
    ...(process.env.HELPDESK_CHROME_PATH ? { executablePath: process.env.HELPDESK_CHROME_PATH } : {}) });
  let checks = 0;
  const check = (condition, message) => { assert.ok(condition, message); checks++; };
  try {
    for (const name of ['register.php', 'terms.php', 'student_privacy.php']) {
      for (const width of [1440, 375, 320]) {
        const context = await browser.newContext({ viewport: { width, height: 900 }, serviceWorkers: 'block' });
        try {
          const page = await context.newPage();
          const errors = [];
          page.on('pageerror', error => errors.push(error.message));
          await page.route(new URL(name, baseUrl).href, route => route.fulfill({ contentType: 'text/html', body: pages[name] }));
          await page.route('https://fonts.googleapis.com/**', route => route.abort());
          await page.goto(new URL(name, baseUrl).href, { waitUntil: 'networkidle' });
          await page.screenshot({ path: path.join(__dirname, '.runtime', `legal-${name.replace('.php', '')}-${width}.png`), fullPage: true });
          const layout = await page.evaluate(() => ({
            pageOverflow: document.documentElement.scrollWidth > innerWidth,
            overflow: [...document.querySelectorAll('main h1,main h2,main p,main label,main input:not([type=hidden]),main select,main button,main a')]
              .filter(node => node.getClientRects().length).filter(node => {
                const rect = node.getBoundingClientRect();
                return rect.left < -1 || rect.right > innerWidth + 1;
              }).map(node => node.textContent.trim().slice(0, 50)),
            brokenImages: [...document.images].filter(img => !img.complete || !img.naturalWidth).length
          }));
          check(!layout.pageOverflow && layout.overflow.length === 0, `${name} at ${width}px: overflowing content: ${layout.overflow.join(', ')}`);
          check(layout.brokenImages === 0, `${name} at ${width}px: broken assets.`);
          if (name === 'register.php') {
            check(!await page.locator('[name=terms_accepted]').isChecked() && !await page.locator('[name=privacy_accepted]').isChecked(), 'Agreements were preselected.');
            await page.evaluate(details => {
              const form = document.getElementById('studentRegistration');
              for (const [key, value] of Object.entries(details)) {
                const field = form.elements[key];
                if (!field || field.type === 'checkbox') continue;
                field.value = value;
                field.dispatchEvent(new Event('change'));
              }
            }, fixture);
            check(!await page.locator('#studentRegistration').evaluate(form => form.checkValidity()), 'Signup permits missing agreements.');
            await page.locator('[name=terms_accepted]').check();
            check(!await page.locator('#studentRegistration').evaluate(form => form.checkValidity()), 'Signup permits missing privacy consent.');
            await page.locator('[name=privacy_accepted]').check();
            check(await page.locator('#studentRegistration').evaluate(form => form.checkValidity()), 'Signup cannot proceed with valid details and both agreements.');
            await page.screenshot({ path: path.join(__dirname, '.runtime', `legal-agreements-${width}.png`) });
            check(await page.locator('a[href="terms.php"]').getAttribute('target') === '_blank' && await page.locator('a[href="student_privacy.php"]').getAttribute('target') === '_blank', 'Reading legal documents would replace the signup form.');
          }
          check(errors.length === 0, `${name} at ${width}px: browser errors: ${errors.join('; ')}`);
        } finally { await context.close(); }
      }
    }
  } finally { await browser.close(); }
  console.log('Registration/legal browser assertions passed: ' + checks);
  return checks;
};
