const CACHE_NAME = "SyncMarksPWA-v1";
const urlsToCache = [
    '../js/bookmarks.js',
    '../images/bookmarks.png'
];

self.addEventListener('install', function(event) {
    // Perform install steps
        event.waitUntil(
            caches.open(CACHE_NAME)
            .then(function(cache) {
                console.log('Opened cache');
            return cache.addAll(urlsToCache);
            })
        );
        console.log("SyncMarks worker installed");
    });

self.addEventListener('fetch', event => {
    event.respondWith(
      caches.match(event.request)
        .then(function(response) {
          // Cache hit - return response
          if (response) {
            return response;
          }
          return fetch(event.request);
        }
      )
    );
  });

self.addEventListener("activate", event => {
    var cacheWhitelist = ['SyncMarksPWA-v1'];

    event.waitUntil(
        caches.keys().then(function(cacheNames) {
        return Promise.all(
            cacheNames.map(function(cacheName) {
            if (cacheWhitelist.indexOf(cacheName) === -1) {
                return caches.delete(cacheName);
            }
            })
        );
        })
    );
    console.log("SyncMarks worker activated");
});
 