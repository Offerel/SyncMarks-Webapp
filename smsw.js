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

const dbName = "bookmarks";
const version = 1;
const storeName = "sharedlinks";
let db;

self.addEventListener('install', event => {
	console.log('Service Worker install event');
	event.waitUntil(
		caches.open(cacheName).then(cache => {
			console.log("Service Worker: Caching files");
			return cache.addAll(cacheResources);
		})
		.catch(err => console.error(err))
		.then(event => {
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
				console.log(responseData);
			});
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
	event.respondWith(caches.match(event.request).then(cachedResponse => {
		return cachedResponse || fetch(event.request)
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

function addToStore(title, url) {
	let openDBRequest = indexedDB.open(dbName);

	openDBRequest.onsuccess = (event) => {
		const transaction = db.transaction(storeName, "readwrite");
		const store = transaction.objectStore(storeName);
		const request = store.put({ title, url });

		request.onsuccess = function () {
			console.log("added to the store", { title: url }, request.result);
		};

		request.onerror = function () {
			console.log("Error did not save to store", request.error);
		};

		transaction.onerror = function (event) {
			console.log("trans failed", event);
		};

		transaction.oncomplete = function (event) {
			console.log("trans completed", event);
		};
	}
}

const cacheFirst = async (request) => {
	const responseFromCache = await cache.match(request);
	if (responseFromCache) {
		return responseFromCache;
	}
	return fetch(request);
  };