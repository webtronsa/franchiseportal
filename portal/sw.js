// Service worker for the franchise portal. It caches only static, non-personal files.
// It never caches the portal page, API responses, sign-in redirects or any franchise data.
const CACHE = 'mnx-portal-static-v2';
const STATIC = ['offline.html', 'manifest.webmanifest', 'img/mascots.webp', 'img/nastix_dot_zone_logo.webp', 'img/icon-192.png', 'img/icon-512.png'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(STATIC)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/api/')) return;

  // Page navigations are never cached: the portal page is personal. Offline, show the static offline page.
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match('offline.html')));
    return;
  }

  // Static assets only: cache-first, filled from the network on a miss. Responses are stored only if they are ok.
  const name = url.pathname.replace(/^\/portal\//, '').replace(/^\//, '');
  if (!STATIC.includes(name)) return;
  event.respondWith(
    caches.match(req).then((hit) => hit || fetch(req).then((res) => {
      if (res.ok && res.type === 'basic') caches.open(CACHE).then((c) => c.put(req, res.clone()));
      return res;
    }))
  );
});
