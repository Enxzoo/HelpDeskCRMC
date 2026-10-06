const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

module.exports = async ({ html, baseUrl }) => {
  if (process.env.HELPDESK_PROFILE_VISUAL !== '1') return 0;
  const { chromium } = require(process.env.HELPDESK_PLAYWRIGHT_PATH || 'playwright');
  const browser = await chromium.launch({ headless: true,
    ...(process.env.HELPDESK_CHROME_PATH ? { executablePath: process.env.HELPDESK_CHROME_PATH } : {}) });
  const artifacts = path.join(__dirname, '.runtime');
  fs.mkdirSync(artifacts, { recursive: true });
  let checks = 0;
  const check = (condition, message) => { assert.ok(condition, message); checks++; };
  try {
    for (const [name, width, height] of [['desktop', 1440, 1000], ['tablet', 768, 1024], ['mobile', 375, 812], ['narrow', 320, 740]]) {
      const context = await browser.newContext({ viewport: { width, height }, serviceWorkers: 'block' });
      try {
        const page = await context.newPage();
        const errors = [];
        const requests = [];
        const saved = [];
        let restored = [];
        const answer = 'You can request your transcript through the Registrar. Did that answer your concern? If you need anything else, feel free to ask!';
        page.on('pageerror', error => errors.push(error.message));
        await page.addInitScript(() => { window.EventSource = undefined; });
        await page.route('**/dashboard_student.php**', route => route.fulfill({ contentType: 'text/html', body: html }));
        await page.route('https://fonts.googleapis.com/**', route => route.abort());
        await page.route('**/api/**', async route => {
          const url = new URL(route.request().url());
          const body = route.request().postDataJSON() || {};
          let data = { success: true, items: [], latest_id: 0, unread_count: 0, email_enabled: false, push_ready: false, concerns: [], sessions: [] };
          if (url.pathname.endsWith('save_chat_session.php')) saved.push(body);
          if (url.pathname.endsWith('load_chat_session.php')) data = {
            success: true, category: 'Registrar', session_key: url.searchParams.get('session_key'), conversationHistory: restored
          };
          if (url.pathname.endsWith('ai_chat.php')) {
            requests.push(body);
            data = { success: true, answer };
            if (body.message.includes('another way')) data.answer = 'Which part would you like me to explain: requirements or processing time?';
            if (body.message.includes('Thank you')) data.answer = "You're welcome!";
            if (body.message === 'provider failure') data = { success: false, error: 'Ben is temporarily unavailable.' };
          }
          await route.fulfill({ contentType: 'application/json', body: JSON.stringify(data) });
        });
        await page.goto(new URL('dashboard_student.php', baseUrl).href, { waitUntil: 'networkidle' });
        await page.locator('[data-category="Registrar"]').click();
        check(await page.locator('.ben-actions button').count() === 3, `${name}: starter replies are missing.`);
        await page.locator('#chatInput').fill('I need my transcript.');
        await page.locator('#chatSendBtn').click();
        await page.locator('.ben-actions button').filter({ hasText: 'Not yet' }).waitFor();
        check(await page.locator('.msg.ben .bubble').last().textContent() === answer, `${name}: suggestions changed Ben's response.`);
        check(await page.locator('.ben-actions').count() === 1, `${name}: stale choices remain.`);
        check(await page.locator('.ben-feedback-prompt').count() === 0, `${name}: feedback added a redundant caption.`);
        const layout = await page.locator('.ben-actions').evaluate(node => ({
          bounds: node.getBoundingClientRect().toJSON(),
          overflow: [...node.querySelectorAll('button')].some(button => button.scrollWidth > button.clientWidth + 1),
          heights: [...node.querySelectorAll('button')].map(button => button.getBoundingClientRect().height),
          fontSize: parseFloat(getComputedStyle(node.querySelector('button')).fontSize),
          messageSize: parseFloat(getComputedStyle(node.parentElement.querySelector('.bubble')).fontSize),
          pageOverflow: document.documentElement.scrollWidth > innerWidth,
          composer: document.getElementById('chatInput').getBoundingClientRect().toJSON()
        }));
        check(!layout.pageOverflow && !layout.overflow && layout.bounds.right <= width + 1, `${name}: compact choices overflow.`);
        check(layout.heights.every(value => value >= 32 && value <= 38), `${name}: buttons are too large or too small.`);
        check(layout.fontSize < layout.messageSize, `${name}: buttons compete with message hierarchy.`);
        check(layout.bounds.bottom <= layout.composer.top, `${name}: choices overlap the composer.`);
        check(await page.locator('#chatInput').isEnabled(), `${name}: suggestions block typing.`);
        await page.screenshot({ path: path.join(artifacts, `ben-chat-feedback-${name}.png`), fullPage: true });
        await page.locator('.ben-actions button').filter({ hasText: 'Not yet' }).click();
        await page.locator('.ben-actions button').filter({ hasText: 'Requirements', exact: true }).waitFor();
        check(!('interaction' in requests.at(-1)) && !('language' in requests.at(-1)), `${name}: UI changed the AI request contract.`);
        check(requests.at(-1).message.includes('explain it another way'), `${name}: Not yet lost its meaning.`);
        check(await page.locator('.escalation-msg').count() === 0, `${name}: Not yet forced escalation.`);
        await page.locator('.ben-actions button').filter({ hasText: 'Requirements', exact: true }).click();
        await page.locator('.ben-actions button').filter({ hasText: 'Yes, thanks' }).click();
        await page.locator('.bubble').filter({ hasText: "You're welcome!", exact: true }).waitFor();
        check(await page.locator('.ben-actions').count() === 0, `${name}: acknowledgement repeated choices.`);
        await page.locator('#chatInput').fill('How long does it take?');
        await page.locator('#chatSendBtn').click();
        await page.locator('.ben-actions button').filter({ hasText: 'Talk to staff', exact: true }).waitFor();
        const requestsBeforeStaff = requests.length;
        await page.locator('[data-chat-action="staff"]').click();
        await page.locator('.esc-header h3').waitFor();
        check(requests.length === requestsBeforeStaff, `${name}: staff button unnecessarily requested another AI reply.`);
        check(await page.locator('.escalation-msg').count() === 1, `${name}: duplicate form displayed.`);
        check(await page.locator('#chatThreadInner').textContent().then(text => !text.includes('Fill out the form below') && !text.includes('I understand you')), `${name}: redundant form introduction remains.`);
        check(await page.locator('[name="fullName"]').inputValue() !== '', `${name}: identity prefill was lost.`);
        check(!await page.locator('.escalation-msg [data-success-msg]').isVisible(), `${name}: form claimed submission before confirmation.`);
        await page.locator('[name="subject"]').fill('My edited subject');
        await page.locator('#chatInput').fill('Another transcript question');
        await page.locator('#chatSendBtn').click();
        await page.locator('[data-chat-action="staff"]').click();
        check(await page.locator('.escalation-msg').count() === 1 && await page.locator('[name="subject"]').inputValue() === 'My edited subject', `${name}: repeat form action overwrote a draft.`);
        restored = saved.find(item => item.conversationHistory.at(-1)?.message === answer).conversationHistory;
        check(!restored.some(entry => entry.action || entry.form), `${name}: UI wrote new AI metadata into chat history.`);
        await page.evaluate(() => showChatView('Registrar', 'a'.repeat(32)));
        await page.locator('.ben-actions button').filter({ hasText: 'Not yet' }).waitFor();
        check(await page.locator('.ben-actions').count() === 1, `${name}: restored chat duplicated choices.`);
        check(errors.length === 0, `${name}: browser errors: ${errors.join('; ')}`);
        check(await page.locator('#chatThreadInner img[src*="ben-model"]').first().evaluate(img => img.complete && img.naturalWidth > 0), `${name}: Ben asset is blank.`);
      } finally { await context.close(); }
    }
  } finally { await browser.close(); }
  console.log('Compact Ben chat browser assertions passed: ' + checks);
  return checks;
};
