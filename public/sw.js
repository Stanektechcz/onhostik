// OnHost service worker — web push (audit 92).
// Receives an encrypted push, shows a notification, and focuses/opens the panel
// when the user clicks it.

self.addEventListener('push', function (event) {
  var data = {};
  try { data = event.data ? event.data.json() : {}; } catch (e) { data = { body: event.data ? event.data.text() : '' }; }

  var title = data.title || 'OnHost';
  var options = {
    body: data.body || '',
    icon: '/panel/svg/icon-192.png',
    badge: '/panel/svg/badge-72.png',
    data: { url: data.url || '/panel' },
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var url = (event.notification.data && event.notification.data.url) || '/panel';
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
      for (var i = 0; i < clients.length; i++) {
        if (clients[i].url.indexOf(url) !== -1 && 'focus' in clients[i]) return clients[i].focus();
      }
      if (self.clients.openWindow) return self.clients.openWindow(url);
    })
  );
});
