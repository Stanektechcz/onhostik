/* OnHost service worker — web push + offline app shell (PWA).
 *
 * Caching strategy, chosen per resource kind:
 *  - navigations  → network-first, falling back to the cached page and finally
 *                   to /offline. A stale panel page is far better than a dino.
 *  - static assets → stale-while-revalidate: instant paint, refreshed in the
 *                   background.
 *  - API/auth/mutations → never cached. Stale money or stale service state is
 *                   worse than an error, and caching POSTs is a correctness bug.
 */

var VERSION      = 'onhost-v2';
var SHELL_CACHE  = VERSION + '-shell';
var ASSET_CACHE  = VERSION + '-assets';
var PAGE_CACHE   = VERSION + '-pages';
var OFFLINE_URL  = '/offline';

var SHELL = [
  OFFLINE_URL,
  '/panel/images/logo/logo-icon.png',
];

// ── lifecycle ───────────────────────────────────────────────────────────────

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(SHELL_CACHE)
      .then(function (cache) { return cache.addAll(SHELL); })
      .then(function () { return self.skipWaiting(); })
      .catch(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.map(function (key) {
        // Drop caches from previous versions.
        if (key.indexOf(VERSION) !== 0) return caches.delete(key);
        return null;
      }));
    }).then(function () { return self.clients.claim(); })
  );
});

// Lets the page trigger an immediate update after a deploy.
self.addEventListener('message', function (event) {
  if (event.data === 'skip-waiting') self.skipWaiting();
});

// ── fetch strategies ────────────────────────────────────────────────────────

function isCacheableAsset(url) {
  return /\.(css|js|png|jpe?g|svg|gif|webp|avif|woff2?|ttf|eot|ico)$/i.test(url.pathname);
}

function neverCache(url) {
  return url.pathname.indexOf('/api/') === 0
    || url.pathname.indexOf('/oauth/') === 0
    || url.pathname.indexOf('/login') === 0
    || url.pathname.indexOf('/logout') === 0
    || url.pathname.indexOf('/livewire') === 0
    || url.pathname.indexOf('/panel/hledat') === 0;
}

self.addEventListener('fetch', function (event) {
  var request = event.request;

  // Only GET is safe to serve from a cache.
  if (request.method !== 'GET') return;

  var url;
  try { url = new URL(request.url); } catch (e) { return; }

  // Cross-origin and sensitive paths go straight to the network.
  if (url.origin !== self.location.origin || neverCache(url)) return;

  // Navigations: network-first, cached page, then the offline shell.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then(function (response) {
          var copy = response.clone();
          caches.open(PAGE_CACHE).then(function (cache) { cache.put(request, copy); });
          return response;
        })
        .catch(function () {
          return caches.match(request).then(function (cached) {
            return cached || caches.match(OFFLINE_URL);
          });
        })
    );
    return;
  }

  // Static assets: stale-while-revalidate.
  if (isCacheableAsset(url)) {
    event.respondWith(
      caches.match(request).then(function (cached) {
        var network = fetch(request).then(function (response) {
          if (response && response.status === 200) {
            var copy = response.clone();
            caches.open(ASSET_CACHE).then(function (cache) { cache.put(request, copy); });
          }
          return response;
        }).catch(function () { return cached; });

        return cached || network;
      })
    );
  }
});

// ── web push (audit 92) ─────────────────────────────────────────────────────

self.addEventListener('push', function (event) {
  var data = {};
  try { data = event.data ? event.data.json() : {}; } catch (e) { data = { body: event.data ? event.data.text() : '' }; }

  var title = data.title || 'OnHost';
  var options = {
    body: data.body || '',
    icon: '/panel/images/logo/logo-icon.png',
    badge: '/panel/images/logo/logo-icon.png',
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
