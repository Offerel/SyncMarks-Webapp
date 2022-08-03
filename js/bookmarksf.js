/**
 * SyncMarks
 *
 * @version 1.7.1
 * @author Offerel
 * @copyright Copyright (c) 2022, Offerel
 * @license GNU General Public License, version 3
 */
if(document.getElementById('preset')) document.getElementById('preset').addEventListener('click', function(e){
	e.preventDefault();
	let data = "reset=request&u="+e.target.dataset.reset;
	let xhr = new XMLHttpRequest();
	let url = document.location.href.substr(0, document.location.href.indexOf('?'))
	
	xhr.open("GET", url+"?"+data, true);
	xhr.onreadystatechange = function() {
		if(this.readyState == 4 && this.status == 200) {
			if(JSON.parse(this.responseText) == 1) {
				let div = document.getElementById('loginformt');
				div.classList.toggle('info');
				div.innerText = 'Password request confirmation is send to your E-Mail. Request is valid for 5 minutes.';
			}
		}
	}
	xhr.send(null);
});