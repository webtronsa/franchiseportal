// Service worker for the franchise portal. It caches only the static app shell.
// It never caches API responses, sign-in redirects or any franchise data.
const CACHE = 'mnx-portal-shell-v1';
const SHELL = ['./', 'index.html', 'manifest.webmanifest', 'img/mascots.webp', 'img/nastix_dot_zone_logo.webp', 'img/icon-192.png', 'img/icon-512.png'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL)));
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
  // Only same-origin GETs of shell files. Everything else goes straight to the network.
  if (req.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/api/')) return;
  const isShell = req.mode === 'navigate' || SHELL.some((p) => url.pathname.endsWith('/' + p.replace('./', '')) || url.pathname === '/' + p);
  if (!isShell) return;
  event.respondWith(
    fetch(req).then((res) => {
      if (res.ok && res.type === 'basic') caches.open(CACHE).then((c) => c.put(req, res.clone()));
      return res;
    }).catch(() => caches.match(req).then((hit) => hit || caches.match('index.html')))
  );
});
