/**
 * SyncMarks
 *
 * @version 2.0.3
 * @author Offerel
 * @copyright Copyright (c) 2024, Offerel
 * @license GNU General Public License, version 3
 */
const dbName = "syncmarks";
const dbStoreName = "bookmarks";
let db, translation;
let dbRequest = indexedDB.open(dbName);

document.addEventListener("DOMContentLoaded",function() {
	if ("serviceWorker" in navigator) {
		try {
			const registration = navigator.serviceWorker.register("smsw.js");
		} catch (error) {
			console.error(`ServiceWorker registration failed with ${error}`);
			pwaMessage(`ServiceWorker registration failed with ${error}`, 'error');
		}
	}

	navigator.serviceWorker.addEventListener('message', event => {
		if (event.data && event.data.type === 'openDialog') {
			showDialog('bmarkadd');
			document.getElementById('url').value = event.data.data;
		}

		if (event.data.addbm) {
			document.getElementById('footer').click();
			return false;
		}

		if (event.data.sharemark) {
			sendRequest(addmark, event.data.sharemark, 2);
			sessionStorage.setItem('gNoti', 1);
			return false;
		}

		if (event.data.delmsaved) {
			sendRequest(mdel, event.data.delmsaved);
			return false;
		}

		if (event.data.bookmarksAddedDB) {
			//console.log("Bookmarks saved");
		}

		if (event.data.clientOffline) {
			if(document.getElementById('db-spinner')) document.getElementById('db-spinner').remove();
			console.warn(event.data.clientOffline);
			pwaMessage(event.data.clientOffline, 'warn');
			let openDBRequest = indexedDB.open(dbName);
			openDBRequest.onsuccess = (event) => {
				let db = event.target.result;
				const transaction = db.transaction(dbStoreName, "readwrite");
				const store = transaction.objectStore(dbStoreName);
				const getRecord = store.get('bookmarks');

				getRecord.onsuccess = function(event) {
					document.getElementById('bookmarks').innerHTML = getRecord.result;
					document.getElementById('hmarks').innerHTML = getRecord.result;
					addBookmarkEvents();
				};
			}
		}
	})

	navigator.serviceWorker.addEventListener("controllerchange", event => {
		//confirm('sw changed');
	});

	setLanguage(document.documentElement.lang);

	if(window.location.href.slice(-1) === '?') window.history.replaceState({}, null, window.location.href.substring(0, window.location.href.length - 1));
	 
	if(document.getElementById('preset')) document.getElementById('preset').addEventListener('click', function(e){
		e.preventDefault();
		let data = "reset=request&u="+e.target.dataset.reset;
		let url = location.protocol + '//' + location.host + location.pathname;
		const xhr = new XMLHttpRequest();
		xhr.open("GET", url+"?"+data, true);
		xhr.onreadystatechange = function() {
			if(this.readyState == 4 && this.status == 200) {
				if(JSON.parse(this.responseText) == 1) {
					let div = document.getElementById('loginformt');
					div.classList.toggle('info');
					div.innerText = translation.messages.passwordResetSend;
				}
			}
		}
		xhr.send(null);
	});

	if(document.getElementById("uf")) document.getElementById("uf").focus();
	if(document.getElementById('loginbody')) {
		document.getElementById('hmenu').classList.add('inlogin1');
		document.querySelector('#menu button').classList.add('inlogin2');
	}

	document.querySelectorAll('.dclose').forEach(e => {
		e.addEventListener('click', el => {
			hideMenu();
		});
	});

	if(document.getElementById('bookmarks')) {
		document.querySelector('#menu input').addEventListener('keyup', function(e) {
			var sfilter = this.value;
			var allmarks = document.querySelectorAll('#bookmarks li.file');
			var bdiv = document.getElementById('bookmarks');
			bdiv.innerHTML = '';
			allmarks.forEach(bookmark => {
				bdiv.appendChild(bookmark);
				if(bookmark.innerText.toUpperCase().includes(sfilter.toUpperCase()) || bookmark.firstChild.dataset.url.toUpperCase().includes(sfilter.toUpperCase())) {
					bookmark.style.display = 'block';
					bookmark.style.paddingLeft = '20px';
				} else {
					bookmark.style.display = 'none';
				}
			});
			if((sfilter == "") || (e.keyCode == 27)) {
				bdiv.innerHTML = document.getElementById('hmarks').innerHTML;
				document.querySelector('#menu input').value = '';
			}
		});

		document.querySelector('#menu button').addEventListener('click', function() {
			if(document.querySelector('#menu button').innerHTML == '\u00D7') {
				document.querySelector('#menu input').blur();
				document.querySelector('#menu button').innerHTML = '\u2315';
				document.querySelector('#menu button').classList.remove('asform');
				document.querySelector('#menu input').classList.remove('asform');
				document.querySelector('#menu input').classList.add('isform');
				document.getElementById('mprofile').style.display = 'block';
			}
			else {				
				document.querySelector('#menu button').innerHTML = '\u00D7';
				document.querySelector('#menu button').classList.add('asform');
				document.querySelector('#menu input').classList.remove('isform');
				document.querySelector('#menu input').classList.add('asform');
				document.getElementById('mprofile').style.display = 'none';
				document.querySelector('#menu input').focus();
			}
			document.querySelector('#menu input').value = '';
			hideMenu();
		});

		document.getElementById('mprofile').addEventListener('click', function() {
			document.querySelector('#menu input').value = '';
			if(document.querySelector('#menu button').innerHTML == '\u00D7') {				
				document.querySelector('#menu input').blur();
				document.querySelector('#menu button').innerHTML = '\u2315';
				document.querySelector('#menu button').classList.remove('asform');
				document.querySelector('#menu input').classList.remove('asform');
				document.querySelector('#menu input').classList.add('isform');
				document.getElementById('mprofile').style.display = 'block';
			}
			else {
				document.querySelector('#menu button').innerHTML = '\u00D7';
				document.querySelector('#menu button').classList.add('asform');
				document.querySelector('#menu input').classList.remove('isform');
				document.querySelector('#menu input').classList.add('asform');
				document.getElementById('mprofile').style.display = 'none';
				document.querySelector('#menu input').focus();
			}
			hideMenu();
		});

		document.getElementById('bmsearch').addEventListener('keydown', function(e) {
			if (e.key === 'Escape') {
				document.querySelector('#menu input').blur();
				document.querySelector('#menu button').innerHTML = '\u2315';
				document.querySelector('#menu button').classList.remove('asform');
				document.querySelector('#menu input').classList.remove('asform');
				document.querySelector('#menu input').classList.add('isform');
				document.getElementById('mprofile').style.display = 'block';
				document.getElementById('bookmarks').innerHTML = document.getElementById('hmarks').innerHTML;
			}
		});

		if(document.getElementById("logfile")) {
			document.getElementById("logfile").addEventListener("mousedown", function(e){
				if (e.offsetX < 3) {
					document.addEventListener("mousemove", resize, false);
				}
			}, false);
		}

		document.querySelectorAll('.tablinks').forEach(tab => tab.addEventListener('click',openMessages, false));
		document.querySelectorAll('.NotiTableCell .fa-trash').forEach(message => message.addEventListener('click',delMessage, false));
		document.querySelector('#cnoti').addEventListener('change',eNoti,false);

		if(sessionStorage.getItem('gNoti') != 1) sendRequest(gurls);

		document.addEventListener('keydown', e => {
			if (e.key === 'Escape') hideMenu();
		});

		if(document.querySelector("#mngcform input[type='text']")) document.querySelector("#mngcform input[type='text']").addEventListener('focus', function() {
			this.select();
		});

		document.getElementById("save").addEventListener('click', function(event) {
			event.preventDefault();
			hideMenu();
			let jsonMark = JSON.stringify({ 
				"id": Math.random().toString(24).substring(2, 14),
				"url": document.getElementById('url').value,
				"title": '',
				"type": 'bookmark',
				"folder": document.getElementById('folder').value,  
				"nfolder": 'More Bookmarks',
				"added": new Date().valueOf()
			});

			sendRequest(addmark, jsonMark, 2);
		});

		if(document.getElementById('npwd')) document.getElementById('npwd').addEventListener('input', function() {checkuform()});
		if(document.getElementById('nuser')) document.getElementById('nuser').addEventListener('input', function() {checkuform()});
		if(document.getElementById('userLevel')) document.getElementById('userLevel').addEventListener('input', function() {checkuform()});
		document.getElementById('hmenu').addEventListener('click', function() {
			var mainmenu = document.getElementById('mainmenu');
			if(document.querySelector('#bookmarks')) document.querySelector('#bookmarks').addEventListener('click', hideMenu, false);
			if(mainmenu.style.display === 'block') {
				mainmenu.style.display = 'none';
			} else {
				hideMenu();
				addBD();
				mainmenu.style.display = 'block';
			}
		});

		if(document.getElementById('mngusers')) document.getElementById('mngusers').addEventListener('click', function() {
			hideMenu();
			sendRequest(getUsers);
		});

		document.getElementById('muser').addEventListener('click', function(e) {
			e.preventDefault();
			hideMenu();
			addBD();
			showDialog('userform');
			document.querySelector('#bookmarks').addEventListener('click',hideMenu, false);
		});

		document.getElementById('mmail').addEventListener('click', function(e) {
			e.preventDefault();
			if(document.getElementById('mailform')) document.getElementById('mailform').remove();
			hideMenu();
			let mailform = document.createElement('div');
			mailform.id = 'mailform';
			mailform.classList.add('mbmdialog');
			let heading = document.createElement('h6');
			heading.appendChild(document.createTextNode(translation.messages.changeMail));
			let mput = document.createElement('input');
			mput.id = 'mput';
			mput.value = document.getElementById('userMail').innerText;

			let dbutton = document.createElement('div');
			dbutton.classList.add('dbutton');
			let mchange = document.createElement('button');
			mchange.id = 'mchange';
			mchange.type = 'submit';
			mchange.disabled = true;
			mchange.innerText = translation.actions.save;
			dbutton.appendChild(mchange);
			
			mput.addEventListener('input', function(){
				mchange.disabled = (this.originalValue != this.value || this.value != '') ? false:true;
			});

			mchange.addEventListener('click', function(){
				sendRequest(cmail, mput.value);
			});

			let cspan = document.createElement('span');
			cspan.classList.add('dclose');
			cspan.innerHTML = '&times;';
			cspan.addEventListener('click', hideMenu, false);

			mailform.appendChild(heading);
			mailform.appendChild(mput);
			mailform.appendChild(dbutton);
			mailform.appendChild(cspan);

			document.querySelector('body').appendChild(mailform);
			addBD();
			document.getElementById('mailform').style.display = 'block';
			document.querySelector('#bookmarks').addEventListener('click',hideMenu, false);
		});

		document.querySelectorAll('.mdcancel').forEach(button => button.addEventListener('click', function() {
			hideMenu();
		}));

		document.getElementById('mpassword').addEventListener('click', function() {
			hideMenu();
			addBD();
			showDialog('passwordform');
			document.querySelector('#bookmarks').addEventListener('click',hideMenu, false);
		});

		document.getElementById('ntfy').addEventListener('click', function() {
			hideMenu();
			addBD();
			showDialog('pushform');
			document.querySelector('#bookmarks').addEventListener('click',hideMenu, false);
		});

		document.getElementById('bexport').addEventListener('click', function() {
			hideMenu();
			addBD();
			showDialog('expimpform');
			document.querySelector('#bookmarks').addEventListener('click',hideMenu, false);
		});

		document.getElementById('nmessages').addEventListener('click', function() {
			hideMenu();
			let loader = document.createElement('div');
			loader.classList.add('db-spinner');
			loader.id = 'db-spinner';
			document.querySelector('body').appendChild(loader);
			sendRequest(rmessage, null, 'aNoti');
		});

		document.getElementById('clientedt').addEventListener('click', function() {
			hideMenu();
			sendRequest(getclients);
		});

		document.getElementById('psettings').addEventListener('click', function() {
			hideMenu();
			addBD();
			document.getElementById('mngsform').style.display = 'block';
			document.querySelector('#bookmarks').addEventListener('click',hideMenu, false);
		});		

		document.getElementById('duplicates').addEventListener('click', function() {
			hideMenu();
			let loader = document.createElement('div');
			loader.classList.add('db-spinner');
			loader.id = 'db-spinner';
			document.querySelector('body').appendChild(loader);
			sendRequest(checkdups);
		});

		document.getElementById('ibfile').addEventListener('change', async function(e) {
			var data = new FormData()
			data.append('file', this.files[0])
			data.append('action', 'bimport')
			data.append('data', 'bimport')
			data.append('add', 'bimport')
			
			const response = await fetch('.', {
				method: 'POST',
				body: data
			});

			const result = await response.json();
			if(result.code == 200) {
				hideMenu();
				pwaMessage(translation.messages.importOK, 'success');
			} else {
				pwaMessage(translation.messages.importFail, 'error');
			}
		});
		document.getElementById("bmf_im").addEventListener('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			document.getElementById('ibfile').click();
		});
		document.getElementById("bmf_ex").addEventListener('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			let rformat = document.getElementsByName('eiformat');
			let format = '';
			for (i = 0; i < rformat.length; i++) {
                if (rformat[i].checked) format = rformat[i].value;
            }
			sendRequest(bexport, format);
		});

		document.getElementById('footer').addEventListener('click', function() {
			hideMenu();
			document.querySelector('#bookmarks').addEventListener('click', hideMenu, false);
			addBD();
			showDialog('bmarkadd');

			if(typeof navigator.clipboard.readText !== "undefined") {
				navigator.clipboard.readText().then(text => {
					if(isValidUrl(text)) document.getElementById('url').value = text;
				}).catch(err => {
					console.warn('Failed to read clipboard contents: ', err);
				});
			}

			url.focus();
			url.setSelectionRange(0,0);
			url.addEventListener('input', enableSave);
		});

		if(document.getElementById('mlog')) document.getElementById('mlog').addEventListener('click', function() {
			hideMenu();
			addBD();
			let logfile = document.getElementById('logfile');
			if(logfile.style.visibility === 'visible') {
				logfile.style.visibility = 'hidden';
				document.getElementById('close').style.visibility = 'hidden';
			} else {
				logfile.style.visibility = 'visible';
				document.getElementById('close').style.visibility = 'visible';
				sendRequest(mlog);
			}
		});

		if(document.getElementById('mclear')) document.getElementById('mclear').addEventListener('click', function() {
			sendRequest(mclear);
		});
		
		if(document.getElementById('mrefresh')) document.getElementById('mrefresh').addEventListener('click', logRefresh);
		
		if(document.getElementById('arefresh')) document.getElementById('arefresh').addEventListener('change', logRefresh);
		
		if(document.getElementById('mclose')) document.getElementById('mclose').addEventListener('click', function() {
			if(document.getElementById('logfile').style.visibility === 'visible') {
				document.getElementById('logfile').style.visibility = 'hidden';
				document.getElementById('close').style.visibility = 'hidden';
				hideMenu();
			}
		}); 

		document.querySelectorAll('#mngcform .clientname').forEach(function(e) {
			e.addEventListener('touchstart',function() {
				this.children[0].style.display = 'block';
			})
		});

		document.querySelectorAll("#mngcform li div.clientname input").forEach(function(element) {
			element.addEventListener('mouseleave', function() {
				if(this.defaultValue != this.value) {
					this.style.display = 'block';
					this.parentElement.parentElement.children[2].classList.add('renamea');
					this.parentElement.parentElement.children[1].classList.add('renamea');
					this.parentElement.parentElement.children[1].classList.remove('rename');
					this.parentElement.parentElement.children[2].classList.remove('remove');
				}
			});
		});

		document.getElementById('fname').addEventListener('input', function() {
			document.getElementById('fsave').disabled = false;
		});

		document.getElementById('edtitle').addEventListener('input', function() {
			document.getElementById('edsave').disabled = false;
		});

		document.getElementById('edurl').addEventListener('input', function() {
			document.getElementById('edsave').disabled = false;
		});

		document.getElementById('mvfolder').addEventListener('change', function() {
			document.getElementById('mvsave').disabled = false;
		});

		document.getElementById('fsave').addEventListener('click', function(e) {
			e.preventDefault();
			sendRequest(cfolder, document.getElementById('fname').value, document.getElementById('fbid').value);
		});

		document.getElementById('edsave').addEventListener('click', function(e) {
			e.preventDefault();
			let jsonMark = JSON.stringify({
				"id": document.getElementById('edid').value,
				"url": document.getElementById('edurl').value,
				"title": document.getElementById('edtitle').value,
			});

			sendRequest(bmedt, jsonMark);
		});
		
		document.getElementById('mvsave').addEventListener('click', function(e) {
			e.preventDefault();
			sendRequest(bmmv, document.getElementById('mvfolder').value, document.getElementById('mvid').value);
			document.getElementById('bmamove').style.display = 'none';
		});

		document.getElementById('mnubg').addEventListener('click', function() {hideMenu()});

		if(document.getElementById('language')) document.getElementById('language').addEventListener('change', function(e) {
			sendRequest(langupdate, this.value);
		});

		addBookmarkEvents();

		navigator.serviceWorker.ready.then( registration => {
			if (registration.active) {
				registration.active.postMessage({
					type: 'bookmarks',
					data: document.getElementById('bookmarks').innerHTML
				});
			}
		});
	}
}, false);

window.addEventListener("keydown",function (e) {
	if ((e.ctrlKey && e.code === 'KeyF')) { 
		e.preventDefault();
		document.getElementById('bmsearch').focus();
		document.querySelector('#menu button').innerHTML = '\u00D7';
		document.querySelector('#menu input').value = '';
		document.querySelector('#menu button').classList.add('asform');
		document.querySelector('#menu input').classList.remove('isform');
		document.querySelector('#menu input').classList.add('asform');
		document.getElementById('mprofile').style.display = 'none';
		document.querySelector('#menu input').focus();
	}

	rmBm(e);
})

var bmIDs = new Array();

const isValidUrl = urlString=> {
	let url;
	try { 
		url =new URL(urlString); 
	}
	catch(e){
		return false; 
	}
	return url.protocol === "http:" || url.protocol === "https:";
}

function addBookmarkEvents() {
	document.addEventListener("mouseup", function(){
		document.removeEventListener("mousemove", resize, false);
	}, false);
	
	document.querySelectorAll('.file').forEach(function(bookmark){
		bookmark.addEventListener('contextmenu', onContextMenu, false);
		bookmark.addEventListener('mouseup', clicCheck, false);
		bookmark.addEventListener('dragstart', function(event){
			event.target.style.opacity = '.3';
			event.dataTransfer.effectAllowed = "move";
		});
		bookmark.addEventListener('dragend', function(event){
			event.target.style.opacity = '';
		});			
	});
	document.querySelectorAll('.folder').forEach(bookmark => bookmark.addEventListener('mouseup', clicCheck, false));
	document.querySelectorAll('.folder').forEach(bookmark => bookmark.addEventListener('contextmenu', onContextMenu, false));
	var draggable;
	document.querySelectorAll('.lbl').forEach(function(folder) {
		folder.addEventListener('mouseup', openFolderBookmarks, false);
		folder.addEventListener('dragover', function(event){
			event.preventDefault();
			event.dataTransfer.dropEffect = "move"
		});
		folder.addEventListener('dragenter', function(){		
			this.style = 'background-color: lightblue;';
		});
		folder.addEventListener('dragleave', function(){		
			this.style = 'background-color: unset;';
		});
		folder.addEventListener('drop', function(event){
			event.preventDefault();
			let tFolder = event.target.htmlFor.substring(2);
			if (bmIDs.length === 0) bmIDs.push(draggable.target.id);
			bmIDs.forEach(bmID => sendRequest(bmmv, tFolder, bmID));
			event.target.style = 'background-color: unset;';
		});
	});
	document.addEventListener("drag", function(event) {
		draggable = event;
	});

	document.getElementById('bookmarks').addEventListener('keyup', rmBm);
	document.querySelectorAll('.file').forEach(bookmark => bookmark.addEventListener('contextmenu', onContextMenu, false));
	document.querySelectorAll('.file').forEach(bookmark => bookmark.addEventListener('mouseup', clicCheck, false));
	document.querySelectorAll('.folder').forEach(bookmark => bookmark.addEventListener('mouseup', clicCheck, false));
	document.querySelectorAll('.folder').forEach(bookmark => bookmark.addEventListener('contextmenu', onContextMenu, false));
}

async function setLanguage(lang) {
	var response = await fetch('locale/' + lang + '.json');
	translation = await response.json();
}

function clicCheck(e) {
	let bookmark = this.children[0];

	switch(e.button) {
		case 0:
			if(e.ctrlKey) {
				if(bookmark.classList.contains('bmMarked')) {
					bmIDs.indexOf(bookmark.id) !== -1 && bmIDs.splice(bmIDs.indexOf(bookmark.id), 1)
					bookmark.classList.remove('bmMarked');
				} else {
					bookmark.classList.add('bmMarked');
					bmIDs.push(bookmark.id);
				}
			} else {
				if (typeof e.srcElement.dataset.url !== 'undefined') window.open(e.srcElement.dataset.url, '_blank', 'noopener,noreferrer');
			}
			break;
		case 1:
			if (typeof e.srcElement.dataset.url !== 'undefined') window.open(e.srcElement.dataset.url, '_blank', 'noopener,noreferrer');
			break;
	}
}

function addBD() {
	menubg = document.getElementById('mnubg');
	menubg.style.visibility = "visible";
	document.getElementById('mnubg').style.visibility = "visible";
}

function openFolderBookmarks(event) {
	if ((event.ctrlKey && event.button == 0) || event.button == 1) {
		let pID = this.htmlFor.substring(2);
		let subs = this.parentElement.children[2].childNodes;
		subs.forEach(function(element) {
			if(element.className != undefined && element.className.indexOf('file') > -1) {
				let bm = element.children[0];
				bm.click();
			}
		});
	}
}

function rmBm(key) {
	if(key.keyCode == 46 && bmIDs.length > 0) {
		let message = translation.messages.delCount;
		message.replace("%count%", bmIDs.length);
		mconfirm(message, JSON.stringify(bmIDs));
	}
}

function sendRequest(action, data = null, addendum = null) {
	const params = {
		action: action.name,
		client: 0,
		data: data,
		add: addendum,
		sync: null
	}

	const xhr = new XMLHttpRequest();

	xhr.open("POST", document.location.href, true);
	xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
	xhr.responseType = 'json';

	xhr.onreadystatechange = function() {
		if (xhr.readyState === 4) {
			if (xhr.status === 200) {
				action(xhr.response, addendum);
			} else {
				let message = `Error ${xhr.status}: ${xhr.statusText}`;
				console.warn(action.name, message);
				pwaMessage(action.name + ": " + message, 'error');
			}
		}
	}

	xhr.onerror = function () {
		let message = "Error: " + xhr.status + ' | ' + xhr.response;
		console.warn(action.name, url, message);
		return false;
	}

	const qparams = new URLSearchParams(params);
	xhr.send(qparams);
}

function pwaMessage(message, state) {
	var mdiv = document.getElementById("pwamessage");
	mdiv.classList.add(state);
	mdiv.innerText = message;
	mdiv.classList.add('show');
	setTimeout(function(){
		mdiv.className = mdiv.classList.remove("show");
	}, 10000);
	return false;
}

function getclients(response) {
	let cList = response.clients;
	var clientListForm = document.getElementById('mngcform');
	if(clientListForm.childNodes.length) clientListForm.removeChild(clientListForm.firstChild);
	var ulClients = document.createElement('ul');
	cList.forEach(function(client, key){
		if(client.id != '0') {
			let liEl = document.createElement('li');
			liEl.title = client.id;
			liEl.dataset.type = client.type.toLowerCase();
			liEl.id = client.id;
			liEl.classList = 'client';
			let cename = document.createElement('div');
			cename.classList = 'clientname';
			cename.appendChild(document.createTextNode(client.name ? client.name:client.id));
			liEl.appendChild(cename);
			let ceinput = document.createElement('input');
			ceinput.type = 'text';
			ceinput.name = 'cname';
			ceinput.value = client.name;
			cename.appendChild(ceinput);
			let cels = document.createElement('div');
			cels.classList = 'lastseen';
			cels.innerText = client.date != "0" ? 'Sync: ' + new Date(parseInt(client.date)).toLocaleString(
				navigator.language, 
				{
					year: "numeric",
					month: "2-digit",
					day: "2-digit",
					hour: '2-digit',
					minute: '2-digit'
				}):'Sync: -- -- ---- -- --';
			cename.appendChild(cels);
			let cedit = document.createElement('div');
			cedit.classList = 'fa-edit rename';
			cedit.addEventListener('click', mvClient, false);
			liEl.appendChild(cedit);
			let cerm = document.createElement('div');
			cerm.classList = 'fa-trash remove';
			cerm.addEventListener('click', delClient, false);
			liEl.appendChild(cerm);
			ulClients.appendChild(liEl);
		}
	});
	clientListForm.appendChild(ulClients);
	addBD();
	document.getElementById('mngcform').style.display = 'block';
	document.querySelector('#bookmarks').addEventListener('click',hideMenu, false);
}

function langupdate(response) {
	location.reload();
}

function addmark(response) {
	let message = translation.messages.bmAddedOK;

	if(response.code !== 200) {
		message = translation.messages.bmAddedFail;
	} else {
		document.getElementById('bookmarks').innerHTML = response.html_bookmarks;
		document.querySelectorAll('.file').forEach(bookmark => bookmark.addEventListener('contextmenu', onContextMenu, false));
		document.querySelectorAll('.file').forEach(bookmark => bookmark.addEventListener('mouseup', clicCheck, false));
		document.querySelectorAll('.folder').forEach(bookmark => bookmark.addEventListener('mouseup', clicCheck, false));
		document.querySelectorAll('.folder').forEach(bookmark => bookmark.addEventListener('contextmenu', onContextMenu, false));
	}
	
	console.info(message);
	
	let state = (message.includes('not')) ? 'warn':'success';
	pwaMessage(message, state);
}

function muedt(response) {
	if(document.getElementById('db-spinner')) document.getElementById('db-spinner').remove();
	response = '' + response;
	if(response.indexOf('failed') != -1) {
		console.error("Syncmarks: "+response);
		show_noti({title:"Syncmarks - Error", url:response, key:""}, false);
	} else if(response.indexOf('not send') != -1) {
		console.warn("Syncmarks: "+response);
		pwaMessage(response, 'error');
	}

	sendRequest(getUsers);
}

function getUsers(response) {
	let uData = response;
	if(uData.indexOf('not allowed') != -1) {
		console.warn('SyncMarks - Warning: '+uData);
		show_noti({title:"Syncmarks - Warning", url:uData, key:""}, false);
	} else {
		if(document.getElementById('mnguform')) document.getElementById('mnguform'). remove();
		document.querySelector('#bookmarks').addEventListener('click', hideMenu, false);
		let mnguform = document.createElement('div');
		mnguform.id = 'mnguform';
		mnguform.classList.add('mbmdialog', 'show-menu');
		let heading = document.createElement('h6');
		heading.appendChild(document.createTextNode('Manage Users'));
		mnguform.appendChild(heading);
		let userSelect = document.createElement('select');
		userSelect.id = 'userSelect';
		let uOptionA = document.createElement('option');
		uOptionA.text = translation.messages.newUser;
		uOptionA.value = 0;
		userSelect.appendChild(uOptionA);

		mngUform(uData, userSelect);

		let nuser = document.createElement('input');
		nuser.id = 'nuser';
		nuser.placeholder = translation.messages.mail;
		nuser.type = 'text';
		nuser.required = true;
		nuser.autocomplete = 'username';

		let npwd = document.createElement('input');
		npwd.id = 'npwd';
		npwd.placeholder = translation.messages.password;
		npwd.type = 'password';
		npwd.autocomplete = 'password';

		let userLevel = document.createElement('select');
		userLevel.id = 'userLevel';

		let uLevel0 = document.createElement('option');
		uLevel0.text = 'Normal';
		uLevel0.value = 1;
		userLevel.appendChild(uLevel0);
		let uLevel1 = document.createElement('option');
		uLevel1.text = 'Admin';
		uLevel1.value = 2;
		userLevel.appendChild(uLevel1);

		let dbutton = document.createElement('div');
		dbutton.classList.add('dbutton');
		let muadd = document.createElement('button');
		muadd.id = 'muadd';
		muadd.type = 'submit';
		muadd.disabled = true;
		muadd.innerText = translation.actions.save;
		let mudel = document.createElement('button');
		mudel.id = 'mudel';
		mudel.type = 'submit';
		mudel.disabled = true;
		mudel.innerText = translation.actions.delete;
		dbutton.appendChild(muadd);
		dbutton.appendChild(mudel);

		userSelect.addEventListener('change', function(e) {
			if(e.target.options[e.target.selectedIndex].value != 0) {
				nuser.value = e.target.options[e.target.selectedIndex].text;
				userLevel.value = e.target.options[e.target.selectedIndex].dataset.lvl;
				mudel.disabled = false;
			} else {
				nuser.value = '';
				userLevel.value = 1;
				mudel.disabled = true;
			}
		});
		
		nuser.addEventListener('input', function () {
			muadd.disabled = (this.originalValue != this.value || this.value != '') ? false:true;
		});

		npwd.addEventListener('input', function () {
			muadd.disabled = (this.originalValue != this.value || this.value != '') ? false:true;
		});

		userLevel.addEventListener('change', function() {
			muadd.disabled = false;
		});

		mnguform.appendChild(userSelect);
		mnguform.appendChild(nuser);
		mnguform.appendChild(npwd);
		mnguform.appendChild(userLevel);
		mnguform.appendChild(dbutton);
		addBD();
		document.querySelector('body').appendChild(mnguform);

		muadd.addEventListener('click', function(e) {
			e.preventDefault();
			let loader = document.createElement('div');
			loader.classList.add('db-spinner');
			loader.id = 'db-spinner';
			document.querySelector('body').appendChild(loader);
			let type = (userSelect.selectedIndex == 0) ? 1:2;

			let data = JSON.stringify({
				"type":type,
				"p":npwd.value,
				"userLevel":userLevel.value,
				"nuser":nuser.value,
				"userSelect":userSelect.options[userSelect.selectedIndex].value
			});
			sendRequest(muedt, data);
		});

		mudel.addEventListener('click', function(e) {
			e.preventDefault();
			let loader = document.createElement('div');
			loader.classList.add('db-spinner');
			loader.id = 'db-spinner';
			document.querySelector('body').appendChild(loader);
			let data = JSON.stringify({
				"type":3,
				"userLevel":userLevel.value,
				"nuser":nuser.value,
				"userSelect":userSelect.options[userSelect.selectedIndex].value
			});
			sendRequest(muedt, data);
		});
	}

	return false;
}

function cmail(response) {
	if(response == 1) {
		document.getElementById('userMail').innerText = mput.value;
		console.info("Mail changed.");
		mailform.remove();
	} else {
		let message = response;
		console.error(message);
		show_noti({title:"Syncmarks - Error", url:message, key:""}, false);
		pwaMessage(message, 'error');
	}
}

function checkdups(response) {
	delete response.version;
	var dubData = Object.values(response);

	if(dubData.length > 0) {
		let dubDIV = document.createElement('div');
		let head = document.createElement('h6');
		let message = translation.messages.duplicatesFound;
		message.replace('%count%', dubData.length);
		head.innerText = message;
		let hspan = document.createElement('span');
		hspan.innerText = translation.messages.duplicateDelete;
		dubDIV.id = 'dubDIV';
		dubDIV.classList.add('mbmdialog');
		dubDIV.appendChild(head);
		dubDIV.appendChild(hspan);
		document.querySelector('body').appendChild(dubDIV);
		let dubMenu = document.createElement('ul');
		dubMenu.id = 'dubMenu';
		dubData.forEach(function(dubURL){
			let dubSub = document.createElement('ul');
			dubSub.classList.add('dubSub');
			let dubLi = document.createElement('li');
			dubLi.id = 'dub_' + dubURL.bmID;
			dubLi.innerText = dubURL.bmTitle;
			dubLi.dataset.url = dubURL.bmURL;
			dubLi.title = dubURL.bmURL;
			dubURL.subs.forEach(function(subEntry){
				let subLi = document.createElement('li');
				subLi.classList.add('menuitem');
				subLi.innerText = subEntry.bmTitle;
				subLi.dataset.bmid = subEntry.bmID;
				subLi.addEventListener('click', function(){
					let dub = this;
					sendRequest(mdel, JSON.stringify([dub.dataset.bmid]), 1);
					let loader = document.createElement('div');
					loader.classList.add('db-spinner');
					loader.id = 'db-spinner';
					document.querySelector('body').appendChild(loader);
				});
				let subSp = document.createElement('span');
				subSp.innerHTML = subEntry.fway;
				subSp.title = subSp.innerHTML;
				subLi.appendChild(subSp);
				dubSub.appendChild(subLi);
			});
			dubLi.appendChild(dubSub);
			dubMenu.appendChild(dubLi);
			dubDIV.appendChild(dubMenu);
			addBD();
			dubDIV.style.display = 'block';
		});
		if(document.getElementById('db-spinner')) document.getElementById('db-spinner').remove();
	} else {
		if(document.getElementById('db-spinner')) document.getElementById('db-spinner').remove();
		console.info("No duplicates found");
		pwaMessage(translation.messages.duplicateNo, 'warn');
	}
}

function logRefresh() {
	let arefresh = document.getElementById('arefresh').checked;
	let logfile = document.getElementById('logfile');
	if(logfile.style.visibility === 'visible') sendRequest(mrefresh);
	if(arefresh === true) setTimeout(logRefresh, 30*1000);
}

function rmessage(response, a = 'aNoti') {
	let noti = '#' + a;
	let rhtm =  new DOMParser().parseFromString(response, "text/html");
	let div = document.querySelector(noti + ' .NotiTable .NotiTableBody');

	while (div.firstChild) {
		div.removeChild(div.lastChild);
	}

	Array.from(rhtm.body.children).forEach((node) => {
		node.children[1].children[0].addEventListener('click',delMessage, false);
		div.appendChild(node); 
	})
	addBD();
	document.getElementById('nmessagesform').style.display = 'block';
	document.querySelector('#bookmarks').addEventListener('click',hideMenu, false);
	if(document.getElementById('db-spinner')) document.getElementById('db-spinner').remove();
}

function bexport(response) {
	let today = new Date();
	let dd = today.getDate();
	let mm = today.getMonth()+1; 

	if(dd<10) dd='0'+dd;
	if(mm<10) mm='0'+mm;
	today = dd + '-' + mm + '-' + today.getFullYear();
	
	let link = document.createElement('a');

	if (Array.isArray(response.bookmarks)) {
		const cleanArray = response.bookmarks.map(item=>{
			delete item.userID
			return item
		})

		let blob = new Blob([JSON.stringify(cleanArray)], { type: 'application/json' });
		link.href = window.URL.createObjectURL(blob);

	} else {
		let blob = new Blob([response.bookmarks], { type: 'text/html' });
		link.href = window.URL.createObjectURL(blob);
	}
	
	link.download = "bookmarks_" + today;
	link.click();
	console.info("Export successfully, please look in your download folder.");
	pwaMessage(translation.messages.exportOK, 'success');
	hideMenu();
}

function mlog(response) {
	const logger = document.getElementById("lfiletext")
	while (logger.firstChild) {
		logger.firstChild.remove()
	}

	let lparse = response.split("\n");
	lparse.forEach(function(line){
		let span = document.createElement('span');
		if(line.indexOf('debug') > 0) {
			span.classList.add("debug");
		} else if (line.indexOf('notice') > 0) {
			span.classList.add("notice");
		} else if (line.indexOf('warn') > 0) {
			span.classList.add("warn");
		} else if (line.indexOf('error') > 0) {
			span.classList.add("error");
		}
		span.innerText = line;
		logger.appendChild(span);
	});
	moveEnd();
}

function mclear(response) {
	const logger = document.getElementById("lfiletext")
	while (logger.firstChild) {
		logger.firstChild.remove()
	}

	let lparse = response.split("\n");
	lparse.forEach(function(line){
		let span = document.createElement('span');
		if(line.indexOf('debug') > 0) {
			span.classList.add("debug");
		} else if (line.indexOf('notice') > 0) {
			span.classList.add("notice");
		} else if (line.indexOf('warn') > 0) {
			span.classList.add("warn");
		} else if (line.indexOf('error') > 0) {
			span.classList.add("error");
		}
		span.innerText = line;
		logger.appendChild(span);
	});
	moveEnd();
	console.info("Logfile should now be empty.");
}

function mrefresh(response) {
	const logger = document.getElementById("lfiletext")
	while (logger.firstChild) {
		logger.firstChild.remove()
	}
	
	let lparse = response.split("\n");
	lparse.forEach(function(line){
		let span = document.createElement('span');
		if(line.indexOf('debug') > 0) {
			span.classList.add("debug");
		} else if (line.indexOf('notice') > 0) {
			span.classList.add("notice");
		} else if (line.indexOf('warn') > 0) {
			span.classList.add("warn");
		} else if (line.indexOf('error') > 0) {
			span.classList.add("error");
		}
		span.innerText = line;
		logger.appendChild(span);
	});
	moveEnd();
	
}

function cfolder(response) {
	if(response == 1)
		location.href = location.href;
	else {
		let message = translation.messages.folderFail;
		console.error(message);
		show_noti({title:"Syncmarks - Error", url:message, key:""}, false);
		pwaMessage(message, 'error');
	}
	hideMenu();
}

function bmedt(response) {
	if(response == 1) {
		location.reload();
	} else {
		let message = translation.messages.changeBMFail;
		console.error(message);
		show_noti({title:"Syncmarks - Error", url:message, key:""}, false);
		pwaMessage(message, 'error');
	}
}

function bmmv(response) {
	if(response !== false) {
		let obm = document.getElementById(response.id).parentElement;
		let nfolder = document.getElementById('f_' + response.folder);
		obm.remove();
		nfolder.lastChild.appendChild(obm);
		obm.children[0].classList.remove('bmMarked');
		bmIDs.shift();
	} else {
		let message = translation.messages.moveFail;
		show_noti({title:"Syncmarks - Error", url:message, key:""}, false);
		console.error(message);
		pwaMessage(message, 'error');
	}
}

function adel(response) {
	document.getElementById('mngcform').innerHTML = response;
	document.querySelectorAll("#mngcform li div.remove").forEach(function(element) {element.addEventListener('click', delClient, false)});
	document.querySelectorAll("#mngcform li div.rename").forEach(function(element) {element.addEventListener('click', mvClient, false)});
}

function arename(response) {
	document.getElementById('mngcform').innerHTML = response;
	document.querySelectorAll("#mngcform li div.remove").forEach(function(element) {element.addEventListener('click', delClient, false)});
	document.querySelectorAll("#mngcform li div.rename").forEach(function(element) {element.addEventListener('click', mvClient, false)});
	if(document.getElementById('db-spinner')) document.getElementById('db-spinner').remove();
}

function mdel(response) {
	delete response.version;
	response = Object.values(response);

	hideMenu();
	if(document.getElementById('db-spinner')) document.getElementById('db-spinner').remove();
	if(Array.isArray(response)) {
		response.forEach(function(element) {
			document.getElementById(element).parentNode.remove();
		});
	} else {
		show_noti({title:"Syncmarks - Error", url:response, key:""}, false);
		console.error(response);
		pwaMessage(response, 'error');
	}
}

function soption(response) {
	if(response == 'true') {
		console.info("Option saved.");
		pwaMessage(translation.messages.optionsSaved, 'success');
	} else {
		let message = translation.messages.optionsIssue;
		show_noti({title:"Syncmarks - Error", url:message, key:""}, false);
		console.error(message);
		pwaMessage(message, 'error');
	}
}

function pushHide(response) {
	if(response == "1") {
		console.info("Notification removed");
	} else {
		let message = translation.messages.notificationIssue;
		console.error(message);
		show_noti({title:"Syncmarks - Error", url:message, key:""}, false);
		pwaMessage(message, 'error');
	}
}

function gurls(response) {
	let notifications = response['notifications'];
	
	if(Array.isArray(notifications) && notifications.length > 0 && response['enabled'] == 1) {
		notifications.forEach(function(notification){
			show_noti(notification);
		});
	}

	sessionStorage.setItem('gNoti', '1');

	navigator.serviceWorker.controller.postMessage({
		type: 'checkIDB',
	});
}

function mngUform(uData, userSelect) {
	userSelect.options.length = 1;
	uData.forEach(function(user){
		let uOption = document.createElement('option');
		uOption.text = user['userName'];
		uOption.value = user['userID'];
		uOption.dataset.lvl = user['userType'];
		userSelect.appendChild(uOption);
	});
}

function delClient(element) {
	sendRequest(adel, element.target.parentElement.id);
}

function mvClient(element) {
	let loader = document.createElement('div');
	loader.classList.add('db-spinner');
	loader.id = 'db-spinner';
	document.querySelector('body').appendChild(loader);
	sendRequest(arename, element.target.parentElement.children[0].children['cname'].value, element.target.parentElement.id);
}

function resize(e){
	let wdt = window.innerWidth - parseInt(e.x);
	document.getElementById("logfile").style.width = wdt + "px";
}

function moveEnd() {
	let lfiletext = document.getElementById("lfiletext");
	lfiletext.scrollTop = lfiletext.scrollHeight;
}

function delBookmark(id, title) {
	const bookmarks = [id];
	let message = translation.messages.delTitle;
	message.replace("%bookmark%", title);
	mconfirm(message, JSON.stringify(bookmarks));
}

function enableSave() {
	if(document.getElementById('url').value.length > 7)
		document.getElementById('save').disabled = false;
	else
		document.getElementById('save').disabled = true;
}

function showMenu(x, y){
	let menu = document.getElementById('cmenu');
	let minbot = window.innerHeight - 120;
	let minwot = window.innerWidth - 140;
	
	if(y >= minbot) y = minbot;
	if(x >= minwot) x = minwot;

	menu.style.left = x + 'px';
	menu.style.top = y + 'px';
	menu.classList.add('show-menu');
	addBD();
	return false;
}

function hideMenu(marked = true){
	let menus = document.querySelectorAll('.menu');
	menus.forEach(e => {
		e.classList.remove('show-menu');
	});

	let dialogs = document.querySelectorAll('.mbmdialog');
	dialogs.forEach(e => {
		e.classList.remove('show-menu');
	});

	document.querySelectorAll('.mmenu').forEach(function(item) {item.style.display = 'none'});
	document.getElementById('mnubg').style.visibility = "hidden";
	if(document.getElementById('dubDIV')) document.querySelector('body').removeChild(document.getElementById('dubDIV'));
	if(document.getElementById('mnguform')) document.getElementById('mnguform').remove();

	if(marked) {
		let bmMarked = document.querySelectorAll(".bmMarked");
		for (let i = 0; i < bmMarked.length; i++) {
			bmMarked[i].classList.remove("bmMarked");
		}
		bmIDs.length = 0;
	}
}

function showDialog(dialogN) {
	let dialog = document.getElementById(dialogN);
	dialog.classList.add('show-menu');
	let elems = dialog.querySelectorAll('input, select, button');
	let le = elems.length - 1;

	elems[le].addEventListener('blur', e => {
		elems[0].focus();
	});
	elems[0].focus();
	if (["text", "url", "tel"].indexOf(elems[0].type) >= 0) {
		elems[0].setSelectionRange(0, 0);
	}
}

function onContextMenu(e){
	e.preventDefault();
	e.stopPropagation();
	e.cancelBubble = true;
	e.returnValue = false;
	hideMenu();
	
	let menu = document.querySelector('.menu');
	menu.style.display = 'block';

	this.querySelector('span').classList.add("bmMarked");

	if(e.target.attributes.id){
		document.getElementById('bmid').value = e.target.attributes.id.value;
		document.getElementById('bmid').title = e.target.attributes.title.value;
		document.getElementById('btnMove').setAttribute('style','display:block !important');
		document.getElementById('btnFolder').setAttribute('style','display:block !important');
	} else {
		document.getElementById('bmid').value = e.target.nextElementSibling.value
		document.getElementById('bmid').title = e.target.textContent
		document.getElementById('btnMove').setAttribute('style','display:none !important');
		document.getElementById('btnFolder').setAttribute('style','display:none !important');
	}

	document.querySelector('#btnEdit').addEventListener('click', onMenuClick, false);
	document.querySelector('#btnMove').addEventListener('click', onMenuClick, false);
	document.querySelector('#btnDelete').addEventListener('click', onMenuClick, false);
	document.querySelector('#btnFolder').addEventListener('click', onMenuClick, false);
	showMenu(e.pageX, e.pageY);
	return false;
}

function onMenuClick(e){
	var minleft = 155;
	var minbot = window.innerHeight - 200;
	var xpos = e.pageX;
	var ypos = e.pageY;
	if(xpos <= minleft) xpos = minleft;
	if(ypos >= minbot) ypos = minbot;
	addBD();
	
	switch(this.id) {
		case 'btnEdit':
			document.getElementById('edtitle').value = document.getElementById('bmid').title.split(/\r?\n|\r|\n/g)[0];
			document.getElementById('edid').value = document.getElementById('bmid').value;

			if(document.getElementById(document.getElementById('bmid').value)) {
				document.getElementById('edurl').value = document.getElementById(document.getElementById('bmid').value).dataset.url;
				document.getElementById('bmarkedt').firstChild.innerText = 'Edit Bookmark';
				document.getElementById('edurl').type = 'text';
			} else {
				document.getElementById('edurl').value = '';
				document.getElementById('edurl').type = 'hidden';
				document.getElementById('bmarkedt').firstChild.innerText = 'Edit Folder';
			}
			
			hideMenu();
			showDialog('bmarkedt');
			break;
		case 'btnMove':
			document.getElementById('mvtitle').innerText = document.getElementById('bmid').title.split(/\r?\n|\r|\n/g)[0];
			document.getElementById('mvtitle').title = document.getElementById('bmid').title.split(/\r?\n|\r|\n/g)[0];
			document.getElementById('mvid').value = document.getElementById('bmid').value;
			hideMenu();
			showDialog('bmamove');
			break;
		case 'btnDelete':
			setTimeout(delBookmark, 5, document.getElementById('bmid').value, document.getElementById('bmid').title.split(/\r?\n|\r|\n/g)[0]);
			break;
		case 'btnFolder':
			hideMenu();
			showDialog('folderf');
			document.getElementById('fbid').value = document.getElementById('bmid').value;
			break;
		default:
			break;
	}

	document.removeEventListener('click', onMenuClick);
}

function openMessages(element) {
	var i, tabcontent, tablinks;
	tabcontent = document.getElementsByClassName("tabcontent");
	for (i = 0; i < tabcontent.length; i++) {
		tabcontent[i].style.display = "none";
	}

	let div = document.querySelector('#'+element.target.dataset.val+' .NotiTable .NotiTableBody');
	while (div.firstChild) {
		div.removeChild(div.lastChild);
	}

	let loader = document.createElement('div');
	loader.classList.add('db-spinner');
	loader.id = 'db-spinner';
	document.querySelector('body').appendChild(loader);
	sendRequest(rmessage, null, element.target.dataset.val);
	
	tablinks = document.getElementsByClassName("tablinks");
	for (i = 0; i < tablinks.length; i++) {
		tablinks[i].className = tablinks[i].className.replace(" active", "");
	}

	document.getElementById(element.target.dataset['val']).style.display = "block";
	element.currentTarget.className += " active";
}

function delMessage(message) {
	let loader = document.createElement('div');
	loader.classList.add('db-spinner');
	loader.id = 'db-spinner';
	document.querySelector('body').appendChild(loader);
	sendRequest(rmessage, message.target.dataset['message'], message.target.parentElement.parentElement.parentElement.parentElement.parentElement.id);
}

function eNoti(e) {
	var nval = e.target.checked;
	if(nval) {
		if (!("Notification" in window)) {
			alert(translation.messages.notificationUnsupported);
			sendRequest(soption, "notifications", 0);
		} else if (Notification.permission === "granted") {
			var notification = new Notification("Syncmarks", {
				body: translation.messages.notificationEnabled,
				icon: './images/bookmarks192.png'
			});
			sendRequest(soption, "notifications", 1);
		} else if (Notification.permission !== "denied") {
			Notification.requestPermission().then(function (permission) {
				if (permission === "granted") {
					var notification = new Notification("Syncmarks", {
						body: translation.messages.notificationEnabled,
						icon: './images/bookmarks192.png'
					});
					sendRequest(soption, "notifications", 1);
				}
			});
		}
	} else {
		sendRequest(soption, "notifications", 0);
	}
}

function show_noti(noti, rei = true) {
	if (Notification.permission !== 'granted')
		Notification.requestPermission();
	else {
		let notification = new Notification(noti.title, {
			body: noti.url,
			icon: './images/bookmarks192.png',
			requireInteraction: rei
		});
		
		notification.onclick = function() {
			if(noti.url.indexOf('http') >= 0) {
				window.open(noti.url);
				sendRequest(pushHide, noti.nkey);
			}
		};
	}
}

function mconfirm(message, ids) {
	hideMenu(false);
	addBD();
	showDialog('reqdialog');
	var dialog = document.getElementById('reqdialog');
	dialog.querySelector('span').innerText = message;

	let ybtn = document.getElementById('ydialog');
	let nbtn = document.getElementById('ndialog');

	dialog.addEventListener('keyup', (e) => {
		const nKeys = ['ArrowLeft', 'ArrowUp', 'ArrowRight', 'ArrowDown'];
		if(nKeys.includes(e.key)) {
			if(document.activeElement == nbtn) {
				ybtn.focus();
			} else if (document.activeElement == ybtn) {
				nbtn.focus();
			}
		}
	})

	ybtn.addEventListener('click', delbm, false);
	nbtn.addEventListener('click', delbm, false);

	ybtn.myparam = ids;
	nbtn.myparam = null;
}

function delbm(e) {
	if(this.id == "ydialog") {
		sendRequest(mdel, e.target.myparam);
		let loader = document.createElement('div');
		loader.classList.add('db-spinner');
		loader.id = 'db-spinner';
		document.querySelector('body').appendChild(loader);
	}
	hideMenu();
}