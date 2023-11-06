const cacheName = 'SyncMarksPWA-v1';
const cacheResources = [
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
	console.log(dbName + " IndexedDB opened successfully");
	db = this.result;
};

dbRequest.onupgradeneeded = function(event) {
	let dbResult = event.target.result;
	if (dbResult.objectStoreNames.contains(dbStoreName)) {
		dbResult.deleteObjectStore(dbStoreName);
	}

	let store = dbResult.createObjectStore(
		dbStoreName, { autoIncrement: true }
	);
};

self.addEventListener('install', event => {
	console.log('Service Worker install event');
	event.waitUntil(
		caches.open(cacheName).then(cache => {
			console.log("Service Worker: Caching files");
			return cache.addAll(cacheResources);
		})
		.catch(err => console.error(err))
		.then(event => {
			/*
			let url = self.location.origin + self.location.pathname.slice(0, self.location.pathname.lastIndexOf('/')) + '/';

			let details = {
				'action': 'bexport',
				'data': 'json',
				'client': 'PWA'
			};

			let formBody = [];
			for (let property in details) {
				let encodedKey = encodeURIComponent(property);
				let encodedValue = encodeURIComponent(details[property]);
				formBody.push(encodedKey + "=" + encodedValue);
			}
			formBody = formBody.join("&");

			fetch(url, {
				method: "POST",
				mode: "cors",
				cache: "no-cache",
				credentials: "same-origin",
				headers: {
					"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
				},
				redirect: "follow",
				referrerPolicy: "no-referrer",
				body: formBody,
				json: true
			}).then(response => response.json()).then(responseData => {
				const bookmarks = JSON.parse(responseData);
				test();
				//bookmarks.forEach(bookmark => {
					//console.log(bookmark);
				//	addToStore(bookmark['bmID'], bookmark, 'bookmarks')
				//});
			});
			*/
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
	console.log('Service Worker: fetching');
	self.clients.matchAll().then(clients => 
		clients[0].postMessage({
			'clientOffline': true,
		})
	);
	event.respondWith(caches.match(event.request).then(cachedResponse => {
		return cachedResponse || fetch(event.request)
	}).catch(err => {
		self.clients.matchAll().then(clients => 
			clients[0].postMessage({
				'clientOffline': true,
			})
		);
	}))
	/*
	if(event.request.method == 'POST') {
		let requestClone = event.request.clone();
		const params = await requestClone.text().catch((err) => err);
		if(params.includes('slink')) {
			event.respondWith(fetch(event.request));
			return;
		}

		const formDataPromise = event.request.formData();
		event.respondWith(
			formDataPromise.then((formData) => {
				const link = formData.get("slink") || "";
				const title = formData.get("title") || "";
				addToStore(title, link);
			})
		);
	}
	*/
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
			const addRecord = store.put({ bookmarks });
	  
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