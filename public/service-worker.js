'use strict';
self.addEventListener('install', event => event.waitUntil(self.skipWaiting()));
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
function notificationUrl(value) {
  const fallback = new URL('login.php', self.registration.scope).href;
  try {
    const target = new URL(typeof value === 'string' ? value : fallback, self.registration.scope);
    return target.origin === self.location.origin && target.href.startsWith(self.registration.scope) ? target.href : fallback;
  } catch { return fallback; }
}
self.addEventListener('push', event => {
  event.waitUntil((async () => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch { /* Keep a generic alert for malformed payloads. */ }
    if (!data || typeof data !== 'object') data = {};
    const url = notificationUrl(data.url);
    await self.registration.showNotification(typeof data.title === 'string' ? data.title : 'Helpdesk CRMC', {
      body: typeof data.message === 'string' ? data.message : 'There is an update to your concern.',
      icon: new URL('assets/images/helpdesk-logo.png', self.registration.scope).href,
      tag: `helpdesk-${Number(data.notification_id) || 'update'}`,
      data: { url }
    });
    for (const client of await self.clients.matchAll({ type: 'window', includeUncontrolled: true })) client.postMessage({ type: 'notification' });
  })());
});
self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil((async () => {
    const target = new URL(notificationUrl(event.notification.data?.url));
    for (const client of await self.clients.matchAll({ type: 'window', includeUncontrolled: true })) {
      if (new URL(client.url).origin === target.origin && 'navigate' in client) {
        await client.navigate(target.href);
        await client.focus();
        return;
      }
    }
    await self.clients.openWindow(target.href);
  })());
});
