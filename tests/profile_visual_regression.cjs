const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

module.exports = async ({ pages, baseUrl }) => {
  if (process.env.HELPDESK_PROFILE_VISUAL !== '1') return 0;
  const { chromium } = require(process.env.HELPDESK_PLAYWRIGHT_PATH || 'playwright');
  const browser = await chromium.launch({
    headless: true,
    ...(process.env.HELPDESK_CHROME_PATH ? { executablePath: process.env.HELPDESK_CHROME_PATH } : {})
  });
  const artifacts = path.join(__dirname, '.runtime');
  fs.mkdirSync(artifacts, { recursive: true });
  fs.writeFileSync(path.join(artifacts, 'profile-visual-fixtures.json'), JSON.stringify(pages));
  let checks = 0;
  const check = (condition, message) => { assert.ok(condition, message); checks++; };
  const cases = [
    ['desktop', 'student_profile.php?fixture=1', 1440, 1000],
    ['compact', 'student_profile.php?fixture=1', 1024, 768],
    ['tablet', 'student_profile.php?fixture=1', 768, 1024],
    ['mobile', 'student_profile.php?fixture=1', 375, 812],
    ['narrow', 'student_profile.php?fixture=1', 320, 740],
    ['registered-desktop', 'student_profile.php', 1440, 1000],
    ['registered-mobile', 'student_profile.php', 375, 812],
    ['edit-desktop', 'student_profile.php?edit=1', 1440, 1000],
    ['edit-mobile', 'student_profile.php?edit=1', 375, 812]
  ];
  try {
    for (const [name, source, width, height] of cases) {
      const context = await browser.newContext({ viewport: { width, height }, serviceWorkers: 'block' });
      try {
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.addInitScript(() => { window.EventSource = undefined; });
        // Replay isolated test fixtures; the browser loads the real local CSS, scripts, and images.
        await page.route('**/student_profile.php**', route => route.fulfill({ contentType: 'text/html', body: pages[source] }));
        await page.route('**/api/**', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({
          success: true, items: [], latest_id: 0, unread_count: 0, email_ready: false, email_enabled: false,
          push_ready: false, concerns: [], sessions: []
        }) }));
        await page.route('https://fonts.googleapis.com/**', route => route.abort());
        await page.goto(new URL('student_profile.php', baseUrl).href, { waitUntil: 'networkidle' });
        await page.screenshot({ path: path.join(artifacts, `profile-${name}.png`), fullPage: true });
        const layout = await page.evaluate(() => {
          const profile = document.getElementById('profileView');
          const rect = profile.getBoundingClientRect();
          const visible = node => node.getClientRects().length > 0;
          const content = [...profile.querySelectorAll('h1,h2,dt,dd,label,input:not([type=hidden]),select,textarea,button,summary,.profile-status')].filter(visible);
          const overflow = content.filter(node => {
            const bounds = node.getBoundingClientRect();
            return bounds.left < rect.left - 1 || bounds.right > rect.right + 1 || node.scrollWidth > node.clientWidth + 2;
          }).map(node => node.id || node.textContent.trim().slice(0, 60));
          const title = document.getElementById('profileTitle').getBoundingClientRect();
          const tools = document.querySelector('.student-notification-bar')?.getBoundingClientRect();
          const collision = tools && title.left < tools.right && title.right > tools.left && title.top < tools.bottom && title.bottom > tools.top;
          const fontSize = selector => [...profile.querySelectorAll(selector)].filter(visible).map(node => parseFloat(getComputedStyle(node).fontSize));
          const luminance = rgb => rgb.match(/[\d.]+/g).slice(0, 3).map(Number).map(v => {
            v /= 255;
            return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4;
          }).reduce((sum, value, i) => sum + value * [.2126, .7152, .0722][i], 0);
          const background = luminance(getComputedStyle(profile).backgroundColor);
          const contrasts = [...profile.querySelectorAll('dt,dd,label,.profile-heading-row p,.profile-identity p')].filter(visible).map(node => {
            const foreground = luminance(getComputedStyle(node).color);
            return (Math.max(foreground, background) + .05) / (Math.min(foreground, background) + .05);
          });
          return {
            overflow, collision, profileWidth: rect.width, pageOverflow: document.documentElement.scrollWidth > innerWidth,
            labelSizes: fontSize('dt,label'), valueSizes: fontSize('dd'), contrasts,
            brokenImages: [...profile.querySelectorAll('img')].filter(visible).filter(img => !img.complete || !img.naturalWidth).map(img => img.src),
            font: getComputedStyle(profile).fontFamily,
            grid: profile.querySelector('.account-details') ? getComputedStyle(profile.querySelector('.account-details')).gridTemplateColumns.split(' ').length : 0
          };
        });
        check(layout.profileWidth > 200 && !layout.pageOverflow, `${name}: profile is blank or overflows the viewport.`);
        check(layout.overflow.length === 0, `${name}: overflowing profile content: ${layout.overflow.join(', ')}`);
        check(!layout.collision, `${name}: profile heading collides with dashboard controls.`);
        check(layout.labelSizes.every(size => size >= 13) && layout.valueSizes.every(size => size >= 14), `${name}: profile text is too small.`);
        check(layout.contrasts.every(ratio => ratio >= 4.5), `${name}: profile text does not have readable contrast.`);
        check(layout.brokenImages.length === 0, `${name}: broken profile icons.`);
        check(layout.font.includes('Inter'), `${name}: profile changed the original student font.`);
        if (layout.grid) check(layout.grid === (width <= 600 ? 1 : 2), `${name}: profile columns do not match the viewport.`);
        const history = page.locator('.profile-history');
        await history.locator('summary').click();
        check(await history.getAttribute('open') !== null, `${name}: account history cannot be opened.`);
        check(await page.locator('.account-history').isVisible(), `${name}: expanded account history is hidden.`);
        await page.locator('.account-history').scrollIntoViewIfNeeded();
        check(await page.locator('.account-history').evaluate(node => {
          const rect = node.getBoundingClientRect();
          const x = rect.left + rect.width / 2;
          const y = Math.max(65, Math.min(innerHeight - 1, rect.top + 8));
          const hit = document.elementFromPoint(x, y);
          return rect.bottom > 65 && rect.top < innerHeight && hit && (node.contains(hit) || hit.contains(node));
        }), `${name}: bottom profile content is clipped or unreachable.`);
        if (width <= 960) {
          check(await page.evaluate(() => document.documentElement.scrollHeight <= innerHeight + 1), `${name}: profile creates a clipped outer page scroller.`);
          await page.screenshot({ path: path.join(artifacts, `profile-${name}-bottom.png`), fullPage: true });
        }
        if (!source.includes('edit=1')) {
          const mobile = page.locator('#profileMobile');
          const original = await mobile.inputValue();
          check(await page.locator('[data-contact-save]').isDisabled(), `${name}: unchanged contact can be saved.`);
          await mobile.fill('09987654321');
          check(await page.locator('[data-contact-save]').isEnabled(), `${name}: contact editing is blocked.`);
          await page.locator('[data-contact-reset]').click();
          check(await mobile.inputValue() === original && await page.locator('[data-contact-save]').isDisabled(), `${name}: contact discard failed.`);
          if (width > 960) {
            const sidebar = await page.locator('.sidebar').boundingBox();
            await page.locator('#navAskBen').click();
            check(!await page.locator('#profileView').isVisible() && await page.locator('#heroView').isVisible(), `${name}: Ask Ben navigation failed.`);
            await page.locator('#navProfile').click();
            check(await page.locator('#profileView').isVisible(), `${name}: Profile navigation failed.`);
            assert.deepEqual(await page.locator('.sidebar').boundingBox(), sidebar, `${name}: Profile changes the dashboard frame.`);
            checks++;
          } else {
            await page.locator('#profileBack').click();
            check(!await page.locator('#profileView').isVisible() && await page.locator('#heroView').isVisible(), `${name}: mobile back navigation failed.`);
          }
        }
        check(errors.length === 0, `${name}: browser errors: ${errors.join('; ')}`);
      } finally { await context.close(); }
    }
  } finally { await browser.close(); }
  console.log('Profile browser/visual assertions passed: ' + checks);
  return checks;
};
