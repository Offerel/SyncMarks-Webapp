<?php
/**
 * SyncMarks
 *
 * @version 1.9.2
 * @author Offerel
 * @copyright Copyright (c) 2024, Offerel
 * @license GNU General Public License, version 3
 */
session_start();
include_once "config.inc.php.dist";
include_once "config.inc.php";

define("CONFIG", [
	'db'		=> $database,
	'logfile'	=> $logfile,
	'realm'		=> $realm,
	'loglevel'	=> $loglevel,
	'sender'	=> $sender,
	'suser'		=> $suser,
	'spwd'		=> $spwd,
	'cexp'		=> $cexpjson,
	'enckey'	=> $enckey,
	'enchash'	=> $enchash,
	'expireDays'=> (!isset($expireDays)) ? 7:$expireDays
]);

$le = "";
set_error_handler("e_log");

if(CONFIG['loglevel'] == 9 && CONFIG['cexp']) e_log(9, $_SERVER['REQUEST_METHOD'].' '.var_export($_REQUEST,true));

$version = explode ("\n", file_get_contents('./CHANGELOG.md',NULL,NULL,0,30))[1];
$version = explode(" ", $version)[1];

if(!isset($_SESSION['sauth'])) checkDB();
$htmlFooter = "<div id = \"mnubg\"></div><div id='pwamessage'></div></body></html>";

if(isset($_GET['reset'])){
	$reset = filter_var($_GET['reset'], FILTER_SANITIZE_STRING);
	$headers = "From: SyncMarks <".CONFIG['sender'].">";
	switch($reset) {
		case "request":
			$user = filter_var($_GET['u'], FILTER_SANITIZE_STRING);
			e_log(8,"Password Reset request for '$user'");
			$user = filter_var($_GET['u'], FILTER_SANITIZE_STRING);
			$query = "SELECT `userID`, `userMail` FROM `users` WHERE `userName` = '$user';";
			$result = db_query($query)[0];
			$uid = $result['userID'];
			$mail = $result['userMail'];

			$token = openssl_random_pseudo_bytes(16);
			$token = bin2hex($token);
			$time = time();

			$query = "DELETE FROM `reset` WHERE `userID` = $uid;";
			db_query($query);

			$query = "INSERT INTO `reset`(`userID`,`tokenTime`,`token`) VALUES ($uid,'$time','$token');";
			if(db_query($query)) {
				$message = "Hello $user,\r\nYou requested a new password for your account. If this is correct, please open the following link, to confirm creating a new password:\n".$_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']."?reset=confirm&t=$token\r\nIf this request is not from your side, you should click the following link to chancel the request:\n".$_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']."?reset=chancel&t=$token";
				if(!mail($mail, "Password request confirmation",$message,$headers)) {
					e_log(1,"Error sending password reset request to user");
				}
			}
			
			die(json_encode("1"));
			break;
		case "chancel":
			$token = filter_var($_GET['t'], FILTER_SANITIZE_STRING);
			$query = "SELECT `r`.`userID`, `u`.`userName`, `u`.`userMail`, `r`.`tokenTime`, `r`.`token` FROM `reset` `r` INNER JOIN `users` `u` ON `u`.`userID` = `r`.`userID` WHERE `token` = '$token';";
			$result = db_query($query)[0];
			e_log(8,"Password Reset chancel for token '$token', '".$result['userName']."'");
			$query = "DELETE FROM `reset` WHERE `token` = '$token';";
			if(db_query($query)) {
				e_log(8,"Request removed successful");
				$message = "Hello ".$result['userName'].",\r\nYour password request is canceled, You can login with your old credentials at ".$_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'].". If you want to make sure, that your account is healthy, you should change your password to a new one after logging in.";
				if(!mail($result['userMail'], "Password request canceled",$message,$headers)) {
					e_log(1,"Error sending remove chancel to ".$result['userName']);
				}
			}
			echo htmlHeader();
			echo "<div id='loginbody'>
				<div id='loginform'>
					<div id='loginformh'>Welcome to SyncMarks</div>
					<div id='loginformt'>Password reset canceled. You can login now <a href='".$_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']."'>login</a> with your new password.</div>
				</div>
			</div>";
			echo $htmlFooter;
			die();
			break;
		case "confirm":
			$token = filter_var($_GET['t'], FILTER_SANITIZE_STRING);
			$query = "SELECT `r`.`userID`, `u`.`userName`, `u`.`userMail`, `r`.`tokenTime`, `r`.`token` FROM `reset` `r` INNER JOIN `users` `u` ON `u`.`userID` = `r`.`userID` WHERE `token` = '$token';";
			$result = db_query($query)[0];
			e_log(8,"Password Reset confirmation for token '$token', '".$result['userName']."'");
			$tdiff = time() - $result['tokenTime'];
			if($tdiff <= 300) {
				$npwd = gpwd(16);
				$pwd = password_hash($npwd,PASSWORD_DEFAULT);
				$query = "UPDATE `users` SET `userHash` = '$pwd' WHERE `userID` = ".$result['userID'].";";
				if(db_query($query)) {
					$query = "DELETE FROM `reset` WHERE `token` = '$token';";
					if(db_query($query)) {
						e_log(8,"New password set successful");
						$message = "Hello ".$result['userName'].",\r\nYour new password is set successful, please use:\n$npwd\n\nYou can login at:\n".$_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'];
						if(!mail($result['userMail'], "New password",$message,$headers)) {
							e_log(1,"Error sending new password to ".$result['userName']);
						}
					}
				} else {
					e_log(1,"Password reset failed");
					die(json_encode("Password reset failed"));
				}
				echo htmlHeader();
				echo "<div id='loginbody'>
					<div id='loginform'>
						<div id='loginformh'>Welcome to SyncMarks</div>
						<div id='loginformt'>Password reset successful, please check your mail. You can login now <a href='".$_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']."'>login</a> with your new password.</div>
					</div>
				</div>";
				echo $htmlFooter;
			} else {
				echo htmlHeader();
				echo "<div id='loginbody'>
					<div id='loginform'>
						<div id='loginformh'>Welcome to SyncMarks</div>
						<div id='loginformt'>Token expired, Password reset failed. You can try to <a data-reset='".$result['userName']."' id='preset' href='#'>reset</a> it again.</div>
					</div>
				</div>";
				echo $htmlFooter;
				e_log(1,"Token expired, Password reset failed");
				$query = "DELETE FROM `reset` WHERE `token` = '$token';";
				db_query($query);
				die();
			}
			break;
		default:
			die(e_log(1,"Undefined Request"));
	}
	die();
}

if(!isset($_SESSION['sauth'])) checkLogin(CONFIG['realm']);
if(!isset($_SESSION['sud'])) getUserdataS();

if (isset($_GET['api'])) {
	$jdata = json_decode(file_get_contents('php://input'), true);
	$jerror = json_last_error();
	$jerrmsg = parseJError($jerror);

	if($jerror == JSON_ERROR_NONE) {
		if(isset($jdata['action'])) {
			$action = $jdata['action'];
			$client = $jdata['client'];
			$data = isset($jdata['data']) ? $jdata['data']:false;
			$time = round(microtime(true) * 1000);
			$ctype = getClientType(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT']:"Unknown");
			$uid = $_SESSION['sud']['userID'];

			switch($action) {
				case "clientCheck":
					$tbt = (isset($data['usebasic'])) ? filter_var($data['usebasic'], FILTER_VALIDATE_BOOLEAN):false;
					$response = clientCheck($client, $tbt, $time, $ctype);
					break;
				case "clientRename":
					$response = clientRename($client, $data, $uid);
					if(!is_array($response)) die($response);
					break;
				case "clientInfo":					
					$response = clientInfo($client, $uid);
					break;
				case "clientList":
					$response = clientList($client, $uid);
					break;
				case "pushURL":
					$response = ntfyNotification($data, $uid);
					break;
				case "pushGet":
					$response = pushGet($client, $uid);
					break;
				case "pushHide":
					$response = pushHide($data, $uid);
					break;
				case "bookmarkExport":
					$response = bookmarkExport($ctype, $time, $data, $client);
					break;
				case "bookmarkImport":
					$response = bookmarkImport($data, $client, $ctype, $time, $uid);
					break;
				case "bookmarkAdd":
					$response = bookmarkAdd($data, $time, $ctype, $client);
					break;
				case "bookmarkDel":
					$response = bookmarkDel($data, $uid);
					break;
				case "bookmarkMove":
					$response = bookmarkMove($data, $client, $time, $uid);
					break;
				case "bookmarkEdit":
					$response = (array_key_exists('url', $bookmark)) ? editBookmark($bookmark, $time, $uid):editFolder($bookmark, $time, $uid);
					break;
				case "tabsGet":
					$response = tabsGet($uid);
					break;
				case "tabsSend":
					$response = tabsSend($data, $uid, $time);
					break;
				default:
					$response['message'] = "Undefined action '$action'";
					$response['code'] = 501;
			}
		}
	} else {
		e_log(1, "JSON error: ".$jerrmsg);
		if(CONFIG['cexp'] == true && CONFIG['loglevel'] == 9) {
			$filename = is_dir(CONFIG['logfile']) ? CONFIG['logfile']."/import_".time().".json":"import_".time().".json";
			e_log(8, "JSON file written as $filename");
			file_put_contents($filename, $jdata, true);
		}
		
		$response['message'] = 'Invalid JSON. '.$jerrmsg;
		$response['code'] = 500;
	}

	sendJSONResponse($response);
}

if(isset($_POST['action'])) {
	$uid = $_SESSION['sud']['userID'];
	$client = (isset($_POST['client'])) ? filter_var($_POST['client'], FILTER_SANITIZE_STRING) : '0';
	$data = filter_var($_POST['data'], FILTER_SANITIZE_STRING);
	$time = round(microtime(true) * 1000);
	$ctype = getClientType($_SERVER['HTTP_USER_AGENT']);
	$add = filter_var($_POST['add'], FILTER_SANITIZE_STRING);

	switch($_POST['action']) {
		case "sendTabs":
			$jtabs = json_decode($_POST['data'], true);
			$urls = [];
			foreach ($jtabs as $tab) {
				$urls[] = $tab['url'];
			}
			$jurls = trim(json_encode($urls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), '[]');
			$user = $_SESSION['sud']['userID'];
			$query = "DELETE FROM `bookmarks` WHERE `bmID` IN (SELECT `bmID` FROM `bookmarks` WHERE `bmURL` NOT IN ($jurls) AND `bmType` = 'tab' AND `userID` = $user);";
			$res = db_query($query);
			
			foreach ($jtabs as $key => $tab) {
				$tID = unique_code(12);
				$title = trim($tab['title']);
				$url = $tab['url'];
				$query = "SELECT count(*) AS count FROM `bookmarks` WHERE `bmType` = 'tab' AND `bmURL` = '$url' AND `userID` = $user;";
				$res = db_query($query)[0]['count'];
				$added = round(microtime(true) * 1000);
				if($res == 0) {
					$query = "INSERT INTO `bookmarks` (`bmID`, `bmIndex`, `bmTitle`, `bmType`, `bmURL`, `bmAdded`, `userID`) VALUES ('$tID', $key, '$title', 'tab', '$url', '$added', $user);";
					$res = db_query($query);
				}
			}
			sendJSONResponse(1);
			break;
		case "getTabs":
			$user = $_SESSION['sud']['userID'];
			$query = "SELECT * FROM `bookmarks` WHERE `bmType` = 'tab' AND `userID` = $user";
			$tabs = db_query($query);
			sendJSONResponse($tabs);
			break;
		case "getclients":
			$response = clientList($client, $uid);
			sendJSONResponse($response);
			break;
		case "gurls":
			$response = pushGet($client, $uid);
			sendJSONResponse($response);
			break;
		case "cinfo":
			e_log(8,"Request client info");
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$query = "SELECT `cname`, `ctype`, `lastseen` FROM `clients` WHERE `cid` = '$client' AND `userID` = ".$_SESSION['sud']['userID'].";";
			$clientData = db_query($query)[0];
			if(count($clientData) > 0) {
				e_log(8,"Send client info to '$client'");
				if(CONFIG['cexp'] == true && CONFIG['loglevel'] == 9) {
					$filename = is_dir(CONFIG['logfile']) ? CONFIG['logfile']."/cinfo_".time().".json":"cinfo_".time().".json";
					e_log(8,"Write client info to $filename");
					file_put_contents($filename,json_encode($clientData),true);
				}
			} else {
				e_log(2,"Client not found.");
				$clientData['lastseen'] = 0;
				$clientData['cname'] = '';
				$clientData['ctype'] = '';
			}

			sendJSONResponse($clientData);
			break;
		case "bexport":
			$response = bookmarkExport($ctype, $time, $data, $client);
			sendJSONResponse($response);
			break;
		case "bimport":
			$jmarks = json_decode($_POST['data'], true);
			$jerrmsg = "";
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$partial = (isset($_POST['add'])) ? filter_var($_POST['add'], FILTER_VALIDATE_INT):0;
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
			}

			if(strlen($jerrmsg) > 0) {
				e_log(1,"JSON error: ".$jerrmsg);
				$filename = is_dir(CONFIG['logfile']) ? CONFIG['logfile']."/import_".time().".json":"import_".time().".json";
				e_log(8,"JSON file written as $filename");
				file_put_contents($filename,urldecode($_POST['data']),true);
				sendJSONResponse($jerrmsg);
			}
			
			$client = $_POST['client'];
			$ctype = getClientType($_SERVER['HTTP_USER_AGENT']);
			$ctime = round(microtime(true) * 1000);
			if($partial === 0) delUsermarks($_SESSION['sud']['userID']);
			$armarks = parseJSON($jmarks);
			$ctime = (filter_var($_POST['sync'], FILTER_SANITIZE_STRING) === 'false') ? 0:$ctime;
			updateClient($client, $ctype, $ctime);
			sendJSONResponse(importMarks($armarks,$_SESSION['sud']['userID']));
			break;
		case "addmark":
			$bookmark = json_decode($_POST['data'], true);
			$response = bookmarkAdd($bookmark, $time, $ctype, $client, $add);
			sendJSONResponse($response);
			break;
		case "editmark":
			$bookmark = json_decode(rawurldecode($_POST['data']),true);
			$response = (array_key_exists('url',$bookmark)) ? editBookmark($bookmark):editFolder($bookmark);
			sendJSONResponse($response);
			break;
		case "movemark":
			$bookmark = json_decode($_POST['data'],true);
			if(CONFIG['cexp'] == true && CONFIG['loglevel'] == 9) {
				$filename = is_dir(CONFIG['logfile']) ? CONFIG['logfile']."/movemark_".time().".json":"movemark_".time().".json";
				e_log(8,"Write move bookmark json to $filename");
				file_put_contents($filename,json_encode($bookmark),true);
			}
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$ctime = round(microtime(true) * 1000);
			$response = moveBookmark($bookmark);
			$ctime = (filter_var($_POST['sync'], FILTER_SANITIZE_STRING) === 'false') ? 0:$ctime;
			updateClient($client, strtolower(getClientType($_SERVER['HTTP_USER_AGENT'])), $ctime);
			sendJSONResponse($response);
			break;
		case "delmark":
			$bookmark = json_decode($_POST['data'], true);
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$ctime = round(microtime(true) * 1000);
			e_log(8,"Try to identify bookmark to delete");
			if(isset($bookmark['url'])) {
				$url = $bookmark['url'];
				$query = "SELECT `bmID` FROM `bookmarks` WHERE `bmType` = 'bookmark' AND `bmURL` = '$url' AND `userID` = ".$_SESSION['sud']['userID'].";";
			} else {
				$query = "SELECT `bmID` FROM `bookmarks` WHERE `bmType` = 'folder' AND `bmTitle` = '".$bookmark['title']."' AND `userID` = ".$_SESSION['sud']['userID'].";";
			}
			$response = 1;
			$bData = db_query($query);
			if(count($bData) == 1) {
				e_log(8, "Bookmark found, trying to remove it");
				$response = delMark(array($bData[0]['bmID']));
			} else if (count($bData) > 1) {
				$response = "No unique bookmark found, doing nothing";
				e_log(2, $response);
			} else {
				e_log(2, "Bookmark not found, mark as deleted");
				$response = 1;
			}
			
			sendJSONResponse($response);
			break;
		case "pushHide":
			e_log(8,"Hide notification");
			$page = filter_var($_POST['data'], FILTER_VALIDATE_INT);
			$query = "UPDATE `pages` SET `nloop`= 0, `ntime`= '".time()."' WHERE `pid` = $page AND `userID` = ".$_SESSION['sud']['userID'];
			sendJSONResponse(db_query($query));
			break;
		case "arename":
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$name = filter_var($_POST['data'], FILTER_SANITIZE_STRING);
			e_log(8,"Rename client $client to $name");
			$query = "UPDATE `clients` SET `cname` = '".$name."' WHERE `userID` = ".$_SESSION['sud']['userID']." AND `cid` = '".$client."';";
			$count = db_query($query);

			if (filter_var($_POST['add'], FILTER_SANITIZE_STRING) == "null" && $count > 0) {
				$response = bClientlist($_SESSION['sud']['userID'], 'json');
				sendJSONResponse($response);
			} else {
				die(bClientlist($_SESSION['sud']['userID']));
			}

			break;
		case "cfolder":
			$ctime = round(microtime(true) * 1000);
			$fname = filter_var($_POST['data'], FILTER_SANITIZE_STRING);
			$fbid = filter_var($_POST['add'], FILTER_SANITIZE_STRING);
			sendJSONResponse(cfolder($ctime,$fname,$fbid));
			break;
		case "rmessage":
			$message = isset($_POST['data']) ? filter_var($_POST['data'], FILTER_VALIDATE_INT):0;
			$loop = filter_var($_POST['add'], FILTER_SANITIZE_STRING) == 'aNoti' ? 1:0;
			if($message > 0) {
				e_log(8,"Try to delete page $message");
				$query = "DELETE FROM `pages` WHERE `userID` = ".$_SESSION['sud']['userID']." AND `pid` = $message;";
				$count = db_query($query);
				($count === 1) ? e_log(8,"Notification successfully removed") : e_log(9,"Error, removing notification");
			}
			sendJSONResponse(notiList($_SESSION['sud']['userID'], $loop));
			break;
		case "soption":
			$option = filter_var($_POST['data'], FILTER_SANITIZE_STRING);
			$value = filter_var(filter_var($_POST['add'], FILTER_SANITIZE_NUMBER_INT), FILTER_VALIDATE_INT);
			e_log(8,"Option received: ".$option.":".$value);
			$oOptionsA = json_decode($_SESSION['sud']['uOptions'],true);
			$oOptionsA[$option] = $value;
			$query = "UPDATE `users` SET `uOptions`='".json_encode($oOptionsA)."' WHERE `userID`=".$_SESSION['sud']['userID'].";";
			header("Content-Type: application/json");
			if(db_query($query) !== false) {
				e_log(8,"Option saved");
				sendJSONResponse(json_encode(true));
			} else {
				e_log(9,"Error, saving option");
				sendJSONResponse(json_encode(false));
			}
			break;

		case "bmedt":
			$bookmark = json_decode($_POST['data'], true);
			$title = filter_var($bookmark['title'], FILTER_SANITIZE_STRING);
			$id = filter_var($bookmark['id'], FILTER_SANITIZE_STRING);
			$url = (isset($bookmark['url']) && strlen($bookmark['url']) > 4) ? '\''.validate_url($bookmark['url']).'\'' : 'NULL';
			e_log(8, "Edit entry '$title'");
			$query = "UPDATE `bookmarks` SET `bmTitle` = '$title', `bmURL` = $url, `bmAdded` = '".round(microtime(true) * 1000)."' WHERE `bmID` = '$id' AND `userID` = ".$_SESSION['sud']['userID'].";";
			$count = db_query($query);
			($count > 0) ? sendJSONResponse(true):sendJSONResponse(false);
			break;
		case "bmmv":
			$id = filter_var($_POST['add'], FILTER_SANITIZE_STRING);
			e_log(8,"Move bookmark $id");
			$folder = filter_var($_POST['data'], FILTER_SANITIZE_STRING);
			$query = "SELECT IFNULL(MAX(bmIndex), 0) + 1 AS 'index' FROM `bookmarks` WHERE `bmParentID` = '$folder';";
			$folderData = db_query($query);
			$query = "SELECT `bmParentID` FROM `bookmarks` WHERE `bmID` = '$id' AND `userID` = ".$_SESSION['sud']['userID'].";";
			$oFolder = db_query($query)[0]['bmParentID'];
			$query = "UPDATE `bookmarks` SET `bmIndex` = ".$folderData[0]['index'].", `bmParentID` = '$folder', `bmAdded` = '".round(microtime(true) * 1000)."' WHERE `bmID` = '$id' AND `userID` = ".$_SESSION['sud']['userID'].";";
			$count = db_query($query);
			reIndex($oFolder);
			$response = array("id" => $id, "folder" => $folder);
			($count > 0) ? sendJSONResponse($response):sendJSONResponse(false);
			break;
		case "adel":
			$client = filter_var($_POST['data'], FILTER_SANITIZE_STRING);
			e_log(8,"Delete client $client");
			$query = "DELETE FROM `clients` WHERE `userID` = ".$_SESSION['sud']['userID']." AND `cid` = '$client';";
			$count = db_query($query);
			($count > 0) ? sendJSONResponse(bClientlist($_SESSION['sud']['userID'])):sendJSONResponse(false);
			break;
		case "cmail":
			e_log(8,"Change e-mail for ".$_SESSION['sud']['userName']);
			$nmail = filter_var($_POST['mail'],FILTER_SANITIZE_EMAIL);
			header("Content-Type: application/json");
			if(filter_var($nmail, FILTER_VALIDATE_EMAIL)) {
				$query = "UPDATE `users` SET `userMail` = '$nmail' WHERE `userID` = ".$_SESSION['sud']['userID'].";";
				die(json_encode(db_query($query)));
			} else {
				e_log(1,"No valid E-Mail. Stop changing E-Mail");
				die(json_encode("No valid mail address. Mail not changed."));
			}
			die();
			break;
		case "muedt":
			if($_SESSION['sud']['userType'] < 2) {	
				e_log(1,"Stop user change, no sufficient privileges.");
				die();
			}
			$del = false;
			$headers = "From: SyncMarks <".CONFIG['sender'].">";
			$url = $_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'];

			$data = json_decode($_POST['data'], true);

			$variant = filter_var($data['type'], FILTER_VALIDATE_INT);
			$password = (isset($data['p']) && $data['p'] != '') ? filter_var($data['p'], FILTER_SANITIZE_STRING):gpwd(16);
			$userLevel = filter_var($data['userLevel'], FILTER_VALIDATE_INT);
			$user = filter_var($data['nuser'], FILTER_SANITIZE_STRING);
			$mail = filter_var($user, FILTER_VALIDATE_EMAIL) ? $user:null;

			switch($variant) {
				case 1:
					$pwd = password_hash($password,PASSWORD_DEFAULT);
					e_log(8,"Try to add new user $user");
					$query = "INSERT INTO `users` (`userName`,`userMail`,`userType`,`userHash`) VALUES ('$user', NULLIF('$mail',''), '$userLevel', '$pwd')";
					$nuid = db_query($query);
					if($nuid > 0) {
						if(filter_var($mail, FILTER_VALIDATE_EMAIL)) {
							$response = $nuid;
							$message = "Hello,\r\na new account with the following credentials is created for SyncMarks:\r\nUsername: $user\r\nPassword: $password\r\n\r\nYou can login at $url";
							if(!mail ($mail, "Account created",$message,$headers)) {
								e_log(1,"Error sending data for created user account to user");
								$response = "User created successful, E-Mail could not send";
							}
						} else {
							$response = $nuid;
						}
						$bmAdded = round(microtime(true) * 1000);
						$query = "INSERT INTO `bookmarks` (`bmID`,`bmIndex`, `bmType`, `bmAdded`, `userID`) VALUES ('root________', 0, 'folder', $bmAdded, $nuid);";
						db_query($query);
						$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('unfiled_____', 'root________', 0, 'Other Bookmarks', 'folder', NULL, $bmAdded, $nuid)";
						db_query($query);
						$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('".unique_code(12)."', 'unfiled_____', 0, 'Git Repository', 'bookmark', 'https://codeberg.org/Offerel', $bmAdded, $nuid)";
						db_query($query);
					} else {
						$response = "User creation failed";
					}
					break;
				case 2:
					e_log(8,"Updating user $user");
					$uID = filter_var($data['userSelect'], FILTER_VALIDATE_INT);
					$query = "UPDATE `users` SET `userName`= '$user', `userType`= '$userLevel' WHERE `userID` = $uID;";
					if(db_query($query) == 1) {
						if(filter_var($user, FILTER_VALIDATE_EMAIL)) {
							$response = "User changed successful, Try to send E-Mail to user";
							$message = "Hello,\r\nyour account is changed for SyncMarks. You can login at $url";
							if(!mail ($user, "Account changed",$message,$headers)) e_log(1,"Error sending email for changed user account");
						} else {
							$response = "User changed successful, No mail send to user";
						}
					} else {
						$response = "User change failed";
					}
					break;
				case 3:
					e_log(8,"Delete user $user");
					$uID = filter_var($data['userSelect'], FILTER_VALIDATE_INT);
					$query = "DELETE FROM `users` WHERE `userID` = $uID;";
					if(db_query($query) == 1) {
						if(filter_var($user, FILTER_VALIDATE_EMAIL)) {
							$response = "User deleted, Try to send E-Mail to user";
							$message = "Hello,\r\nyour account '$user' and all it's data is removed from $url.";
							if(!mail ($user, "Account removed",$message,$headers)) e_log(1,"Error sending data for created user account to user");
						} else {
							$response = "User deleted successful, No mail send to user";
						}
					} else {
						$response = "Delete user failed";
					}
					break;
				default:
					$response = "Unknown action for managing users";
					e_log(1,$response);
			}
			sendJSONResponse($response);
			break;
		case "mlog":
			if($_SESSION['sud']['userType'] > 1) {
			    $lfile = is_dir(CONFIG['logfile']) ? CONFIG['logfile'].'/syncmarks.log':CONFIG['logfile'];
				sendJSONResponse(file_get_contents($lfile));
			} else {
				$message = "Not allowed to read server logfile.";
				e_log(2,$message);
				sendJSONResponse($message);
			}
			break;
		case "mrefresh":
			if($_SESSION['sud']['userType'] > 1) {	
			    $lfile = is_dir(CONFIG['logfile']) ? CONFIG['logfile'].'/syncmarks.log':CONFIG['logfile'];
				sendJSONResponse(file_get_contents($lfile));
			} else {
				$message = "Not allowed to read server logfile.";
				e_log(2,$message);
				sendJSONResponse($message);
			}
			break;
		case "mclear":
			e_log(8,"Clear logfile");
			if($_SESSION['sud']['userType'] > 1) {
				$lfile = is_dir(CONFIG['logfile']) ? CONFIG['logfile'].'/syncmarks.log':CONFIGg['logfile'];
				file_put_contents($lfile,"");
				sendJSONResponse(file_get_contents($lfile));
			}
			die();
			break;
		case "mdel":
			$bmID = json_decode($_POST['data'], true);
			$response = delMark($bmID);
			sendJSONResponse($bmID);
			break;
		case "logout":
			e_log(8,"Logout user ".$_SESSION['sauth']);
			unset($_SESSION['sauth']);
			clearAuthCookie();
			e_log(8,"User logged out");
			if(!isset($_POST['client'])) {
				echo htmlHeader();
				echo "<div id='loginbody'>
					<div id='loginform'>
						<div id='loginformh'>Logout successful</div>
						<div id='loginformt'>User logged out. <a href='?'>Login</a> again</div>
					</div>
				</div>";
				echo $htmlFooter;
			}
			session_destroy();
			exit;
			break;
		case "ntfyupdate":
			e_log(8,"ntfy: Updating ntfy information.");
			$password = filter_var($_POST['password'], FILTER_SANITIZE_STRING);
			$cnoti = filter_var($_POST['cnoti'], FILTER_SANITIZE_STRING);
			$ntfyInstance = filter_var($_POST['ntfyInstance'], FILTER_SANITIZE_STRING);
			$ntfyToken = filter_var($_POST['ntfyToken'], FILTER_SANITIZE_STRING);

			if(password_verify($password,$_SESSION['sud']['userHash'])) {
				$ntfyToken = edcrpt('en', $ntfyToken);		
				$oOptionsA = json_decode($_SESSION['sud']['uOptions'],true);
				$oOptionsA['notifications'] = $cnoti;
				$oOptionsA['ntfy']['instance'] = $ntfyInstance;
				$oOptionsA['ntfy']['token'] = $ntfyToken;
		
				$query = "UPDATE `users` SET `uOptions`='".json_encode($oOptionsA)."' WHERE `userID`=".$_SESSION['sud']['userID'].";";
				$count = db_query($query);
				($count === 1) ? e_log(8,"Option saved") : e_log(9,"Error, saving option");
				header("location: ?");
				die();
			}
			else {
				e_log(1,"Password mismatch. ntfy info not updated.");
				die("Password mismatch. ntfy info not updated.");
			}
			die();
			break;
		case "pupdate":
			e_log(8,"User change: Updating user password started");
			$opassword = filter_var($_POST['opassword'], FILTER_SANITIZE_STRING);
			$npassword = filter_var($_POST['npassword'], FILTER_SANITIZE_STRING);
			$cpassword = filter_var($_POST['cpassword'], FILTER_SANITIZE_STRING);

			if($opassword != "" && $npassword !="" && $cpassword !="") {
				e_log(8,"User change: Data complete entered");
				if(password_verify($opassword,$_SESSION['sud']['userHash'])) {
					e_log(8,"User change: Verify original password");
					if($npassword === $cpassword) {
						e_log(8,"User change: New and confirmed password");
						if($npassword != $opassword) {
							$password = password_hash($npassword,PASSWORD_DEFAULT);
							$query = "UPDATE `users` SET `userHash`='$password' WHERE `userID`=".$_SESSION['sud']['userID'].";";
							db_query($query);
							e_log(8,"User change: Password changed");
						} else {
							e_log(2,"User change: Old and new password identical, user not changed");
						}
					}
				} else {
					e_log(2,"User change: Old password mismatch");
				}
			} else {
				e_log(2,"User change: Data missing, process failed");
			}

			unset($_SESSION['sauth']);
			e_log(8,"User logged out");
			echo htmlHeader();
			echo "<div id='loginbody'>
				<div id='loginform'>
					<div id='loginformh'>Logout successful</div>
					<div id='loginformt'>User logged out. <a href='".$_SERVER['SCRIPT_NAME']."'>Login</a> again</div>
				</div>
			</div>";
			echo $htmlFooter;

			die();
			break;
		case "uupdate":
			e_log(8,"User change: Updating user name started");
			$opassword = filter_var($_POST['opassword'], FILTER_SANITIZE_STRING);
			$username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);

			if($opassword != "") {
				e_log(8,"User change: Data complete entered");
				if(password_verify($opassword, $_SESSION['sud']['userHash'])) {
					e_log(8,"User change: Verify original password");
					$query = "UPDATE `users` SET `userName`='$username' WHERE `userID`=".$_SESSION['sud']['userID'].";";
					db_query($query);
					e_log(8,"User change: Username changed");
				}
				else {
					e_log(2,"User change: Failed to verify original password");
				}
			}
			else {
				e_log(2,"User change: Data missing");
			}
			unset($_SESSION['sauth']);
			e_log(8,"User logged out");
			echo htmlHeader();
			echo "<div id='loginbody'>
				<div id='loginform'>
					<div id='loginformh'>Logout successful</div>
					<div id='loginformt'>User logged out. <a href='?'>Login</a> again</div>
				</div>
			</div>";
			echo $htmlFooter;
			die();
			break;
		case "getUsers":
			header("Content-Type: application/json");
			if($_SESSION['sud']['userType'] == 2) {
				$query = "SELECT `userID`, `userName`, `userType` FROM `users` ORDER BY `userName`;";
				$uData = db_query($query);
				die(json_encode($uData));
			} else {
				die(json_encode('Editing users not allowed'));
			}
			break;
		case "checkdups":
			e_log(8,"Checking for duplicated bookmarks by url");
			$query = "SELECT `bmID`, `bmTitle`, `bmURL` FROM `bookmarks` WHERE `bmType` = 'bookmark' AND `userID` = ".$_SESSION['sud']['userID']." GROUP BY `bmURL` HAVING COUNT(`bmURL`) > 1;";
			$dubData = db_query($query);
			foreach($dubData as $key => $dub) {
				$query = "SELECT `bmID`, `bmParentID`, `bmTitle`, `bmAdded` FROM `bookmarks` WHERE `bmType` = 'bookmark' AND `bmURL` = '".$dub['bmURL']."' AND `userID` = ".$_SESSION['sud']['userID']." ORDER BY `bmParentID`, `bmIndex`;";
				$subData = db_query($query);
				foreach($subData as $index => $entry) {
					$subData[$index]['fway'] = fWay($entry['bmParentID'], $_SESSION['sud']['userID'],'');
				}
				$dubData[$key]['subs'] = $subData;
			}
			sendJSONResponse($dubData);
			break;
		default:
			die(e_log(1, "Unknown Action ".$_POST['action']));
	}
}

if(isset($_GET['link'])) {
	$url = validate_url(trim($_GET["link"]));
	e_log(9,"URL add request: " . $url);
	
	$bookmark['url'] = $url;
	$bookmark['folder'] = 'unfiled_____';
	$bookmark['title'] = (isset($_GET["title"]) && $_GET["title"] != '') ? filter_var($_GET["title"], FILTER_SANITIZE_STRING):getSiteTitle($url);;
	$bookmark['id'] = unique_code(12);
	$bookmark['type'] = 'bookmark';
	$bookmark['added'] = round(microtime(true) * 1000);

	$uas = array(
		"HttpShortcuts",
		"Tasker",
		"Android"
	);

	$so = false;

	foreach($uas as $ua) {
		if(strpos($_SERVER['HTTP_USER_AGENT'], $ua) !== false || isset($_GET["client"])) {
			$so = true;
			break;
		}
	}
	
	if(CONFIG['cexp'] == true && CONFIG['loglevel'] == 9) {
		$filename = is_dir(CONFIG['logfile']) ? CONFIG['logfile']."/addmark_".time().".json":"addmark_".time().".json";
		e_log(8,"Write addmark json to $filename");
		file_put_contents($filename,json_encode($bookmark),true);
	}
	
	$res = addBookmark($bookmark);
	if($res == "Bookmark added") {
		$message = ($so) ? "URL added.":"<script>window.onload = function() { window.close();}</script>";
	} else {
		$message = $res;
	}
	e_log(8, $message);
	die($message);
}

if(isset($_GET['push'])) {
	$url = validate_url($_GET['push']);
	e_log(8,"Received new pushed URL from bookmarklet: ".$url);

	$data['url'] = $url;
	$data['target'] = (isset($_GET['tg'])) ? filter_var($_GET['tg'], FILTER_SANITIZE_STRING):NULL;
	if(ntfyNotification($data, $_SESSION['sud']['userID']) !== 0) die('Pushed');
}

echo htmlHeader();
echo htmlForms();
echo showBookmarks(2);
echo $htmlFooter;

function tabsSend($jtabs, $user, $added) {
	$urls = [];
	foreach ($jtabs as $tab) {
		$urls[] = $tab['url'];
	}

	$jurls = trim(json_encode($urls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), '[]');
	$query = "DELETE FROM `bookmarks` WHERE `bmID` IN (SELECT `bmID` FROM `bookmarks` WHERE `bmURL` NOT IN ($jurls) AND `bmType` = 'tab' AND `userID` = $user);";
	$res = db_query($query);
	
	foreach ($jtabs as $key => $tab) {
		$tID = unique_code(12);
		$title = trim($tab['title']);
		$url = $tab['url'];
		$query = "SELECT count(*) AS count FROM `bookmarks` WHERE `bmType` = 'tab' AND `bmURL` = '$url' AND `userID` = $user;";
		$res = db_query($query)[0]['count'];
		if($res == 0) {
			$query = "INSERT INTO `bookmarks` (`bmID`, `bmIndex`, `bmTitle`, `bmType`, `bmURL`, `bmAdded`, `userID`) VALUES ('$tID', $key, '$title', 'tab', '$url', '$added', $user);";
			$res = db_query($query);
		}
	}

	$response['tabs'] = count($jtabs);
	return $response;
}

function tabsGet($user) {
	e_log(8, "Request tabs for user '$user'");
	$query = "SELECT * FROM `bookmarks` WHERE `bmType` = 'tab' AND `userID` = $user;";
	$tabs = db_query($query);
	$response['tabs'] = $tabs;

	return $response;
}

function durl($pid, $uid) {
	e_log(8,"Hide notification");
	if(db_query("UPDATE `pages` SET `nloop`= 0, `ntime`= '".time()."' WHERE `pid` = $pid AND `userID` = $uid;") == 1) {
		$response['message'] = "Notification is now hidden";
		$response['code'] = 200;
	} else {
		$response['error'] = "Update failed";
		$response['code'] = 500;
	}
	return $response;
}

function pushGet($client, $uid) {
	e_log(8,"Request pushed sites for '$client'");
	$query = "SELECT * FROM `pages` WHERE `nloop` = 1 AND `userID` = $uid AND (`cid` IN ('$client') OR `cid` IS NULL);";
	$options = json_decode($_SESSION['sud']['uOptions'],true);
	$notificationData = db_query($query);
	if (!empty($notificationData)) {
		e_log(8,"Found ".count($notificationData)." links. Will send them to the client.");
		
		foreach($notificationData as $key => $notification) {
			$myObj[$key]['title'] = html_entity_decode($notification['ptitle'], ENT_QUOTES | ENT_XML1, 'UTF-8');
			$myObj[$key]['url'] = $notification['purl'];
			$myObj[$key]['nkey'] = $notification['pid'];
		}
		$response['enabled'] = $options['notifications'];
		$response['notifications'] = $myObj;
	} else {
		$msg = "No pushed sites found";
		e_log(8,$msg);
		$response['message'] = $msg;
		$response['code'] = 200;
	}

	if(isset($_COOKIE['syncmarks'])) 
		e_log(8,'Cookie is available');
	else
		e_log(8,'Cookie is not set');

	return $response;
}

function clientList($client, $uid) {
	e_log(8,"Try to get list of clients");
	$query = "SELECT `cid`, IFNULL(`cname`, `cid`) `cname`, `ctype`, `lastseen` FROM `clients` WHERE `userID` = $uid AND NOT `cid` = '$client';";
	$clientList = db_query($query);

	e_log(8,"Found ".count($clientList)." clients. Send list to '$client'.");
	
	uasort($clientList, function($a, $b) {
		return strnatcasecmp($a['cname'], $b['cname']);
	});
	
	if (!empty($clientList)) {
		foreach($clientList as $key => $clients) {
			$myObj[$key]['id'] =	$clients['cid'];
			$myObj[$key]['name'] = 	$clients['cname'];
			$myObj[$key]['type'] = 	$clients['ctype'];
			$myObj[$key]['date'] = 	$clients['lastseen'];
		}
		$all = array('id'=>'0', 'name'=>'All Clients', 'type'=>'', 'date'=>'');
		array_unshift($myObj, $all);
	} else {
		$myObj[0]['id'] =	'0';
		$myObj[0]['name'] =	'All Clients';
		$myObj[0]['type'] =	'';
		$myObj[0]['date'] =	'';

		$response['error'] = "No clients found";
		$response['code'] = 500;
	}
	
	if(CONFIG['cexp'] == true && CONFIG['loglevel'] == 9) {
		$filename = is_dir(CONFIG['logfile']) ? CONFIG['logfile']."/clist_".time().".json":"clist_".time().".json";
		e_log(8,"Write clientlist to $filename");
		file_put_contents($filename,json_encode($myObj),true);
	}
	
	$response['clients'] = $myObj;

	return $response;
}

function parseJError($jerror) {
	switch ($jerror) {
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
	}

	return $jerrmsg;
}

function bookmarkExport($ctype, $ctime, $format, $client) {
	e_log(8,"Request bookmark export");
	switch($format) {
		case "html":
			e_log(8,"Exporting in HTML format for download");
			$response['bookmarks'] = html_export();
			break;
		case "json":
			e_log(8,"Exporting in JSON format");
			$bookmarks = getBookmarks();
			if(CONFIG['loglevel'] == 9 && CONFIG['cexp'] == true) {
				$filename = is_dir(CONFIG['logfile']) ? CONFIG['logfile']."/export_".time().".json":"export_".time().".json";
				file_put_contents($filename,json_encode($bookmarks),true);
				e_log(8,"Export file is saved to $filename");
			}
			$bcount = count($bookmarks);
			e_log(8,"Send $bcount bookmarks to '$client'");
			updateClient($client, $ctype, $ctime);
			$response['bookmarks'] = $bookmarks;
			break;
		default:
			$msg = "Unknown export format, exit process";
			e_log(2, $msg);
			$response['error'] = $msg;
			$response['code'] = 501;
	}

	return $response;
}

function bookmarkImport($jmarks, $client, $ctype, $ctime, $user) {
	delUsermarks($user);
	$armarks = parseJSON($jmarks);
	updateClient($client, $ctype, $ctime);
	$response = importMarks($armarks, $user);

	return $response;
}

function bookmarkAdd($bookmark, $stime, $ctype, $client, $add = null) {
	$bookmark['added'] = $stime;
	$bookmark['title'] = ($bookmark['title'] === '') ? getSiteTitle(trim($bookmark['url'])):htmlspecialchars(mb_convert_encoding(htmlspecialchars_decode($bookmark['title'], ENT_QUOTES),"UTF-8"),ENT_QUOTES,'UTF-8', false);
	
	e_log(8,"Try to add new bookmark '".$bookmark['title']."'");
	e_log(9, print_r($bookmark, true));
	if(array_key_exists('url',$bookmark)) $bookmark['url'] = validate_url($bookmark['url']);
	if($ctype != "firefox") $bookmark = cfolderMatching($bookmark);
	if($bookmark['type'] == 'bookmark' && isset($bookmark['url'])) {
		$erg['message'] = addBookmark($bookmark);
		$erg['code'] = ($erg['message'] === 'Bookmark added') ? 200:500;

		if($add === '2') {
			if($erg['message'] === "Bookmark added") {
				e_log(8, $erg['message']);
				$erg['html_bookmarks'] = bmTree();
			} else {
				$erg['message'] = 'Bookmark not added';
				$erg['code'] = 417;
			}
		} else {
			updateClient($client, $ctype, $stime);
		}
	} else if($bookmark['type'] == 'folder') {
		$erg['addFolder'] = addFolder($bookmark);
		updateClient($client, $ctype, $stime);
	} else {
		$message = "This bookmark is not added, some parameters are missing";
		e_log(1, $message);
		$erg['message'] = $message;
		$erg['code'] = 500;
	}

	return $erg;
}

function bookmarkDel($bookmark, $user) {
	$bookmark = json_decode($bookmark, true);
	e_log(8,"Try to identify bookmark to delete");

	if(isset($bookmark['url'])) {
		$query = "SELECT `bmID` FROM `bookmarks` WHERE `bmType` = 'bookmark' AND `bmURL` = '".$bookmark['url']."' AND `userID` = $user;";
	} else {
		$query = "SELECT `bmID` FROM `bookmarks` WHERE `bmType` = 'folder' AND `bmTitle` = '".$bookmark['title']."' AND `userID` = $user;";
	}
	$bData = db_query($query);

	if(count($bData) == 1) {
		e_log(8, "Bookmark found, trying to remove it");
		$erg = delMark(array($bData[0]['bmID']));
		$message = ($erg == 1) ? "Bookmark deleted":"Delete Bookmark failed";
		$code = ($erg == 1) ? 200:500;
	} else if (count($bData) > 1) {
		$message = "No unique bookmark found, doing nothing";
		$code = 204;
		e_log(2, $message);
	} else {
		$message = "Bookmark not found, mark as deleted";
		$code = 200;
		e_log(2, $message);
	}

	$response['message'] = $message;
	$response['code'] = $code;

	return $response;
}

function bookmarkMove($bookmark, $client, $ctime, $ctype) {
	if(CONFIG['cexp'] == true && CONFIG['loglevel'] == 9) {
		$filename = is_dir(CONFIG['logfile']) ? CONFIG['logfile']."/movemark_".time().".json":"movemark_".time().".json";
		e_log(8,"Write move bookmark json to $filename");
		file_put_contents($filename,json_encode($bookmark),true);
	}

	$response = moveBookmark($bookmark);
	updateClient($client, $ctype, $ctime);

	return $response;
}

function clientInfo($client, $uid) {
	e_log(8,"Request client info");
	$query = "SELECT `cname`, `ctype`, `lastseen`, `cinfo` FROM `clients` INNER JOIN `c_token`  WHERE `clients`.`cid` = `c_token`.`cid` AND `clients`.`cid` = '$client' AND `clients`.`userID` = $uid;";
	$clientData = db_query($query)[0];
	if(count($clientData) > 0) {
		e_log(8,"Send client info to '$client'");
		$clientData['code'] = 200;

		if(isset($clientData['cinfo'])) {
			$clientData['cinfo'] = json_decode($clientData['cinfo'], true);
		}

		if(CONFIG['cexp'] == true && CONFIG['loglevel'] == 9) {
			$filename = is_dir(CONFIG['logfile']) ? CONFIG['logfile']."/cinfo_".time().".json":"cinfo_".time().".json";
			e_log(8,"Write client info to $filename");
			file_put_contents($filename,json_encode($clientData),true);
		}
	} else {
		e_log(2,"Client not found.");
		$clientData['lastseen'] = 0;
		$clientData['cname'] = '';
		$clientData['ctype'] = '';
		$clientData['message'] = "Client not found.";
		$clientData['code'] = 500;
	}

	return $clientData;
}

function clientRename($client, $data, $add = null, $uid) {
	e_log(8,"Rename client $client to '$data'");
	$query = "UPDATE `clients` SET `cname` = '$data' WHERE `userID` = $uid AND `cid` = '$client';";
	$count = db_query($query);
	
	if(isset($add)) {
		$data = bClientlist($uid, $add);
	} else {
		$data = bClientlist($uid);
	}

	return $data;
}

function clientCheck($client, $tbt, $ctime, $type) {
	$tm = ($tbt) ? "Basic login from client '$client'":"Token request from client '$client'";
	e_log(8, $tm);
	$tResponse['message'] = updateClient($client, $type, $ctime);
	$userID = $_SESSION['sud']['userID'];

	if(!$tbt) {
		$query = "SELECT `c_token`.*, `clients`.`cname` FROM `c_token` INNER JOIN `clients` ON `clients`.`cid` = `c_token`.`cid` WHERE `c_token`.`cid` = '$client' AND `c_token`.`userID` = $userID;";
		$tData = db_query($query);
		$expireTime = time()+60*60*24*CONFIG['expireDays'];
		$token = bin2hex(openssl_random_pseudo_bytes(32));
		$thash = password_hash($token, PASSWORD_DEFAULT);
		if(count($tData) > 0) {
			$query = "UPDATE `c_token` SET `tHash` = '$thash', `exDate` = '$expireTime' WHERE `cid` = '$client' AND `userID` = $userID;";
			$tResponse['cname'] = $tData[0]['cname'];
		} else {
			$query = "INSERT INTO `c_token` (`cid`, `tHash`, `exDate`, `userID`) VALUES ('$client', '$thash', '$expireTime', $userID);";
			$tResponse['cname'] = '';
		}
		db_query($query);
		$tResponse['token'] = $token;
		e_log(8, "Send new token to $client");
	} else {
		e_log(8, "Send token to $client");
	}

	$tResponse['code'] = 200;
	e_log(8, "Logout $client");
	unset($_SESSION['sauth']);
	@session_destroy();
	return $tResponse;
}

function sendJSONResponse($response) {
	global $version;
	$code = isset($response['code']) ? $response['code']:200;
	header('Content-Type: application/json; charset=utf-8');
	if(is_array($response)) {
		http_response_code($code);
		$response['version'] = 'v'.$version;
	}
	die(json_encode($response, JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE));
}

function ntfyNotification($data, $uid) {
	$url = validate_url($data['url']);
	$target = $data['target'];
	e_log(8,"Received new push URL: '$url'");
	$title = getSiteTitle($url);
	$ctime = time();

	$query = "INSERT INTO `pages` (`ptitle`,`purl`,`ntime`,`nloop`,`publish_date`,`userID`, `cid`) VALUES ('$title', '$url', $ctime, 1, $ctime, $uid, NULLIF('$target',''));";
	$res = db_query($query);
	
	if($res > 0) {
		$options = json_decode($_SESSION['sud']['uOptions'],true);
		if(isset($options['ntfy']['instance']) && $options['notifications'] == "1") {
			$res = pushntfy($title, $url);
		} else {
			$msg = "Can't publish to ntfy, missing data. Please check options";
			e_log(2,$msg);
			$res['error'] = $msg;
		}
	} else {
		$res['error'] = "SQL error. Please check server logfile";
	}

	$message = (!isset($res['error'])) ? "URL successful pushed":"Failed to push URL";
	e_log(8, $message);
	
	return $res;
}

function gpwd($length = 12){
	$allowedC =  'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-=~!#$^&*()_+,./<>:[]{}|';
	$pwd = '';
	$max = strlen($allowedC) - 1;
	for ($i=0; $i < $length; $i++) $pwd .= $allowedC[random_int(0, $max)];
	return $pwd;
}

function fWay($parent, $user, $str) {
	e_log(8,"Get folder structure for bookmark");
	do {
		$query = "SELECT `bmID`, `bmParentID`, `bmTitle` FROM `bookmarks` WHERE `bmID` = '$parent' AND `userID` = $user";
		$fData = db_query($query)[0];
		$str = ' &#187; '.$fData['bmTitle'].$str;
		$parent = $fData['bmParentID'];
	} while (strpos($fData['bmParentID'],'root________') === false);
	
	$str = substr($str,8);
	return $str;
}

function delMark($bmID) {
	$count = 0;
	$bms = implode(", ", $bmID);
	e_log(8,"Delete bookmark(s) $bms");

	foreach ($bmID as $key => $value) {
		$bm = $value;
		$query = "SELECT `bmParentID`, `bmIndex`, `bmURL` FROM `bookmarks` WHERE `bmID` = '$bm' AND `userID` = ".$_SESSION['sud']['userID'].";";
		$dData = db_query($query)[0];

		$query = "DELETE FROM `bookmarks` WHERE `bmID` = '$bm' AND `userID` = ".$_SESSION['sud']['userID'].";";
		$count = db_query($query);

		reIndex($dData['bmParentID']);
	}

	return $count;
}

function reIndex($parentid) {
	e_log(8,"Check for remaining entries in folder");
	$query = "SELECT * FROM `bookmarks` WHERE `bmParentID` = '$parentid' AND `userID` = ".$_SESSION['sud']['userID']." ORDER BY bmIndex;";
	$fBookmarks = db_query($query);

	$bm_count = count($fBookmarks);
	e_log(8, "Re-index folder $parentid");
	for ($i = 0; $i < $bm_count; $i++) {
		$data[] = array($i, $fBookmarks[$i]['bmID']);
	}

	$query = "UPDATE `bookmarks` SET `bmIndex` = ? WHERE bmID = ?";
	db_query($query, $data);
}

function cfolder($ctime,$fname,$fbid) {
	e_log(8,"Request to create folder $fname");
	$query = "SELECT `bmParentID`  FROM `bookmarks` WHERE `bmID` = '$fbid' AND `userID` = ".$_SESSION['sud']['userID'];
	$pdata = db_query($query);
	$res = '';
	$parentid = $pdata[0]['bmParentID'];

	if(count($pdata) == 1) {
		e_log(8,"Try to get index folder");
		$query = "SELECT MAX(`bmIndex`)+1 as nIndex FROM `bookmarks` WHERE `bmParentID` = '$parentid' AND `userID` = ".$_SESSION['sud']['userID'];
		$idata = db_query($query);

		if(count($idata) == 1) {
			e_log(8,"Add new folder to database");
			$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmAdded`,`userID`) VALUES ('".unique_code(12)."', '$parentid', ".$idata[0]['nIndex'].", '$fname', 'folder', $ctime, ".$_SESSION['sud']["userID"].")";
			if(db_query($query) === false)
				$res = "Adding folder failed.";
			else {
				$res = "1";
			}
		} else {
			$res = "No index found, folder not added";
		}
	} else {
		$res = "Parent folder not found, folder not added";
	}
	return $res;
}

function getClientType($uas) {
	if(strpos($uas,"Firefox")) return "Firefox";
	elseif(strpos($uas, "Edg")) return "Edge";
	elseif(strpos($uas, "OPR")) return "Opera";
	elseif(strpos($uas, "Vivaldi")) return "Vivaldi";
	elseif(strpos($uas, "Brave")) return "Brave";
	elseif(strpos($uas, "SamsungBrowser")) return "SamsungBrowser";
	elseif(strpos($uas, "Chrome")) return "Chrome";
	else return "Unknown";
}

function validate_url($url) {
	$urla = parse_url(trim($url));

	$pass      = $urla['pass'] ?? null;
    $user      = $urla['user'] ?? null;
    $userinfo  = $pass !== null ? "$user:$pass" : $user;
    $port      = $urla['port'] ?? 0;
    $scheme    = $urla['scheme'] ?? "";
    $query     = $urla['query'] ?? "";
    $fragment  = $urla['fragment'] ?? "";
	
    $authority = (
        ($userinfo !== null ? "$userinfo@" : "") .
        (urlencode($urla['host']) ?? "") .
        ($port ? ":$port" : "")
	);

	$url =
        (\strlen($scheme) > 0 ? "$scheme:" : "") .
        (\strlen($authority) > 0 ? "//$authority" : "") .
        (join('/', array_map('rawurlencode', explode('/', $urla['path']))) ?? "") .		
        (\strlen($query) > 0 ? "?$query" : "") .
        (\strlen($fragment) > 0 ? "#$fragment" : "")
    ;

	$url = filter_var(filter_var($url, FILTER_SANITIZE_STRING), FILTER_UNSAFE_RAW);
	if (filter_var($url, FILTER_VALIDATE_URL)) {
		return $url;
	} else {
		e_log(2,"URL is not a valid URL. Exit now.");
		exit;
	}
}

function pushntfy($title,$url) {
	global $le;
	e_log(8,"Publish ntfy notification");
	$options = json_decode($_SESSION['sud']['uOptions'],true);
	$instance = $options['ntfy']['instance'];
	$token = isset($options['ntfy']['token']) ? edcrpt('de',$options['ntfy']['token']):null;

	$encTitle = html_entity_decode($title, ENT_QUOTES | ENT_XML1, 'UTF-8');
	$authHeader = base64_encode(":$token");
	$authHeader = (strlen($authHeader) > 4) ? "Authorization: Basic $authHeader\r\n":'';

	$content = @file_get_contents($instance, false, stream_context_create([
		'http' => [
			'method' => 'POST',
			'header' => 
				"Content-Type: text/plain\r\n".
				$authHeader.
				"title: $encTitle\r\n".
				"click: $url\r\n",
			'content' => $url
		]
	]));

	if($content === false) {
		$response['error'] = $le;
		$response['code'] = 500;
	} else
		$response = json_decode($content, true);
	
	return $response;
}

function edcrpt($action, $text) {
	$encrypt_method = "AES-256-CBC";
	$key = hash('sha256', CONFIG['enckey']);
	$iv = substr(hash('sha256', CONFIG['enchash']), 0, 16);

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

function html_export() {
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

	$umarks = makeHTMLExport(getBookmarks());
	do {
		$start = strpos($umarks,"%ID");
		$end = strpos($umarks,"\n",$start);
		$len = $end - $start;
		$umarks = substr_replace($umarks, "", $start, $len);
	} while (strpos($umarks,"%ID") > 0);

	$content.="$umarks\r\n</DL><p>";

	return $content;
}

function editFolder($bm, $time, $uid) {
	e_log(8,"Edit folder request, try to find the folder...");
	$query = "SELECT * FROM `bookmarks` WHERE `bmIndex` >= ".$bm['index']." AND `bmType` = 'folder' AND `bmParentID` = '".$bm['parentId']."' AND `userID` = $uid;";
	$fData = db_query($query);

	if(count($fData) == 1) {
		$query = "UPDATE `bookmarks` SET `bmTitle` = '".$bm['title']."', `bmModified` = $time WHERE WHERE `bmID` = '".$fData[0]['bmID']."' AND userID = $uid;";
		$count = db_query($query);
		$response = [
			"message" => "Unique folder found, edit the folder",
			"code" => 200,
		];
		e_log(8, $response['message']);
	} else {
		$response = [
			"message" => "Folder not found, chancel operation and send error to client",
			"code" => 500,
		];
		e_log(2, $response['message']);
	}
	return $response;
}

function editBookmark($bm, $time, $uid) {
	e_log(8,"Edit bookmark request, try to find the bookmark first by url...");
	$query = "SELECT `bmID`  FROM `bookmarks` WHERE `bmURL` = '".$bm['url']."' AND `userID` = $uid";
	$bmData = db_query($query);

	if(count($bmData) == 1) {
		$query = "UPDATE `bookmarks` SET `bmTitle` = '".$bm['title']."', `bmModified` = $time WHERE `bmID` = '".$bmData[0]['bmID']."' AND userID = $uid;";
		$count = db_query($query);
		$response = [
			"message" => "Unique entry found, edit the title of the bookmark",
			"code" => 200,
		];
		e_log(8,$response['message']);
	} else {
		e_log(2,"No unique bookmark found, try to find now by title...");
		$query = "SELECT `bmID`  FROM `bookmarks` WHERE `bmTitle` = '".$bm['title']."' AND `userID` = $uid";
		$bmData = db_query($query);

		if(count($bmData) == 1) {
			$query = "UPDATE `bookmarks` SET `bmURL` = '".$bm['url']."', `bmModified` = $time WHERE `bmID` = '".$bmData[0]['bmID']."' AND userID = $uid;";
			$count = db_query($query);
			$response = [
				"message" => "Unique entry found, edit the url of the bookmark",
				"code" => 200,
			];
			e_log(8,$response['message']);
		} else {
			$response = [
				"message" => "No Unique entry found, chancel operation and send error to client",
				"code" => 500,
			];
			e_log(2, $response['message']);
		}
	}

	return $response;
}

function moveBookmark($bm) {
	e_log(8,"Bookmark seems to be moved, checking current folder data");
	$query = "SELECT `bmID`, `bmParentID` FROM `bookmarks` WHERE `bmType` = 'folder' AND `bmTitle` = '".$bm['nfolder']."' AND `userID` = ".$_SESSION['sud']['userID'].";";
	$folderData = db_query($query)[0];
	
	if(is_null($folderData['bmID'])) {
		$response = array("message"=>"Folder not found, bookmark not moved", "code"=>500);
		e_log(2, $response['message']);
		return $response;
	} else {
		$bm['folder'] = $folderData['bmID'];
	}

	if(array_key_exists("url", $bm)) {
		e_log(8,"Checking bookmark data before moving it");
		$query = "SELECT * FROM `bookmarks` WHERE `bmType` = 'bookmark' AND `userID`= ".$_SESSION['sud']["userID"]." AND `bmURL` = '".$bm["url"]."';";
		$oldData = db_query($query)[0];
		
		if (!empty($folderData) && !empty($oldData)) {
			if(($folderData['bmParentID'] != $oldData['bmParentID']) || ($oldData['bmIndex'] != $bm['index'])) {
				e_log(8,"Folder or Position changed, moving bookmark");
				$nfolder = $bm['folder'];
				$bid = $oldData["bmID"];
				$bindex = $bm['index'];
				$bAdded = round(microtime(true) * 1000);
				$query = "UPDATE `bookmarks` SET `bmParentID` = '$nfolder', `bmIndex` = $bindex, `bmAdded` = $bAdded  WHERE `bmID` = '$bid' AND `userID` = ".$_SESSION['sud']["userID"];
				db_query($query);
				reIndex($oldData['bmParentID']);
				$response = array("message"=>"Bookmark moved", "code"=>200);
				return $response;
			} else {
				$response = array("message"=>"Bookmark not moved, exiting", "code"=>500);
				e_log(2,$response['message']);
				return $response;
			}
		} else {
			$response = array("message"=>"Can't move bookmark, data not found", "code"=>500);
			e_log(2,$response['message']);
			return $response;
		}
	} else {
		$response = array("message"=>"url key not found", "code"=>500);
		e_log(2,$response['message']);
		return $response;
	}
}

function addFolder($bm) {
	$count = 0;
	e_log(8,"Try to find if this folder exists already");
	$query = "SELECT COUNT(*) AS bmCount, bmID  FROM `bookmarks` WHERE `bmTitle` = '".$bm['title']."' AND `bmParentID` = '".$bm['folder']."' AND `userID` = ".$_SESSION['sud']['userID'].";";
	$res = db_query($query)[0];

	if($res["bmCount"] > 0 && $count != 1) {
		e_log(2,"Folder not added, it exists already for this user, exit request");
		return false;
	}
	
	e_log(8,"Get folder data for adding folder");
	$query = "SELECT IFNULL(MAX(`bmIndex`),-1) + 1 AS `nindex`, `bmParentId` FROM `bookmarks` WHERE `bmParentId` = '".$bm['folder']."' AND `userID` = ".$_SESSION['sud']['userID'].";";
	$folderData = db_query($query);
	
	if (!empty($folderData)) {
		$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmAdded`,`userID`) VALUES ('".$bm['id']."', '".$bm['folder']."', ".$folderData[0]['nindex'].", '".$bm['title']."', '".$bm['type']."', ".$bm['added'].", ".$_SESSION['sud']["userID"].")";
		db_query($query);
		return true;
	}
	else {
		e_log(1,"Couldn't add folder");
		return false;
	}
}

function addBookmark($bm) {
	e_log(8,"Check if bookmark already exists for user.");
	$query = "SELECT `bmID`, COUNT(*) AS `bmcount` FROM `bookmarks` WHERE `bmUrl` = '".$bm['url']."' AND `bmParentID` = '".$bm["folder"]."' AND `userID` = ".$_SESSION['sud']["userID"].";";
	$bmExistData = db_query($query);
	if($bmExistData[0]["bmcount"] > 0) {
		$message = "Bookmark not added at server, it already exists";
		e_log(2, $message);
		return $message;
	}
	e_log(8,"Identify folder for new bookmark");
	$query = "SELECT COALESCE(MAX(`bmID`), 'unfiled_____') `bmID` FROM `bookmarks` WHERE `bmID` = '".$bm["folder"]."' AND `userID` = ".$_SESSION['sud']['userID'].";";
	$folderID = db_query($query)[0]['bmID'];

	e_log(8,"Get new index for bookmark");
	$query = "SELECT IFNULL(MAX(`bmIndex`),-1) + 1 AS `nindex` FROM `bookmarks` WHERE `userID` = ".$_SESSION['sud']['userID']." AND `bmParentID` = '$folderID';";
	$nindex = db_query($query)[0]['nindex'];
	
	e_log(8,"Add bookmark '".$bm['title']."'");
	$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('".$bm['id']."', '$folderID', $nindex, '".trim($bm['title'])."', '".$bm['type']."', '".$bm['url']."', ".$bm['added'].", ".$_SESSION['sud']["userID"].");";
	if(db_query($query) === false ) {
		$message = "Adding bookmark failed";
		e_log(1, $message);
		return $message;
	} else {
		return "Bookmark added";
	}
}

function updateClient($cl, $ct, $time) {
	$fclients = array("bookmarkTab", "Android");
	if(in_array($cl, $fclients)) return 0;

	$uid = $_SESSION['sud']["userID"];
	$query = "SELECT * FROM `clients` WHERE `cid` = '".$cl."' AND `userID` = ".$uid.";";
	$clientData = db_query($query);

	if (!empty($clientData)) {
		e_log(8,"Updating lastlogin for '$cl'");
		$query = "UPDATE `clients` SET `lastseen`= '".$time."' WHERE `cid` = '".$cl."';";
		$res = db_query($query);
		$message = ($res === 1 || $res === 0) ? "Client updated.":"Failed update client";
	} else {
		e_log(8,"New client detected. Try to register client $cl for user ".$_SESSION['sud']["userName"]);
		$query = "INSERT INTO `clients` (`cid`,`cname`,`ctype`,`userID`,`lastseen`) VALUES ('".$cl."','".$cl."', '".$ct."', ".$uid.", '0')";
		$res = db_query($query);
		$message = ($res == 0) ? "Client registered":"Failed to register client";
	}

	e_log(8, $message);
	
	return $message;
}

function bmTree() {
	e_log(8,"Build HTML tree from bookmarks");
	$bmTree = makeHTMLTree(getBookmarks());
	
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
	e_log(8,"Get new bookmark ID");
	$query = "SELECT IFNULL(MAX(`bmIndex`), 0) AS OIndex  FROM `bookmarks` WHERE `bmParentID` = '".$folder."'";
	$IndexArr = db_query($query);
	$maxIndex = $IndexArr[0]['OIndex'] + 1;
	return $maxIndex;
}

function getSiteTitle($url) {
	e_log(8,"Get titel for site '$url'");
	
    $options = array( 
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => "SyncMarks",
        CURLOPT_AUTOREFERER    => true,
        CURLOPT_CONNECTTIMEOUT => 120,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_MAXREDIRS      => 10,
    ); 
    $ch = curl_init($url); 
    curl_setopt_array($ch, $options); 
    $src = curl_exec($ch);
    $err = curl_errno($ch); 
    $errmsg = curl_error($ch);
    curl_close($ch);

	if(strlen($src) > 0) {
		$title = preg_match('/<title[^>]*>(.*?)<\/title>/ims', $src, $matches) ? $matches[1]:null;
		$convTitle = ($title == '' ) ? substr($url, 0, 240):htmlspecialchars(mb_convert_encoding(htmlspecialchars_decode($title, ENT_QUOTES),"UTF-8"),ENT_QUOTES,'UTF-8', false);
	}
	$cTitle = html_entity_decode($convTitle, ENT_QUOTES | ENT_XML1, 'UTF-8');
	e_log(8,"Titel for site is '$cTitle'");
	return $convTitle;
}

function getUserdata() {
	$query = "SELECT * FROM `users` WHERE `userName`='".$_SESSION['sauth']."'";
	$userData = db_query($query);
	if (!empty($userData)) {
		return $userData[0];
	} else {
		unset($_SESSION['sauth']);
	}
}

function getUserdataS() {
	$query = "SELECT * FROM `users` WHERE `userName`='".$_SESSION['sauth']."'";
	$userData = db_query($query);
	if (!empty($userData)) {
		$_SESSION['sud'] = $userData[0];
		return $userData[0];
	} else {
		unset($_SESSION['sud']);
		unset($_SESSION['sauth']);
	}
}

function unique_code($limit) {
	return substr(base_convert(sha1(uniqid(mt_rand())), 16, 36), 0, $limit);
}

function e_log($level, $message, $errfile="", $errline="", $output=0) {
	global $le;
	switch($level) {
		case 9:
			$mode = "debug ";
			break;
		case 8:
			$mode = "notice";
			break;
		case 4:
			$mode = "parse ";
			break;
		case 2:
			$mode = "warn  ";
			break;
		case 1:
			$mode = "error ";
			break;
		default:
			$mode = "unknown";
			break;
	}
	$le = $message;
	if($errfile != "") $message = $message." in ".$errfile." on line ".$errline;
	$user = '';
	if(isset($_SESSION['sauth'])) $user = "- ".$_SESSION['sauth']." ";
	$line = "[".date("d-M-Y H:i:s")."] $mode $user- $message\n";
	
	if($level <= CONFIG['loglevel']) {
		$lfile = is_dir(CONFIG['logfile']) ? CONFIG['logfile'].'/syncmarks.log':CONFIG['logfile'];
		file_put_contents($lfile, $line, FILE_APPEND);
	}
}

function delUsermarks($uid) {
	e_log(8, "Delete all bookmarks for logged in user");
	$query = "DELETE FROM `bookmarks` WHERE `userID` = $uid AND `bmID` <> 'root________'";
	db_query($query); 
}

function htmlHeader() {
	$hjs = hash_file('crc32','js/bookmarks.js');
	$hcs = hash_file('crc32','css/bookmarks.css');
	$js = (file_exists("js/bookmarks.min.js")) ? "<script src='js/bookmarks.min.js'></script>":"<script src='js/bookmarks.js'></script>";
	$css = (file_exists("css/bookmarks.min.css")) ? "<link type='text/css' rel='stylesheet' href='css/bookmarks.min.css'>":"<link type='text/css' rel='stylesheet' href='css/bookmarks.css'>";
	
	$htmlHeader = "<!DOCTYPE html>
		<html lang='en'>
			<head>
				<meta name='viewport' content='width=device-width, initial-scale=1'>
				$js
				$css
				<link rel='shortcut icon' type='image/x-icon' href='images/bookmarks.ico'>
				<link rel='manifest' href='manifest.json'>
				<meta name='theme-color' content='#0879D9'>
				<title>SyncMarks</title>
			</head>
			<body>";

	$htmlHeader.= "
	<div id='menu'>
		<div id='hmenu'>
			<div class='hline'></div>
			<div class='hline'></div>
			<div class='hline'></div>
		</div>
		<button>&#8981;</button><input type='search' name='bmsearch' id='bmsearch' value=''>
		<div id='mprofile'>SyncMarks</div>
	</div>";

	return $htmlHeader;
}

function htmlForms() {
	global $version;
	$clink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	$bookmarklet = "javascript:void function(){window.open('$clink?title='+encodeURIComponent(document.title)+'&link='+encodeURIComponent(document.location.href),'bWindow','width=480,height=245',replace=!0)}();";
	$userName = $_SESSION['sud']['userName'];
	$userMail = $_SESSION['sud']['userMail'];
	$userID = $_SESSION['sud']['userID'];
	$userOldLogin = date("d.m.y H:i",$_SESSION['sud']['userOldLogin']);
	$admenu = ($_SESSION['sud']['userType'] == 2) ? "<hr><li class='menuitem' id='mlog'>Logfile</li><li class='menuitem' id='mngusers'>Users</li>":"";
	$logform = ($_SESSION['sud']['userType'] == 2) ? "<div id=\"logfile\"><div id=\"close\"><button id='mrefresh'>refresh</button><label for='arefresh'><input type='checkbox' id='arefresh' name='arefresh'>Auto Refresh</label> <button id='mclear'>clear</button> <button id='mclose'>&times;</button></div><div id='lfiletext' contenteditable='true'></div></div>":"";

	$uOptions = json_decode($_SESSION['sud']['uOptions'],true);
	$oswitch = (isset($uOptions['notifications']) && $uOptions['notifications'] == 1) ? " checked":"";
	$oswitch =  "<label class='switch' title='Enable/Disable Notifications'><input id='cnoti' type='checkbox'$oswitch><span class='slider round'></span></label>";

	$ntfyInstance = (isset($uOptions['ntfy']['instance'])) ? $uOptions['ntfy']['instance']:'';
	$ntfyToken = (isset($uOptions['ntfy']['token'])) ? edcrpt('de', $uOptions['ntfy']['token']):'';

	$mngsettingsform = "
	<div id='mngsform' class='mmenu'><h6>SyncMarks Settings</h6>
		<span class='dclose'>&times;</span>
		<table>
			<tr><td colspan='2' style='height: 5px;'></td></tr>
			<tr><td><span class='rdesc'>Username:</span>$userName</td><td class='bright'><button id='muser'>Edit</button></td></tr>
			<tr><td colspan='2' style='height: 5px;'></td></tr>
			<tr><td><span class='rdesc'>Password:</span>**********</td><td class='bright'><button id='mpassword'>Edit</button></td></tr>
			<tr><td colspan='2' style='height: 5px;'></td></tr>
			<tr><td><span class='rdesc'>E-Mail:</span><span id='userMail'>$userMail</span></td><td class='bright'><button id='mmail'>Edit</button></td></tr>
			<tr><td colspan='2' style='height: 5px;'></td></tr>
			<tr><td colspan=2 class='bcenter'><button id='clientedt'>Show Clients</button></td></tr>
			<tr><td colspan='2' style='height: 2px;'></td></tr>
			<tr><td colspan=2 class='bcenter'><button id='ntfy'>ntfy</button></td></tr>
			<tr><td colspan='2' style='height: 5px;'></td></tr>
			<tr><td>Notifications</td><td class='bright'>$oswitch</td></tr>
		</table>
		<div id='bmlet'><a href=\"$bookmarklet\">Bookmarklet</a></div>
	</div>";

	$mngclientform = "<div id='mngcform' class='mmenu'></div>";

	$nmessagesform = "
	<div id='nmessagesform' class='mmenu'>
		<span class='dclose'>&times;</span>
		<div class='tab'>
		<button class='tablinks active' data-val='aNoti'>Active</button>
		<button class='tablinks' data-val='oNoti'>Archived</button>
		$oswitch
		</div>
		<div id='aNoti' class='tabcontent'style='display: block'>
		<div class='NotiTable'>
			<div class='NotiTableBody'></div>
		</div>
		</div>
		<div id='oNoti' class='tabcontent' style='display: none'>
		<div class='NotiTable'>
			<div class='NotiTableBody'></div>
		</div>
		</div>
	</div>";

	$pushform = "
	<div id='pushform' class='mbmdialog'>
		<span class='dclose'>&times;</span>
		<h6>ntfy</h6>
		<div class='dialogdescr'>Please enter the ntfy url including the topic and the token</div>
		<form action='' method='POST'>$oswitch
			<input required placeholder='URL' type='text' id='ntfyInstance' name='ntfyInstance' value='$ntfyInstance' autocomplete='Service-URL'/>
			<input placeholder='Token' type='password' id='ntfyToken' name='ntfyToken' value='$ntfyToken' autocomplete='ntfy-token' />
			<div class='dbutton'><button class='mdcancel' type='reset' value='Reset'>Cancel</button><button type='submit' name='action' value='ntfyupdate'>Save</button></div>
		</form>
	</div>";

	$passwordform = "
	<div id='passwordform' class='mbmdialog'>
		<span class='dclose'>&times;</span>
		<h6>Change Password</h6>
		<div class='dialogdescr'>Enter your current password and a new password and confirm the new password.</div>
		<form action='' method='POST'>					
			<input required placeholder='Current password' type='password' id='opassword' name='opassword' autocomplete='current-password' value='' />
			<input required placeholder='New password' type='password' id='npassword' name='npassword' autocomplete='new-password' value='' />
			<input required placeholder='Confirm new password' type='password' id='cpassword' name='cpassword' autocomplete='new-password' value='' />
			<div class='dbutton'><button class='mdcancel' type='reset' value='Reset'>Cancel</button><button type='submit' name='action' value='pupdate'>Save</button></div>
		</form>
	</div>";

	$userform = "
	<div id='userform' class='mbmdialog'>
		<span class='dclose'>&times;</span>
		<h6>Change Username</h6>
		<div class='dialogdescr'>Here you can change your username. Type in your new username and your current password and click on save to change it.</div>
		<form action='' method='POST'>
			<input placeholder='Username' required type='text' name='username' id='username' autocomplete='username' value='$userName'>
			<input placeholder='Password' required type='password' id='oopassword' name='opassword' autocomplete='current-password' value='' />
			<div class='dbutton'><button class='mdcancel' type='reset' value='Reset'>Cancel</button><button type='submit' name='action' value='uupdate'>Save</button></div>
		</form>
	</div>";

	$mainmenu = "
	<div id='mainmenu' class='mmenu'>
		<ul>
			<li id='meheader'><span class='appv'><a href='https://codeberg.org/Offerel/SyncMarks-Webapp'>SyncMarks $version</a></span><span class='logo'>&nbsp;</span><span class='text'>$userName<br>Last login: $userOldLogin</span></li>
			<li class='menuitem' id='nmessages'>Notifications</li>
			<li class='menuitem' id='bexport'>Export</li>
			<li class='menuitem' id='duplicates'>Duplicates</li>
			<li class='menuitem' id='psettings'>Settings</li>
			$admenu
			<hr>
			<li class='menuitem' id='logout'><form method='POST'><button name='action' id='loutaction' value='logout'>Logout</button></form></li>
		</ul>
	</div>";

	$bmMenu = "
	<menu class='menu' id='cmenu'><input type='hidden' id='bmid' title='bmtitle' value=''>
		<ul>
			<li id='btnEdit' class='menu-item'>Edit</li>
			<li id='btnMove' class='menu-item'>Move</li>
			<li id='btnDelete' class='menu-item'>Delete</li>
			<li id='btnFolder' class='menu-item'>New Folder</li>
		</ul>
	</menu>";

	$bmDialog = "
	<div id='reqdialog' class='mbmdialog'>
		<h6>Delete Bookmark</h6>
		<span class='dtext'></span>
		<div class='btna'>
			<button id='ydialog'>Yes</button>
			<button id='ndialog'>No</button>
		</div>
		<span class='dclose'>&times;</span>
	</div>";

	$editForm = "
	<div id='bmarkedt' class='mbmdialog'>
		<span class='dclose'>&times;</span>
		<h6>Edit Bookmark</h6>
		<form id='-' method='POST'>
			<input placeholder='Title' type='text' id='edtitle' name='edtitle' value=''>
			<input placeholder='URL' type='text' id='edurl' name='edurl' value=''>
			<input type='hidden' id='edid' name='edid' value=''>
			<div class='dbutton'>
				<button type='submit' id='edsave' name='edsave' value='Save' disabled>Save</button>
			</div>
		</form>
	</div>";

	$sFolderOptions = "<option value='' hidden>Select Folder</option>";
	$sFolderArr = getUserFolders($userID);
	foreach ($sFolderArr as $key => $folder) {
		if($folder['bmID'] === "unfiled_____")
			$sFolderOptions.= "<option selected value='".$folder['bmID']."'>".$folder['bmTitle']."</option>";
		else
			$sFolderOptions.= "<option value='".$folder['bmID']."'>".$folder['bmTitle']."</option>";
	}
	$moveForm = "
	<div id='bmamove' class='mbmdialog'>
		<span class='dclose'>&times;</span>
		<h6>Move Bookmark</h6>
		<form id='bmmv' method='POST'>
			<span id='mvtitle'></span>
			<div class='select'>
				<select id='mvfolder' name='mvfolder'>$sFolderOptions</select>
				<!-- <div class='select__arrow'></div> -->
			</div>
			<input type='hidden' id='mvid' name='mvid' value=''>
			<div class='dbutton'><button type='submit' id='mvsave' name='mvsave' value='Save' disabled>Save</button></div>
		</form>
	</div>";

	$folderForm = "
	<div id='folderf' class='mbmdialog'>
		<span class='dclose'>&times;</span>
		<h6>Create new folder</h6>
		<form id='fadd' method='POST'>
			<input placeholder='Foldername' type='text' id='fname' name='fname' value=''>
			<input type='hidden' id='fbid' name='fbid' value=''>
			<div class='dbutton'><button type='submit' id='fsave' name='fsave' value='Create' disabled>Create</button></div>
		</form>
	</div>";

	$footerButton = "
	<div id='bmarkadd' class='mbmdialog'>
		<span class='dclose'>&times;</span>
		<h6>Add Bookmark</h6>
		<form id='bmadd' action='?' method='POST'>
			<input placeholder='URL' type='text' id='url' name='url' value=''>
			<div class='select'>
				<select id='folder' name='folder'>
					$sFolderOptions
				</select>
			</div>
			<div class='dbutton'><button type='submit' id='save' name='' value='Save'>Save</button></div>
		</form>
	</div>
	<div id='footer'></div>";

	$htmlData = $folderForm.$moveForm.$editForm.$bmMenu.$bmDialog.$logform.$mainmenu.$userform.$passwordform.$pushform.$mngsettingsform.$mngclientform.$nmessagesform.$footerButton;	
	return $htmlData;
}

function showBookmarks($mode) {
	$bmTree = bmTree();
	$htmlData = "<div id='bookmarks'>$bmTree</div>";
	if($mode === 2) $htmlData.= "<div id='hmarks' style='display: none'>$bmTree</div>";
	return $htmlData;
}

function bClientlist($uid, $mode = 'html') {
	$query = "SELECT `cid`, IFNULL(`cname`, `cid`) `cname`, `ctype`, `lastseen` FROM `clients` WHERE `userID` = $uid;";
	$clientData = db_query($query);
	
	uasort($clientData, function($a, $b) {
		return strnatcasecmp($a['cname'], $b['cname']);
	});

	if($mode == 'html') {
		$clientList = "<ul>";
		foreach($clientData as $key => $client) {
			$cname = $client['cid'];
			if(isset($client['cname'])) $cname = $client['cname'];
			$timestamp = $client['lastseen'] / 1000;
			$lastseen = ($timestamp != '0') ? date('D, d. M. Y H:i', $timestamp):'----: -- -- ---- -- --';
			$clientList.= "<li title='".$client['cid']."' data-type='".strtolower($client['ctype'])."' id='".$client['cid']."' class='client'><div class='clientname'>$cname<input type='text' name='cname' value='$cname'><div class='lastseen'>$lastseen</div></div><div class='fa-edit rename'></div><div class='fa-trash remove'></div></li>";
		}
		$clientList.= "</ul>";
	} else {
		$clientList['clients'] = $clientData;
	}
	
	return $clientList;
}

function notiList($uid, $loop) {
	$query = "SELECT n.pid, n.ptitle, n.purl, n.publish_date, IFNULL(c.cname, n.cid) AS client FROM pages n LEFT JOIN clients c ON c.cid = n.cid WHERE n.userID = $uid AND n.nloop = $loop ORDER BY n.publish_date;";
	$aNotitData = db_query($query);
	$notiList = "";
	foreach($aNotitData as $key => $aNoti) {
		$cl = ($aNoti['client'] == NULL) ? "All":$aNoti['client'];
		$title = html_entity_decode($aNoti['ptitle'],ENT_QUOTES,'UTF-8');

		$notiList.= "<div class='NotiTableRow'>
					<div class='NotiTableCell'>
						<span><a class='link' target='_blank' title='$title' href='".$aNoti['purl']."'>$title</a></span>
						<span class='nlink'>".$aNoti['purl']."</span>
						<span class='ndate'>".date("d.m.Y H:i",$aNoti['publish_date'])." | $cl</span>
					</div>
					<div class='NotiTableCell'><a class='fa fa-trash' data-message='".$aNoti['pid']."' href='#'></a></div>
				</div>";
	}
	return $notiList;
}

function getUserFolders($uid) {
	e_log(8,"Get bookmark folders for user");
	$query = "SELECT * FROM `bookmarks` WHERE `bmID` <> 'root________' AND `bmType` = 'folder' and `userID` = ".$uid.";";
	$folders = db_query($query);
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
					$sfolder = '';
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
			$title = ($bm['bmTitle'] != "") ? $bm['bmTitle']:'unknown title';
			$title = htmlspecialchars(mb_convert_encoding(htmlspecialchars_decode($title, ENT_QUOTES),"UTF-8"),ENT_QUOTES,'UTF-8', false);
			$bookmark = "\n<li class='file'><span draggable='true' id='".$bm['bmID']."' title='".$title.'&#10;'.$bm['bmURL']."' data-url='".$bm['bmURL']."'>".$title."</span></li>%ID".$bm['bmParentID'];
			$bookmarks = str_replace("%ID".$bm['bmParentID'], $bookmark, $bookmarks);
		}

		if($bm['bmType'] == "folder") {
			$fclass = strpos($bm['bmID'], '_____') === false ? "class='folder'" : "";
			$nFolder = "\n<li $fclass id='f_".$bm['bmID']."'><label for=\"i_".$bm['bmID']."\" class='lbl'>".$bm['bmTitle']."</label><input class='ffolder' value='".$bm['bmID']."' id=\"i_".$bm['bmID']."\" type=\"checkbox\"><ol>%ID".$bm['bmID']."\n</ol></li>";
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

function cid($id) {
	switch($id) {
		case "0": $id = "root________"; break;
		case "1": $id = "toolbar_____"; break;
		case "2": $id = "unfiled_____"; break;
		case "3": $id = "mobile______"; break;
		default: $id = $id;
	}
	return $id;
}

function importMarks($bookmarks, $uid) {
	e_log(8,"Starting bookmark import");
	foreach ($bookmarks as $bookmark) {
		$title = htmlspecialchars($bookmark['bmTitle'], ENT_QUOTES, 'UTF-8');
		$dateGroupModified = strlen($bookmark['dateGroupModified']) == 0 ? NULL : $bookmark['dateGroupModified'];
		$url = strlen($bookmark['bmURL']) == 0 ? NULL : $bookmark['bmURL'];

		$data[] = array(
			cid($bookmark['bmID']),
			cid($bookmark['bmParentID']),
			$bookmark['bmIndex'],
			$title,
			$bookmark['bmType'],
			$url,
			$bookmark['bmAdded'],
			$dateGroupModified,
			$uid
		);
	}

	foreach($data as $key => $bookmark) {
		if(is_numeric($bookmark[0])) {
			$nid = unique_code(12);
			$bmap[$bookmark[0]] = $nid;
			$bookmark[0] = $nid;
			if(array_key_exists($bookmark[1], $bmap)) {
				$bookmark[1] = $bmap[$bookmark[1]];
			}
		}

		$data2[] = array(
			$bookmark[0],
			$bookmark[1],
			$bookmark[2],
			$bookmark[3],
			$bookmark[4],
			$bookmark[5],
			$bookmark[6],
			$bookmark[7],
			$bookmark[8]
		);
	}

	if(CONFIG['cexp'] == true && CONFIG['loglevel'] == 9) {
		$filename = is_dir(CONFIG['logfile']) ? CONFIG['logfile']."/cexport_".time().".json":"cexport_".time().".json";
		e_log(9,"Import bookmarks saved to $filename");
		file_put_contents($filename, print_r($data2, true));
	}
	
	$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`bmModified`,`userID`) VALUES (?,?,?,?,?,?,?,?,?)";
	$response = db_query($query, $data2);

	if($response) {
		$response = [
			"message" => "Bookmark import successful",
			"code" => 200,
		];
		e_log(8, $response['message']);
	} else {
		$response = [
			"message" => "Error importing bookmarks",
			"code" => 500,
		];
		e_log(1, $response['message']);
	}
		
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

function getBookmarks() {
	$query = "SELECT * FROM `bookmarks` WHERE `bmType` IN ('bookmark', 'folder') AND `bmID` <> 'root________' AND `userID` = ".$_SESSION['sud']['userID']." ORDER BY `bmAdded` ASC, `bmType` DESC;";
	e_log(8,"Get bookmarks");
	$userMarks = db_query($query);
	foreach($userMarks as &$element) {
		$element['bmTitle'] = html_entity_decode($element['bmTitle'],ENT_QUOTES,'UTF-8');
		$element['bmTitle'] = ($element['bmTitle'] == "") ? 'unknown title':$element['bmTitle'];
	}
	return $userMarks;
}

function c2hmarks($item, $key) {
	html_entity_decode($item,ENT_QUOTES,'UTF-8');
}

function prepare_url($url) {
	$parsed = parse_url($url);
	if(array_key_exists('query',$parsed)) {
		parse_str($parsed['query'], $query);
		$parsed['query'] = http_build_query($query);
	}

	$pass      = $parsed['pass'] ?? null;
	$user      = $parsed['user'] ?? null;
	$userinfo  = $pass !== null ? "$user:$pass" : $user;
	$port      = $parsed['port'] ?? 0;
	$scheme    = $parsed['scheme'] ?? "";
	$query     = $parsed['query'] ?? "";
	$fragment  = $parsed['fragment'] ?? "";
	$authority = (
		($userinfo !== null ? "$userinfo@" : "") .
		($parsed['host'] ?? "") .
		($port ? ":$port" : "")
	);
	return (
		(strlen($scheme) > 0 ? "$scheme:" : "") .
		(strlen($authority) > 0 ? "//$authority" : "") .
		($parsed['path'] ?? "") .
		(strlen($query) > 0 ? "?$query" : "") .
		(strlen($fragment) > 0 ? "#$fragment" : "")
	);
}

function clearAuthCookie() {
	e_log(8,'Reset Cookie');
	if(isset($_COOKIE['syncmarks'])) {
		$cookieStr = cryptCookie($_COOKIE['syncmarks'], 2);

		$cookieArr = json_decode($cookieStr, true);

		$query = "DELETE FROM `auth_token` WHERE `userName` = '".$cookieArr['user']."' AND `pHash` = '".$cookieArr['token']."'";
		db_query($query);
		
		$cOptions = array (
			'expires' => 0,
			'path' => null,
			'domain' => null,
			'secure' => true,
			'httponly' => false,
			'samesite' => 'Strict'
		);
		
		setcookie("syncmarks", "", $cOptions);
		e_log(8,"Cookie cleared");
	}
}

function checkLogin() {
	global $htmlFooter;
	e_log(8,"Check login...");

	if(isset($_COOKIE['syncmarks'])) 
		e_log(8,'Cookie is available');
	else
		e_log(8,'Cookie is not set');

	$headers = null;
	if (isset($_SERVER['Authorization'])) {
		$headers = trim($_SERVER["Authorization"]);
	} else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
		$headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    }

	$ctarr = explode(' ', $headers);
	$ctoken = ($ctarr[0] === 'Bearer') ? $ctarr[1]:false;

	$cdata = ($ctoken) ? json_decode(urldecode(base64_decode($ctoken)), true):false;

	$realm = CONFIG['realm'];
	$tVerified = false;
	$cookieStr = (!isset($_COOKIE['syncmarks'])) ? '':cryptCookie($_COOKIE['syncmarks'], 2);

	$cookieArr = json_decode($cookieStr, true);

	$aTime = time();

	if(isset($cookieArr) && strlen($cookieArr['user']) > 0 && strlen($cookieArr['rtkn']) > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
		e_log(8,"Cookie found. Try to login via authToken...");
		$query = "SELECT t.*, u.userLastLogin, u.sessionID FROM `auth_token` t INNER JOIN `users` u ON u.userName = t.userName WHERE t.userName = '".$cookieArr['user']."' ORDER BY t.exDate DESC;";
		$tkdata = db_query($query);

		foreach($tkdata as $key => $token) {
			if(password_verify($cookieArr['rtkn'], $token['tHash'])) {
				$tVerified = $token['tID'];
				break;
			}
		}
		
		if($tVerified) {
			e_log(8,"Cookie Login successful. Renew cookie");
			$seid = session_id();
			$oTime = $tkdata[0]['userLastLogin'];
			$_SESSION['sauth'] = $tkdata[0]['userName'];
			
			$expireTime = time()+60*60*24*CONFIG['expireDays'];
			$rtkn = unique_code(32);
			
			$cOptions = array (
				'expires' => $expireTime,
				'path' => null,
				'domain' => null,
				'secure' => true,
				'httponly' => false,
				'samesite' => 'Strict'
			);
			
			$cookieData = cryptCookie(json_encode(array('rtkn' => $rtkn, 'user' => $tkdata[0]['userName'], 'token' => $cookieArr['rtkn'])), 1);

			setcookie('syncmarks', $cookieData, $cOptions);
			e_log(8,"New cookie refreshed");
			$rtknh = password_hash($rtkn, PASSWORD_DEFAULT);
			
			$query = "UPDATE `auth_token` SET `tHash` = '$rtknh', `exDate` = '$expireTime' WHERE `tID` = $tVerified;";
			$erg = db_query($query);
			
			$query = "UPDATE `users` SET `userLastLogin` = '$aTime', `sessionID` = '$seid', `userOldLogin` = '$oTime' WHERE `userName` = '".$cookieArr['user']."';";
			$erg = db_query($query);
			header("location: ?");
			die();
	    } else {
	        e_log(8,"Cookie not valid, using standard login now");
			clearAuthCookie();
	    }
	}
	
	if(count($_GET) != 0 || count($_POST) != 0) {
		$u = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER']:false;
		$p = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW']:false;
		$user = (isset($_POST['username'])) ? filter_var($_POST['username'], FILTER_SANITIZE_STRING):$u;
		$pw = (isset($_POST['password'])) ? filter_var($_POST['password'], FILTER_SANITIZE_STRING):$p;

		if($ctoken && isset($cdata)) {
			$client = $cdata['client'];
			e_log(8,"Try token login $client");
			$query = "SELECT `c`.*, `u`.`userName` FROM `c_token` `c` INNER JOIN `users` `u` ON `u`.`userID` = `c`.`userID` WHERE `cid` = '$client';";
			$dbdata = db_query($query);
			
			if(count($dbdata) === 1) {
				$pverify = (password_verify($cdata['token'], $dbdata[0]['tHash'])) ? "true":"false";
				if(password_verify($cdata['token'], $dbdata[0]['tHash'])) {
					e_log(8,"$client token is valid. checking time.");
					if($dbdata[0]['exDate'] > time()) {
						e_log(8,"$client login successful");
						$_SESSION['sauth'] = $dbdata[0]['userName'];
						$expireTime = time()+60*60*24*CONFIG['expireDays'];
						$token = bin2hex(openssl_random_pseudo_bytes(32));
						$thash = password_hash($token, PASSWORD_DEFAULT);
						$userID = $dbdata[0]['userID'];
						$ipjson = json_encode(ip_info());
						$query = "UPDATE `c_token` SET `tHash` = '$thash', `exDate` = '$expireTime', `cInfo` = '$ipjson' WHERE `cid` = '$client';";
						db_query($query);
						header("X-Request-Info: $token");
						e_log(8,"New token send to $client and saved in DB, set new expireTime");
					} else {
						e_log(2,"$client login failed, expireTime reached");
						$query = "SELECT `cInfo` FROM `c_token` WHERE `cid` = '$client';";
						$cInfo = db_query($query)[0];
						$query = "UPDATE `c_token` SET `tHash` = '' WHERE `cid` = '$client';";
						db_query($query);
						e_log(2,"Removed token for client $client");
						unset($_SESSION['sauth']);
						session_destroy();
						header("X-Request-Info: 0");
						header("Content-Type: application/json");
						$cInfo['task'] = 'cInfo';
						die(json_encode($cInfo));
					}
				} else {
					e_log(2,"$client login failed, token invalid");
					$query = "SELECT `cInfo` FROM `c_token` WHERE `cid` = '$client';";
					$cInfo = db_query($query)[0];
					$cInfo['task'] = 'cInfo';
					$query = "UPDATE `c_token` SET `tHash` = '' WHERE `cid` = '$client';";
					db_query($query);
					e_log(2,"Removed token for client $client");
					unset($_SESSION['sauth']);
					session_destroy();
					header("X-Request-Info: 0");
					header("Content-Type: application/json");
					die(json_encode($cInfo));
				}
			} else {
				e_log(2,"Client not registered");
				unset($_SESSION['sauth']);
				session_destroy();
				header("X-Request-Info: 0");
				header("Content-Type: application/json");
				die(json_encode(""));
			}
		} else if(!$user || !$pw) {
			header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");
			header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
			header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
			header("Cache-Control: post-check=0, pre-check=0", false);
			header("Pragma: no-cache");

			if(!isset($_POST['client'])) {
				e_log(8,'Username and password not set. using web loginform');
				header('WWW-Authenticate: Basic realm="'.$realm.'", charset="UTF-8"');
				http_response_code(401);
			}

			echo htmlHeader();
			echo "<div id='loginbody'>
				<div id='loginform'>
					<div id='loginformh'>Access denied</div>
					<div id='loginformt'>Access denied. You must <a href='?'>login</a> to use this tool.</div>
				</div>
			</div>";
			echo $htmlFooter;
			exit;
		} else {
			$client = isset($cdata['client']) ? $cdata['client']:'';
			e_log(8,"Try basic login $client");
			$query = "SELECT * FROM `users` WHERE `userName`= '$user';";
			$udata = db_query($query);
			if(count($udata) == 1) {
				if(password_verify($pw, $udata[0]['userHash'])) {
					$seid = session_id();
					$oTime = $udata[0]['userLastLogin'];
					$uid = $udata[0]['userID'];
					$_SESSION['sauth'] = $udata[0]['userName'];
					e_log(8,"Login successful");
					
					if(isset($_POST['remember']) && $_POST['remember'] == true) {
						e_log(8,'Set login Cookie');
						$expireTime = time()+60*60*24*CONFIG['expireDays'];
						$rtkn = unique_code(32);
						
						$cOptions = array (
							'expires' => $expireTime,
							'path' => null,
							'domain' => null,
							'secure' => true,
							'httponly' => false,
							'samesite' => 'Strict'
						);
						
						$dtoken = bin2hex(openssl_random_pseudo_bytes(16));
						$cookieData = cryptCookie(json_encode(array('rtkn' => $rtkn, 'user' => $udata[0]['userName'], 'token' => $dtoken)), 1);

						setcookie('syncmarks', $cookieData, $cOptions);
						e_log(8,"Cookie saved. Valid until $expireTime");
						$rtknh = password_hash($rtkn, PASSWORD_DEFAULT);
						
						$query = "INSERT INTO `auth_token` (`userName`,`pHash`, `tHash`,`exDate`) VALUES ('".$udata[0]['userName']."', '$dtoken', '$rtknh', '$expireTime');";
						$erg = db_query($query);
					}
					
					if($seid != $udata[0]['sessionID']) {
						e_log(8,"Save session to database.");
						$query = "UPDATE `users` SET `userLastLogin` = $aTime, `sessionID` = '$seid', `userOldLogin` = '$oTime' WHERE `userID` = $uid;";
						db_query($query);
					}
				} else {
					unset($_SESSION['sauth']);
					session_destroy();
					if(!isset($_POST['login']) || !isset($_POST['client']) ) {
						header('WWW-Authenticate: Basic realm="'.$realm.'", charset="UTF-8"');
						http_response_code(401);
					}
					e_log(2,"Login failed. Password missmatch");
					echo htmlHeader();
					$lform = "<div id='loginbody'>
						<div id='loginform'>
							<div id='loginformh'>Login failed</div>
							<div id='loginformt'>You must <a href='?'>authenticate</a> to use this tool.";
					$lform.= (filter_var($udata[0]['userMail'], FILTER_VALIDATE_EMAIL)) ? "<br /><br />Forgot your password? You can try to <a data-reset='$user' id='preset' href=''>reset</a> it.":"<br /><br />Forgot your password? Please contact the admin.";
					$lform.= "</div></div>
					</div>";
					echo $lform;
					echo $htmlFooter;
					exit;
				}
			} else {
				unset($_SESSION['sauth']);
				session_destroy();
				
				if(!isset($_POST['login']) || !isset($_POST['client'])) {
					header('WWW-Authenticate: Basic realm="'.$realm.'", charset="UTF-8"');
					if(!isset($_POST['client'])) http_response_code(401);
				} else {
					echo htmlHeader();
					echo "<div id='loginbody'>
							<div id='loginform'>
								<div id='loginformh'>Login failed</div>
								<div id='loginformt'>You must <a href='?'>authenticate</a> to use this tool.</div>
							</div>
						</div>";
					echo $htmlFooter;
				}
				e_log(2,"Login failed. Credential missmatch");
				exit;
			}
		}
	} else {
		echo htmlHeader();
		echo "<div id='loginbody'>
			<form method='POST' id='lform'>
			<div id='loginform'>
				<div id='loginformh'>Welcome to SyncMarks</div>
				<div id='loginformt'>Please use your credentials to login to SyncMarks</div>
				<div id='loginformb'>
					<input type='text' id='uf' name='username' placeholder='Username'>
					<input type='password' name='password' placeholder='Password'>
					
					<label for='remember'><input type='checkbox' id='remember' name='remember'>Stay logged in</label>
					<button name='login' value='login'>Login</button>
				</div>
			</div>
			</form>
		</div>";
		echo $htmlFooter;
		exit;
	}
}

function ip_info() {
	$ipArr = [];
	if(!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    $ipArr['ip'] = $ip;
    $wArr = preg_split('/\r\n|\r|\n/', shell_exec("whois '".addslashes($ipArr['ip'])."'"));

	foreach($wArr as $ipi ) {
		$iarr = explode(": ", $ipi);
		if($iarr[0] == "descr") {
			$ipArr['de'] = trim($iarr[1]);
			break;
		}
	}
	
	$ip_info = @json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=".$ipArr['ip']));
	if($ip_info && $ip_info->geoplugin_countryName != null){
		$ipArr['co'] = $ip_info->geoplugin_continentName;
		$ipArr['ct'] = $ip_info->geoplugin_countryName;
		$ipArr['re'] = $ip_info->geoplugin_region;
		$ipArr['ua'] = $_SERVER['HTTP_USER_AGENT'];
		$ipArr['tm'] = time();
	}
	
	return $ipArr;
}

function cryptCookie($data, $crypt) {
	$method = 'aes-256-cbc';
	$iv = substr(hash('sha256', CONFIG['enchash']), 0, 16);
	$opts   = defined('OPENSSL_RAW_DATA') ? OPENSSL_RAW_DATA : true;
	$key = hash('sha256', CONFIG['enckey']);
	$str = ($crypt == 1) ? base64_encode(openssl_encrypt($data, $method, $key, $opts, $iv)):openssl_decrypt(base64_decode($data), $method, $key, $opts, $iv);
	return $str;
}

function db_query($query, $data=null) {
	e_log(9,$query);
	$options = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_CASE => PDO::CASE_NATURAL,
		PDO::ATTR_ORACLE_NULLS => PDO::NULL_EMPTY_STRING
	];
	try {
		if(CONFIG['db']['type'] == 'mysql') {
			$hs = (substr(CONFIG['db']['host'],0,1) === '/') ? 'unix_socket':'host';
			$constr = CONFIG['db']['type'].':'.$hs.'='.CONFIG['db']['host'].';dbname='.CONFIG['db']['dbname'];
			$db = new PDO($constr, CONFIG['db']['user'], CONFIG['db']['pwd'], $options);
		} elseif(CONFIG['db']['type'] == 'sqlite') {
			$db = new PDO(CONFIG['db']['type'].':'.CONFIG['db']['dbname'], null, null, $options);
			$db->exec( 'PRAGMA foreign_keys = ON;' );
		}
	} catch (PDOException $e) {
		e_log(1,'DB connection failed: '.$e->getMessage());
		return false;
	}

	if(is_array($data)) {
		$statement = $db->prepare($query);
		$waiting = true;
		while($waiting) {
			try {
				$db->beginTransaction();
				foreach ($data as $row) $statement->execute($row);
				$queryData = $db->commit();
				$waiting = false;
			} catch(PDOException $e) {
				if(stripos($e->getMessage(), 'DATABASE IS LOCKED') !== false) {
					$queryData = $db->commit();
					usleep(250000);
				} else {
					$db->rollBack();
					$queryData = false;
					e_log(1,"DB transaction failed. Data is rolled back: ".$e->getMessage());
					exit;
				}
			}
		}
	} else {
		if(strpos($query, 'SELECT') === 0 || strpos($query, 'PRAGMA') === 0) {
			try {
				$statement = $db->prepare($query);
				$statement->execute();
			} catch(PDOException $e) {
				e_log(1,"DB query failed: ".$e->getMessage());
				return false;
			}
			$queryData = $statement->fetchAll(PDO::FETCH_ASSOC);
		} else {
			try {
				$queryData = $db->exec($query);
				if(strpos($query, 'INSERT') === 0) $queryData = $db->lastInsertId();
			} catch(PDOException $e) {
				e_log(1,"DB update failed: ".$e->getMessage());
				return false;
			}
		}
	}

	$db = NULL;
	return $queryData;
}

function checkDB() {
	$vInfoa = db_query("SELECT * FROM `system` ORDER BY `updated` DESC LIMIT 1;");
	if(is_array($vInfoa) && count($vInfoa) > 0) {
		$vInfo = $vInfoa[0];
	} else {
		$vInfo['updated'] = 0;
		$vInfo['db_version'] = 0;
	}
	
	$olddate = $vInfo['updated'];
	$newdate = filemtime(__FILE__);
	$dbv = 9;
	$aversion = explode ("\n", file_get_contents('./CHANGELOG.md',NULL,NULL,0,30))[1];
	$aversion = substr($aversion,0,strpos($aversion, " "));

	if($vInfo['db_version'] && $vInfo['db_version'] < $dbv) {
		e_log(8,"Database update needed. Starting DB update...");
		if(CONFIG['db']['type'] == "sqlite") {
			db_query(file_get_contents("./sql/sqlite_update.sql"));
		} elseif (CONFIG['db']['type'] == "mysql") {
			db_query(file_get_contents("./sql/mysql_update.sql"));
		}
		db_query("INSERT INTO `system`(`app_version`,`db_version`,`updated`) VALUES ('$aversion','$dbv','$newdate');");
	} elseif ($vInfo['db_version'] && $vInfo['db_version'] >= $dbv) {
		if($olddate <> $newdate) db_query("UPDATE `system` SET `updated` = '$newdate' WHERE `updated` = '$olddate';");
	} else {
		e_log(2,"Database not ready. Initialize database now");
		if(CONFIG['db']['type'] == "sqlite") {
			if(!file_exists(CONFIG['db']['dbname'])) {
				if(!file_exists(dirname(CONFIG['db']['dbname']))) {
					if(!mkdir(dirname(CONFIG['db']['dbname']),0777,true)) {
						$message = "Directory for database (".dirname(CONFIG['db']['dbname']).") couldn't created, please check privileges";
						e_log(1,$message);
						die($message);
					} else {
						e_log(8,"Directory for database created (".dirname(CONFIG['db']['dbname'])."), initialize database now");
						db_query(file_get_contents("./sql/sqlite_init.sql"));
					}
				}
			} else {
				e_log(8,"Initialise new SQLite database");
				db_query(file_get_contents("./sql/sqlite_init.sql"));
			}
		} elseif (CONFIG['db']['type'] == "mysql") {
			e_log(8,"Initialise new MySQL database");
			db_query(file_get_contents("./sql/mysql_init.sql"));
		}

		$bmAdded = round(microtime(true) * 1000);
		$userPWD = password_hash(CONFIG['spwd'],PASSWORD_DEFAULT);
		$query = "INSERT INTO `users` (userName,userType,userHash) VALUES ('".CONFIG['suser']."',2,'$userPWD');";		
		db_query($query);
		$query = "INSERT INTO `bookmarks` (`bmID`, `bmIndex`, `bmType`, `bmAdded`, `userID`) VALUES ('root________', 0, 'folder', '0', 1);";
		db_query($query);
		$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('unfiled_____', 'root________', 0, 'Other Bookmarks', 'folder', NULL, ".$bmAdded.", 1)";
		db_query($query);
		$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('".unique_code(12)."', 'unfiled_____', 0, 'GitHub Repository', 'bookmark', 'https://codeberg.org/Offerel/SyncMarks-Webapp', ".$bmAdded.", 1)";
		db_query($query);
		db_query("INSERT INTO `system`(`app_version`,`db_version`,`updated`) VALUES ('$aversion','$dbv','$newdate');");
	}
}
?>