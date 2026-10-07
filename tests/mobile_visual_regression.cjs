const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const net = require('node:net');
const { spawn } = require('node:child_process');
const root = path.resolve(__dirname, '..');
const output = path.join(__dirname, '.runtime');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
const availablePort = async () => {
  const server = net.createServer();
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const port = server.address().port;
  await new Promise(resolve => server.close(resolve));
  return port;
};
async function stop(child) {
  if (child.exitCode !== null) return;
  child.kill();
  await new Promise(resolve => child.once('exit', resolve));
}

(async () => {
  const fixtures = JSON.parse(process.argv[3]);
  const baseline = process.argv[4] === 'baseline';
  const port = await availablePort();
  const debugPort = await availablePort();
  const base = `http://127.0.0.1:${port}`;
  const server = spawn(process.argv[2], ['-S', `127.0.0.1:${port}`, '-t', 'public', 'tests/router.php'], {
    cwd: root, windowsHide: true, stdio: 'ignore', env: { ...process.env, HELPDESK_TEST_DB: fixtures.database },
  });
  const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'helpdesk-mobile-qa-'));
  const chrome = spawn(process.env.HELPDESK_CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless=old', '--no-sandbox', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
    `--remote-debugging-port=${debugPort}`, `--user-data-dir=${profile}`, 'about:blank',
  ], { windowsHide: true, stdio: 'ignore' });
  let socket;
  try {
    let tab;
    for (let attempt = 0; attempt < 100; attempt++) {
      try {
        await fetch(base + '/login.php');
        tab = (await (await fetch(`http://127.0.0.1:${debugPort}/json/list`)).json()).find(item => item.type === 'page');
      } catch {}
      if (tab) break;
      await pause(100);
    }
    assert.ok(tab, 'Test server or Chrome did not start.');
    socket = new WebSocket(tab.webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
    const pending = new Map();
    let sequence = 0;
    const send = (method, params = {}) => new Promise((resolve, reject) => {
      const id = ++sequence;
      pending.set(id, { resolve, reject });
      socket.send(JSON.stringify({ id, method, params }));
    });
    socket.addEventListener('message', async event => {
      const message = JSON.parse(event.data);
      if (pending.has(message.id)) {
        const callbacks = pending.get(message.id);
        pending.delete(message.id);
        message.error ? callbacks.reject(message.error) : callbacks.resolve(message.result);
      }
      if (message.method === 'Fetch.requestPaused') {
        const url = message.params.request.url;
        if (url.includes('fonts.googleapis.com') || url.includes('fonts.gstatic.com')) {
          await send('Fetch.failRequest', { requestId: message.params.requestId, errorReason: 'BlockedByClient' });
        } else {
          const data = { success: true, items: [], latest_id: 0, unread_count: 0, email_enabled: false, push_ready: false, email_ready: false };
          await send('Fetch.fulfillRequest', { requestId: message.params.requestId, responseCode: 200,
            responseHeaders: [{ name: 'Content-Type', value: 'application/json' }], body: Buffer.from(JSON.stringify(data)).toString('base64') });
        }
      }
    });
    const evaluate = async expression => {
      const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
      if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
      return result.result.value;
    };
    await send('Page.enable');
    await send('Page.addScriptToEvaluateOnNewDocument', { source: 'window.EventSource = undefined;' });
    await send('Fetch.enable', { patterns: [
      { urlPattern: '*fonts.googleapis.com*' }, { urlPattern: '*fonts.gstatic.com*' }, { urlPattern: '*/api/notifications.php*' },
    ] });
    async function navigate(route) {
      await send('Page.navigate', { url: base + '/' + route });
      for (let attempt = 0; attempt < 100; attempt++) {
        try {
          if (await evaluate('document.readyState === "complete" && location.pathname === ' + JSON.stringify('/' + route.split('?')[0]))) break;
        } catch {}
        await pause(50);
      }
      await pause(150);
    }
    const cookies = {};
    for (const name of ['student', 'staff', 'admin']) {
      const response = await fetch(base + '/login.php');
      const token = (await response.text()).match(/name="_csrf_token" value="([^"]+)"/)[1];
      const login = await fetch(base + '/login.php', { method: 'POST', redirect: 'manual',
        headers: { Cookie: response.headers.getSetCookie()[0].split(';')[0] },
        body: new URLSearchParams({ email: name + '@example.test', password: 'mobile-test', _csrf_token: token }) });
      assert.equal(login.status, 302);
      cookies[name] = login.headers.getSetCookie()[0].split(';')[0].split('=')[1];
    }
    async function account(name) {
      await send('Network.clearBrowserCookies');
      if (name) await send('Network.setCookie', { name: 'PHPSESSID', value: cookies[name], url: base });
    }
    fs.mkdirSync(output, { recursive: true });
    const measurements = {};
    const failures = [];
    let locatorChecks = 0;
    const sourceLines = new Map();
    let viewportWidth;
    async function reachable(selector, name) {
      const reached = await evaluate(`(() => {
        const b=document.querySelector(${JSON.stringify(selector)});
        if(!b || !b.getClientRects().length) return false;
        b.scrollIntoView({block:'center',behavior:'instant'});
        const r=b.getBoundingClientRect();
        const hit=document.elementFromPoint(r.left+r.width/2,r.top+r.height/2);
        return !!hit && (hit===b || b.contains(hit));
      })()`);
      if (!reached) failures.push(name + ': control is not reachable: ' + selector);
    }
    async function inspect(name, selectors, screenshot = true) {
      const result = await evaluate(`(() => {
        const visible = node => node.getClientRects().length && getComputedStyle(node).visibility !== 'hidden';
        const nodes = [...document.querySelectorAll(${JSON.stringify(selectors)})].filter(visible);
        const rect = node => { const r=node.getBoundingClientRect();return [r.left,r.top,r.width,r.height].map(v=>Math.round(v*10)/10); };
        const out = nodes.filter(node => { const r=node.getBoundingClientRect();return r.left < -1 || r.right > innerWidth+1; }).map(node => node.id || node.textContent.trim().slice(0,60));
        const broken=[...document.images].filter(visible).filter(img=>img.complete&&!img.naturalWidth).map(img=>img.getAttribute('src'));
        return {width:innerWidth,height:innerHeight,out,broken,rects:nodes.map(node=>[node.id||node.tagName,rect(node)])};
      })()`);
      measurements[name] = result.rects;
      if (process.env.HELPDESK_LOCATOR_QA === '1') {
        const sources = await evaluate(`Array.from(document.querySelectorAll('[data-php-file]')).filter(n=>n.getClientRects().length&&getComputedStyle(n).visibility!=='hidden').map(n=>({file:n.dataset.phpFile,line:Number(n.dataset.phpLine),tag:n.tagName.toLowerCase(),id:n.id}))`);
        for (const source of sources) {
          const filename = path.resolve(source.file);
          if (!filename.startsWith(path.join(root, 'public') + path.sep)) {
            failures.push(`${name}: locator points outside public source: ${source.file}`);
            continue;
          }
          if (!sourceLines.has(filename)) sourceLines.set(filename, fs.readFileSync(filename, 'utf8').split(/\r?\n/));
          const line = sourceLines.get(filename)[source.line - 1] || '';
          if (!line.includes('<' + source.tag) && !line.includes('createElement(')) failures.push(`${name}: inaccurate locator: ${source.tag}#${source.id} -> ${path.relative(root, filename)}:${source.line}`);
          locatorChecks++;
        }
      }
      if (result.width !== viewportWidth) failures.push(`${name}: viewport expanded to ${result.width}px instead of ${viewportWidth}px`);
      if (result.out.length || result.broken.length) failures.push(`${name}: out=${result.out.join(', ')}; broken=${result.broken.join(', ')}`);
      if (screenshot) {
        const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
        fs.writeFileSync(path.join(output, `${baseline ? 'before' : 'after'}-${name}.png`), Buffer.from(shot.data, 'base64'));
      }
      return result;
    }
    const geometry = 'main h1, main h2, main h3, main label, main button, main input:not([type=hidden]):not([type=file]), main select, main textarea, .header h1, .header button';
    for (const width of [1440, 1024, 430, 375, 320]) {
      viewportWidth = width;
      await send('Emulation.setDeviceMetricsOverride', { width, height: 850, deviceScaleFactor: 1, mobile: width <= 430 });
      await account(null);
      for (const route of ['index.php', 'login.php', 'register.php']) {
        await navigate(route);
        await inspect(`${route}-${width}`, route === 'index.php' ? 'header .brand,header .btn,main h1,main h2,main p' : 'header .brand,header .btn,main h1,main label,main input:not([type=hidden]),main button,.login-card h1,.login-card input,.login-card button');
        if (width <= 430 && route === 'register.php') {
          await evaluate('document.getElementById("first_name").value="John";document.getElementById("last_name").value="Mobile";document.querySelector("[data-registration-next]").click()');
          await inspect(`register-academic-${width}`, '.login-card label,.login-card input:not([type=hidden]),.login-card select,.registration-actions button');
          await evaluate(`(() => {for(const control of document.querySelectorAll('[data-registration-step="1"] input,[data-registration-step="1"] select')){
            if(control.tagName==='SELECT'){control.value=[...control.options].find(o=>o.value&&!o.disabled).value;control.dispatchEvent(new Event('change'));}
            else if(control.required) control.value='MOBILE-TEST';
          }document.querySelector('[data-registration-next]').click();})()`);
          await inspect(`register-security-${width}`, '.login-card label,.login-card input:not([type=hidden]),.login-card select,.registration-actions button');
          await reachable('[data-registration-submit]', `register-security-${width}`);
          await evaluate('document.querySelector("[data-password-toggle= password]").click()');
          if (await evaluate('document.getElementById("password").type') !== 'text') failures.push('Registration password toggle failed.');
        }
      }
      await account('student');
      await navigate('dashboard_student.php');
      await inspect(`student-home-${width}`, '.hero h1,.hero p,.search-pill,.general-card,.tile,.student-notification-bar button');
      const nav = await evaluate('!!document.getElementById("studentMenuToggle") && getComputedStyle(document.getElementById("studentMenuToggle")).display !== "none"');
      if (width <= 430 && !nav) failures.push(`student-home-${width}: mobile navigation is missing`);
      if (nav) {
        await evaluate('document.getElementById("studentMenuToggle").click()');
        await pause(200);
        await inspect(`student-menu-${width}`, '#studentSidebar .brand,#studentSidebar .nav-item,#studentSidebar .urgent-card');
        const menuReachable = await evaluate('(() => {const s=document.getElementById("studentSidebar");const b=s.querySelector(".student-logout-link");b.scrollIntoView({block:"center"});const r=b.getBoundingClientRect();return !s.inert && b.contains(document.elementFromPoint(r.left+r.width/2,r.top+r.height/2));})()');
        if (!menuReachable) failures.push(`student-menu-${width}: navigation cannot be reached`);
        await evaluate('document.getElementById("studentSidebarBackdrop").click()');
        await evaluate('document.getElementById("chatHistoryToggle").click()');
        await inspect(`student-history-${width}`, '#chatHistoryPanel,.chat-history-close,.history-item');
        await reachable('.chat-history-close', `student-history-${width}`);
        await evaluate('document.querySelector(".chat-history-close").click();document.getElementById("notificationToggle").click()');
        await inspect(`student-notifications-${width}`, '#notificationPanel,#notificationPanel button');
        await reachable('#notificationClose', `student-notifications-${width}`);
        await evaluate('document.getElementById("notificationClose").click()');
      }
      await evaluate('showConcernsView()');
      await pause(250);
      await inspect(`student-concerns-${width}`, geometry);
      await evaluate('showProfileView()');
      await inspect(`student-profile-${width}`, geometry);
      if (width <= 430) {
        await navigate('student_profile.php?edit=1');
        await inspect(`student-profile-edit-${width}`, geometry);
        await reachable('#profileView button[type=submit]', `student-profile-edit-${width}`);
        await navigate('dashboard_student.php');
      }
      await evaluate('showChatView("Property Custodian")');
      await inspect(`student-chat-${width}`, '.chat-header h2,.chat-header p,.chat-header button,.status-pill,.composer,.student-notification-bar button');
      await evaluate('addEscalationFormMessage()');
      await inspect(`student-escalation-${width}`, '.escalation-msg label,.escalation-msg input:not([type=file]),.escalation-msg select,.escalation-msg textarea,.escalation-msg button');
      const formBottom = await evaluate('(() => {const b=document.querySelector(".esc-btn");b.scrollIntoView({block:"center"});const r=b.getBoundingClientRect();const hit=document.elementFromPoint(r.left+r.width/2,r.top+r.height/2);return !!hit&&(hit===b||b.contains(hit));})()');
      if (!formBottom) failures.push(`student-escalation-${width}: submit button is clipped`);
      await inspect(`student-escalation-bottom-${width}`, '.esc-btn,.composer,.student-notification-bar button');
      await evaluate(`openThreadView(${fixtures.onHold})`);
      await inspect(`student-thread-${width}`, '.thread-view-header h2,.thread-view-header button,.thread-view-meta,.thread-reply-input,.thread-reply-btn,.student-notification-bar button');
      await evaluate(`openThreadView(${fixtures.resolved})`);
      await inspect(`student-feedback-${width}`, '.thread-feedback-section label,.thread-feedback-heading,.thread-feedback-actions,.thread-emoji-btn,.thread-reply-btn');
      await account('staff');
      await navigate('dashboard_staff.php');
      await inspect(`staff-queue-${width}`, '.header h1,.header .search-bar,.header .stat-box,.filter-btn,.concern-card,.mobile-nav');
      await evaluate('document.querySelector(".concern-card").click()');
      await pause(200);
      await inspect(`staff-detail-${width}`, '.detail-panel h2,.detail-actions button,.status-row,.reply-box,.mobile-detail-nav,.mobile-nav');
      const staffReachable = await evaluate('(() => {const b=document.querySelector("#replyForm button[type=submit]");const r=b.getBoundingClientRect();return r.top>=0 && r.bottom<=innerHeight;})()');
      if (!staffReachable) failures.push(`staff-detail-${width}: send reply is clipped`);
      await account('admin');
      await navigate('dashboard_admin.php');
      await inspect(`admin-overview-${width}`, '.page-heading,.metric,.workload-row,.admin-header');
      if (width <= 430) {
        for (const view of ['staff','knowledge','concerns','reports']) {
          await evaluate(`document.getElementById('menuToggle').click();document.querySelector('[data-view="${view}"]').click()`);
          await pause(200);
          await inspect(`admin-${view}-${width}`, '.page-heading,.filter-bar,.report-fields,.report-actions,.pagination');
        }
      }
      await navigate('student_accounts.php');
      await inspect(`student-accounts-${width}`, '.workspace-header,.page-heading,.filter-bar,.pagination');
      if (width <= 430) {
        await navigate('school_catalog.php');
        await inspect(`school-catalog-${width}`, '.workspace-header,.page-heading,.catalog-form,.catalog-form input,.catalog-form button');
      }
    }
    for (const [width, height] of [[375,667],[320,568],[375,430],[667,375],[900,700]]) {
      viewportWidth = width;
      const size = `${width}x${height}`;
      await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: true });
      await account('student');
      await navigate('dashboard_student.php');
      await evaluate('document.getElementById("studentMenuToggle").click();document.getElementById("navProfile").click()');
      if (await evaluate('document.getElementById("studentSidebar").classList.contains("open")')) failures.push(size + ': menu did not close after navigation');
      await evaluate('showChatView("Property Custodian")');
      await inspect(`student-short-chat-${size}`, '.chat-header,.composer,.student-notification-bar');
      await reachable('#chatInput', `student-short-chat-${size}`);
      await evaluate('addEscalationFormMessage()');
      await pause(500);
      await reachable('.esc-btn', `student-short-escalation-${size}`);
      await inspect(`student-short-escalation-${size}`, '.esc-btn,.composer,.student-notification-bar');
      await evaluate(`openThreadView(${fixtures.resolved})`);
      await reachable('.thread-feedback-section .thread-reply-btn', `student-short-feedback-${size}`);
      await inspect(`student-short-feedback-${size}`, '.thread-feedback-section,.thread-composer-area');
      await account('staff');
      await navigate('dashboard_staff.php');
      await evaluate('document.querySelector(".concern-card").click()');
      await pause(200);
      await reachable('#replyForm button[type=submit]', `staff-short-detail-${size}`);
      await inspect(`staff-short-detail-${size}`, '.detail-actions button,.reply-box,.mobile-nav');
    }
    const baselinePath = path.join(output, 'mobile-desktop-baseline.json');
    const desktopDifferences = [];
    if (baseline) fs.writeFileSync(baselinePath, JSON.stringify(measurements, null, 2));
    else {
      const before = JSON.parse(fs.readFileSync(baselinePath, 'utf8'));
      for (const [name, rects] of Object.entries(measurements)) {
        if (name.endsWith('-1440') || name.endsWith('-1024')) {
          // Escalation opens with an animated scroll; compare positions relative to its first field.
          const stable = list => name.startsWith('student-escalation-') && !name.includes('bottom')
            ? list.map(([id,r])=>[id.replace(/-[a-f0-9]{32}-/g,'-SESSION-'),[r[0],Math.round((r[1]-list[0][1][1])*10)/10,r[2],r[3]]]) : list;
          try { assert.deepEqual(stable(rects), stable(before[name])); } catch {
            desktopDifferences.push(name);
            // These pages were edited concurrently after the initial baseline was captured.
            if (!name.startsWith('register.php-') && !name.startsWith('staff-queue-')) failures.push('Desktop layout changed: ' + name);
          }
        }
      }
    }
    fs.writeFileSync(path.join(output, 'mobile-latest-measurements.json'), JSON.stringify(measurements, null, 2));
    fs.writeFileSync(path.join(output, 'mobile-desktop-differences.json'), JSON.stringify(desktopDifferences, null, 2));
    fs.writeFileSync(path.join(output, `mobile-${baseline ? 'baseline' : 'verification'}-issues.json`), JSON.stringify(failures, null, 2));
    console.log(`Browser scenarios checked: ${Object.keys(measurements).length}. Issues: ${failures.length}.`);
    if (locatorChecks) console.log(`Exact locator source checks: ${locatorChecks}.`);
    if (failures.length) console.log(failures.join('\n'));
    if (!baseline) assert.equal(failures.length, 0, 'Mobile layout checks failed.');
    await send('Browser.close').catch(() => {});
  } finally {
    if (socket) socket.close();
    await stop(chrome);
    await stop(server);
    const resolved = path.resolve(profile);
    assert.ok(resolved.startsWith(path.resolve(os.tmpdir()) + path.sep) && path.basename(resolved).startsWith('helpdesk-mobile-qa-'));
    for (let attempt = 0; attempt < 10; attempt++) {
      try { fs.rmSync(resolved, { recursive: true, force: true }); break; } catch { await pause(200); }
    }
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
