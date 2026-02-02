const CACHE_NAME = 'pdc-sim-2026-v1';
const urlsToCache = [
  '/darts.html',
  '/icon.png',
  '/darts.png',
  '/bottom_bg.png',
  '/endscreen_bg.png',
  '/pdc-logo.png',
  '/pdc-logo.webp',
  '/worldchampionship.png',
  '/worldmatchplay.png',
  '/worldmasters.png',
  '/worldseries.png',
  '/worldseriesofdarts.png',
  '/premierleague.png',
  '/ukopen.png',
  '/grandprix.png',
  '/europeanchampionship.png',
  '/mrvegas.png',
  '/playersc.png',
  '/180.mp3',
  '/167.MP3',
  '/bigcheckout.MP3',
  '/intro.MP3',
  '/outromusic.MP3'
];

// Install event - cache resources
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
      .catch(err => {
        console.log('Cache install error:', err);
      })
  );
  self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Return cached version or fetch from network
        if (response) {
          return response;
        }
        return fetch(event.request).then(response => {
          // Don't cache non-successful responses or non-GET requests
          if (!response || response.status !== 200 || response.type !== 'basic' || event.request.method !== 'GET') {
            return response;
          }
          // Clone the response
          const responseToCache = response.clone();
          caches.open(CACHE_NAME)
            .then(cache => {
              cache.put(event.request, responseToCache);
            });
          return response;
        });
      })
      .catch(() => {
        // Return offline fallback if available
        return caches.match('/darts.html');
      })
  );
});
