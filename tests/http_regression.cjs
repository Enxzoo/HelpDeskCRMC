const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');
const net = require('node:net');
const path = require('node:path');
const vm = require('node:vm');

async function main() {
  const fixtures = JSON.parse(process.argv[3]);
  const listener = net.createServer();
  await new Promise(resolve => listener.listen(0, '127.0.0.1', resolve));
  const port = listener.address().port;
  await new Promise(resolve => listener.close(resolve));
  const base = `http://127.0.0.1:${port}`;
  const server = spawn(process.argv[2], ['-S', `127.0.0.1:${port}`, '-t', 'public', 'tests/router.php'], {
    cwd: path.resolve(__dirname, '..'), windowsHide: true, stdio: ['ignore', 'ignore', 'pipe']
  });
  let serverErrors = '';
  server.stderr.on('data', chunk => { serverErrors += chunk; });
  let checks = 0;
  async function request(route, account, body, options = {}) {
    const headers = account ? { Cookie: account.cookie, 'X-CSRF-Token': account.token } : {};
    let payload;
    if (body instanceof FormData || body instanceof URLSearchParams) payload = body;
    else if (body !== undefined) { payload = JSON.stringify(body); headers['Content-Type'] = 'application/json'; }
    const response = await fetch(base + route, {
      method: body === undefined ? 'GET' : 'POST', headers: { ...headers, ...options.headers },
      body: payload, redirect: 'manual', signal: AbortSignal.timeout(10000)
    });
    const text = await response.text();
    assert.equal(response.status, options.status ?? 200, `${route} returned unexpected HTTP status`);
    checks++;
    if (route.startsWith('/api/') && response.headers.get('content-type')?.includes('application/json')) return text ? JSON.parse(text) : null;
    return { text, response };
  }
  async function login(name) {
    const response = await fetch(base + '/login.php');
    const html = await response.text();
    const token = html.match(/name="_csrf_token" value="([^"]+)"/)[1];
    const cookie = response.headers.getSetCookie()[0].split(';')[0];
    const result = await fetch(base + '/login.php', {
      method: 'POST', headers: { Cookie: cookie }, redirect: 'manual',
      body: new URLSearchParams({ email: name + '@example.test', password: 'regression-password', _csrf_token: token })
    });
    assert.equal(result.status, 302, `${name} cannot sign in`);
    checks++;
    return { token, cookie: result.headers.getSetCookie()[0].split(';')[0] };
  }
  function checkScripts(html, filename) {
    for (const match of html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)) {
      if (match[1].trim()) new vm.Script(match[1], { filename });
    }
    checks++;
  }
  try {
    for (let attempt = 0; attempt < 50; attempt++) {
      try { await fetch(base + '/login.php'); break; }
      catch { await new Promise(resolve => setTimeout(resolve, 100)); }
    }
    await request('/api/debug_inquiries.php', null, undefined, { status: 401 });
    const student = await login('student');
    const otherStudent = await login('otherStudent');
    const staff = await login('staff');
    const unassigned = await login('unassignedStaff');
    const admin = await login('admin');
    let staffHtml;
    let studentHtml;
    for (const [page, account] of [['dashboard_student.php', student], ['dashboard_staff.php', staff], ['dashboard_admin.php', admin]]) {
      const result = await request('/' + page, account);
      checkScripts(result.text, page);
      if (page === 'dashboard_staff.php') staffHtml = result.text;
      if (page === 'dashboard_student.php') studentHtml = result.text;
    }
    checks += await require('./ben_chat_visual.cjs')({ html: studentHtml, baseUrl: base + '/' });
    for (const field of ['email', 'password']) {
      const body = new URLSearchParams({ email: 'student@example.test', password: 'regression-password', _csrf_token: student.token });
      body.delete(field);
      body.set(field + '[]', 'invalid');
      const response = await fetch(base + '/login.php', { method: 'POST', headers: { Cookie: student.cookie }, body });
      assert.equal(response.status, 400, 'Array-valued login input must not crash.');
      assert.match(await response.text(), /Please enter a valid email and password/);
      checks++;
    }
    await request('/api/debug_inquiries.php', staff, undefined, { status: 403 });
    assert.equal((await request('/api/debug_inquiries.php', admin)).success, true);
    for (const action of ['respond', 'update_status', 'assign', 'override_priority']) {
      await request('/api/staff_action.php', staff, {
        action, inquiry_id: fixtures.otherInquiry, message: 'Unauthorized', status: 'Resolved', priority: 'High'
      }, { status: 403 });
    }
    await request('/api/get_responses.php?inquiry_id=' + fixtures.otherInquiry, staff, undefined, { status: 403 });
    await request('/api/get_responses.php?inquiry_id=' + fixtures.inquiry, unassigned, undefined, { status: 403 });
    await request('/api/staff_action.php', unassigned, { action: 'assign', inquiry_id: fixtures.inquiry }, { status: 403 });
    await request('/api/staff_action.php', staff, { action: 'assign', inquiry_id: fixtures.inquiry }, { headers: { 'X-CSRF-Token': 'invalid' }, status: 403 });
    await request('/api/staff_action.php', staff, { action: 'update_status', inquiry_id: fixtures.inquiry, status: 'On Hold' });
    assert.equal((await request('/api/submit_student_reply.php', student, { inquiry_id: fixtures.inquiry, message: 'Follow-up information' })).success, true);
    await request('/api/submit_student_reply.php', otherStudent, { inquiry_id: fixtures.inquiry, message: 'Unauthorized' }, { status: 403 });
    const replies = await request('/api/get_responses.php?inquiry_id=' + fixtures.inquiry, staff);
    assert.equal(replies.responses.at(-1).sender_role, 'student');
    const concerns = await request('/api/get_student_concerns_with_replies.php', student);
    assert.equal(concerns.concerns.find(item => item.inquiry_id === fixtures.inquiry).replies.at(-1).sender_role, 'student');
    await request('/api/notifications.php', null, undefined, { status: 401 });
    await request('/api/notification_stream.php', null, undefined, { status: 401 });
    const inbox = await request('/api/notifications.php', student);
    assert.equal(inbox.success, true);
    assert.ok(inbox.items.length > 0 && inbox.unread_count > 0);
    assert.ok(inbox.items.every(item => [fixtures.inquiry, fixtures.otherInquiry].includes(item.inquiry_id)));
    assert.ok(!JSON.stringify(inbox).includes('PRIVATE') && !('push_private_key' in inbox));
    checks++;
    const officeInbox = await request('/api/notifications.php', staff);
    assert.ok(officeInbox.items.every(item => item.inquiry_id !== fixtures.otherInquiry));
    checks++;
    await request('/api/notifications.php', student, { action: 'mark_all_read' }, { headers: { 'X-CSRF-Token': 'invalid' }, status: 403 });
    await request('/api/notifications.php', student, { action: 'mark_read', notification_id: [] }, { status: 400 });
    await request('/api/notifications.php', student, { action: 'email_preference', enabled: 'false' }, { status: 400 });
    await request('/api/notifications.php', student, { action: 'unknown' }, { status: 400 });
    await request('/api/notifications.php', student, { action: 'subscribe', subscription: { endpoint: 'https://127.0.0.1/private', keys: {} } }, { status: 400 });
    await request('/api/notifications.php', student, { action: 'subscribe', subscription: { endpoint: 'https://fcm.googleapis.com/test', keys: 'bad' } }, { status: 400 });
    const subscription = { endpoint: 'https://fcm.googleapis.com/fcm/send/http-test-only', keys: {
      p256dh: Buffer.concat([Buffer.from([4]), Buffer.alloc(64, 1)]).toString('base64url'), auth: Buffer.alloc(16, 1).toString('base64url')
    } };
    await request('/api/notifications.php', student, { action: 'subscribe', subscription });
    await request('/api/notifications.php', student, { action: 'unsubscribe', endpoint: subscription.endpoint });
    assert.equal((await request('/api/notifications.php', student, { action: 'email_preference', enabled: false })).email_enabled, false);
    assert.equal((await request('/api/notifications.php', student, { action: 'email_preference', enabled: true })).email_enabled, true);
    await request('/api/notifications.php', otherStudent, { action: 'mark_read', notification_id: inbox.items[0].notification_id });
    assert.equal((await request('/api/notifications.php', student)).unread_count, inbox.unread_count);
    assert.equal((await request('/api/notifications.php', student, { action: 'mark_read', notification_id: inbox.items[0].notification_id })).unread_count, inbox.unread_count - 1);
    assert.equal((await request('/api/notifications.php', student, { action: 'mark_all_read' })).unread_count, 0);
    await request('/api/notification_stream.php?after=-1', student, undefined, { status: 400 });
    const history = [{ role: 'user', message: 'Hello' }, { role: 'model', message: 'Welcome' }];
    await request('/api/save_chat_session.php', student, { category: 'Registrar', conversationHistory: history, lastMessage: 'x'.repeat(600) });
    const restored = await request('/api/load_chat_session.php?category=Registrar', student);
    assert.deepEqual(restored.conversationHistory, history);
    assert.equal(restored.lastMessage.length, 500);
    const oldChatKey = restored.session_key;
    const newChatKey = 'c'.repeat(32);
    const newChatHistory = [{ role: 'user', message: 'A separate Registrar conversation' }];
    const newChat = await request('/api/save_chat_session.php', student, {
      category: 'Registrar', session_key: newChatKey, conversationHistory: newChatHistory, lastMessage: newChatHistory[0].message
    });
    assert.equal(newChat.session_key, newChatKey);
    assert.notEqual(newChat.session_id, restored.session_id);
    assert.deepEqual((await request('/api/load_chat_session.php?session_key=' + oldChatKey, student)).conversationHistory, history);
    assert.deepEqual((await request('/api/load_chat_session.php?session_key=' + newChatKey, student)).conversationHistory, newChatHistory);
    const historyList = await request('/api/list_chat_sessions.php', student);
    assert.equal(historyList.sessions.filter(item => item.category === 'Registrar').length, 2);
    assert.ok(historyList.sessions.every(item => !('session_data' in item)));
    checks += 4;
    await request('/api/load_chat_session.php?session_key=' + oldChatKey, otherStudent, undefined, { status: 404 });
    assert.deepEqual((await request('/api/list_chat_sessions.php', otherStudent)).sessions, []);
    await request('/api/list_chat_sessions.php', null, undefined, { status: 401 });
    await request('/api/list_chat_sessions.php', staff, undefined, { status: 403 });
    await request('/api/load_chat_session.php?session_key[]=invalid', student, undefined, { status: 400 });
    await request('/api/save_chat_session.php', student, { category: 'Registrar', session_key: [], conversationHistory: [] }, { status: 400 });
    await request('/api/save_chat_session.php', student, { category: 'Finance', session_key: newChatKey, conversationHistory: [] }, { status: 400 });
    await request('/api/save_chat_session.php', student, { category: [], conversationHistory: [] }, { status: 400 });
    await request('/api/ai_chat.php', student, { message: 'Hello', history: [null] }, { status: 400 });
    await request('/api/ai_chat_stream.php', null, { message: 'Hello' }, { status: 401 });
    await request('/api/ai_chat_stream.php', staff, { message: 'Hello' }, { status: 403 });
    await request('/api/ai_chat_stream.php', student, { message: 'Hello', history: [null] }, { status: 400 });
    await request('/api/ai_chat.php', student, { message: 'Hello', stream: 'yes' }, { status: 400 });
    await request('/api/ai_chat.php', student, { message: 'x'.repeat(4001) }, { status: 400 });
    await request('/api/ai_chat.php', student, { message: 'Hello', history: [{ role: 'user', message: 'x'.repeat(131073) }] }, { status: 413 });
    await request('/api/staff_action.php', staff, { action: 'assign', inquiry_id: [] }, { status: 400 });
    await request('/api/staff_action.php', staff, { action: 'respond', inquiry_id: fixtures.inquiry, message: [] }, { status: 400 });
    await request('/api/submit_feedback.php', student, { inquiry_id: fixtures.inquiry, rating: 5, comment: 'x'.repeat(501) }, { status: 400 });
    await request('/api/submit_student_reply.php', student, { inquiry_id: fixtures.inquiry, message: [] }, { status: 400 });
    const upload = new FormData();
    upload.append('file', new Blob(['Regression upload content']), 'original-name.php');
    const attachment = await request('/api/upload_file.php', student, upload);
    assert.equal(attachment.success, true);
    assert.match(attachment.filename, /^[a-f0-9]{32}\.txt$/);
    const adminChecks = await require('./admin_http_regression.cjs')({ request, login, student, staff, admin, fixtures });
    checks += adminChecks;
    await request('/api/admin_action.php', admin, {
      action: 'create_user', first_name: 'New', last_name: 'Student', email: 'created@example.test',
      password: 'regression-password', role: 'student', student_number: 'TEST-CREATED'
    }, { status: 409 });
    await request('/api/admin_action.php', admin, { action: 'create_user', first_name: 'Invalid', last_name: 'Staff', email: 'invalid@example.test', password: 'regression-password', role: 'staff' }, { status: 400 });
    await request('/api/admin_action.php', admin, { action: 'toggle_user_status', user_id: fixtures.admin }, { status: 409 });
    await request('/api/admin_action.php', admin, { action: 'toggle_user_status', user_id: fixtures.otherStudent }, { status: 409 });
    checks += await require('./student_accounts_http.cjs')({ request, student, staff, admin, fixtures });
    await request('/api/staff_action.php', staff, { action: 'update_status', inquiry_id: fixtures.inquiry, status: 'Resolved' });
    await request('/api/submit_student_reply.php', student, { inquiry_id: fixtures.inquiry, message: 'Too late' }, { status: 409 });
    await request('/api/submit_feedback.php', student, { inquiry_id: fixtures.inquiry, rating: 5, comment: 'Resolved' });
    const logoutForms = [...staffHtml.matchAll(/<form\b[^>]*class="staff-logout-form"[^>]*>([\s\S]*?)<\/form>/g)];
    assert.equal(logoutForms.length, 2, 'Both desktop and mobile logout forms must exist.');
    for (const form of logoutForms) {
      const account = await login('staff');
      const action = form[0].match(/action="([^"]+)"/)[1];
      assert.equal(action, 'logout.php', 'Staff logout points to a login form.');
      assert.match(form[1], /name="_csrf_token"/);
      const response = await fetch(base + '/' + action, { method: 'POST', headers: { Cookie: account.cookie }, redirect: 'manual', body: new URLSearchParams({ _csrf_token: account.token }) });
      assert.equal(response.status, 302);
      await request('/api/get_responses.php?inquiry_id=' + fixtures.inquiry, account, undefined, { status: 401 });
      checks++;
    }
    const logout = await request('/logout.php', admin, undefined, { status: 302 });
    assert.equal(logout.response.headers.get('Location'), 'login.php');
    await request('/api/debug_inquiries.php', admin, undefined, { status: 401 });
    // Run streaming last: PHP's CLI development server has a single request thread.
    const finalInbox = await request('/api/notifications.php', student);
    const abort = new AbortController();
    const sse = await fetch(base + '/api/notification_stream.php?after=0', {
      headers: { Cookie: student.cookie, 'Last-Event-ID': String(finalInbox.latest_id) }, signal: abort.signal
    });
    assert.equal(sse.status, 200);
    assert.match(sse.headers.get('content-type'), /text\/event-stream/);
    const reader = sse.body.getReader();
    let events = '';
    const timeout = setTimeout(() => abort.abort(), 10000);
    try {
      while (!events.includes('event: notifications')) {
        const chunk = await reader.read();
        if (chunk.done) break;
        events += Buffer.from(chunk.value).toString();
      }
      const data = JSON.parse(events.match(/data: (.+)/)[1]);
      assert.equal(data.cursor, finalInbox.latest_id);
      assert.equal(data.unread_count, finalInbox.unread_count);
      assert.deepEqual(data.items, [], 'Last-Event-ID did not prevent replay.');
      checks += 5;
    } finally { clearTimeout(timeout); abort.abort(); await reader.cancel().catch(() => {}); }
    const warnings = serverErrors.split('\n').filter(line => /PHP (?:Fatal error|Warning|Parse error)|Uncaught /.test(line));
    assert.equal(warnings.length, 0, warnings.join('\n'));
    console.log('HTTP regression checks passed: ' + checks);
  } finally {
    const stopped = new Promise(resolve => server.once('exit', resolve));
    server.kill();
    await stopped;
  }
}
main().catch(error => { console.error(error.message); process.exitCode = 1; });
