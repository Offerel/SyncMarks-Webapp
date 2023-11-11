const cacheName = 'SyncMarksPWA-v1';
const cacheResources = [
	'./',
	'manifest.json',
	'js/bookmarks.js',
	'js/bookmarks.min.js',
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

const dbName = "syncmarks";
const version = 1;
const dbStoreName = "bookmarks";

let db;
let dbRequest = indexedDB.open(dbName, version);

dbRequest.onsuccess = function(event) {
	db = this.result;
};

dbRequest.onupgradeneeded = function(event) {
	let dbResult = event.target.result;
	if (dbResult.objectStoreNames.contains(dbStoreName)) {
		dbResult.deleteObjectStore(dbStoreName);
	}
	
	let store = dbResult.createObjectStore(dbStoreName);
};

self.addEventListener('install', event => {
	event.waitUntil(
		caches.open(cacheName).then(cache => {
			return cache.addAll(cacheResources);
		}).catch(err => console.error(err))
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
	let requestClone = event.request.clone();
	requestClone.text().then(data => {
		if(data.includes('slink')) {
			const urlObject = {};
			const pairs = data.split("&");
			for(var i = 0; i < pairs.length; i++) {
				var pos = pairs[i].indexOf('=');       
				if (pos == -1){ continue;}
				const name = pairs[i].substring(0,pos);
				const value = decodeURIComponent(pairs[i].substring(pos+1).replace(/\+/g,  " ")) || null;
				urlObject[name] = value;
			}

			self.clients.matchAll().then(clients => 
				clients[0].postMessage({
					'sharemark': urlObject,
				})
			);
			return false;
		}
	})

	event.respondWith(caches.match(event.request).then(cachedResponse => {
		return fetch(event.request, {
			mode: "same-origin",
			credentials: 'same-origin',
			cache: "default"
		}) || cachedResponse
	}).catch(err => {
		console.warn('SyncMarks seems to be offline. Loading from internal cache/db');
		self.clients.matchAll().then(clients => 
			clients[0].postMessage({
				'clientOffline': true,
			})
		);
	}))
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

self.addEventListener('message', message => {
	if(message.data.type == 'bookmarks') {
		let bookmarks = message.data.data;
		let openDBRequest = indexedDB.open(dbName);

		openDBRequest.onsuccess = (event) => {
			let db = event.target.result;
			const transaction = db.transaction(dbStoreName, "readwrite");
			const store = transaction.objectStore(dbStoreName);
			const addRecord = store.put( bookmarks, 'bookmarks' );
	  
			addRecord.onsuccess = (event) => {
				self.clients.matchAll().then(clients => 
					clients[0].postMessage({
						'bookmarksAddedDB': true,
					})
				);
			}
		};
	}
});