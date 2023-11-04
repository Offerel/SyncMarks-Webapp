const CACHE_NAME = 'SyncMarksPWA-v1';
const urlsToCache = [
		'js/bookmarks.js',
		'js/bookmarks.min.js',
		'smsw.js',
		'images/bookmarks.ico',
		'images/bookmarks.png',
		'images/bookmarks48.png',
		'images/bookmarks72.png',
		'images/bookmarks96.png',
		'images/bookmarks144.png',
		'images/bookmarks192.png',
		'images/bookmarks512.png',
		'css/bookmarks.css',
		'css/bookmarks.min.css',
];

self.addEventListener('install', event => {
	event.waitUntil(
		caches.open(CACHE_NAME).then(function(cache) {
			return cache.addAll(urlsToCache);
		})
	);
});

self.addEventListener('activate', event => {
	var cacheWhitelist = ['SyncMarksPWA-v1'];
	clients.claim();
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
});

self.addEventListener('fetch', async event => {
	if(event.request.method == 'POST') {
		let requestClone = event.request.clone();
		const params = await requestClone.text().catch((err) => err);
		if(params.includes('title')) {		
			self.clients.matchAll().then((clients) => { 
                clients.forEach((client) => { 
                    client.postMessage({  
                        type: 'openDialog',  
                        data: params 
                    }) 
                }) 
            })
		}
	}

	event.respondWith(
		caches.match(event.request).then(function(response) {
			if (response) {
				return response;
			}
			return fetch(event.request);
		})
	);
});

self.addEventListener('push', event => {
	let notification = event.data.json();
	//Test JSON for push: {"title":"Test title","url":"https://domain.com/path/document"}
	event.waitUntil(
		self.registration.showNotification(notification.title, {
			body: notification.url,
			icon: './images/bookmarks192.png',
			requireInteraction: true
		}),
	);
});

self.addEventListener('notificationclick', (event) => {
	event.notification.close();
	event.waitUntil(clients.matchAll({
		type: "window",
	}).then((clientList) => {
		for (const client of clientList) {
			if (client.url === event.notification.body && "focus" in client) return client.focus();
    	}
		if (clients.openWindow) return clients.openWindow(event.notification.body);
	}))
});