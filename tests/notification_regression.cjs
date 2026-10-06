const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
let checks = 0;
function equal(actual, expected, message) { assert.deepEqual(actual, expected, message); checks++; }
const settle = () => new Promise(resolve => setImmediate(resolve));

class Event {
  constructor(type, options = {}) { this.type = type; Object.assign(this, options); this.defaultPrevented = false; }
  preventDefault() { if (this.cancelable) this.defaultPrevented = true; }
}
class Element {
  constructor() { this.children = []; this.listeners = new Map(); this.attributes = {}; this.hidden = false; this.disabled = false; this.checked = false; this.textContent = ''; }
  addEventListener(type, handler) { if (!this.listeners.has(type)) this.listeners.set(type, []); this.listeners.get(type).push(handler); }
  async trigger(type, event = {}) { for (const handler of this.listeners.get(type) || []) await handler(event); }
  append(...children) { this.children.push(...children); }
  appendChild(child) { this.children.push(child); }
  replaceChildren(...children) { this.children = children; }
  setAttribute(name, value) { this.attributes[name] = value; }
  focus() { this.focused = true; }
  click() { return this.trigger('click'); }
  contains(target) { return this === target || this.children.some(child => child.contains(target)); }
  set innerHTML(value) { throw new Error('Notification content must never be rendered as HTML.'); }
}

function dashboard(options = {}) {
  const elements = new Map();
  for (const name of ['Center', 'Panel', 'Status', 'Push', 'Email', 'Badge', 'Toggle', 'ReadAll', 'List', 'Toast', 'EmailStatus', 'PushStatus', 'Close']) {
    elements.set('notification' + name, new Element());
  }
  elements.get('notificationPanel').hidden = true;
  const docListeners = new Map();
  const windowListeners = new Map();
  const events = [];
  const document = {
    getElementById: id => elements.get(id), querySelector: () => ({ content: 'csrf-test' }),
    createElement: () => new Element(),
    addEventListener(type, callback) { if (!docListeners.has(type)) docListeners.set(type, []); docListeners.get(type).push(callback); },
    dispatchEvent(event) { events.push(event); for (const callback of docListeners.get(event.type) || []) callback(event); return !event.defaultPrevented; }
  };
  const timers = new Map();
  let nextTimer = 1;
  const timer = (callback, delay) => { const id = nextTimer++; timers.set(id, { callback, delay }); return id; };
  const requests = [];
  let feed = {
    success: true, role: 'student', user_id: 1, items: [], unread_count: 0, latest_id: 0,
    email_enabled: true, email_ready: false, push_ready: true, push_public_key: Buffer.alloc(65, 1).toString('base64url')
  };
  let failSubscribe = !!options.failSubscribe;
  let permissionRequests = 0;
  let registrations = 0;
  let subscriptions = 0;
  let unsubscribes = 0;
  let first = true;
  const pushSubscription = {
    endpoint: 'https://fcm.googleapis.com/test',
    toJSON: () => ({ endpoint: 'https://fcm.googleapis.com/test', keys: {} }),
    async unsubscribe() { unsubscribes++; return true; }
  };
  const registration = { pushManager: {
    async getSubscription() { return options.existingSubscription ? pushSubscription : null; },
    async subscribe(settings) { equal(settings.userVisibleOnly, true); subscriptions++; return pushSubscription; }
  } };
  const Notification = { permission: options.permission || 'default',
    async requestPermission() { permissionRequests++; Notification.permission = options.deny ? 'denied' : 'granted'; return Notification.permission; }
  };
  const sources = [];
  class EventSource {
    constructor(url) { this.url = url; this.listeners = new Map(); sources.push(this); }
    addEventListener(type, callback) { this.listeners.set(type, callback); }
    emit(type, data) { this.listeners.get(type)?.({ data: JSON.stringify(data) }); }
    close() { this.closed = true; }
  }
  const assigned = [];
  const window = {
    isSecureContext: !!options.secure, EventSource, Notification, PushManager: {},
    location: { assign: value => assigned.push(value) },
    addEventListener: (type, callback) => windowListeners.set(type, callback)
  };
  if (options.noEvents) delete window.EventSource;
  const navigator = { serviceWorker: {
    async register(url) { equal(url, 'service-worker.js'); registrations++; return registration; },
    ready: Promise.resolve(registration), addEventListener() {}
  } };
  const context = vm.createContext({
    window, document, navigator, Notification, EventSource, CustomEvent: Event, Uint8Array,
    atob: value => Buffer.from(value, 'base64').toString('binary'),
    setTimeout: timer, setInterval: timer, clearTimeout: id => timers.delete(id), clearInterval: id => timers.delete(id),
    async fetch(url, settings) {
      equal(url, 'api/notifications.php');
      const body = settings.body ? JSON.parse(settings.body) : null;
      requests.push({ settings, body });
      if (first && options.failFirst) { first = false; return { status: 503, ok: false, json: async () => ({ success: false }) }; }
      first = false;
      if (body) {
        equal(settings.headers['X-CSRF-Token'], 'csrf-test');
        if (body.action === 'subscribe' && failSubscribe) return { status: 503, ok: false, json: async () => ({ success: false, error: 'Save failed' }) };
        if (body.action === 'mark_all_read') { feed.items.forEach(item => { item.is_read = true; }); feed.unread_count = 0; }
        if (body.action === 'mark_read') { feed.items.find(item => item.notification_id === body.notification_id).is_read = true; feed.unread_count--; }
        if (body.action === 'email_preference') feed.email_enabled = body.enabled;
      }
      return { status: 200, ok: true, json: async () => structuredClone(feed) };
    }
  });
  vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/assets/js/notifications.js'), 'utf8'), context);
  return {
    elements, requests, sources, timers, assigned, events, document, windowListeners,
    setFeed(value) { feed = { ...feed, ...value }; }, setFailSubscribe(value) { failSubscribe = value; },
    metrics: () => ({ permissionRequests, registrations, subscriptions, unsubscribes })
  };
}

async function testDashboard() {
  const app = dashboard();
  await settle();
  equal(app.elements.get('notificationPush').disabled, true);
  equal(app.elements.get('notificationPushStatus').textContent, 'HTTPS required');
  equal(app.metrics().permissionRequests, 0, 'The app prompted for push permission on load.');
  equal(app.metrics().registrations, 0, 'The app registered a worker over insecure HTTP.');
  equal(app.sources.length, 1);
  app.sources[0].onopen();
  equal(app.elements.get('notificationStatus').textContent, 'Live');
  const item = { notification_id: 1, inquiry_id: 42, title: '<img onerror=alert(1)>', message: 'Generic update', is_read: false, created_at: 1720000000 };
  app.setFeed({ items: [item], unread_count: 1, latest_id: 1 });
  app.sources[0].emit('notifications', { items: [item], unread_count: 1, cursor: 1 });
  equal(app.elements.get('notificationBadge').textContent, '1');
  equal(app.elements.get('notificationList').children[0].children[0].textContent, item.title);
  equal(app.elements.get('notificationToast').hidden, false);
  equal(app.events.filter(event => event.type === 'helpdesk:notification').length, 1);
  app.sources[0].emit('notifications', { items: [item], unread_count: 1, cursor: 1 });
  equal(app.events.filter(event => event.type === 'helpdesk:notification').length, 1, 'Replayed events produced repeated alerts.');
  await app.elements.get('notificationList').children[0].click();
  await settle();
  equal(app.elements.get('notificationBadge').hidden, true);
  equal(app.assigned, ['dashboard_student.php?inquiry_id=42']);
  await app.elements.get('notificationEmail').trigger('change');
  equal(app.requests.at(-1).body.action, 'email_preference');
  app.sources[0].onerror();
  equal([...app.timers.values()].some(timer => timer.delay === 5000), true, 'Stream failure did not start polling.');
  app.windowListeners.get('pagehide')();
  equal(app.sources[0].closed, true);
  equal([...app.timers.values()].some(timer => timer.delay === 5000), false);
  app.windowListeners.get('pageshow')({ persisted: true });
  await settle();
  equal(app.sources.length, 2, 'Back/forward navigation did not restart the live connection.');
  app.sources[1].emit('signed-out', {});
  equal(app.elements.get('notificationStatus').textContent, 'Signed out');
  equal(app.elements.get('notificationEmail').disabled, true);

  const failed = dashboard({ failFirst: true, noEvents: true });
  await settle();
  equal(failed.elements.get('notificationEmail').disabled, true);
  equal(failed.elements.get('notificationPush').disabled, true);
  await [...failed.timers.values()].find(timer => timer.delay === 5000).callback();
  await settle();
  equal(failed.elements.get('notificationEmail').disabled, false, 'Initial request failure did not recover.');

  const push = dashboard({ secure: true, failSubscribe: true });
  await settle();
  equal(push.elements.get('notificationPush').disabled, false);
  equal(push.metrics().permissionRequests, 0);
  push.elements.get('notificationPush').checked = true;
  await push.elements.get('notificationPush').trigger('change');
  equal(push.elements.get('notificationPush').checked, false, 'Failed registration left browser alerts enabled.');
  equal(push.metrics().unsubscribes, 1, 'A failed save left a newly created subscription behind.');
  push.setFailSubscribe(false);
  push.elements.get('notificationPush').checked = true;
  await push.elements.get('notificationPush').trigger('change');
  equal(push.elements.get('notificationPush').checked, true);
  push.elements.get('notificationPush').checked = false;
  await push.elements.get('notificationPush').trigger('change');
  equal(push.elements.get('notificationPush').checked, false);
  equal(push.requests.at(-1).body.action, 'unsubscribe');
  const existing = dashboard({ secure: true, existingSubscription: true, permission: 'granted' });
  await settle();
  equal(existing.elements.get('notificationPush').checked, true);
  equal(existing.metrics().permissionRequests, 0);
  const denied = dashboard({ secure: true, permission: 'denied' });
  await settle();
  equal(denied.elements.get('notificationPush').disabled, true);
  equal(denied.elements.get('notificationPushStatus').textContent, 'Blocked');
}

async function testWorker() {
  const listeners = new Map();
  const shown = [];
  const opened = [];
  let clients = [];
  const scope = 'https://helpdesk.example.test/public/';
  const self = {
    location: { origin: 'https://helpdesk.example.test' },
    addEventListener: (type, callback) => listeners.set(type, callback),
    skipWaiting: async () => {},
    registration: { scope, async showNotification(title, options) { shown.push({ title, options }); } },
    clients: { claim: async () => {}, matchAll: async () => clients, openWindow: async url => opened.push(url) }
  };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../public/service-worker.js'), 'utf8'), { self, URL });
  async function dispatch(type, event) {
    let pending;
    listeners.get(type)({ ...event, waitUntil: promise => { pending = promise; } });
    await pending;
  }
  await dispatch('install', {});
  await dispatch('activate', {});
  equal(listeners.has('fetch'), false, 'Authenticated pages must not be cached by the worker.');
  const messages = [];
  clients = [{ postMessage: message => messages.push(message) }];
  await dispatch('push', { data: { json: () => ({ title: 'Staff replied', message: 'Generic', notification_id: 7, url: 'dashboard_student.php?inquiry_id=42' }) } });
  equal(shown[0].title, 'Staff replied');
  equal(shown[0].options.data.url, scope + 'dashboard_student.php?inquiry_id=42');
  equal(shown[0].options.tag, 'helpdesk-7');
  equal(messages.length, 1);
  for (const url of ['https://evil.example.test/', 'https://helpdesk.example.test/private', 'http://[invalid']) {
    await dispatch('push', { data: { json: () => ({ url }) } });
    equal(shown.at(-1).options.data.url, scope + 'login.php');
  }
  await dispatch('push', { data: { json: () => null } });
  equal(shown.at(-1).title, 'Helpdesk CRMC');
  await dispatch('push', { data: { json: () => { throw new Error('Malformed'); } } });
  equal(shown.at(-1).options.body, 'There is an update to your concern.');
  const navigated = [];
  let focused = false;
  clients = [{ url: scope + 'dashboard_student.php', navigate: async url => navigated.push(url), focus: async () => { focused = true; } }];
  let closed = false;
  await dispatch('notificationclick', { notification: { data: { url: scope + 'dashboard_student.php?inquiry_id=42' }, close: () => { closed = true; } } });
  equal(closed, true);
  equal(focused, true);
  equal(navigated, [scope + 'dashboard_student.php?inquiry_id=42']);
  clients = [];
  await dispatch('notificationclick', { notification: { data: { url: scope + 'dashboard_staff.php?inquiry_id=42' }, close() {} } });
  equal(opened, [scope + 'dashboard_staff.php?inquiry_id=42']);
}

function testStudentUpdates() {
  const html = fs.readFileSync(path.join(__dirname, '../public/dashboard_student.php'), 'utf8');
  const script = [...html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)].at(-1)[1];
  const listeners = new Map();
  const reply = { value: '', disabled: false };
  const feedback = { value: '', disabled: false };
  let opened = 0;
  vm.runInNewContext(script, {
    activeThreadInquiryId: 42, URLSearchParams, window: { location: { search: '' } },
    openThreadView: () => { opened++; }, renderConcernsList() {},
    document: {
      addEventListener: (type, callback) => listeners.set(type, callback),
      getElementById: id => id === 'concernsView' ? { style: { display: 'none' } } : id === 'threadReplyInput' ? reply : feedback,
      querySelector: selector => selector.includes('selected') ? null : selector.includes('Feedback') ? feedback : reply
    }
  });
  const update = () => listeners.get('helpdesk:notification')({ detail: { inquiry_id: 42 } });
  update();
  equal(opened, 1, 'An idle student thread did not update in real time.');
  feedback.disabled = true;
  update();
  equal(opened, 1, 'An incoming alert interrupted pending feedback.');
  feedback.disabled = false;
  reply.value = 'Unsent draft';
  update();
  equal(opened, 1, 'An incoming alert discarded an unsent reply.');
  reply.value = '';
  feedback.value = 'Unsent feedback';
  update();
  equal(opened, 1, 'An incoming alert discarded unsent feedback.');
}

(async () => {
  await testDashboard();
  await testWorker();
  testStudentUpdates();
  const styles = fs.readFileSync(path.join(__dirname, '../public/assets/css/notifications.css'), 'utf8');
  equal(styles.includes('--notification-accent:var(--amber-dk,#c98a06)'), true, 'Notifications must share the dashboard accent.');
  equal(styles.includes('background:var(--notification-active)'), true, 'Unread notifications must share the dashboard active surface.');
  equal(/#168474|#193c35|#eef7f5|#dce1e5/.test(styles), false, 'The unrelated notification palette remains.');
  console.log('Notification JavaScript regression checks passed: ' + checks);
})().catch(error => { console.error(error); process.exitCode = 1; });
