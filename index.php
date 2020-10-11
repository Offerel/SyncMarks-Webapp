<?php
/**
 * SyncMarks
 *
 * @version 1.2.11
 * @author Offerel
 * @copyright Copyright (c) 2020, Offerel
 * @license GNU General Public License, version 3
 */
if (!isset ($_SESSION['fauth'])) {
    session_start();
}

include_once "config.inc.php";
set_error_handler("e_log");
if(!file_exists($database)) initDB($database);

if(!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] === "" || !isset($_SERVER['PHP_AUTH_PW']) || !isset($_SESSION['fauth'])) {
	doLogin($database,$realm);
}
else {
	$db = new PDO('sqlite:'.$database);
	e_log(8,"Update lastseen date for user");
	$query = "UPDATE `users` SET `userLastLogin`=".time()." WHERE `userName` = '".$_SERVER['PHP_AUTH_USER']."'";
	e_log(9,$query);
	$db->exec($query);
	$db = NULL;
	session_unset();
}

if(!isset($userData)) $userData = getUserdata($database);

if(isset($_POST['bmedt'])) {
	$db = new PDO('sqlite:'.$database);
	$query = "UPDATE `bookmarks` SET `bmTitle` = '".$_POST['title']."', `bmURL` = '".validate_url($_POST['url'])."', `bmAdded` = '".round(microtime(true) * 1000)."' WHERE `bmID` = '".$_POST['id']."' AND `userID` = ".$userData['userID'];
	$db->exec($query);
	$count = $db = NULL;
	if($count > 0)
		die(true);
	else
		die(false);
}

if(isset($_POST['bmmv'])) {
	$db = new PDO('sqlite:'.$database);
	$query = "SELECT MAX(bmIndex)+1 AS 'index' FROM `bookmarks` WHERE `bmParentID` = '".$_POST['folder']."'";
	$statement = $db->prepare($query);
	$statement->execute();
	$folderData = $statement->fetchAll(PDO::FETCH_ASSOC);
	$query = "UPDATE `bookmarks` SET `bmIndex` = ".$folderData[0]['index'].", `bmParentID` = '".$_POST['folder']."', `bmAdded` = '".round(microtime(true) * 1000)."' WHERE `bmID` = '".$_POST['id']."' AND `userID` = ".$userData['userID'];
	$count = $db->exec($query);
	$db = NULL;
	if($count > 0)
		die(true);
	else
		die(false);
}

if(isset($_POST['arename'])) {
	$cliento = filter_var($_POST['cido'], FILTER_SANITIZE_STRING);
	$name = filter_var($_POST['nname'], FILTER_SANITIZE_STRING);
	e_log(8,"Renaming client $cliento to $name");
	$db = new PDO('sqlite:'.$database);
	$query = "UPDATE `clients` SET `cname` = '".$name."' WHERE `uid` = ".$userData['userID']." AND `cid` = '".$cliento."'";
	e_log(9,$query);
	$count = $db->exec($query);
	$db = NULL;
	
	if($count == 1)
		die(bClientlist($userData['userID'], $database));
	else
		die(false);
}

if(isset($_POST['adel'])) {
	$db = new PDO('sqlite:'.$database);
	$query = "DELETE FROM `clients` WHERE `uid` = ".$userData['userID']." AND `cid` = '".$_POST['cido']."'";
	$count = $db->exec($query);
	$db = NULL;
	if($count > 0)
		die(bClientlist($userData['userID'], $database));
	else
		die(false);
}

if(isset($_POST['muedt'])) {
	$del = false;
	$headers = "From: PHPMarks <$sender>";
	$url = $_SERVER['REQUEST_SCHEME']."://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];

	switch($_POST['muedt']) {
		case "Add User":
			$pwd = password_hash($_POST['npwd'],PASSWORD_DEFAULT);
			$level = $_POST['userLevel'] + 1;
			$query = "INSERT INTO `users` (`userName`,`userType`,`userHash`) VALUES ('".$_POST['nuser']."', '".$level."', '".$pwd."')";
			e_log(8,"Adding new user ".$_POST['nuser']);
			$message = "Hello,\r\n\r\na account with the following credentials is created and stored encrypted on the database:\r\nE-Mail: ".$_POST['nuser']."\r\nPassword: ".$_POST['npwd']."\r\n\r\nYou can login at $url";
			if(!mail ($_POST['nuser'], "Account created",$message,$headers)) e_log(1,"Error sending data for created user account to user");
			break;
		case "Edit User":
			$pwd = password_hash($_POST['npwd'],PASSWORD_DEFAULT);
			$level = $_POST['userLevel'] + 1;
			$query = "UPDATE `users` SET `userName`= '".$_POST['nuser']."', `userType`= '".$level."', `userHash`= '".$pwd."' WHERE `userID` = ".$_POST['userSelect'].";";
			e_log(8,"Updating user ".$_POST['nuser']);
			$message = "Hello,\r\n\r\nyour account is changed and stored encrypted on the database. Your new credentials are:\r\nE-Mail: ".$_POST['nuser']."\r\nPassword: ".$_POST['npwd']."\r\n\r\nYou can login at $url";
			if(!mail ($_POST['nuser'], "Account changed",$message,$headers)) e_log(1,"Error sending data for changed user account to user");
			break;
		case "Delete User":
			$query = "DELETE FROM `users` WHERE `userID` = ".$_POST['userSelect'];
			$del = true;
			e_log(8,"Removing user ".$_POST['nuser']);
			$message = "Hello,\r\n\r\nyour account '".$_POST['nuser']."' and all it's data is removed from $url.";
			if(!mail ($_POST['nuser'], "Account removed",$message,$headers)) e_log(1,"Error sending data for created user account to user");
			break;
		default:
			e_log(1,"Unknown action by managing users");
			die("Unknown action by managing users");
			break;
	}
	
	$db = new PDO('sqlite:'.$database);
	e_log(9,$query);
	$db->exec($query);
	if($del) {
		$query = "DELETE FROM `clients` WHERE `userID` = ".$_POST['userSelect'];
		e_log(8,"Removing clients for user ".$_POST['nuser']);
		e_log(9,$query);
		$db->exec($query);
		$query = "DELETE FROM `bookmarks` WHERE `userID` = ".$_POST['userSelect'];
		e_log(8,"Removing bookmarks for user ".$_POST['nuser']);
		e_log(9,$query);
		$db->exec($query);
	}
	$db = NULL;
}

if(isset($_POST['mlog'])) {
	if($userData['userType'] > 1) {
		die(file_get_contents($logfile));
	}	else {
		$message = "Not allowed to read server logfile.";
		e_log(2,$message);
		die($message);
	} 
}

if(isset($_POST['mclear'])) {
	if($userData['userType'] > 1) {
		file_put_contents($logfile,"");
	}
	die();
}

if(isset($_POST['madd'])) {
	$bmParentID = $_POST['folder'];
	$bmURL = validate_url(trim($_POST['url']));
	e_log(8,"Try to add manually new bookmark: ".$bmURL);
	$bmID = unique_code(12);
	$bmIndex = getIndex($bmParentID);
	if(strpos($bmURL,'http') != 0) {
		e_log(1,"Given string is not a real URL, cant add this.");
		exit;
	}
	$bmTitle = getSiteTitle($bmURL);
	$bmAdded = round(microtime(true) * 1000);
	$userID = $userData['userID'];

	if($bmTitle === "") {
		e_log(1,"Titel is missing, adding bookmark failed.");
		die("Titel is missing, adding bookmark failed.");
	}
	else {
		try {
			$db = new PDO('sqlite:'.$database);
			$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('".$bmID."', '".$bmParentID."', ".$bmIndex.", '".$bmTitle."', 'bookmark', '".$bmURL."', ".$bmAdded.", ".$userID.")";
			$db->exec($query);
			e_log(9,$query);
		}
		catch(PDOException $e) {
			e_log(1,'Exception: '.$e->getMessage());
		}
		$db = NULL;
	}
	if(!isset($_POST['rc'])) {
		e_log(8,"Manually added bookmark.");
		die(bmTree($userData,$database));
	} else {
		e_log(8,"Roundcube added bookmark.");
		die();
	}
}

if(isset($_POST['mdel'])) {
	$bmID = $_POST['id'];
	$userID = $userData['userID'];

	try {
		$db = new PDO('sqlite:'.$database);
		$query = "UPDATE `bookmarks` SET `bmAction`= 1, `bmAdded`= '".round(microtime(true) * 1000)."' WHERE `bmID` = '".$bmID."' AND `userID` = ".$userID;
		$db->exec($query);
	}
	catch(PDOException $e) {
		e_log(1,'Exception : '.$e->getMessage());
	}
	$db = NULL;
	if(!isset($_POST['rc'])) {
		e_log(8,"Manual deleted bookmark ".$bmID);
		e_log(9,$query);
		die(bmTree($userData,$database));
	} else {
		e_log(8,"Bookmark ".$bmID." removed by Roundcube");
		e_log(9,$query);
		die();
	}
}

if(isset($_POST['pupdate'])) {
	e_log(8,"Userchange: Updating user password started OK");
	if($_POST['opassword'] != "" && $_POST['npassword'] !="" && $_POST['cpassword'] !="") {
		e_log(8,"Userchange: Data complete entered OK");
		if(password_verify($_POST['opassword'],$userData['userHash'])) {
			e_log(8,"Userchange: Verify original password OK");
			if($_POST['npassword'] === $_POST['cpassword']) {
				e_log(8,"Userchange: New and confirmed password OK");
				if($_POST['npassword'] != $_POST['opassword']) {
					e_log(2,"Userchange: Old and new password NOT identical");
					$password = password_hash($_POST['npassword'],PASSWORD_DEFAULT);
					try {
						$db = new PDO('sqlite:'.$database);
						$db->exec("UPDATE `users` SET `userHash`='".$password."' WHERE `userID`=".$userData['userID']);
						e_log(9,"UPDATE `users` SET `userHash`='".$password."' WHERE `userID`=".$userData['userID']);
					}
					catch(PDOException $e) {
						e_log(1,'Exception : '.$e->getMessage());
					}
					$db = NULL;
					e_log(8,"Userchange: Password changed OK");
					$_SERVER['PHP_AUTH_USER'] = "";
					$_SERVER['PHP_AUTH_PW'] = "";
				}
				else {
					e_log(2,"Userchange: Old and new password identical, user not changed");
				}
			}
			else {
				e_log(2,"Userchange: New and confirmed password NOT OK");
			}
		}
		else {
			e_log(2,"Userchange: Verify original password NOT OK");
		}
	}
	else {
		e_log(2,"Userchange: Data missing, NOT OK");
	}
	die();
}

if(isset($_POST['pbupdate'])) {
	if(password_verify($_POST['password'],$userData['userHash'])) {
		e_log(8,"Pushbullet: Updating Pushbullet information.");
		
		$token = edcrpt('en', $_POST['ptoken']);
		$device = edcrpt('en', $_POST['pdevice']);

		try {
			$db = new PDO('sqlite:'.$database);
			$sql = "UPDATE `users` SET `pAPI`='".$token."', `pDevice`='".$device."' WHERE `userID`=".$userData['userID'];
			$db->exec($sql);
			e_log(9,$sql);
		}
		catch(PDOException $e) {
			e_log(1,'Exception : '.$e->getMessage());
		}
		$db = NULL;
		header("location:".$_SERVER['PHP_SELF']);
		die();
	}
	else {
		e_log(1,"Password missmatch. Pushbullet not updated.");
		die("Password missmatch. Pushbullet not updated.");
	}
}

if(isset($_POST['uupdate'])) {
	e_log(8,"Userchange: Updating user name started");
	if($_POST['opassword'] != "") {
		e_log(8,"Userchange: Data complete entered");
		if(password_verify($_POST['opassword'],$userData['userHash'])) {
			e_log(8,"Userchange: Verify original password");
			try {
				$db = new PDO('sqlite:'.$database);
				$db->exec("UPDATE `users` SET `userName`='".$_POST['username']."' WHERE `userID`=".$userData['userID']);
				e_log(9,"UPDATE `users` SET `userName`='".$_POST['username']."' WHERE `userID`=".$userData['userID']);
			}
			catch(PDOException $e) {
				e_log(1,'Exception : '.$e->getMessage());
			}
			$db = NULL;
			e_log(8,"Userchange: Username changed");
			$_SERVER['PHP_AUTH_USER'] = "";
			$_SERVER['PHP_AUTH_PW'] = "";
		}
		else {
			e_log(2,"Userchange: Failed to verify original password");
		}
	}
	else {
		e_log(2,"Userchange: Data missing");
	}
	die();
}

if(isset($_POST['logout'])) {
	e_log(8,"Logout user ".$_SERVER['PHP_AUTH_USER']);
	unset($_SERVER['PHP_AUTH_USER']);
	unset($_SERVER['PHP_AUTH_PW']);

	header('WWW-Authenticate: Basic realm="'.$realm.'", charset="UTF-8"');
	http_response_code(401);

	die("User is now logged out.");
}

if(isset($_POST['caction'])) {
	switch($_POST['caction']) {
		case "addmark":
			$bookmark = json_decode(rawurldecode($_POST['bookmark']), true);			
			$bookmark['url'] = validate_url($bookmark['url']);
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$ctype = getClientType($_SERVER['HTTP_USER_AGENT']);
			if(strtolower($ctype) != "firefox") $bookmark = cfolderMatching($bookmark);
			$ctime = $bookmark["added"];
			if($bookmark['type'] == 'bookmark' && isset($bookmark['url'])) {
				die(json_encode(addBookmark($database, $userData, $bookmark)));
			} else if($bookmark['type'] == 'folder') {
				die(addFolder($database, $userData, $bookmark));
			} else {
				e_log(1,"This bookmark is not added, some parameters are missing");
				die(false);
			}
			break;
		case "movemark":
			$bookmark = json_decode($_POST['bookmark'],true);
			$client = $_POST['client'];
			$ctime = round(microtime(true) * 1000);
			die(json_encode(moveBookmark($database, $userData, $bookmark)));
			break;
		case "editmark":
			$bookmark = json_decode(rawurldecode($_POST['bookmark']),true);
			die(editBookmark($bookmark, $database, $userData));
			break;
		case "delmark":
			$bookmark = json_decode(rawurldecode($_POST['bookmark']),true);
			$client = $_POST['client'];
			$ctime = round(microtime(true) * 1000);
			if(isset($bookmark['url'])) {
				die(json_encode(delBookmark($database, $userData, $bookmark)));
			} else {
				die(json_encode(delFolder($database, $userData, $bookmark)));
			}
			break;
		case "startup":
			$client = $_POST['client'];
			$ctype = getClientType($_SERVER['HTTP_USER_AGENT']);
			$ctime = round(microtime(true) * 1000);
			die(json_encode(getChanges($database, $client, $ctype, $userData, $ctime),JSON_UNESCAPED_SLASHES));
			break;
		case "import":
			$jmarks = json_decode(urldecode($_POST['bookmark']),true);
			$jerrmsg = "";
			switch (json_last_error()) {
				case JSON_ERROR_NONE:
					$jerrmsg = '';
				break;
				case JSON_ERROR_DEPTH:
					$jerrmsg = 'Maximum stack depth exceeded';
				break;
				case JSON_ERROR_STATE_MISMATCH:
					$jerrmsg = 'Underflow or the modes mismatch';
				break;
				case JSON_ERROR_CTRL_CHAR:
					$jerrmsg = 'Unexpected control character found';
				break;
				case JSON_ERROR_SYNTAX:
					$jerrmsg = 'Syntax error, malformed JSON';
				break;
				case JSON_ERROR_UTF8:
					$jerrmsg = 'Malformed UTF-8 characters, possibly incorrectly encoded';
				break;
				default:
					$jerrmsg = 'Unknown error';
				break;
			}

			if(strlen($jerrmsg) > 0) {
				e_log(1,"JSON error: ".$jerrmsg);
				die(json_encode($jerrmsg));
			}

			$client = $_POST['client'];
			$ctype = getClientType($_SERVER['HTTP_USER_AGENT']);
			$ctime = round(microtime(true) * 1000);
			delUsermarks($userData['userID']);
			$armarks = parseJSON($jmarks);
			updateClient($database, $client, $ctype, $userData, $ctime, true);
			die(json_encode(importMarks($armarks,$userData['userID'],$database)));
			break;
		case "export":
			e_log(8,"Browser requested bookmark import...");
			$bookmarks = json_encode(getBookmarks($userData['userID'],$database));
			echo $bookmarks;
			e_log(8,count(json_decode($bookmarks))." bookmarks send to client.");
			die();
			break;
		case "getpurl":
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$url = validate_url($_POST['url']);
			$target = (isset($_POST['tg'])) ? filter_var($_POST['tg'], FILTER_SANITIZE_STRING) : '0';
			$ctime = time();
			$title = getSiteTitle($url);
			$db = new PDO('sqlite:'.$database);
			e_log(8,"Get new pushed URL: ".$url);
			$uidd = $userData['userID'];
			$query = "INSERT INTO `notifications` (`title`,`message`,`ntime`,`repeat`,`nloop`,`publish_date`,`userID`) VALUES ('$title', '$url', $ctime, $target,1,$ctime,$uidd)";
			e_log(9,$query);
			$erg = $db->exec($query);
			if($erg !== 0) echo("URL successfully pushed.");
			break;
		case "lsnc":
			$db = new PDO('sqlite:'.$database);
			e_log(8,"Get lastseen date.");
			$query = "SELECT MAX(`lastseen`) as lastseen FROM `clients` WHERE `uid` = ".$userData['userID'].";";
			e_log(9,$query);
			$statement = $db->prepare($query);
			$statement->execute();
			$lastSeen = $statement->fetchColumn();
			$db = NULL;
			e_log(8,"lastseen date: ".$lastSeen);
			die($lastSeen);
			break;
		case "rmessage":
			$db = new PDO('sqlite:'.$database);
			$message = filter_var($_POST['message'], FILTER_VALIDATE_INT);
			$loop = filter_var($_POST['lp'], FILTER_SANITIZE_STRING) == 'aNoti' ? 1 : 0;
			e_log(8,"Try to remove notification ".$message);
			$query = "DELETE FROM `notifications` WHERE `userID` = ".$userData['userID']." AND `id` = $message;";
			e_log(9,$query);
			$count = $db->exec($query);
			$db = NULL;
			($count === 1) ? e_log(8,"Notification successfully removed") : e_log(9,"Error, removing notification");
			die(notiList($userData['userID'], $loop, $database));
			break;
		case "soption":
			$option = filter_var($_POST['option'], FILTER_SANITIZE_STRING);
			$value = filter_var(filter_var($_POST['value'], FILTER_SANITIZE_NUMBER_INT), FILTER_VALIDATE_INT);
			e_log(8,"Option received: ".$option.":".$value);
			$oOptionsA = json_decode($userData['uOptions'],true);
			$oOptionsA[$option] = $value;
			$db = new PDO('sqlite:'.$database);
			$query = "UPDATE `users` SET `uOptions`='".json_encode($oOptionsA)."' WHERE `userID`=".$userData['userID'];
			e_log(9,$query);
			$count = $db->exec($query);
			($count === 1) ? e_log(8,"Option saved") : e_log(9,"Error, saving option");
			echo $count;
			break;
		case "getclients":
			e_log(8,"Try to get list of clients.");
			$db = new PDO('sqlite:'.$database);
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$query = "SELECT cid, IFNULL(cname, cid) cname, ctype, lastseen FROM clients WHERE uid = ".$userData['userID']." AND NOT cid = '$client' ORDER BY 2 COLLATE NOCASE ASC;";
			e_log(9,$query);
			$statement = $db->prepare($query);
			$statement->execute();
			$clientList = $statement->fetchAll(PDO::FETCH_ASSOC);
			e_log(8,"Found ".count($clientList)." clients. Send list to requesting client.");

			if (!empty($clientList)) {
				foreach($clientList as $key => $client) {
					$myObj[$key]['id'] =	$client['cid'];
					$myObj[$key]['name'] = 	$client['cname'];
					$myObj[$key]['type'] = 	$client['ctype'];
					$myObj[$key]['date'] = 	$client['lastseen'];
				}
				die(json_encode($myObj));
			} else {
				$myObj[0]['id'] =	'0';
				$myObj[0]['name'] =	'All Clients';
				$myObj[0]['type'] =	'';
				$myObj[0]['date'] =	'';
				die(json_encode($myObj));
			}
			break;
		case "tl":
			e_log(8,"Get testrequest from saving client options.");
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$type = getClientType($_SERVER['HTTP_USER_AGENT']);
			$time = round(microtime(true) * 1000);
			die(updateClient($database, $client, $type, $userData, $time));
			break;
		case "gname":
			e_log(8,"Get clientname.");
			$client = filter_var($_POST['cl'], FILTER_SANITIZE_STRING);
			$db = new PDO('sqlite:'.$database);
			$query = "SELECT cname, ctype FROM clients WHERE cid = '$client' and uid = ".$userData['userID'].";";
			e_log(9,$query);
			$statement = $db->prepare($query);
			$statement->execute();
			$clientData = $statement->fetchAll(PDO::FETCH_ASSOC)[0];
			e_log(8,"Send name ".$clientData['cname']." back to client.");
			die(json_encode($clientData));
			break;

	default:
			die(json_encode("Unknown Action"));
	}
	die();
}

if(isset($_GET['gurls'])) {
	$db = new PDO('sqlite:'.$database);
	$client = (isset($_GET['client'])) ? $_GET['client'] : '0';
	e_log(8,"Get pushed site from clients.");
	$query = "SELECT * FROM `notifications` WHERE `nloop` = 1 AND `userID` = ".$userData['userID']." AND `repeat` IN ('".$client."','0');";
	$uOptions = json_decode($userData['uOptions'],true);
	$statement = $db->prepare($query);
	e_log(9,$query);
	$statement->execute();
	$notificationData = $statement->fetchAll(PDO::FETCH_ASSOC);
	e_log(8,"Found ".count($notificationData)." links. Will push them to the client.");
	
	if (!empty($notificationData)) {
		foreach($notificationData as $key => $notification) {
			$myObj[$key]['title'] = html_entity_decode($notification['title']);
			$myObj[$key]['url'] = $notification['message'];
			$myObj[$key]['nkey'] = $notification['id'];
			$myObj[$key]['nOption'] = $uOptions['notifications'];
		}
		die(json_encode($myObj));
	}
	else {
		die();
	}
}

if(isset($_GET['durl'])) {
	$db = new PDO('sqlite:'.$database);
	$notification = filter_var($_GET['durl'], FILTER_VALIDATE_INT);
	e_log(8,"Remove notification.");	
	$query = "UPDATE `notifications` SET `nloop`= 0, `ntime`= '".time()."' WHERE `id` = $notification AND `userID` = ".$userData['userID'];
	e_log(9,$query);
	$count = $db->exec($query);
	echo $count;
	die();
}

if(isset($_GET['link'])) {
	$url = validate_url($_GET["link"]);
	e_log(9,"Bookmarklet URL: " . $url);

	if(!empty($_GET["title"])) {
		$title = $_GET["title"];
	}
	else {
		$title = getSiteTitle($url);
	}

	$bookmark['url'] = $url;
	$bookmark['nfolder'] = 'unfiled_____';
	$bookmark['title'] = $title;
	$bookmark['id'] = unique_code(12);
	$bookmark['type'] = 'bookmark';
	$bookmark['added'] = round(microtime(true) * 1000);
	
	if(isset($_GET['push']) && $_GET['push']=='false') {
		$push = false;
	} else {
		$push = true;
	}
	
	if(strlen($userData['pAPI']) > 0 && strlen($userData['pDevice']) > 0 && $push) {
		pushlink($title,$url,$userData);
	}
	
	$res = addBookmark($database, $userData, $bookmark);
	if($res == 1) {
		if(isset($_GET['client']) && $_GET['client'] == 'Android') {
			echo("URL is added successfully.");
		}
		else {
			echo "<script>window.onload = function() { window.close();}</script>";
		}
	}
	else {
		echo $res;
	}
	die();
}

if(isset($_POST['export'])) {
	$format = $_POST['export'];
	html_export($userData['userID'],$database);
	exit;
}

echo htmlHeader($userData);
$bmTree = bmTree($userData,$database);
echo "<div id='bookmarks'>$bmTree</div>";
echo "<div id='hmarks' style='display: none'>$bmTree</div>";
echo htmlFooter($userData['userID']);

function getClientType($uas) {
	if(strpos($uas,"Firefox")) return "Firefox";
    elseif(strpos($uas, "Edg")) return "Edge";
    elseif(strpos($uas, "OPR")) return "Opera";
    elseif(strpos($uas, "Vivaldi")) return "Vivaldi";
    elseif(strpos($uas, "Brave")) return "Brave";
    elseif(strpos($uas, "SamsungBrowser")) return "SamsungBrowser";
    elseif(strpos($uas, "Chrome")) return "Chrome";
}

function validate_url($url) {
	$url = filter_var(filter_var(urldecode($url), FILTER_SANITIZE_STRING), FILTER_SANITIZE_URL);

	if (filter_var($url, FILTER_VALIDATE_URL)) {
		return $url;
	} else {
		e_log(2,"URL is not a valid URL. Exit now.");
		exit;
	}
}

function pushlink($title,$url,$userdata) {
	$token = edcrpt('de', $userdata['pAPI']);
	$device = edcrpt('de', $userdata['pDevice']);
	e_log(8,"Send Push Notification to device. Token: $token, Device: $device");
	$data = json_encode(array(
		'type' => 'link',
		'title' => $title,
		'url'	=> $url,
		'device_iden' => $device
	));

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, 'https://api.pushbullet.com/v2/pushes');
	curl_setopt($curl, CURLOPT_USERPWD, $token);
	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
	curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($data)]);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HEADER, false);
	curl_exec($curl);
	curl_close($curl);
}

function edcrpt($action, $text) {
    $output = false;
    $encrypt_method = "AES-256-CBC";
    $secret_key = 'p4M7kU49DVGB73dM';
    $secret_iv = 'Hd6uRkLHmg3Z59d7';
    $key = hash('sha256', $secret_key);
    $iv = substr(hash('sha256', $secret_iv), 0, 16);

    if ( $action == 'en' ) {
        $output = openssl_encrypt($text, $encrypt_method, $key, 0, $iv);
        $output = base64_encode($output);
    } else if( $action == 'de' ) {
        $output = openssl_decrypt(base64_decode($text), $encrypt_method, $key, 0, $iv);
    }
    return $output;
}

function cfolderMatching($bookmark) {
	switch($bookmark['folder']) {
		case "0": $bookmark['folder'] = "root________"; break;
		case "1": $bookmark['folder'] = "toolbar_____"; break;
		case "2": $bookmark['folder'] = "unfiled_____"; break;
		case "3": $bookmark['folder'] = "mobile______"; break;
		default: break;
	}
	$bookmark['id'] = unique_code(12);
	return $bookmark;
}

function html_export($uid,$database) {
	header('Content-Description: File Transfer');
	header('Content-Type: text/html');
	header('Content-Disposition: attachment; filename="bookmarks.html"'); 
	header('Content-Transfer-Encoding: binary');
	header('Connection: Keep-Alive');
	header('Expires: 0');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');

$content = '<!DOCTYPE NETSCAPE-Bookmark-file-1>
<!-- This is an automatically generated file.
	 It will be read and overwritten.
	 DO NOT EDIT! -->
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
<TITLE>Bookmarks</TITLE>';

$umarks = makeHTMLExport(getBookmarks($uid,$database));
do {
	$start = strpos($umarks,"%ID");
	$end = strpos($umarks,"\n",$start);
	$len = $end - $start;
	$umarks = substr_replace($umarks, "", $start, $len);
} while (strpos($umarks,"%ID") > 0);

$content.="$umarks\r\n</DL><p>";

	echo $content;
}

function delFolder($database, $ud, $bm) {
	$db = new PDO('sqlite:'.$database);
	e_log(8,"Remove folder");
	$query = "UPDATE `bookmarks` SET `bmAction`= 1, `bmAdded`= '".round(microtime(true) * 1000)."' WHERE `bmID` = '".$bm['id']."' AND `userID` = ".$ud['userID'];
	e_log(9,$query);
	$db->exec($query);
	e_log(8,"Remove bookmarks for that folder");
	$query = "UPDATE `bookmarks` SET `bmAction`= 1, `bmAdded`= '".round(microtime(true) * 1000)."' WHERE `bmParentID` = '".$bm['id']."' AND `userID` = ".$ud['userID'];
	e_log(9,$query);
	$db->exec($query);
	return true;
}

function delBookmark($database, $ud, $bm) {
	$db = new PDO('sqlite:'.$database);
	e_log(8,"Remove bookmark");
	$query = "UPDATE `bookmarks` SET `bmAction`= 1, `bmAdded`= '".round(microtime(true) * 1000)."' WHERE `bmURL` = '".$bm['url']."' AND `userID` = ".$ud['userID'];
	e_log(9,$query);
	$db->exec($query);
	return true;
}

function editBookmark($bm, $database, $ud) {
	$db = new PDO('sqlite:'.$database);
	e_log(8,"Edit bookmark request, try to find the bookmark first by url...");
	$query = "SELECT `bmID`  FROM `bookmarks` WHERE `bmURL` = '".$bm['url']."' AND `userID` = ".$ud['userID'];
	e_log(9,$query);
	$statement = $db->prepare($query);
	$statement->execute();
	$bmData = $statement->fetchAll(PDO::FETCH_ASSOC);

	if(count($bmData) == 1) {
		e_log(8,"Unique entry found, edit the title of the bookmark.");
		$query = "UPDATE `bookmarks` SET `bmTitle` = '".$bm['title']."' WHERE `bmID` = '".$bmData[0]['bmID']."' AND userID = ".$ud["userID"].";";
		e_log(9,$query);
		$count = $db->exec($query);
	} else {
		e_log(8,"No unique bookmark found, try to find now by title...");
		$query = "SELECT `bmID`  FROM `bookmarks` WHERE `bmTitle` = '".$bm['title']."' AND `userID` = ".$ud['userID'];
		e_log(9,$query);
		$statement = $db->prepare($query);
		$statement->execute();
		$bmData = $statement->fetchAll(PDO::FETCH_ASSOC);

		if(count($bmData) == 1) {
			e_log(8,"Unique entry found, edit the url of the bookmark.");
			$query = "UPDATE `bookmarks` SET `bmURL` = '".$bm['url']."' WHERE `bmID` = '".$bmData[0]['bmID']."' AND userID = ".$ud["userID"].";";
			e_log(9,$query);
			$count = $db->exec($query);
		} else {
			e_log(8,"No Unique entry found, chancel operation and send error to client.");
			$count = 0;
		}
	}

	return $count;
}

function moveBookmark($database, $ud, $bm) {
	$db = new PDO('sqlite:'.$database);
	e_log(8,"Bookmark seems to be moved, checking current folder data");
	$query = "SELECT `bmID` FROM `bookmarks` WHERE `bmType` = 'folder' AND `bmTitle` = '".$bm['nfolder']."' AND `userID` = ".$ud['userID'];
	$statement = $db->prepare($query);
	e_log(9,$query);
	$statement->execute();
	$folderData = $statement->fetchAll(PDO::FETCH_ASSOC)[0];
	
	if(is_null($folderData['bmID'])) {
		e_log(2,"Folder not found, can`t move bookmark.");
		return "Folder not found, bookmark not moved.";
	}

	if(array_key_exists("url", $bm)) {
		e_log(8,"Checking bookmark data before moving it");
		$query = "SELECT * FROM `bookmarks` WHERE `userID`= ".$ud["userID"]." AND `bmURL` = '".$bm["url"]."'";
		$statement = $db->prepare($query);
		e_log(9,$query);
		$statement->execute();
		$oldData = $statement->fetchAll(PDO::FETCH_ASSOC)[0];
		
		if (!empty($folderData) && !empty($oldData)) {
			if(($folderData['bmParentID'] != $oldData['bmParentID']) || ($oldData['bmIndex'] != $bm['index'])) {
				e_log(8,"Folder or Position changed, moving bookmark");
				$query = "DELETE FROM `bookmarks` WHERE `bmID` = '".$oldData["bmID"]."'";
				e_log(9,$query);
				$db->exec($query);
				e_log(8,"Re-Add bookmark on new position");
				$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('".$oldData["bmID"]."', '".$bm['folder']."', ".$bm['index'].", '".$oldData['bmTitle']."', '".$oldData['bmType']."', '".$oldData['bmURL']."', ".$oldData['bmAdded'].", ".$ud["userID"].")";
				e_log(9,$query);
				$db->exec($query);
				return true;
			}
			else {
				e_log(2,"Bookmark not moved, exiting");
				return "Bookmark not moved, exiting";
			}
		}
		else {
			return "Cant move bookmark, data not found.";
		}
	}
	else {
		e_log(8,"url key not found");
	}
}

function addFolder($database, $ud, $bm) {
	try {
		$db = new PDO('sqlite:'.$database);
	}
	catch (PDOException $e) {
		e_log(1,'DB connection failed: '.$e->getMessage());
	}
	
	$query = "SELECT COUNT(*) AS bmcount  FROM `bookmarks` WHERE `bmTitle` = '".$bm['title']."' AND `bmParentID` = (SELECT `bmID` FROM `bookmarks` WHERE `bmTitle` = '".$bm['nfolder']."') AND `userID` = ".$ud['userID'];
	$statement = $db->prepare($query);
	$statement->execute();
	$fdExistData = $statement->fetchAll(PDO::FETCH_ASSOC)[0]["bmcount"];
	if($fdExistData > 0) {
		e_log(8,"Folder not added, it exists already for this user");
		return false;
	}
	
	e_log(8,"Get folder data for adding folder");
	$query = "SELECT MAX(`bmIndex`) +1 AS `nindex`, `bmParentId` FROM `bookmarks` WHERE `bmParentId` IN (SELECT `bmId` FROM `bookmarks` WHERE `bmType` = 'folder' AND `bmTitle` = '".$bm['nfolder']."' AND `userId` = ".$ud['userID'].")";
	$statement = $db->prepare($query);
	e_log(9,$query);
	
	$statement->execute();
	$folderData = $statement->fetchAll();
	
	if (!empty($folderData)) {
		e_log(8,"Add folder '".$bm['title']."'");
		$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmAdded`,`userID`) VALUES ('".$bm['id']."', '".$folderData[0]['bmParentID']."', ".$folderData[0]['nindex'].", '".$bm['title']."', '".$bm['type']."', ".$bm['added'].", ".$ud["userID"].")";
		e_log(9,$query);
		$db->exec($query);
		$db = NULL;
		return true;
	}
	else {
		$db = NULL;
		e_log(1,"Couldn't add folder");
		return false;
	}
}

function addBookmark($database, $ud, $bm) { 
	$db = new PDO('sqlite:'.$database);
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	e_log(8,"Check if bookmark already exists for user.");
	$query = "SELECT COUNT(*) AS bmcount, MAX(bmAction) as bmaction FROM `bookmarks` WHERE `bmUrl` = '".$bm['url']."' and userID = ".$ud["userID"];
	e_log(9,$query);
	$statement = $db->prepare($query);
	$statement->execute();
	$bmExistData = $statement->fetchAll(PDO::FETCH_ASSOC);
	if($bmExistData[0]["bmcount"] > 0) {
		if($bmExistData[0]["bmaction"] == 1) {
			e_log(8,"Undelete removed bookmark.");
			$query = "UPDATE `bookmarks` SET `bmAction` = NULL WHERE `bmUrl` = '".$bm['url']."' AND userID = ".$ud["userID"].";";
			$db->exec($query);
			e_log(9,$query);
			$message = "Bookmark not added, it exists already for this user, instead bookmark is undeleted now on old position.";
			e_log(8,$message);
			return $message;
		}
		else {
			e_log(8,"Bookmark not added, it exists already for this user");
			return "Bookmark not added, it exists already for this user";
		}
	}
	e_log(8,"Get folder data for adding bookmark");
	$query = "SELECT MAX(`bmIndex`) +1 AS `nindex`, `bmParentId` FROM `bookmarks` WHERE `bmParentId` IN (SELECT `bmId` FROM `bookmarks` WHERE `bmType` = 'folder' AND `bmTitle` = '".$bm['nfolder']."' AND `userId` = ".$ud['userID'].")";
	$statement = $db->prepare($query);
	e_log(9,$query);
	$statement->execute();
	$folderData = $statement->fetchAll(PDO::FETCH_ASSOC)[0];
	
	if(is_null($folderData['bmParentID'])) {
		e_log(8,"Folder not found, using 'unfiled_____'.");
		$query = "SELECT MAX(`bmIndex`) +1 AS `nindex`, `bmParentId` FROM `bookmarks` WHERE `userId` = 1";
		$statement = $db->prepare($query);
		e_log(9,$query);
		$statement->execute();
		$folderData = $statement->fetchAll(PDO::FETCH_ASSOC)[0];
		$folderData['bmParentID'] = 'unfiled_____';
	}
	
	if(!empty($folderData)) {
		$title = htmlspecialchars($bm['title'],ENT_QUOTES,'UTF-8');
		e_log(8,"Add bookmark '".$title."'");
		$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('".$bm['id']."', '".$folderData['bmParentID']."', ".$folderData['nindex'].", '".$title."', '".$bm['type']."', '".$bm['url']."', ".$bm['added'].", ".$ud["userID"].")";
		e_log(9,$query);
		try {
			$db->exec($query);
		}
		catch(PDOException $e) {
			e_log(1,'INSERT failed: '.$e->getMessage());
			return "Adding bookmark failed.";
		}
		$db = NULL;
		return 1;
	}
	else {
		$db = NULL;
		e_log(1,"Couldn't add bookmark, folder do not exists.");
		return "Couldn't add bookmark, folder do not exists.";
	}
}

function getChanges($dbase, $cl, $ct, $ud, $time) {
	$db = new PDO('sqlite:'.$dbase);
	$uid = $ud["userID"];
	e_log(8,"Browser startup sync started, get client data");
	$query = "SELECT `lastseen` FROM `clients` WHERE `cid` = '".$cl."' AND `uid` = $uid AND `ctype` = '".$ct."'";
	$statement = $db->prepare($query);
	e_log(9,$query);
	$statement->execute();
	$clientData = $statement->fetch();

	if($clientData) {
		$lastseen = $clientData["lastseen"];
		e_log(8,"Get changed bookmarks for client $cl");
		$query = "SELECT b.bmID AS fdID, b.bmTitle AS fdName, b.bmIndex AS fdIndex, a.bmIndex, a.bmTitle, a.bmType, a.bmURL, a.bmAdded, a.bmModified, a.bmAction FROM bookmarks a INNER JOIN bookmarks b ON b.bmID = a.bmParentID WHERE (a.bmAdded >= $lastseen AND a.userID = $uid) OR (a.bmAction = 1 AND a.bmAdded >= $lastseen AND a.userID = $uid)";
		$statement = $db->prepare($query);
		e_log(9,$query);
		$statement->execute();
		$bookmarkData = $statement->fetchAll(PDO::FETCH_ASSOC);
	}
	else {
		e_log(2,"Client not found in database, registering now");
		updateClient($dbase, $cl, $ct, $ud, $time, true);
		return "New client registered for user.";
	}

	if (!empty($bookmarkData)) {
		updateClient($dbase, $cl, $ct, $ud, $time, true);
		e_log(8,"Try to find bookmarks, which could be completely deleted");
		$query = "SELECT bmID FROM bookmarks WHERE bmAdded <= (SELECT MIN(lastseen) FROM clients WHERE uid = $uid AND lastseen > 1) AND bmAction = 1";
		$statement = $db->prepare($query);
		e_log(9,$query);
		$statement->execute();
		$removeMarks = $statement->fetchAll(PDO::FETCH_ASSOC);

		if (!empty($removeMarks)) {
			e_log(8,count($removeMarks)." are deletable from the database");
			foreach($removeMarks as $bookmark) {
				$query = "DELETE FROM bookmarks WHERE bmID = '".$bookmark["bmID"]."'";
				e_log(9,$query);
				$db->exec($query);
			}
			e_log(8,"Try to compacting database");
			$db->exec("VACUUM");
		}
		else {
			e_log(8,"No bookmarks found to delete from the database");
		}

		return $bookmarkData;
	}
	else {
		e_log(8,"No bookmarks changed since last sync");
		return "No bookmarks added, removed or changed since the client was last seen.";
	}
}

function updateClient($dbase, $cl, $ct, $ud, $time, $sync = false) {
	try {
		$db = new PDO('sqlite:'.$dbase);
	}
	catch (PDOException $e) {
		e_log(1,'DB connection failed: '.$e->getMessage());
	}

	$uid = $ud["userID"];
	$query = "SELECT * FROM `clients` WHERE `cid` = '".$cl."' AND uid = ".$uid;
	$statement = $db->prepare($query);
	e_log(9,$query);
	
	try {
		 $statement->execute();
	}
	catch(PDOException $e) {
		 echo "DB query failed: " . $e->getMessage();
		 e_log(1,"DB query failed: ".$e->getMessage());
		 return false;
	}

	$clientData = $statement->fetchAll();
	if (!empty($clientData) && $sync) {
		$query = "UPDATE `clients` SET `lastseen`= '".$time."' WHERE `cid` = '".$cl."';";
		$db->exec($query);
		e_log(8,"Updating lastlogin for client $cl.");
	}
	else if(empty($clientData)) {
		$query = "INSERT INTO `clients` (`cid`,`cname`,`ctype`,`uid`,`lastseen`) VALUES ('".$cl."','".$cl."', '".$ct."', ".$uid.", '0')";
		e_log(9, $query);
		$db->exec($query);
		e_log(8,"New client detected. Register client $cl for user ".$ud["userName"]);
	}
	
	return "Client updated.";
}

function bmTree($user,$database) {
	e_log(8,"Build HTML tree from bookmarks");
	$bmTree = makeHTMLTree(getBookmarks($user['userID'],$database));
	
	do {
		$start = strpos($bmTree,"%ID");
		$end = strpos($bmTree,"\n",$start);
		$len = $end - $start;
		$bmTree = substr_replace($bmTree, "", $start, $len);
	} while (strpos($bmTree,"%ID") > 0);
	$bmTree = preg_replace("/[\r\n]\s*[\r\n]/",' ',$bmTree);
	return $bmTree;
}

function getIndex($folder) {
	global $database;
	try {
		$db = new PDO('sqlite:'.$database);
	}
	catch (PDOException $e) {
		e_log(1,'DB connection failed: '.$e->getMessage());
	}
	
	$query = "SELECT MAX(`bmIndex`) FROM `bookmarks` WHERE `bmParentID` = '".$folder."'";
	$statement = $db->prepare($query);

	e_log(8,"Get new bookmark ID");
	e_log(9,$query);
	
	try {
		 $statement->execute();
	}
	catch(PDOException $e) {
		e_log(1,'DB query failed: '.$e->getMessage());
		return false;
	}
	$IndexArr = $statement->fetchAll();
	$maxIndex = $IndexArr[0][0] + 1;
	$db = NULL;
	return $maxIndex;
}

function getSiteTitle($url) {
	e_log(8,"Get titel from site ".$url);
	$src = file_get_contents($url);
	if(strlen($src) > 0) {
		preg_match("/\<title\>(.*)\<\/title\>/i",$src,$title_arr);
		$title = (strlen($title_arr[1]) > 0) ? strval($title_arr[1]) : 'unknown';
		e_log(8,"Titel for site is '$title'");
		return mb_convert_encoding($title,"UTF-8");
	}
}

function getUserdata($database) {
	e_log(8,"Get userdata from the database");
	try {
		$db = new PDO('sqlite:'.$database);
	}
	catch (PDOException $e) {
		e_log(1,'DB connection failed: '.$e->getMessage());
	}
	
	$query = "SELECT * FROM `users` WHERE `userName`='".$_SERVER['PHP_AUTH_USER']."'";
	$statement = $db->prepare($query);
	e_log(9,$query);
	
	try {
		 $statement->execute();
	}
	catch(PDOException $e) {
		echo "DB query failed: " . $e->getMessage();
		e_log(1,"DB query failed: ".$e->getMessage());
		$db = NULL;
		return false;
	}

	$userData = $statement->fetchAll();
	if (!empty($userData)) {
		if(password_verify($_SERVER['PHP_AUTH_PW'], $userData[0]['userHash']))
			$db = NULL;
			return $userData[0];
	}
	else {
		$_SERVER['PHP_AUTH_PW'] = '';
		$_SERVER['PHP_AUTH_USER'] = '';
	}
	$db = NULL;
}

function unique_code($limit) {
	e_log(8,"Building bookmark id");
	return substr(base_convert(sha1(uniqid(mt_rand())), 16, 36), 0, $limit);
}

function e_log($level,$message,$errfile="",$errline="",$output=0) {
	global $logfile,$loglevel;
	switch($level) {
		case 9:
			$mode = "debug";
			break;
		case 8:
			$mode = "notice";
			break;
		case 4:
			$mode = "parse";
			break;
		case 2:
			$mode = "warn";
			break;
		case 1:
			$mode = "error";
			break;
		default:
			$mode = "unknown";
			break;
	}
	if($errfile != "") $message = $message." in ".$errfile." on line ".$errline;
	$user = '';
	if(isset($_SERVER['PHP_AUTH_USER'])) $user = $_SERVER['PHP_AUTH_USER'];
	$line = "[".date("d-M-Y H:i:s")."] - [$mode] - $user - ".$_SERVER['REMOTE_ADDR']." - $message\n";

	if($level <= $loglevel) {
		file_put_contents($logfile, $line, FILE_APPEND);
	}
}

function delUsermarks($uid) {
	global $database;
	try {
		$db = new PDO('sqlite:'.$database);
	}
	catch (PDOException $e) {
		e_log(1,'DB connection failed: '.$e->getMessage());
	}
	
	$query = "DELETE FROM `bookmarks` WHERE `UserID`=".$uid;
	$statement = $db->prepare($query);
	e_log(9,$query);
	
	try {
		 $statement->execute();
	}
	catch(PDOException $e) {
		 e_log(1,'DB query failed: '.$e->getMessage());
		 return false;
	}
	$db = NULL;
}

function minFile($infile) {
	$outfile = $infile;
	$infile = pathinfo($infile);
	$minfile = $infile['filename'].'.min.'.$infile['extension'];
	$outfile = (file_exists($minfile)) ? $minfile : $outfile;
	return $outfile;
}

function htmlHeader($ud) {
	global $database;
	$db = new PDO('sqlite:'.$database);
	$htmlHeader = "<!DOCTYPE html>
		<html>
			<head>
				<meta name='viewport' content='width=device-width, initial-scale=1'>
				<script type='text/javascript' src='".minfile("bookmarks.js")."'></script>
				<link type='text/css' rel='stylesheet' href='".minfile("bookmarks.css")."'>
				<link type='text/css' rel='stylesheet' href='font-awesome/css/font-awesome.min.css'>
				<link rel='shortcut icon' type='image/x-icon' href='./images/bookmarks.ico'>
				<link rel='manifest' href='manifest.json'>
				<meta name='theme-color' content='#0879D9'>
				<title>Bookmarks</title>
			</head>
			<body>";
	
	$htmlHeader.= "<div id='menu'>
	<div id='hmenu'>
		<div class='hline'></div>
		<div class='hline'></div>
		<div class='hline'></div>
	</div>
	<button>&#8981;</button><input type='search' name='bmsearch' value=''>
	<a id='mprofile' title=\"Last login: ".date("d.m.y H:i",$ud['userOldLogin'])."\">My Bookmarks</a>
		</div>";
	
	if($ud['userType'] == 2) {
		$userSelect = "<select id='userSelect' name='userSelect'>";
		$userSelect.= "<option value='' hidden>-- Select User --</option>";
		$statement = $db->prepare("SELECT `userID`, `userName` FROM `users`");
		$statement->execute();
		$userList = $statement->fetchAll(PDO::FETCH_ASSOC);
		
		foreach ($userList as $key => $user) {
			$userSelect.= "<option value='".$user['userID']."'>".$user['userName']."</option>";
		}
		$userSelect.= "</select>";
	} else {
		$userSelect = "";
	}

	if($ud['userType'] == 2) {
		$admenu = "<hr><li class='fa fa-file-text-o' id='mlog'>Logfile</li><li class='fa fa-users' id='mngusers'>Users</li>";
		$logform = "<div id=\"logfile\"><div id=\"close\"><button id='mclear'>clear</button> <button id='mclose'>&times;</button></div><div id='lfiletext'></div></div>";
		$mnguserform = "<div id='mnguform' class='mbmdialog'><h6>Manage Users</h6><form enctype='multipart/form-data' action='".$_SERVER['PHP_SELF']."' method='POST'>
						<div class='select'>
						$userSelect
						<div class='select__arrow'></div>
						</div>
						<input placeholder='Username' type='text' required id='nuser' name='nuser' autocomplete='username' value='' />
						<input placeholder='Password' type='password' required id='npwd' name='npwd' autocomplete='password' value='' />
						<div class='select'>
						<select id='userLevel' required name='userLevel'><option value='' hidden>-- Select Level --</option><option value='0'>Normal</option><option value='1'>Admin</option></select>
						<div class='select__arrow'></div>
						</div>
						<div class='dbutton'>
						<button type='submit' id='muadd' name='muedt' value='Add User' disabled>Save</button><button type='submit' id='mudel' name='muedt' value='Delete User' disabled formnovalidate>Delete</button>
						</div>
						</form></div>";
	}
	else {
		$admenu = "";
		$logform = "";
		$mnguserform = "";
	}

	$clink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; 
	$bookmarklet = "javascript:void function(){window.open('$clink?title='+document.title+'&link='+encodeURIComponent(document.location.href),'bWindow','width=480,height=245',replace=!0)}();";	
	$mainmenu = "<div id='mainmenu' class='mmenu'>
					<ul>
						<li id='meheader'><span class='logo'>&nbsp;</span><span class='text'>".$ud['userName']."<br>Last login: ".date("d.m.y H:i",$ud['userLastLogin'])."</span></li>
						<li class='fa fa-user-circle-o' id='muser'>Username</li>
						<li class='fa fa-unlock-alt' id='mpassword'>Password</li>
						<li class='fa fa-desktop' id='clientedt'>Clients</li>
						<li class='fa fa-commenting' id='pbullet'>Pushbullet</li>
						<li class='fa fa-bell' id='nmessages'>Notifications</li>
						<li class='fa fa-external-link' id='bexport'>Export</li>
						<li id='bmlet'><a href=\"$bookmarklet\">Bookmarklet</a></li>
						$admenu
						<hr>
						<li class='fa fa-sign-out' id='mlogout'>Logout</li>
					</ul>
				</div>";
				
	$userform = "<div id='userform' class='mbmdialog'>
				<h6>Change Username</h6>
				<div class='dialogdescr'>Here you can change your username. Type in your new username and your current password and click on save to change it.
				</div>
					<form action='".$_SERVER['PHP_SELF']."' method='POST'>
						<input placeholder='Username' required type='text' name='username' id='username' autocomplete='username' value='".$ud['userName']."'>
						<input placeholder='Password' required type='password' id='password' name='opassword' autocomplete='current-password' value='' />
						<div class='dbutton'><button class='mdcancel' type='reset' value='Reset'>Cancel</button><button type='submit' name='uupdate' value='Save'>Save</button></div>
					</form>
				</div>";
				
	$passwordform = "<div id='passwordform' class='mbmdialog'>
				<h6>Change Password</h6>
				<div class='dialogdescr'>Enter your current password and a new password and confirm the new password. 
				</div>
					<form action='".$_SERVER['PHP_SELF']."' method='POST'>					
						<input required placeholder='Current password' type='password' id='opassword' name='opassword' autocomplete='current-password' value='' />
						<input required placeholder='New password' type='password' id='npassword' name='npassword' autocomplete='new-password' value='' />
						<input required placeholder='Confirm new password' type='password' id='cpassword' name='cpassword' autocomplete='new-password' value='' />
						<div class='dbutton'><button class='mdcancel' type='reset' value='Reset'>Cancel</button><button type='submit' name='pupdate' value='Save'>Save</button></div>
					</form>
				</div>";

	$pbulletform = "<div id='pbulletform' class='mbmdialog'>
				<h6>Pushbullet</h6>
				<div class='dialogdescr'>Maintain your API Token and Device ID. 
				</div>
					<form action='".$_SERVER['PHP_SELF']."' method='POST'>					
						<input placeholder='API Token' type='text' id='ptoken' name='ptoken' value='".edcrpt('de', $ud['pAPI'])."' />
						<input placeholder='Device ID' type='text' id='pdevice' name='pdevice' value='".edcrpt('de', $ud['pDevice'])."' />
						<input required placeholder='Password' type='password' id='password' name='password' autocomplete='current-password' value='' />
						<div class='dbutton'><button class='mdcancel' type='reset' value='Reset'>Cancel</button><button type='submit' name='pbupdate' value='Save'>Save</button></div>
					</form>
				</div>";

	$uOptions = json_decode($ud['uOptions'],true);
	if($uOptions['notifications'] == 1) {
		$oswitch = "<label class='switch' title='Enable/Disable Notifications'><input id='cnoti' type='checkbox' checked><span class='slider round'></span></label>";
	} else {
		$oswitch = "<label class='switch' title='Enable/Disable Notifications'><input id='cnoti' type='checkbox'><span class='slider round'></span></label>";
	}

	$nmessagesform = "<div id='nmessagesform' class='mmenu'>
	<div class='tab'>
	  <button class='tablinks active' data-val='aNoti'>Active</button>
	  <button class='tablinks' data-val='oNoti'>Archived</button>

	  $oswitch

	</div>
	<div id='aNoti' class='tabcontent'style='display: block'>
	  <div class='NotiTable'>
	  	<div class='NotiTableBody'>
		  ".notiList($ud['userID'], 1, $database)."
		</div>
	  </div>
	</div>
	
	<div id='oNoti' class='tabcontent' style='display: none'>
	  <div class='NotiTable'>
	  	<div class='NotiTableBody'>
		  ".notiList($ud['userID'], 0, $database)."
		</div>
	  </div>
	</div>
	</div>";
	
	$mngclientform = "<div id='mngcform' class='mmenu'>".bClientlist($ud['userID'], $database)."</div>";
	
	$htmlHeader.= $mainmenu.$userform.$passwordform.$pbulletform.$logform.$mnguserform.$mngclientform.$nmessagesform;
	$db = NULL;
	return $htmlHeader;
}

function bClientlist($uid, $database) {
	$db = new PDO('sqlite:'.$database);
	$query = "SELECT * FROM `clients` WHERE `uid` = $uid ORDER BY `lastseen` DESC";
	$statement = $db->prepare($query);
	$statement->execute();
	$clientData = $statement->fetchAll(PDO::FETCH_ASSOC);
	
	$clientList = "<ul>";
	foreach($clientData as $key => $client) {
		$cname = $client['cid'];
		if(isset($client['cname'])) $cname = $client['cname'];
		$timestamp = $client['lastseen'] / 1000;
		$lastseen = (date('D, d. M. Y H:i', $timestamp));
		$clientList.= "<li data-type='".strtolower($client['ctype'])."' id='".$client['cid']."' class='client'><div class='clientname'>$cname<input type='text' name='cname' value='$cname'><div class='lastseen'>$lastseen</div></div><div class='fa fa-edit rename'></div><div class='fa fa-trash-o remove'></div></li>";
	}
	$clientList.= "</ul>";
	return $clientList;
}

function notiList($uid, $loop, $database) {
	$db = new PDO('sqlite:'.$database);
	$query = "SELECT * FROM `notifications` WHERE `userID` = $uid AND `nloop` = $loop ORDER BY `publish_date`";
	$statement = $db->prepare($query);
	$statement->execute();
	$aNotitData = $statement->fetchAll(PDO::FETCH_ASSOC);
	$notiList = "";
	foreach($aNotitData as $key => $aNoti) {
		$notiList.= "<div class='NotiTableRow'>
					<div class='NotiTableCell'>
						<span><a class='link' title='".$aNoti['title']."' href='".$aNoti['message']."'>".$aNoti['title']."</a></span>
						<span class='nlink'>".$aNoti['message']."</span>
						<span class='ndate'>".date("d.m.Y H:i",$aNoti['publish_date'])."</span>
					</div>
					<div class='NotiTableCell'><a class='fa fa-trash-o' data-message='".$aNoti['id']."' href='#'></a></div>
				</div>";
	}

	return $notiList;
}

function htmlFooter($uid) {
	$sFolderOptions = "<option value='' hidden>Select Folder</option>";
	$sFolderArr = getUserFolders($uid);
	foreach ($sFolderArr as $key => $folder) {
		if($folder['bmID'] === "unfiled_____")
			$sFolderOptions.= "<option selected value='".$folder['bmID']."'>".$folder['bmTitle']."</option>";
		else
			$sFolderOptions.= "<option value='".$folder['bmID']."'>".$folder['bmTitle']."</option>";
	}
	$burl = (isset($_GET['burl'])) ? $_GET['burl'] : "";
	
	if(isset($_GET['burl']) && isset($_GET['title'])) {
		$mad = "style='display: block'";
		$mdis = "";
	}
	else {
		$mad = "";
		$mdis = "disabled";
	}
	
	$editform = "<div id='bmarkedt' class='mbmdialog'><h6>Edit Bookmark</h6><form id='bmedt' method='POST'>
				<input placeholder='Title' type='text' id='edtitle' name='edtitle' value=''>
				<input placeholder='URL' type='text' id='edurl' name='edurl' value=''>
				<input type='hidden' id='edid' name='edid' value=''>
				<div class='dbutton'><button type='submit' id='edsave' name='edsave' value='Save' disabled>Save</button></div>
				</form></div>";
				
	$moveform = "<div id='bmamove' class='mbmdialog'><h6>Move Bookmark</h6><form id='bmmv' method='POST'>
				<input placeholder='Title' type='text' id='mvtitle' name='mvtitle' value='' disabled>
				<div class='select'>
				<select id='mvfolder' name='mvfolder'>$sFolderOptions</select>
				<div class='select__arrow'></div>
				</div>
				<input type='hidden' id='mvid' name='mvid' value=''>
				<div class='dbutton'><button type='submit' id='mvsave' name='mvsave' value='Save' disabled>Save</button></div>
				</form></div>";

	$htmlFooter = "<div id='bmarkadd' class='mbmdialog' $mad>
					<h6>Add Bookmark</h6>
					<form id='bmadd' action='?madd' method='POST'>
					<input placeholder='URL' type='text' id='url' name='url' value='$burl'>
					<div class='select'>
					<select id='folder' name='folder'>
						$sFolderOptions
					</select>
					<div class='select__arrow'></div>
					</div>
					<div class='dbutton'><button type='submit' id='save' name='madd' value='Save' $mdis>Save</button></div>
					</form></div>
					
					<div id='footer'></div>
					</body></html>";

	$menu = "<menu class='menu'><input type='hidden' id='bmid' title='bmtitle' value=''>
			<ul>
			<li id='btnEdit' class='menu-item fa fa-pencil-square-o'>Edit</li>
			<li id='btnMove' class='menu-item fa fa-arrows-alt'>Move</li>
			<li id='btnDelete' class='menu-item fa fa-trash-o'>Delete</li>
			</ul>
			</menu>";
	return $menu.$editform.$moveform.$htmlFooter;
}

function getUserFolders($uid) {
	global $database;
	try {
		$db = new PDO('sqlite:'.$database);
	}
	catch (PDOException $e) {
		e_log(1,'DB connection failed: '.$e->getMessage());
	}
	
	$statement = $db->prepare("SELECT * FROM `bookmarks` WHERE `bmType` = 'folder' and `userID` = ".$uid);

	e_log(8,"Get folders for user ".$_SERVER['PHP_AUTH_USER']);
	e_log(9,"SELECT * FROM `bookmarks` WHERE `bmType` = 'folder' and `userID` = ".$uid);
	
	try {
		 $statement->execute();
	}
	catch(PDOException $e) {
		e_log(1,'DB query failed: '.$e->getMessage());
		return false;
	}
	$folders = $statement->fetchAll();
	$db = NULL;
	return $folders;
}

function makeHTMLExport($arr) {
	$bookmarks = "";
	
	foreach($arr as $bm) {
		if($bm['bmType'] == "bookmark") {
			$bookmark = "\r\n\t<DT><A HREF=\"".$bm['bmURL']."\" bid=\"".$bm['bmID']."\" ADD_DATE=\"".round($bm['bmAdded']/1000)."\">".$bm['bmTitle']."</A>%ID".$bm['bmParentID'];
			
			$bookmarks = str_replace("%ID".$bm['bmParentID'], $bookmark, $bookmarks);
		}
		
		if($bm['bmType'] == "folder") {
			switch($bm['bmID']) {
				case 'toolbar_____':
					$sfolder = ' PERSONAL_TOOLBAR_FOLDER="true"';
					$fclose = '</DL><p>';
					break;
				case 'unfiled_____':
					$sfolder = ' UNFILED_BOOKMARKS_FOLDER="true"';
					$fclose = '</DL><p>';
					break;
				case 'menu________':
					$fclose = '';
					break;
				default:
					$sfolder = '';
					$fclose = '</DL><p>';
			}

			$flvls = ($bm['bmID'] == 'menu________') ? "\r\n<H1 " : "\r\n\t<DT><H3";
			$flvle = ($bm['bmID'] == 'menu________') ? '</H1>' : '</H3>';
			$nFolder = "$flvls ADD_DATE=\"".round($bm['bmAdded']/1000)."\" LAST_MODIFIED=\"".round($bm['bmModified']/1000)."\"$sfolder>".$bm['bmTitle']."$flvle\r\n\t<DL><p>%ID".$bm['bmID']."\r\n\t$fclose";			
			if(strpos($bookmarks, "%ID".$bm['bmParentID']) > 0) {
				$nFolder = "\r\n\t".$nFolder."\n%ID".$bm['bmParentID'];
				$bookmarks = str_replace("%ID".$bm['bmParentID'], $nFolder, $bookmarks);
			}
			else {
				$bookmarks.= $nFolder;
			}
		}
	}
	return $bookmarks;
}

function makeHTMLTree($arr) {
	$bookmarks = "";
	
	foreach($arr as $bm) {
		if($bm['bmType'] == "bookmark") {
			$title = htmlspecialchars_decode($bm['bmTitle'],ENT_QUOTES);
			$bookmark = "\n<li class='file'><a id='".$bm['bmID']."' title='".$title."' rel='noopener' target='_blank' href='".$bm['bmURL']."'>".$title."</a></li>%ID".$bm['bmParentID'];
			$bookmarks = str_replace("%ID".$bm['bmParentID'], $bookmark, $bookmarks);
		}
		
		if($bm['bmType'] == "folder") {
			$nFolder = "\n<li id='f_".$bm['bmID']."'><label for=\"".$bm['bmTitle']."\">".$bm['bmTitle']."</label><input class='ffolder' value='".$bm['bmID']."' id=\"".$bm['bmTitle']."\" type=\"checkbox\"><ol>%ID".$bm['bmID']."\n</ol></li>";
			if(strpos($bookmarks, "%ID".$bm['bmParentID']) > 0) {
				$nFolder = "\n".$nFolder."\n%ID".$bm['bmParentID'];
				$bookmarks = str_replace("%ID".$bm['bmParentID'], $nFolder, $bookmarks);
			}
			else {
				$bookmarks.= $nFolder;
			}
		}
	}
	return $bookmarks;
}

function importMarks($bookmarks,$uid,$database) {
	e_log(8,"Starting import browser bookmarks");
	$db = new PDO('sqlite:'.$database);
	$db->beginTransaction();
	
	foreach ($bookmarks as $bookmark) {
		if(strlen($bookmark['dateGroupModified'])>0) {
			$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`bmModified`,`userID`) VALUES ('".$bookmark['bmID']."', '".$bookmark['bmParentID']."', ".$bookmark['bmIndex'].", '".$bookmark['bmTitle']."', '".$bookmark['bmType']."', '".$bookmark['bmURL']."', ".$bookmark['bmAdded'].", ".$bookmark['dateGroupModified'].", ".$uid.")";
		}
		else {
			$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('".$bookmark['bmID']."', '".$bookmark['bmParentID']."', ".$bookmark['bmIndex'].", '".$bookmark['bmTitle']."', '".$bookmark['bmType']."', '".$bookmark['bmURL']."', ".$bookmark['bmAdded'].", ".$uid.")";
		}
		e_log(9,$query);
		$db->query($query);
	}
	
	$response = $db->commit();
	$db = NULL;
	if($response)
		e_log(8,"Browser bookmark import successfully");
	else
		e_log(1,"Error importing browser bookmarks");
	
	return $response;
}

function parseJSON($arr) {
	static $bookmarks;
	if(is_array($arr) && array_key_exists("url", $arr)) {
		$dateGroupModified = (isset($arr['dateGroupModified'])) ? $arr['dateGroupModified'] : '';
		if($arr['url'] != "data:") $bookmarks[] = array("bmID"=>$arr['id'],"bmTitle"=>$arr['title'],"bmIndex"=>$arr['index'],"bmAdded"=>$arr['dateAdded'],"dateGroupModified"=>$dateGroupModified,"bmType"=>"bookmark","bmURL"=>$arr['url'],"bmParentID"=>$arr['parentId']);
	}
	elseif(is_array($arr) && !array_key_exists("url", $arr)) {
		$dateGroupModified = (isset($arr['dateGroupModified'])) ? $arr['dateGroupModified'] : '';
		if(array_key_exists("parentId", $arr)) $bookmarks[] = array("bmID"=>$arr['id'],"bmTitle"=>$arr['title'],"bmIndex"=>$arr['index'],"bmAdded"=>$arr['dateAdded'],"dateGroupModified"=>$dateGroupModified,"bmType"=>"folder","bmURL"=>NULL,"bmParentID"=>$arr['parentId']);
	}
	
	if(is_array($arr)) {
		foreach($arr as $k => $v) {
			parseJSON($v);
		}
	}
	return $bookmarks;
}

function getBookmarks($uid,$database) {
	try {
		$db = new PDO('sqlite:'.$database);
	}
	catch (PDOException $e) {
		e_log(1,'DB connection failed: '.$e->getMessage());
	}
	
	$query = "SELECT * FROM `bookmarks` WHERE `bmAction` IS NULL AND `userID` = ".$uid;
	$statement = $db->prepare($query);
	e_log(8,"Get bookmarks for user ".$_SERVER['PHP_AUTH_USER']);
	e_log(9,$query);
	$statement->execute();
	$userMarks = $statement->fetchAll(PDO::FETCH_ASSOC);
	foreach($userMarks as &$element) {
		$element['bmTitle'] = html_entity_decode($element['bmTitle']);
	}
	$db = NULL;
	return $userMarks;
}

function c2hmarks($item, $key) {
	html_entity_decode($item);
}

function doLogin($database,$realm) {
	$valid = false;
	
	if (isset($_SERVER['PHP_AUTH_USER'])) {
		try {
			$db = new PDO('sqlite:'.$database);
		}
		catch (PDOException $e) {
			e_log(1,'DB connection failed: '.$e->getMessage());
		}
		
		$statement = $db->prepare("SELECT * FROM `users` WHERE `userName`='".$_SERVER['PHP_AUTH_USER']."'");
		e_log(9,"SELECT * FROM `users` WHERE `userName`='".$_SERVER['PHP_AUTH_USER']."'");		
		try {
			 $statement->execute();
		}
		catch(PDOException $e) {
			 echo "DB query failed: " . $e->getMessage();
			 e_log(1,"DB query failed: ".$e->getMessage());
			 $db = NULL;
			 return false;
		}

		$userData = $statement->fetchAll();

		if(password_verify($_SERVER['PHP_AUTH_PW'], $userData[0]['userHash'])) {
			$valid = true;
			if (session_status() == PHP_SESSION_ACTIVE) {
				$aTime = time();
				$oTime = $userData[0]['userLastLogin'];
				$seid = session_id();
				$uid = $userData[0]['userID'];

				if($seid != $userData[0]['sessionID']) {
					e_log(8,"Save session to database.");
					$query = "UPDATE `users` SET `userLastLogin`= $aTime, `sessionID` = '$seid', `userOldLogin`= $oTime WHERE `userID` = '$uid'";
					$db->exec($query);
					e_log(9,$query);
				}
			}
		}
	}
	
	if (!$valid) {
		e_log(8,"No user logged in, sending 401 to client.");
		header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");
		header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
		header("Cache-Control: post-check=0, pre-check=0",false);
		header("Pragma: no-cache");
		header('WWW-Authenticate: Basic realm="'.$realm.'", charset="UTF-8"');
		http_response_code(401);
		$db = NULL;
		$lpage = "<!DOCTYPE html>
		<html>
			<head>
				<meta name='viewport' content='width=device-width, initial-scale=1'>
				<base href='".dirname($_SERVER['SCRIPT_NAME'])."/' />
				<link rel='shortcut icon' type='image/x-icon' href='.images/bookmarks.ico'>
				<link rel='manifest' href='./manifest.json'>
				<meta name='theme-color' content='#0879D9'>
				<title>Bookmarks</title>
			</head>
			<body>
				You must login to use this tool.
			</body>
		</html>";
		die($lpage);
	}

	$db = NULL;
}

function initDB($database) {
	if(!file_exists(dirname($database))) {
		if(!mkdir(dirname($database,0777,true))) {
			e_log(1,"Directory for database couldn't created, please check privileges");
		}
		else {
			e_log(8,"Directory for database created, initialize database now");
		}
	}
	
	try {
		$db = new PDO('sqlite:'.$database);
		$query = "CREATE TABLE `bookmarks` (`bmID`	TEXT NOT NULL, `bmParentID`	TEXT NOT NULL, `bmIndex` INTEGER NOT NULL, `bmTitle` TEXT, `bmType`	TEXT NOT NULL, `bmURL` TEXT, `bmAdded` TEXT NOT NULL, `bmModified` TEXT, `userID` INTEGER NOT NULL, `bmAction` INTEGER, PRIMARY KEY(`bmID`))";
		$db->exec($query);
		e_log(9,$query);
		$query = "CREATE TABLE `users` (`userID` INTEGER NOT NULL, `userName` TEXT UNIQUE NOT NULL, `userType` INTEGER NOT NULL, `userHash`	TEXT NOT NULL, `userLastLogin` INT(11), `sessionID`	VARCHAR(255) UNIQUE, `userOldLogin`	INT(11), `pAPI`	VARCHAR(255) UNIQUE, `pDevice` VARCHAR(255) UNIQUE, `uOptions` TEXT, PRIMARY KEY(`userID`));";
		$db->exec($query);
		e_log(9,$query);
		$query = "CREATE TABLE `clients` (`cid` TEXT NOT NULL UNIQUE,`cname` TEXT, `ctype` TEXT NOT NULL, `uid`	INTEGER NOT NULL, `lastseen` TEXT NOT NULL, PRIMARY KEY(`cid`));";
		$db->exec($query);
		e_log(9,$query);
		$query = "CREATE TABLE `notifications` (`id` INTEGER NOT NULL, `title` varchar(250) NOT NULL, `message` TEXT NOT NULL, `ntime` varchar(250) NOT NULL DEFAULT NULL, `repeat` INTEGER NOT NULL DEFAULT 1, `nloop` INTEGER NOT NULL DEFAULT 1, `publish_date` varchar(250) NOT NULL, `userID` INTEGER NOT NULL, PRIMARY KEY(`id`), FOREIGN KEY(`userID`) REFERENCES `users`(`userID`));";
		$db->exec($query);
		e_log(9,$query);

		$bmAdded = time();
		$userPWD = password_hash("mypass",PASSWORD_DEFAULT);
		$db->exec("INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('0', '0', 0, 'GitHub Repository', 'bookmark', 'https://github.com/Offerel', ".$bmAdded.", 1)");
		e_log(9,"INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('0', '0', 0, 'GitHub Repository', 'bookmark', 'https://github.com/Offerel', ".$bmAdded.", 1)");
		$db->exec("INSERT INTO `users` (userName,userType,userHash) VALUES ('admin',2,'".$userPWD."');");
		e_log(9,"INSERT INTO `users` (userName,userType,userHash) VALUES ('admin',2,'".$userPWD."');");
	}
	catch(PDOException $e) {
		e_log(1,'Exception : '.$e->getMessage());
	}
	$db = NULL;
}
?>