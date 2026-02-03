const CACHE_NAME = 'pdc-sim-2026-v2.1.0';
const APP_VERSION = '2.1.0';

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

// Install event - cache resources and skip waiting immediately
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache:', CACHE_NAME);
        return cache.addAll(urlsToCache);
      })
      .catch(err => {
        console.log('Cache install error:', err);
      })
  );
  // Force the waiting service worker to become active immediately
  self.skipWaiting();
});

// Activate event - clean up old caches and take control immediately
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
    }).then(() => {
      // Notify all clients that there's a new version
      self.clients.matchAll().then(clients => {
        clients.forEach(client => {
          client.postMessage({ type: 'SW_UPDATED', version: APP_VERSION });
        });
      });
    })
  );
  // Take control of all pages immediately
  self.clients.claim();
});

// Fetch event - Network first for HTML, cache first for assets
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  
  // For HTML files - always try network first to get latest version
  if (event.request.mode === 'navigate' || url.pathname.endsWith('.html') || url.pathname === '/' || url.pathname === '') {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // Clone and cache the new response
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, responseToCache);
          });
          return response;
        })
        .catch(() => {
          // Fallback to cache if offline
          return caches.match(event.request).then(response => {
            return response || caches.match('/darts.html');
          });
        })
    );
    return;
  }
  
  // For other assets - cache first, then network
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) {
          // Return cached version but also fetch and update cache in background
          fetch(event.request).then(networkResponse => {
            if (networkResponse && networkResponse.status === 200) {
              caches.open(CACHE_NAME).then(cache => {
                cache.put(event.request, networkResponse);
              });
            }
          }).catch(() => {});
          return response;
        }
        
        return fetch(event.request).then(response => {
          if (!response || response.status !== 200 || event.request.method !== 'GET') {
            return response;
          }
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, responseToCache);
          });
          return response;
        });
      })
      .catch(() => {
        return caches.match('/darts.html');
      })
  );
});

// Listen for skip waiting message from client
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
