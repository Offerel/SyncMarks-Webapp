const CACHE_NAME = "SyncMarksPWA-v1";
const urlsToCache = [
		'../js/bookmarks.js',
		'../images/bookmarks.png'
];

self.addEventListener('install', event => {
	event.waitUntil(
		caches.open(CACHE_NAME).then(function(cache) {
			console.log('Opened cache');
			return cache.addAll(urlsToCache);
		})
	);
	console.log("SyncMarks worker installed");
});

self.addEventListener('fetch', event => {
	console.log(event.request.url);
	console.log(event.request.method);
	event.respondWith(
		caches.match(event.request).then(function(response) {
			if (response) {
				alert(response);
				return response;
			}
			return fetch(event.request);
		})
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

self.addEventListener("push", event => {
	let notification = event.data.json();
	//Test JSON for push: {"title":"Test title","url":"https://developers.google.com/learn/pathways/pwa-push-notifications"}
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