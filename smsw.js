/**
 * SyncMarks
 *
 * @version 2.1.0
 * @author Offerel
 * @copyright Copyright (c) 2025, Offerel
 * @license GNU General Public License, version 3
 */
const cacheName = 'SyncMarksPWA-v3';
const cacheResources = [
	'./',
	'./manifest.json',
	'./js/syncmarks.js',
	'./js/syncmarks.min.js',
	'./images/bookmarks.ico',
	'./images/bookmarks.png',
	'./images/maskable_icon.png',
	'./images/maskable_icon_x128.png',
	'./images/bookmarks48.png',
	'./images/bookmarks72.png',
	'./images/bookmarks96.png',
	'./images/bookmarks144.png',
	'./images/bookmarks192.png',
	'./images/bookmarks512.png',
	'./css/syncmarks.css',
	'./css/syncmarks.min.css',
	'./smsw.js',
];

const dbName = "syncmarks";
const version = 3;

let db;
let dbRequest = indexedDB.open(dbName, version);

dbRequest.onsuccess = function(event) {
	db = this.result;
};

dbRequest.onupgradeneeded = function(event) {
	let dbResult = event.target.result;
	if (dbResult.objectStoreNames.contains('bookmarks')) {
		dbResult.deleteObjectStore('bookmarks');
	}

	if (dbResult.objectStoreNames.contains('bmAdd')) {
		dbResult.deleteObjectStore('bmAdd');
	}

	if (dbResult.objectStoreNames.contains('bmDel')) {
		dbResult.deleteObjectStore('bmDel');
	}
	
	let store1 = dbResult.createObjectStore('bookmarks');
	let store2 = dbResult.createObjectStore('bmAdd', { keyPath: 'id' });
	let store3 = dbResult.createObjectStore('bmDel', { autoIncrement: true, unique: true });
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

self.addEventListener('fetch', async (event) => {
	var url = event.request.url;
	var clone = event.request.clone();
	if(url.includes('sharelink')) {
		const onShare = async () => {
			const originalData = await event.request.text();
			const shareObject = {};
			const pairs = originalData.split("&");
			for(var i = 0; i < pairs.length; i++) {
				var pos = pairs[i].indexOf('=');       
				if (pos == -1){ continue;}
				const name = pairs[i].substring(0,pos);
				const value = decodeURIComponent(pairs[i].substring(pos+1).replace(/\+/g,  " ")) || null;
				shareObject[name] = value;
			}
	
			const addMark = {
				id: Math.random().toString(24).substring(2, 12),
				title: shareObject['title'] || null,
				url: shareObject['link'],
				type: 'bookmark',
				folder: 'unfiled_____',
				nfolder: 'More Bookmarks',
				added: new Date().valueOf()
			};
			
			let jsonMark = JSON.stringify(addMark);

			self.clients.matchAll().then(clients => 
				clients[0].postMessage({
					'sharemark': jsonMark,
				})
			);
			return fetch(url.replace('?sharelink',''));
		}
		return event.respondWith(onShare());
	}

	if(url.includes('addbm')) {
		const onAdd = async () => {
			self.clients.matchAll().then(clients => 
				clients[0].postMessage({
					'addbm': true,
				})
			);
			return fetch(url.replace('?addbm',''));
		}
		return event.respondWith(onAdd());
	}
	
	event.respondWith(caches.match(event.request).then(cachedResponse => {
		return fetch(event.request, {
			mode: "same-origin",
			credentials: 'same-origin',
			cache: "default"
		}) || cachedResponse
	}).catch(async err => {
		console.warn('SyncMarks seems to be offline. Loading from internal cache/db');

		var clonedBody = await clone.text();
		const pairs = clonedBody.split("&");
		const clonedObject = {};
		let message = 'SyncMarks offline or unreachable.';

		for(var i = 0; i < pairs.length; i++) {
			var pos = pairs[i].indexOf('=');       
			if (pos == -1){ continue;}
			const name = pairs[i].substring(0,pos);
			const value = decodeURIComponent(pairs[i].substring(pos+1).replace(/\+/g,  " ")) || null;
			clonedObject[name] = value;
		}

		if(clonedObject["action"] === 'addmark') {
			bmLater(JSON.parse(clonedObject.data), 'add');
			message = 'SyncMarks offline or unreachable. Send added bookmark later.';
		}

		if(clonedObject["action"] === 'mdel') {
			bmLater(JSON.parse(clonedObject.data), 'del');
			message = 'SyncMarks offline or unreachable. Send deleted bookmark later.';
		}

		self.clients.matchAll().then(clients => 
			clients[0].postMessage({
				'clientOffline': message,
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
			const transaction = db.transaction('bookmarks', "readwrite");
			const store = transaction.objectStore('bookmarks');
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

	if(message.data.type == 'checkIDB') {
		checkIDB();
	}
});

function bmLater(data, action) {
	let openDBRequest = indexedDB.open(dbName);
	let objectstore = '';

	switch(action) {
		case 'add':
			objectstore = 'bmAdd';
			break;
		case 'del':
			objectstore = 'bmDel';
			break;
		default:
			console.warn('Save for later: Unknown action');
			return false;
	}

	openDBRequest.onsuccess = (event) => {
		let db = event.target.result;
		const transaction = db.transaction(objectstore, "readwrite");
		const store = transaction.objectStore(objectstore);
		const addRecord = store.put(data);
	};
}

function checkIDB() {
	let openDBRequest = indexedDB.open(dbName);
	const oStores = ['bmAdd', 'bmDel'];

	openDBRequest.onsuccess = (event) => {
		let db = event.target.result;
		oStores.forEach(oStore => {
			const transaction = db.transaction(oStore, "readonly");
			const objectstore = transaction.objectStore(oStore);
			
			objectstore.getAllKeys().onsuccess = event => {
				let keys = event.target.result;
				objectstore.getAll().onsuccess = event => {
					let entries = event.target.result;
					if(entries.length > 0) {
						sendLater(entries, keys, oStore);
					}
				};
			}
			
		});
	};
}

function sendLater(data, keys, store) {
	switch(store) {
		case 'bmAdd':
			data.forEach(entry => {
				let jsonMark = JSON.stringify(entry);
				delIdbEntry(entry.id, store);
				
				self.clients.matchAll().then(clients => 
					clients[0].postMessage({
						'sharemark': jsonMark,
					})
				);
			});
			break;
		case 'bmDel':
			var delObj = []
			k = 0;
			data.forEach( e => {
				e.forEach( e => {
					delObj.push(e);
				});
				delIdbEntry(keys[k], store);
				k++;
			});
			delObj = [...new Set(delObj)];

			self.clients.matchAll().then(clients => 
				clients[0].postMessage({
					'delmsaved': JSON.stringify(delObj),
				})
			);
			break;
		default:
			return false;
	}
}

function delIdbEntry(key, store) {
	const request = db.transaction(store, 'readwrite').objectStore(store).delete(key);
}