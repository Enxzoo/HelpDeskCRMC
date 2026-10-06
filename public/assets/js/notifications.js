(() => {
  'use strict';
  const root = document.getElementById('notificationCenter');
  if (!root) return;
  const get = id => document.getElementById(id);
  const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const panel = get('notificationPanel');
  const status = get('notificationStatus');
  const pushToggle = get('notificationPush');
  const emailToggle = get('notificationEmail');
  let settings;
  let items = [];
  let cursor = 0;
  let stream;
  let pollTimer;
  let toastTimer;
  let refreshing = false;
  let stopped = false;
  let registration;
  let subscription;
  let pushEnabled = false;
  pushToggle.disabled = true;
  emailToggle.disabled = true;

  async function request(body) {
    const response = await fetch('api/notifications.php', {
      method: body ? 'POST' : 'GET', cache: 'no-store',
      headers: body ? { 'Content-Type': 'application/json', 'X-CSRF-Token': token } : {},
      body: body ? JSON.stringify(body) : undefined
    });
    if (response.status === 401 || response.status === 403) stop();
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.error || 'Notifications are unavailable.');
    return data;
  }

  function stop() {
    stopped = true;
    stream?.close();
    clearInterval(pollTimer);
    pushToggle.disabled = true;
    emailToggle.disabled = true;
    status.textContent = 'Signed out';
  }

  function render(unread) {
    const badge = get('notificationBadge');
    badge.textContent = unread > 99 ? '99+' : String(unread);
    badge.hidden = unread === 0;
    get('notificationToggle').setAttribute('aria-label', unread ? `Notifications, ${unread} unread` : 'Notifications');
    get('notificationReadAll').disabled = unread === 0;
    const list = get('notificationList');
    list.replaceChildren();
    if (!items.length) {
      const empty = document.createElement('p');
      empty.className = 'notification-empty';
      empty.textContent = 'No notifications yet';
      list.appendChild(empty);
    }
    for (const item of items) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `notification-item${item.is_read ? '' : ' unread'}`;
      const title = document.createElement('strong');
      title.textContent = item.title;
      const message = document.createElement('p');
      message.textContent = item.message;
      const time = document.createElement('time');
      const date = new Date(item.created_at * 1000);
      time.dateTime = date.toISOString();
      time.textContent = date.toLocaleString();
      button.append(title, message, time);
      button.addEventListener('click', () => open(item).catch(showError));
      list.appendChild(button);
    }
  }

  function notify(newItems) {
    if (!newItems.length) return;
    const latest = newItems.at(-1);
    const toast = get('notificationToast');
    toast.textContent = `${latest.title}: ${latest.message}`;
    toast.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.hidden = true; }, 6000);
    for (const notification of newItems) {
      document.dispatchEvent(new CustomEvent('helpdesk:notification', { detail: notification }));
    }
  }

  function apply(data, announce = false) {
    const newItems = data.items.filter(item => item.notification_id > cursor);
    items = data.items;
    cursor = Math.max(cursor, data.latest_id);
    settings = data;
    emailToggle.checked = data.email_enabled;
    emailToggle.disabled = false;
    get('notificationEmailStatus').textContent = data.email_ready ? '' : 'Not configured';
    render(data.unread_count);
    if (announce) notify(newItems.sort((a, b) => a.notification_id - b.notification_id));
  }

  function showError(error) {
    status.textContent = error.message || 'Connection interrupted';
  }

  async function refresh(announce = true) {
    if (refreshing || stopped) return;
    refreshing = true;
    try {
      const initial = !settings;
      apply(await request(), announce && !initial);
      if (initial) {
        startStream();
        try { await setupPush(); }
        catch (error) { pushToggle.disabled = true; showError(error); }
      }
    }
    catch (error) { showError(error); startPolling(); }
    finally { refreshing = false; }
  }

  function startStream() {
    if (stopped) return;
    if (!('EventSource' in window)) { startPolling(); return; }
    stream?.close();
    stream = new EventSource(`api/notification_stream.php?after=${cursor}`);
    stream.onopen = () => { status.textContent = 'Live'; clearInterval(pollTimer); pollTimer = undefined; };
    stream.addEventListener('notifications', event => {
      try {
        const data = JSON.parse(event.data);
        const fresh = data.items.filter(item => item.notification_id > cursor);
        const merged = new Map([...items, ...data.items].map(item => [item.notification_id, item]));
        items = [...merged.values()].sort((a, b) => b.notification_id - a.notification_id).slice(0, 30);
        cursor = Math.max(cursor, data.cursor);
        render(data.unread_count);
        notify(fresh);
        if (!data.items.length) refresh(false);
      } catch { status.textContent = 'Reconnecting...'; refresh(); }
    });
    stream.addEventListener('signed-out', stop);
    stream.addEventListener('reconnect', () => { stream.close(); startPolling(); });
    stream.onerror = () => { status.textContent = 'Reconnecting...'; startPolling(); };
  }

  function startPolling() {
    if (pollTimer || stopped) return;
    pollTimer = setInterval(() => refresh(), 5000);
  }

  async function open(item) {
    apply(await request({ action: 'mark_read', notification_id: item.notification_id }));
    panel.hidden = true;
    get('notificationToggle').setAttribute('aria-expanded', 'false');
    if (!item.inquiry_id) return;
    const event = new CustomEvent('helpdesk:open-notification', { detail: item, cancelable: true });
    if (!document.dispatchEvent(event)) return;
    const page = settings.role === 'student' ? 'dashboard_student.php' : (settings.role === 'admin' ? 'dashboard_admin.php' : 'dashboard_staff.php');
    window.location.assign(`${page}?inquiry_id=${Number(item.inquiry_id)}`);
  }

  function applicationKey(value) {
    const binary = atob(value.replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(binary, character => character.charCodeAt(0));
  }

  async function setupPush() {
    if (!window.isSecureContext) {
      pushToggle.disabled = true;
      get('notificationPushStatus').textContent = 'HTTPS required';
      return;
    }
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
      pushToggle.disabled = true;
      get('notificationPushStatus').textContent = 'Unavailable';
      return;
    }
    if (!settings.push_ready) {
      pushToggle.disabled = true;
      get('notificationPushStatus').textContent = 'Not configured';
      return;
    }
    registration = await navigator.serviceWorker.register('service-worker.js');
    registration = await navigator.serviceWorker.ready;
    subscription = await registration.pushManager.getSubscription();
    if (subscription && Notification.permission === 'granted') {
      await request({ action: 'subscribe', subscription: subscription.toJSON() });
      pushEnabled = true;
      pushToggle.checked = true;
    }
    if (Notification.permission === 'denied') {
      pushToggle.disabled = true;
      get('notificationPushStatus').textContent = 'Blocked';
    } else {
      pushToggle.disabled = false;
    }
  }

  get('notificationToggle').addEventListener('click', () => {
    panel.hidden = !panel.hidden;
    get('notificationToggle').setAttribute('aria-expanded', String(!panel.hidden));
    if (!panel.hidden) { refresh(false); get('notificationClose').focus(); }
  });
  get('notificationClose').addEventListener('click', () => {
    panel.hidden = true;
    get('notificationToggle').setAttribute('aria-expanded', 'false');
    get('notificationToggle').focus();
  });
  document.addEventListener('click', event => {
    if (!root.contains(event.target)) { panel.hidden = true; get('notificationToggle').setAttribute('aria-expanded', 'false'); }
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !panel.hidden) get('notificationClose').click();
  });
  get('notificationReadAll').addEventListener('click', async () => {
    try { apply(await request({ action: 'mark_all_read' })); }
    catch (error) { showError(error); }
  });
  emailToggle.addEventListener('change', async () => {
    emailToggle.disabled = true;
    try { apply(await request({ action: 'email_preference', enabled: emailToggle.checked })); }
    catch (error) { emailToggle.checked = settings.email_enabled; showError(error); }
    finally { emailToggle.disabled = false; }
  });
  pushToggle.addEventListener('change', async () => {
    pushToggle.disabled = true;
    const wasEnabled = pushEnabled;
    try {
      if (pushToggle.checked) {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') throw new Error('Browser alerts are not permitted.');
        subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: applicationKey(settings.push_public_key) });
        await request({ action: 'subscribe', subscription: subscription.toJSON() });
        pushEnabled = true;
      } else if (subscription) {
        await request({ action: 'unsubscribe', endpoint: subscription.endpoint });
        pushEnabled = false;
        await subscription.unsubscribe();
        subscription = null;
      }
    } catch (error) {
      if (!wasEnabled && !pushEnabled && subscription) {
        try { await subscription.unsubscribe(); subscription = null; } catch { /* Retry on the next explicit attempt. */ }
      }
      showError(error);
    }
    finally {
      pushToggle.checked = pushEnabled;
      pushToggle.disabled = stopped || Notification.permission === 'denied';
    }
  });
  window.addEventListener('pagehide', () => { stream?.close(); clearInterval(pollTimer); pollTimer = undefined; });
  window.addEventListener('pageshow', event => { if (event.persisted) { refresh(false); startStream(); } });
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  if ('serviceWorker' in navigator) navigator.serviceWorker.addEventListener('message', () => refresh());
  refresh(false);
})();
