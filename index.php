<?php
/**
 * SyncMarks
 *
 * @version 1.6.7
 * @author Offerel
 * @copyright Copyright (c) 2021, Offerel
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
	'enchash'	=> $enchash
]);

set_error_handler("e_log");

if(CONFIG['loglevel'] == 9 && CONFIG['cexp']) e_log(9, $_SERVER['REQUEST_METHOD'].' '.var_export($_REQUEST,true));

if(!isset($_SESSION['sauth'])) checkDB();

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
			echo htmlFooter();
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
				echo htmlFooter();
			} else {
				echo htmlHeader();
				echo "<div id='loginbody'>
					<div id='loginform'>
						<div id='loginformh'>Welcome to SyncMarks</div>
						<div id='loginformt'>Token expired, Password reset failed. You can try to <a data-reset='".$result['userName']."' id='preset' href='#'>reset</a> it again.</div>
					</div>
				</div>";
				echo htmlFooter();
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

if(isset($_POST['caction'])) {
	switch($_POST['caction']) {
		case "addmark":
			$bookmark = json_decode($_POST['bookmark'], true);
			e_log(8,"Try to add new bookmark '".$bookmark['title']."'");
			$stime = round(microtime(true) * 1000);
			$bookmark['added'] = $stime;
			$bookmark['title'] = htmlspecialchars(mb_convert_encoding(htmlspecialchars_decode($bookmark['title'], ENT_QUOTES),"UTF-8"),ENT_QUOTES,'UTF-8', false);
			e_log(9, print_r($bookmark, true));
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			if(array_key_exists('url',$bookmark)) $bookmark['url'] = validate_url($bookmark['url']);
			if(strtolower(getClientType($_SERVER['HTTP_USER_AGENT'])) != "firefox") $bookmark = cfolderMatching($bookmark);
			$stime = (!isset($_POST['s'])) ? 0:$stime;
			header("Content-Type: application/json");
			if($bookmark['type'] == 'bookmark' && isset($bookmark['url'])) {
				$response = json_encode(addBookmark($bookmark));
				updateClient($client, strtolower(getClientType($_SERVER['HTTP_USER_AGENT'])), $stime, true);
				die($response);
			} else if($bookmark['type'] == 'folder') {
				$response = addFolder($bookmark);
				updateClient($client, strtolower(getClientType($_SERVER['HTTP_USER_AGENT'])), $stime, true);
				die(json_encode($response));
			} else {
				$message = "This bookmark is not added, some parameters are missing";
				e_log(1, $message);
				die(json_encode($message));
			}
			break;
		case "movemark":
			$bookmark = json_decode($_POST['bookmark'],true);

			if(CONFIG['cexp'] == true && CONFIG['loglevel'] == 9) {
				$filename = is_dir(CONFIG['logfile']) ? CONFIG['logfile']."/movemark_".time().".json":"movemark_".time().".json";
				e_log(8,"Write move bookmark json to $filename");
				file_put_contents($filename,json_encode($bookmark),true);
			}

			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$ctime = round(microtime(true) * 1000);
			$response = json_encode(moveBookmark($bookmark));
			$ctime = (filter_var($_POST['s'], FILTER_SANITIZE_STRING) === 'false') ? 0:$ctime;
			updateClient($client, strtolower(getClientType($_SERVER['HTTP_USER_AGENT'])), $ctime, true);
			die($response);
			break;
		case "editmark":
			$bookmark = json_decode(rawurldecode($_POST['bookmark']),true);
			(array_key_exists('url',$bookmark)) ? die(editBookmark($bookmark)) : die(editFolder($bookmark));
			break;
		case "delmark":
			$bookmark = json_decode(rawurldecode($_POST['bookmark']),true);
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$ctime = round(microtime(true) * 1000);
			e_log(8,"Try to identify bookmark to delete");
			if(isset($bookmark['url'])) {
				$url = prepare_url($bookmark['url']);
				$query = "SELECT `bmID` FROM `bookmarks` WHERE `bmType` = 'bookmark' AND `bmURL` = '$url' AND `userID` = ".$_SESSION['sud']['userID'].";";
			} else {
				$query = "SELECT `bmID` FROM `bookmarks` WHERE `bmType` = 'folder' AND `bmTitle` = '".$bookmark['title']."' AND `userID` = ".$_SESSION['sud']['userID'].";";
			}

			$bData = db_query($query);
			header("Content-Type: application/json");
			if(count($bData) == 1) {
				e_log(2, "Bookmark found, trying to remove it");
				$delmark = delMark($bData[0]['bmID']);
				die(json_encode($delmark));
			} else if (count($bData) > 1) {
				$message = "No unique bookmark found, doing nothing";
				e_log(2,$message);
				die(json_encode($message));
			} else {
				e_log(2, "Bookmark not found, mark as deleted");
				die(json_encode(1));
			}
			break;
		case "startup":
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$ctype = getClientType($_SERVER['HTTP_USER_AGENT']);
			$ctime = (filter_var($_POST['s'], FILTER_SANITIZE_STRING) === 'false') ? 0:round(microtime(true) * 1000);
			$changes = getChanges($client, $ctype, $ctime);

			if(CONFIG['cexp'] == true && CONFIG['loglevel'] == 9) {	
				$filename = is_dir(CONFIG['logfile']) ? CONFIG['logfile']."/startup_".time().".json":"startup_".time().".json";
				e_log(8,"Write startup json to $filename");
				file_put_contents($filename,json_encode($changes),true);
			}
			header("Content-Type: application/json");
			die(json_encode($changes,JSON_UNESCAPED_SLASHES));
			break;
		case "cfolder":
			$ctime = round(microtime(true) * 1000);
			$fname = filter_var($_POST['fname'], FILTER_SANITIZE_STRING);
			$fbid = filter_var($_POST['fbid'], FILTER_SANITIZE_STRING);
			die(cfolder($ctime,$fname,$fbid));
			break;
		case "import":
			$jmarks = json_decode($_POST['bookmark'],true);
			$jerrmsg = "";
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$partial = (isset($_POST['p'])) ? filter_var($_POST['p'], FILTER_VALIDATE_INT):0;
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
				file_put_contents($filename,urldecode($_POST['bookmark']),true);
				header("Content-Type: application/json");
				die(json_encode($jerrmsg));
			}

			$client = $_POST['client'];
			$ctype = getClientType($_SERVER['HTTP_USER_AGENT']);
			$ctime = round(microtime(true) * 1000);
			
			if($partial === 0) delUsermarks($_SESSION['sud']['userID']);
			
			$armarks = parseJSON($jmarks);
			$ctime = (filter_var($_POST['s'], FILTER_SANITIZE_STRING) === 'false') ? 0:$ctime;
			updateClient($client, $ctype, $ctime, true);
			header("Content-Type: application/json");
			die(json_encode(importMarks($armarks,$_SESSION['sud']['userID'])));
			break;
		case "getpurl":
			$url = validate_url($_POST['url']);
			e_log(8,"Received new pushed URL: ".$url);
			$target = (isset($_POST['tg'])) ? filter_var($_POST['tg'], FILTER_SANITIZE_STRING) : '0';
			if(newNotification($url, $target) !== 0) die("URL successful pushed.");
			break;
		case "rmessage":
			$message = isset($_POST['message']) ? filter_var($_POST['message'], FILTER_VALIDATE_INT):0;
			$loop = filter_var($_POST['lp'], FILTER_SANITIZE_STRING) == 'aNoti' ? 1 : 0;

			if($message > 0) {
				e_log(8,"Try to delete notification $message");
				$query = "DELETE FROM `notifications` WHERE `userID` = ".$_SESSION['sud']['userID']." AND `id` = $message;";
				$count = db_query($query);
				($count === 1) ? e_log(8,"Notification successfully removed") : e_log(9,"Error, removing notification");
			}
			
			die(notiList($_SESSION['sud']['userID'], $loop));
			break;
		case "soption":
			$option = filter_var($_POST['option'], FILTER_SANITIZE_STRING);
			$value = filter_var(filter_var($_POST['value'], FILTER_SANITIZE_NUMBER_INT), FILTER_VALIDATE_INT);
			e_log(8,"Option received: ".$option.":".$value);
			$oOptionsA = json_decode($_SESSION['sud']['uOptions'],true);
			$oOptionsA[$option] = $value;
			$query = "UPDATE `users` SET `uOptions`='".json_encode($oOptionsA)."' WHERE `userID`=".$_SESSION['sud']['userID'].";";
			header("Content-Type: application/json");
			if(db_query($query) !== false) {
				e_log(8,"Option saved");
				die(json_encode(true));
			} else {
				e_log(9,"Error, saving option");
				die(json_encode(false));
			}
			break;
		case "getclients":
			e_log(8,"Try to get list of clients");
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$query = "SELECT `cid`, IFNULL(`cname`, `cid`) `cname`, `ctype`, `lastseen` FROM `clients` WHERE `uid` = ".$_SESSION['sud']['userID']." AND NOT `cid` = '$client';";
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
			}
			
			if(CONFIG['cexp'] == true && CONFIG['loglevel'] == 9) {
				$filename = is_dir(CONFIG['logfile']) ? CONFIG['logfile']."/clist_".time().".json":"clist_".time().".json";
				e_log(8,"Write clientlist to $filename");
				file_put_contents($filename,json_encode($myObj),true);
			}
			
			header("Content-Type: application/json");
			die(json_encode($myObj));
			break;
		case "tlg":
			$userID = $_SESSION['sud']['userID'];
			$query = "SELECT * FROM `c_token` WHERE `userID` = $userID;";
			$tData = db_query($query);
			header("Content-Type: application/json");
			die(json_encode($tData));
			break;
		case "tld":
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$userID = $_SESSION['sud']['userID'];
			$query = "DELETE FROM `c_token` WHERE `cid` = '$client' AND `userID` = $userID;";
			$tData = db_query($query);
			header("Content-Type: application/json");
			die(json_encode($tData));
			break;
		case "tl":
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			e_log(8,"Token request from client '$client'");
			$type = getClientType($_SERVER['HTTP_USER_AGENT']);
			$time = round(microtime(true) * 1000);
			$ctime = (filter_var($_POST['s'], FILTER_SANITIZE_STRING) === 'false') ? 0:$time;
			$tResponse['message'] = updateClient($client, $type, $ctime);
			$userID = $_SESSION['sud']['userID'];
			$query = "SELECT `c_token`.*, `clients`.`cname` FROM `c_token` INNER JOIN `clients` ON `clients`.`cid` = `c_token`.`cid` WHERE `c_token`.`cid` = '$client' AND `userID` = $userID;";
			$tData = db_query($query);
			$expireTime = time()+60*60*24*7;
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
			
			e_log(8, "Logout $client");
			unset($_SESSION['sauth']);
			session_destroy();

			header("Content-Type: application/json");
			e_log(8, "Send new token to client $client");
			die(json_encode($tResponse));
			break;
		case "cinfo":
			e_log(8,"Request client info");
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$query = "SELECT `cname`, `ctype`, `lastseen` FROM `clients` WHERE `cid` = '$client' AND `uid` = ".$_SESSION['sud']['userID'].";";
			$clientData = db_query($query);
			if(count($clientData) > 0) {
				e_log(8,"Send client info to '$client'");
			} else {
				e_log(2,"Client not found.");
				$clientData[0]['lastseen'] = 0;
				$clientData[0]['cname'] = '';
				$clientData[0]['ctype'] = '';
			}
			
			header("Content-Type: application/json");
			die(json_encode($clientData[0]));
			break;
		case "cfsync":
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$query = "SELECT `fs`, `lastseen` FROM `clients` WHERE `cid` = '$client';";
			$fsdata = db_query($query)['0'];
			header("Content-Type: application/json");
			die(json_encode($fsdata));
			break;
		case "gurls":
			$client = (isset($_POST['client'])) ? filter_var($_POST['client'], FILTER_SANITIZE_STRING) : '0';
			e_log(8,"Request pushed sites for '$client'");
			$query = "SELECT * FROM `notifications` WHERE `nloop` = 1 AND `userID` = ".$_SESSION['sud']['userID']." AND `client` IN ('".$client."','0');";
			$uOptions = json_decode($_SESSION['sud']['uOptions'],true);
			$notificationData = db_query($query);
			if (!empty($notificationData)) {
				e_log(8,"Found ".count($notificationData)." links. Will push them to the client.");
				foreach($notificationData as $key => $notification) {
					$myObj[$key]['title'] = html_entity_decode($notification['title'], ENT_QUOTES | ENT_XML1, 'UTF-8');
					$myObj[$key]['url'] = $notification['message'];
					$myObj[$key]['nkey'] = $notification['id'];
					$myObj[$key]['nOption'] = $uOptions['notifications'];
				}
				header("Content-Type: application/json");
				die(json_encode($myObj));
			} else {
				e_log(8,"No pushed sites found");
				header("Content-Type: application/json");
				die(json_encode("0"));
			}
			break;
		case "durl":
			e_log(8,"Hide notification");
			$notification = filter_var($_POST['durl'], FILTER_VALIDATE_INT);
			$query = "UPDATE `notifications` SET `nloop`= 0, `ntime`= '".time()."' WHERE `id` = $notification AND `userID` = ".$_SESSION['sud']['userID'];
			die(db_query($query));
			break;
		case "bmedt":
			$title = filter_var($_POST['title'], FILTER_SANITIZE_STRING);
			$id = filter_var($_POST['id'], FILTER_SANITIZE_STRING);
			e_log(8,"Edit entry '$title'");
			$url = (isset($_POST['url']) && strlen($_POST['url']) > 4) ? '\''.validate_url($_POST['url']).'\'' : 'NULL';
			$query = "UPDATE `bookmarks` SET `bmTitle` = '$title', `bmURL` = $url, `bmAdded` = '".round(microtime(true) * 1000)."' WHERE `bmID` = '$id' AND `userID` = ".$_SESSION['sud']['userID'].";";
			$count = db_query($query);
			($count > 0) ? die(true) : die(false);
			break;
		case "bmmv":
			$id = filter_var($_POST['id'], FILTER_SANITIZE_STRING);
			e_log(8,"Move bookmark $id");
			$folder = filter_var($_POST['folder'], FILTER_SANITIZE_STRING);
			$query = "SELECT MAX(bmIndex)+1 AS 'index' FROM `bookmarks` WHERE `bmParentID` = '$folder';";
			$folderData = db_query($query);
			$query = "UPDATE `bookmarks` SET `bmIndex` = ".$folderData[0]['index'].", `bmParentID` = '$folder', `bmAdded` = '".round(microtime(true) * 1000)."' WHERE `bmID` = '$id' AND `userID` = ".$_SESSION['sud']['userID'].";";
			$count = db_query($query);
			($count > 0) ? die(true) : die(false);
			break;
		case "arename":
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$name = filter_var($_POST['nname'], FILTER_SANITIZE_STRING);
			e_log(8,"Rename client $client to $name");
			$query = "UPDATE `clients` SET `cname` = '".$name."' WHERE `uid` = ".$_SESSION['sud']['userID']." AND `cid` = '".$client."';";
			$count = db_query($query);
			($count > 0) ? die(bClientlist($_SESSION['sud']['userID'])) : die(false);
			break;
		case "adel":
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			e_log(8,"Delete client $client");
			$query = "DELETE FROM `clients` WHERE `uid` = ".$_SESSION['sud']['userID']." AND `cid` = '$client';";
			$count = db_query($query);
			($count > 0) ? die(bClientlist($_SESSION['sud']['userID'])) : die(false);
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
			$variant = filter_var($_POST['type'], FILTER_VALIDATE_INT);
			$password = (isset($_POST['p']) && $_POST['p'] != '') ? filter_var($_POST['p'], FILTER_SANITIZE_STRING):gpwd(16);
			$userLevel = filter_var($_POST['userLevel'], FILTER_VALIDATE_INT);
			$user = filter_var($_POST['nuser'], FILTER_SANITIZE_STRING);
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
							$message = "Hello,\r\na new account with the following credentials is created and stored encrypted on for SyncMarks:\r\nUsername: $user\r\nPassword: $password\r\n\r\nYou can login at $url";
							if(!mail ($mail, "Account created",$message,$headers)) {
								e_log(1,"Error sending data for created user account to user");
								$response = "User created successful, E-Mail could not send";
							}
						} else {
							$response = $nuid;
						}
						$bmAdded = round(microtime(true) * 1000);
						$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('unfiled_____', 'root________', 0, 'Other Bookmarks', 'folder', NULL, ".$bmAdded.", $nuid)";
						db_query($query);
						$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('".unique_code(12)."', 'unfiled_____', 0, 'Git Repository', 'bookmark', 'https://codeberg.org/Offerel', ".$bmAdded.", $nuid)";
						db_query($query);
					} else {
						$response = "User creation failed";
					}
					header("Content-Type: application/json");
					die(json_encode($response));
					break;
				case 2:
					e_log(8,"Updating user $user");
					$uID = filter_var($_POST['userSelect'], FILTER_VALIDATE_INT);
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
					header("Content-Type: application/json");
					die(json_encode($response));
					break;
				case 3:
					e_log(8,"Delete user $user");
					$uID = filter_var($_POST['userSelect'], FILTER_VALIDATE_INT);
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
					header("Content-Type: application/json");
					die(json_encode($response));
					break;
				default:
					$message = "Unknown action for managing users";
					e_log(1,$message);
					die($message);
			}
			break;
		case "mlog":
			if($_SESSION['sud']['userType'] > 1) {
			    $lfile = is_dir(CONFIG['logfile']) ? CONFIG['logfile'].'/syncmarks.log':CONFIG['logfile'];
				die(file_get_contents($lfile));
			} else {
				$message = "Not allowed to read server logfile.";
				e_log(2,$message);
				die($message);
			}
			break;
		case "mrefresh":
			if($_SESSION['sud']['userType'] > 1) {	
			    $lfile = is_dir(CONFIG['logfile']) ? CONFIG['logfile'].'/syncmarks.log':CONFIG['logfile'];
				die(file_get_contents($lfile));
			} else {
				$message = "Not allowed to read server logfile.";
				e_log(2,$message);
				die($message);
			}
			break;
		case "mclear":
			e_log(8,"Clear logfile");
			if($_SESSION['sud']['userType'] > 1) {
				$lfile = is_dir(CONFIG['logfile']) ? CONFIG['logfile'].'/syncmarks.log':CONFIGg['logfile'];
				file_put_contents($lfile,"");
				die(file_get_contents($lfile));
			}
				
			die();
			break;
		case "madd":
			$bmParentID = filter_var($_POST['folder'], FILTER_SANITIZE_STRING);
			$bmURL = validate_url(trim($_POST['url']));
			e_log(8,"Try to add manually new bookmark ".$bmURL);
			
			if(strpos($bmURL,'http') != 0) {
				e_log(1,"Given string is not a real URL, cant add this.");
				exit;
			}
			
			$bookmark['url'] = $bmURL;
			$bookmark['folder'] = $bmParentID;
			$bookmark['title'] = getSiteTitle($bmURL);
			$bookmark['id'] = unique_code(12);
			$bookmark['type'] = 'bookmark';
			$bookmark['added'] = round(microtime(true) * 1000);
			
			$res = addBookmark($bookmark);
			
			if($res === 1) {
				if(!isset($_POST['rc'])) {
					e_log(8,"Manually added bookmark.");
					die(bmTree());
				} else {
					die(e_log(8,"Roundcube added bookmark."));
				}
			} else {
				echo $res;
				http_response_code(417);
			}
			
			break;
		case "mdel":
			$bmID = filter_var($_POST['id'], FILTER_SANITIZE_STRING);
			$delMark = delMark($bmID);
			if($delMark != 0) {
				if(!isset($_POST['rc'])) {
					e_log(8,"Bookmark $bmID removed");
					die();
				} else {
					die(e_log(8,"Bookmark $bmID deleted by Roundcube"));
				}
			} else {
				die(e_log(2,"There was an problem removing the bookmark, please check the logfile"));
			}
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
			echo htmlFooter();

			die();
			break;
		case "pbupdate":
			e_log(8,"Pushbullet: Updating Pushbullet information.");
			$password = filter_var($_POST['password'], FILTER_SANITIZE_STRING);
			$ptoken = filter_var($_POST['ptoken'], FILTER_SANITIZE_STRING);
			$pdevice = filter_var($_POST['pdevice'], FILTER_SANITIZE_STRING);
			$pbe = filter_var($_POST['pbe'], FILTER_SANITIZE_STRING);

			if(password_verify($password,$_SESSION['sud']['userHash'])) {
				$token = edcrpt('en', $ptoken);
				$device = edcrpt('en', $pdevice);
				$pbEnable = filter_var($pbe,FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
		
				$oOptionsA = json_decode($_SESSION['sud']['uOptions'],true);
				$oOptionsA['pAPI'] = $token;
				$oOptionsA['pDevice'] = $device;
				$oOptionsA['pbEnable'] = $pbEnable;
		
				$query = "UPDATE `users` SET `uOptions`='".json_encode($oOptionsA)."' WHERE `userID`=".$_SESSION['sud']['userID'].";";
				$count = db_query($query);
				($count === 1) ? e_log(8,"Option saved") : e_log(9,"Error, saving option");
				header("location: ?");
				die();
			}
			else {
				e_log(1,"Password mismatch. Pushbullet not updated.");
				die("Password mismatch. Pushbullet not updated.");
			}
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
			echo htmlFooter();
			die();
			break;
		case "export":
			e_log(8,"Request bookmark export");
			$ctype = getClientType($_SERVER['HTTP_USER_AGENT']);
			$ctime = round(microtime(true) * 1000);
			$format = filter_var($_POST['type'], FILTER_SANITIZE_STRING);
			switch($format) {
				case "html":
					e_log(8,"Exporting in HTML format for download");
					die(html_export());
					break;
				case "json":
					e_log(8,"Exporting in JSON format");
					$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
					$bookmarks = json_encode(getBookmarks());
					if(CONFIG['loglevel'] == 9 && CONFIG['cexp'] == true) {
						$filename = is_dir(CONFIG['logfile']) ? CONFIG['logfile']."/export_".time().".json":"export_".time().".json";
						file_put_contents($filename,$bookmarks,true);
						e_log(8,"Export file is saved to $filename");
					}
					$bcount = count(json_decode($bookmarks));
					e_log(8,"Send $bcount bookmarks to '$client'");
					$ctime = (filter_var($_POST['s'], FILTER_SANITIZE_STRING) === 'false') ? 0:$ctime;
					updateClient($client, $ctype, $ctime, true);

					header("Content-Type: application/json");
					die($bookmarks);
					break;
				default:
					die(e_log(2,"Unknown export format, exit process"));
			}
			exit;
			break;
		case "checkdups":
			e_log(8,"Checking for duplicated bookmarks by url");
			$query = "SELECT `bmID`, `bmTitle`, `bmURL` FROM `bookmarks` WHERE `userID` = ".$_SESSION['sud']['userID']." GROUP BY `bmURL` HAVING COUNT(`bmURL`) > 1;";
			$dubData = db_query($query);
			foreach($dubData as $key => $dub) {
				$query = "SELECT `bmID`, `bmParentID`, `bmTitle`, `bmAdded` FROM `bookmarks` WHERE `bmURL` = '".$dub['bmURL']."' AND `userID` = ".$_SESSION['sud']['userID']." ORDER BY `bmParentID`, `bmIndex`;";
				$subData = db_query($query);
				foreach($subData as $index => $entry) {
					$subData[$index]['fway'] = fWay($entry['bmParentID'], $_SESSION['sud']['userID'],'');
				}
				$dubData[$key]['subs'] = $subData;
			}
			header("Content-Type: application/json");
			die(json_encode($dubData));
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
				echo htmlFooter();
			}
			exit;
			break;
		case "maddon":
			$rResponse['bookmarks'] = showBookmarks(1);
			$rResponse['folders'] = getUserFolders($_SESSION['sud']['userID']);
			header("Content-Type: application/json");
			die(json_encode($rResponse));
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
		default:
			header("Content-Type: application/json");
			die(json_encode("Unknown Action"));
	}
	exit;
}

if(isset($_GET['link'])) {
	$url = validate_url($_GET["link"]);
	e_log(9,"URL add request: " . $url);
	
	$title = (isset($_GET["title"]) && $_GET["title"] != '') ? filter_var($_GET["title"], FILTER_SANITIZE_STRING):getSiteTitle($url);

	$bookmark['url'] = $url;
	$bookmark['folder'] = 'unfiled_____';
	$bookmark['title'] = $title;
	$bookmark['id'] = unique_code(12);
	$bookmark['type'] = 'bookmark';
	$bookmark['added'] = round(microtime(true) * 1000);

	$uas = array(
		"HttpShortcuts",
		"Irix"
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
	if($res == 1) {
		if ($so) {
			echo("URL added.");
		} else {
			echo "<script>window.onload = function() { window.close();}</script>";
		}
	} else {
		echo $res;
	}
	die();
}

if(isset($_GET['push'])) {
	$url = validate_url($_GET['push']);
	e_log(8,"Received new pushed URL from bookmarklet: ".$url);
	$target = (isset($_GET['tg'])) ? filter_var($_GET['tg'], FILTER_SANITIZE_STRING) : '0';
	
	if(newNotification($url, $target) !== 0) die('Pushed');
}

echo htmlHeader();
echo htmlForms();
echo showBookmarks(2);
echo htmlFooter();

function newNotification($url, $target) {
	$erg = 0;
	$title = getSiteTitle($url);
	$ctime = time();
	$uidd = $_SESSION['sud']['userID'];

	$query = "INSERT INTO `notifications` (`title`,`message`,`ntime`,`client`,`nloop`,`publish_date`,`userID`) VALUES ('$title', '$url', $ctime, '$target', 1, $ctime, $uidd);";
	$erg = db_query($query);
	
	$options = json_decode($_SESSION['sud']['uOptions'],true);
	
	if(strlen($options['pAPI']) > 1 && strlen($options['pDevice']) > 1 && $options['pbEnable'] == "1") {
		pushlink($title,$url);
	} else {
		e_log(2,"Can't send to Pushbullet, missing data. Please check options");
	}
	
	return $erg;
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
	e_log(8,"Delete bookmark '$bmID'");

	$query = "SELECT `bmParentID`, `bmIndex`, `bmURL` FROM `bookmarks` WHERE `bmID` = '$bmID' AND `userID` = ".$_SESSION['sud']['userID'].";";
	$dData = db_query($query)[0];

	$query = "DELETE FROM `bookmarks` WHERE `bmID` = '$bmID' AND `userID` = ".$_SESSION['sud']['userID'].";";
	$count = db_query($query);

	if(isset($dData['bmType']) && $dData['bmType'] === 'folder') {
		e_log(8,"'$bmID' appeared to be a folder, delete all the contents of it");
		$query = "DELETE FROM `bookmarks` WHERE `bmParentID` = '$bmID' AND `userID` = ".$_SESSION['sud']['userID'].";";
		db_query($query);
	}

	e_log(8,"Check for remaining entries in this folder");
	$query = "SELECT * FROM `bookmarks` WHERE `bmParentID` = '".$dData['bmParentID']."' AND `userID` = ".$_SESSION['sud']['userID']." AND `bmIndex` > ".$dData['bmIndex']." ORDER BY bmIndex;";
	$fBookmarks = db_query($query);
	$bm_count = count($fBookmarks);
	
	e_log(8,"Re-index folder ".$dData['bmParentID']);
	for ($i = 0; $i < $bm_count; $i++) {
		$query = "UPDATE `bookmarks` SET `bmIndex`= $i WHERE `bmID` = '".$fBookmarks[$i]['bmID']."' AND `userID` = ".$_SESSION['sud']['userID'].";";
		db_query($query);
	}

	return $count;
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
	$url = filter_var(filter_var($url, FILTER_SANITIZE_STRING), FILTER_SANITIZE_URL);
	if (filter_var($url, FILTER_VALIDATE_URL)) {
		return $url;
	} else {
		e_log(2,"URL is not a valid URL. Exit now.");
		exit;
	}
}

function pushlink($title,$url) {
	$pddata = json_decode($_SESSION['sud']['uOptions'],true);
	$token = edcrpt('de', $pddata['pAPI']);
	$device = edcrpt('de', $pddata['pDevice']);
	e_log(8,"Send Pushbullet notification to device: $device");
	$encTitle = html_entity_decode($title, ENT_QUOTES | ENT_XML1, 'UTF-8');
	
	$data = json_encode(array(
		'type' => 'link',
		'title' => $encTitle,
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

function editFolder($bm) {
	e_log(8,"Edit folder request, try to find the folder...");
	$query = "SELECT * FROM `bookmarks` WHERE `bmIndex` >= ".$bm['index']." AND `bmType` = 'folder' AND `bmParentID` = '".$bm['parentId']."' AND `userID` = ".$_SESSION['sud']['userID'].";";
	$fData = db_query($query);

	if(count($fData) == 1) {
		e_log(8,"Unique folder found, edit the folder");
		$query = "UPDATE `bookmarks` SET `bmTitle` = '".$bm['title']."' WHERE `bmID` = '".$fData[0]['bmID']."' AND userID = ".$_SESSION['sud']["userID"].";";
		$count = db_query($query);
	} else {
		e_log(2,"Folder not found, chancel operation and send error to client.");
		$count = 0;
	}
	return $count;
}

function editBookmark($bm) {
	e_log(8,"Edit bookmark request, try to find the bookmark first by url...");
	$query = "SELECT `bmID`  FROM `bookmarks` WHERE `bmURL` = '".$bm['url']."' AND `userID` = ".$_SESSION['sud']['userID'];
	$bmData = db_query($query);

	if(count($bmData) == 1) {
		e_log(8,"Unique entry found, edit the title of the bookmark.");
		$query = "UPDATE `bookmarks` SET `bmTitle` = '".$bm['title']."' WHERE `bmID` = '".$bmData[0]['bmID']."' AND userID = ".$_SESSION['sud']["userID"].";";
		$count = db_query($query);
	} else {
		e_log(2,"No unique bookmark found, try to find now by title...");
		$query = "SELECT `bmID`  FROM `bookmarks` WHERE `bmTitle` = '".$bm['title']."' AND `userID` = ".$_SESSION['sud']['userID'];
		$bmData = db_query($query);

		if(count($bmData) == 1) {
			e_log(8,"Unique entry found, edit the url of the bookmark.");
			$query = "UPDATE `bookmarks` SET `bmURL` = '".$bm['url']."' WHERE `bmID` = '".$bmData[0]['bmID']."' AND userID = ".$_SESSION['sud']["userID"].";";
			$count = db_query($query);
		} else {
			e_log(2,"No Unique entry found, chancel operation and send error to client.");
			$count = 0;
		}
	}

	return $count;
}

function moveBookmark($bm) {
	e_log(8,"Bookmark seems to be moved, checking current folder data");
	$query = "SELECT `bmID`, `bmParentID` FROM `bookmarks` WHERE `bmType` = 'folder' AND `bmTitle` = '".$bm['nfolder']."' AND `userID` = ".$_SESSION['sud']['userID'].";";
	$folderData = db_query($query)[0];
	
	if(is_null($folderData['bmID'])) {
		e_log(2,"Folder not found, can`t move bookmark.");
		return "Folder not found, bookmark not moved.";
	} else {
		$bm['folder'] = $folderData['bmID'];
	}

	if(array_key_exists("url", $bm)) {
		e_log(8,"Checking bookmark data before moving it");
		$query = "SELECT * FROM `bookmarks` WHERE `userID`= ".$_SESSION['sud']["userID"]." AND `bmURL` = '".$bm["url"]."';";
		$oldData = db_query($query)[0];
		
		if (!empty($folderData) && !empty($oldData)) {
			if(($folderData['bmParentID'] != $oldData['bmParentID']) || ($oldData['bmIndex'] != $bm['index'])) {
				e_log(8,"Folder or Position changed, moving bookmark");
				$query = "DELETE FROM `bookmarks` WHERE `bmID` = '".$oldData["bmID"]."'";
				db_query($query);
				e_log(8,"Re-Add bookmark on new position");
				$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('".$oldData["bmID"]."', '".$bm['folder']."', ".$bm['index'].", '".$oldData['bmTitle']."', '".$oldData['bmType']."', '".$oldData['bmURL']."', ".$oldData['bmAdded'].", ".$_SESSION['sud']["userID"].")";
				db_query($query);
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
		e_log(2,"url key not found");
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
		e_log(2,$message);
		return $message;
	}
	e_log(8,"Identify folder for new bookmark");
	$query = "SELECT COALESCE(MAX(`bmID`), 'unfiled_____') `bmID` FROM `bookmarks` WHERE `bmID` = '".$bm["folder"]."' AND `userID` = ".$_SESSION['sud']['userID'].";";
	$folderID = db_query($query)[0]['bmID'];

	e_log(8,"Get new index for bookmark");
	$query = "SELECT IFNULL(MAX(`bmIndex`),-1) + 1 AS `nindex` FROM `bookmarks` WHERE `userID` = ".$_SESSION['sud']['userID']." AND `bmParentID` = '$folderID';";
	$nindex = db_query($query)[0]['nindex'];
	
	e_log(8,"Add bookmark '".$bm['title']."'");
	$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('".$bm['id']."', '$folderID', $nindex, '".$bm['title']."', '".$bm['type']."', '".$bm['url']."', ".$bm['added'].", ".$_SESSION['sud']["userID"].");";
	if(db_query($query) === false ) {
		$message = "Adding bookmark failed";
		e_log(1,$message);
		return $message;
	} else {
		return 1;
	}
}

function getChanges($cl, $ct, $time) {
	$uid = $_SESSION['sud']["userID"];
	e_log(8,"Browser startup sync started, get client data");
	$query = "SELECT `lastseen` FROM `clients` WHERE `cid` = '".$cl."' AND `uid` = $uid AND `ctype` = '".$ct."';";
	$clientData = db_query($query)[0];

	if($clientData) {
		$lastseen = $clientData["lastseen"];
		e_log(8,"Get changed bookmarks for client $cl");
		$query = "SELECT a.`bmParentID` as fdID, (SELECT `bmTitle` FROM `bookmarks` WHERE `bmID` = a.`bmParentID` AND userID = $uid) as fdName, (SELECT `bmIndex` FROM `bookmarks` WHERE `bmID` = a.`bmParentID` AND userID = $uid) as fdIndex, `bmID`, `bmIndex`, `bmTitle`, `bmType`, `bmURL`, `bmAdded`, `bmModified` FROM `bookmarks` a WHERE (bmAdded > $lastseen AND userID = $uid) OR (bmAdded > $lastseen AND userID = $uid);";
		
		$bookmarkData = db_query($query);
		foreach($bookmarkData as $key => $entry) {
			$bookmarkData[$key]['bmTitle'] = html_entity_decode($entry['bmTitle'], ENT_QUOTES, 'UTF-8'); 
		}
	}
	else {
		e_log(2,"Client not found in database, registering now");
		updateClient($cl, $ct, $time, true);
		return "New client registered for user.";
	}

	if (!empty($bookmarkData)) {
		updateClient($cl, $ct, $time, true);

		if(CONFIG['cexpjson'] && CONFIG['loglevel'] == 9) {
			$filename = is_dir(CONFIG['logfile']) ? CONFIG['logfile']."/changes_".time().".json":"changes_".time().".json";
			file_put_contents($filename,json_encode($bookmarkData),true);
			e_log(8,'Export file is saved to '.$filename);
		}

		e_log(8,"Found ".count($bookmarkData)." changes. Sending them to the client");
		return $bookmarkData;
	}
	else {
		e_log(8,"No bookmarks changed since last sync");
		return "No bookmarks added, removed or changed since the client was last seen.";
	}
}

function updateClient($cl, $ct, $time, $sync = false) {
	$fclients = array("bookmarkTab", "Android");
	if(in_array($cl, $fclients)) return 0;

	$uid = $_SESSION['sud']["userID"];
	$query = "SELECT * FROM `clients` WHERE `cid` = '".$cl."' AND uid = ".$uid.";";
	$clientData = db_query($query);
	$message = "";

	if (!empty($clientData)) {
		e_log(8,"Updating lastlogin for '$cl'");
		$query = "UPDATE `clients` SET `lastseen`= '".$time."' WHERE `cid` = '".$cl."';";
		$message = (db_query($query)) ? "Client updated.":"Failed update client";
	} else if(empty($clientData)) {
		e_log(8,"New client detected. Try to register client $cl for user ".$_SESSION['sud']["userName"]);
		$query = "INSERT INTO `clients` (`cid`,`cname`,`ctype`,`uid`,`lastseen`) VALUES ('".$cl."','".$cl."', '".$ct."', ".$uid.", '0')";
		$message = (db_query($query)) ? "Client updated/registered.":"Failed to register client";
	} elseif(!empty($clientData)) {
		$message = "Client updated";
	}
	
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
	$query = "SELECT MAX(`bmIndex`) AS OIndex  FROM `bookmarks` WHERE `bmParentID` = '".$folder."'";
	$IndexArr = db_query($query);
	$maxIndex = $IndexArr[0]['OIndex'] + 1;
	return $maxIndex;
}

function getSiteTitle($url) {
	e_log(8,"Get titel for site ".$url);
	$src = file_get_contents($url);
	if(strlen($src) > 0) {
		preg_match("/\<title\>(.*)\<\/title\>/i",$src,$title_arr);
		$title = (strlen($title_arr[1]) > 0) ? strval($title_arr[1]) : substr($url, 0, 240);
		e_log(8,"Titel for site is '$title'");
		$convTitle = htmlspecialchars(mb_convert_encoding(htmlspecialchars_decode($title, ENT_QUOTES),"UTF-8"),ENT_QUOTES,'UTF-8', false);
	} else {
		$convTitle = substr($url, 0, 240);
	}
	
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
	if($errfile != "") $message = $message." in ".$errfile." on line ".$errline;
	$user = '';
	if(isset($_SESSION['sauth'])) $user = "- ".$_SESSION['sauth']." ";
	$line = "[".date("d-M-Y H:i:s")."] $mode $user- $message\n";
	
	if($level <= CONFIG['loglevel']) {
		$lfile = is_dir(CONFIG['logfile']) ? CONFIG['logfile'].'/syncmarks.log':CONFIG['logfile'];
		file_put_contents($lfile, $line, FILE_APPEND);
	}
}

function filterIP($remote) {
	$v4mapped_prefix_bin = hex2bin('00000000000000000000ffff'); 
	$addr_bin = inet_pton($remote);
	if($addr_bin === FALSE ) die(e_log(1,'Invalid IP address'));

	if( substr($addr_bin, 0, strlen($v4mapped_prefix_bin)) == $v4mapped_prefix_bin) $addr_bin = substr($addr_bin, strlen($v4mapped_prefix_bin));

	return inet_ntop($addr_bin);
}

function delUsermarks($uid) {
	e_log(8, "Delete all bookmarks for logged in user");
	$query = "DELETE FROM `bookmarks` WHERE `UserID`=".$uid;
	db_query($query);
}

function htmlHeader() {
	$htmlHeader = "<!DOCTYPE html>
		<html lang='en'>
			<head>
				<meta name='viewport' content='width=device-width, initial-scale=1'>
				<script src='js/bookmarks.min.js'></script>
				<link type='text/css' rel='stylesheet' href='css/bookmarks.min.css'>
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
	$version = explode ("\n", file_get_contents('./CHANGELOG.md',NULL,NULL,0,30))[2];
	$version = substr($version,0,strpos($version, " "));
	$clink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	$bookmarklet = "javascript:void function(){window.open('$clink?title='+encodeURIComponent(document.title)+'&link='+encodeURIComponent(document.location.href),'bWindow','width=480,height=245',replace=!0)}();";
	$userName = $_SESSION['sud']['userName'];
	$userMail = $_SESSION['sud']['userMail'];
	$userID = $_SESSION['sud']['userID'];
	$userOldLogin = date("d.m.y H:i",$_SESSION['sud']['userOldLogin']);
	$admenu = ($_SESSION['sud']['userType'] == 2) ? "<hr><li class='menuitem' id='mlog'>Logfile</li><li class='menuitem' id='mngusers'>Users</li>":"";
	$logform = ($_SESSION['sud']['userType'] == 2) ? "<div id=\"logfile\"><div id=\"close\"><button id='mrefresh'>refresh</button><label for='arefresh'><input type='checkbox' id='arefresh' name='arefresh'>Auto Refresh</label> <button id='mclear'>clear</button> <button id='mclose'>&times;</button></div><div id='lfiletext' contenteditable='true'></div></div>":"";

	$uOptions = json_decode($_SESSION['sud']['uOptions'],true);
	$oswitch = ($uOptions['notifications'] == 1) ? " checked":"";
	$oswitch =  "<label class='switch' title='Enable/Disable Notifications'><input id='cnoti' type='checkbox'$oswitch><span class='slider round'></span></label>";

	$pbswitch = (isset($uOptions['pbEnable']) && $uOptions['pbEnable'] == 1) ? " checked":"";
	$pbswitch = "<label class='switch' title='Enable/Disable Pushbullet'><input id='pbe' name='pbe' value='1' type='checkbox'$pbswitch><span class='slider round'></span></label>";
	$pAPI = (isset($uOptions['pAPI'])) ? edcrpt('de',$uOptions['pAPI']):'';
	$pDevice = (isset($uOptions['pDevice'])) ? edcrpt('de',$uOptions['pDevice']):'';

	$mngsettingsform = "
	<div id='mngsform' class='mmenu'><h6>SyncMarks Settings</h6>
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
			<tr><td colspan=2 class='bcenter'><button id='pbullet'>Pushbullet</button></td></tr>
			<tr><td colspan='2' style='height: 5px;'></td></tr>
			<tr><td>Notifications</td><td class='bright'>$oswitch</td></tr>
		</table>
		<div id='bmlet'><a href=\"$bookmarklet\">Bookmarklet</a></div>
	</div>";

	$mngclientform = "<div id='mngcform' class='mmenu'></div>";

	$nmessagesform = "
	<div id='nmessagesform' class='mmenu'>
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

	$pbulletform = "
	<div id='pbulletform' class='mbmdialog'>
		<h6>Pushbullet</h6>
		<div class='dialogdescr'>Maintain your API Token and Device ID.</div>
		<form action='' method='POST'>$pbswitch
			<input placeholder='API Token' type='text' id='ptoken' name='ptoken' value='$pAPI' />
			<input placeholder='Device ID' type='text' id='pdevice' name='pdevice' value='$pDevice' autocomplete='device-token' />
			<input required placeholder='Password' type='password' id='password' name='password' autocomplete='current-password' value='' />
			<div class='dbutton'><button class='mdcancel' type='reset' value='Reset'>Cancel</button><button type='submit' name='caction' value='pbupdate'>Save</button></div>
		</form>
	</div>";

	$passwordform = "
	<div id='passwordform' class='mbmdialog'>
		<h6>Change Password</h6>
		<div class='dialogdescr'>Enter your current password and a new password and confirm the new password.</div>
		<form action='' method='POST'>					
			<input required placeholder='Current password' type='password' id='opassword' name='opassword' autocomplete='current-password' value='' />
			<input required placeholder='New password' type='password' id='npassword' name='npassword' autocomplete='new-password' value='' />
			<input required placeholder='Confirm new password' type='password' id='cpassword' name='cpassword' autocomplete='new-password' value='' />
			<div class='dbutton'><button class='mdcancel' type='reset' value='Reset'>Cancel</button><button type='submit' name='caction' value='pupdate'>Save</button></div>
		</form>
	</div>";

	$userform = "
	<div id='userform' class='mbmdialog'>
		<h6>Change Username</h6>
		<div class='dialogdescr'>Here you can change your username. Type in your new username and your current password and click on save to change it.</div>
		<form action='' method='POST'>
			<input placeholder='Username' required type='text' name='username' id='username' autocomplete='username' value='$userName'>
			<input placeholder='Password' required type='password' id='oopassword' name='opassword' autocomplete='current-password' value='' />
			<div class='dbutton'><button class='mdcancel' type='reset' value='Reset'>Cancel</button><button type='submit' name='caction' value='uupdate'>Save</button></div>
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
			<li class='menuitem' id='logout'><form method='POST'><button name='caction' id='loutaction' value='logout'>Logout</button></form></li>
		</ul>
	</div>";

	$bmMenu = "
	<menu class='menu'><input type='hidden' id='bmid' title='bmtitle' value=''>
		<ul>
			<li id='btnEdit' class='menu-item'>Edit</li>
			<li id='btnMove' class='menu-item'>Move</li>
			<li id='btnDelete' class='menu-item'>Delete</li>
			<li id='btnFolder' class='menu-item'>New Folder</li>
		</ul>
	</menu>";

	$editForm = "
	<div id='bmarkedt' class='mbmdialog'>
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
		<h6>Move Bookmark</h6>
		<form id='bmmv' method='POST'>
			<input placeholder='Title' type='text' id='mvtitle' name='mvtitle' value='' disabled>
			<div class='select'>
				<select id='mvfolder' name='mvfolder'>$sFolderOptions</select>
				<div class='select__arrow'></div>
			</div>
			<input type='hidden' id='mvid' name='mvid' value=''>
			<div class='dbutton'><button type='submit' id='mvsave' name='mvsave' value='Save' disabled>Save</button></div>
		</form>
	</div>";

	$folderForm = "
	<div id='folderf' class='mbmdialog'>
		<h6>Create new folder</h6>
		<form id='fadd' method='POST'>
			<input placeholder='Foldername' type='text' id='fname' name='fname' value=''>
			<input type='hidden' id='fbid' name='fbid' value=''>
			<div class='dbutton'><button type='submit' id='fsave' name='fsave' value='Create' disabled>Create</button></div>
		</form>
	</div>";

	$footerButton = "
	<div id='bmarkadd' class='mbmdialog'>
		<h6>Add Bookmark</h6>
		<form id='bmadd' action='?madd' method='POST'>
			<input placeholder='URL' type='text' id='url' name='url' value=''>
			<div class='select'>
				<select id='folder' name='folder'>
					$sFolderOptions
				</select>
				<div class='select__arrow'></div>
			</div>
			<div class='dbutton'><button type='submit' id='save' name='madd' value='Save'>Save</button></div>
		</form>
	</div>
	<div id='footer'></div>";

	$htmlData = $folderForm.$moveForm.$editForm.$bmMenu.$logform.$mainmenu.$userform.$passwordform.$pbulletform.$mngsettingsform.$mngclientform.$nmessagesform.$footerButton;	
	return $htmlData;
}

function showBookmarks($mode) {
	$bmTree = bmTree();
	$htmlData = "<div id='bookmarks'>$bmTree</div>";
	if($mode === 2) $htmlData.= "<div id='hmarks' style='display: none'>$bmTree</div>";
	return $htmlData;
}

function bClientlist($uid) {
	$query = "SELECT `cid`, IFNULL(`cname`, `cid`) `cname`, `ctype`, `lastseen` FROM `clients` WHERE `uid` = $uid;";
	$clientData = db_query($query);
	
	uasort($clientData, function($a, $b) {
		return strnatcasecmp($a['cname'], $b['cname']);
	});
	
	$clientList = "<ul>";
	foreach($clientData as $key => $client) {
		$cname = $client['cid'];
		if(isset($client['cname'])) $cname = $client['cname'];
		$timestamp = $client['lastseen'] / 1000;
		$lastseen = ($timestamp != '0') ? date('D, d. M. Y H:i', $timestamp):'----: -- -- ---- -- --';
		$clientList.= "<li title='".$client['cid']."' data-type='".strtolower($client['ctype'])."' id='".$client['cid']."' class='client'><div class='clientname'>$cname<input type='text' name='cname' value='$cname'><div class='lastseen'>$lastseen</div></div><div class='fa-edit rename'></div><div class='fa-trash remove'></div></li>";
	}
	$clientList.= "</ul>";
	return $clientList;
}

function notiList($uid, $loop) {
	$query = "SELECT n.id, n.title, n.message, n.publish_date, IFNULL(c.cname, n.client) AS client FROM notifications n LEFT JOIN clients c ON c.cid = n.client WHERE n.userID = $uid AND n.nloop = $loop ORDER BY n.publish_date;";
	$aNotitData = db_query($query);
	$notiList = "";
	foreach($aNotitData as $key => $aNoti) {
		$cl = ($aNoti['client'] == "0") ? "All":$aNoti['client'];
		$title = html_entity_decode($aNoti['title'],ENT_QUOTES,'UTF-8');

		$notiList.= "<div class='NotiTableRow'>
					<div class='NotiTableCell'>
						<span><a class='link' target='_blank' title='$title' href='".$aNoti['message']."'>$title</a></span>
						<span class='nlink'>".$aNoti['message']."</span>
						<span class='ndate'>".date("d.m.Y H:i",$aNoti['publish_date'])." | $cl</span>
					</div>
					<div class='NotiTableCell'><a class='fa fa-trash' data-message='".$aNoti['id']."' href='#'></a></div>
				</div>";
	}
	return $notiList;
}

function htmlFooter() {
	$htmlFooter = "<script src='js/bookmarksf.min.js'></script></body></html>";
	return $htmlFooter;
}

function getUserFolders($uid) {
	e_log(8,"Get bookmark folders for user");
	$query = "SELECT * FROM `bookmarks` WHERE `bmType` = 'folder' and `userID` = ".$uid.";";
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
			$title = htmlspecialchars(mb_convert_encoding(htmlspecialchars_decode($bm['bmTitle'], ENT_QUOTES),"UTF-8"),ENT_QUOTES,'UTF-8', false);
			$bookmark = "\n<li class='file'><a id='".$bm['bmID']."' title='".$title."' rel='noopener' target='_blank' href='".$bm['bmURL']."'>".$title."</a></li>%ID".$bm['bmParentID'];
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
	
	$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`bmModified`,`userID`) VALUES (?,?,?,?,?,?,?,?,?)";
	$response = db_query($query,$data2);

	if($response)
		e_log(8,"Bookmark import successful");
	else
		e_log(1,"Error importing bookmarks");
	
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
	$query = "SELECT * FROM `bookmarks` WHERE `userID` = ".$_SESSION['sud']['userID'].";";
	e_log(8,"Get bookmarks");
	$userMarks = db_query($query);
	foreach($userMarks as &$element) {
		$element['bmTitle'] = html_entity_decode($element['bmTitle'],ENT_QUOTES,'UTF-8');
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
			'httponly' => true,
			'samesite' => 'Strict'
		);
		
		setcookie("syncmarks", "", $cOptions);
	}
}

function checkLogin() {
	e_log(8,"Check login...");
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
		$query = "SELECT t.*, u.userlastLogin, u.sessionID FROM `auth_token` t INNER JOIN `users` u ON u.userName = t.userName WHERE t.userName = '".$cookieArr['user']."' ORDER BY t.exDate DESC;";
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
			
			$expireTime = time()+60*60*24*7;
			$rtkn = unique_code(32);
			
			$cOptions = array (
				'expires' => $expireTime,
				'path' => null,
				'domain' => null,
				'secure' => true,
				'httponly' => true,
				'samesite' => 'Strict'
			);
			
			$cookieData = cryptCookie(json_encode(array('rtkn' => $rtkn, 'user' => $tkdata[0]['userName'], 'token' => $cookieArr['rtkn'])), 1);

			setcookie('syncmarks', $cookieData, $cOptions);
			
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
			e_log(8,"Try login $client");
			$query = "SELECT `c`.*, `u`.`userName` FROM `c_token` `c` INNER JOIN `users` `u` ON `u`.`userID` = `c`.`userID` WHERE `cid` = '$client';";
			$dbdata = db_query($query);
			
			if(count($dbdata) === 1) {
				$pverify = (password_verify($cdata['token'], $dbdata[0]['tHash'])) ? "true":"false";
				$zeit = ($dbdata[0]['exDate'] > time()) ? "dbzeit":"actzeit";
				if(password_verify($cdata['token'], $dbdata[0]['tHash']) && $dbdata[0]['exDate'] > time()) {
					$_SESSION['sauth'] = $dbdata[0]['userName'];
					e_log(8,"$client login successful");
					$expireTime = time()+60*60*24*7;
					$token = bin2hex(openssl_random_pseudo_bytes(32));
					$thash = password_hash($token, PASSWORD_DEFAULT);
					$userID = $dbdata[0]['userID'];
					$ipjson = json_encode(ip_info());
					$query = "UPDATE `c_token` SET `tHash` = '$thash', `exDate` = '$expireTime', `cInfo` = '$ipjson' WHERE `cid` = '$client';";
					db_query($query);
					header("X-Request-Info: $token");
				} else {
					e_log(2,"Client login failed");
					$query = "SELECT `cInfo` FROM `c_token` WHERE `cid` = '$client';";
					$cInfo = db_query($query)[0];
					$query = "UPDATE `c_token` SET `tHash` = '' WHERE `cid` = '$client';";
					db_query($query);
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
			if(!isset($_POST['client'])) header('WWW-Authenticate: Basic realm="'.$realm.'", charset="UTF-8"');
			if(!isset($_POST['client'])) http_response_code(401);
			echo htmlHeader();
			echo "<div id='loginbody'>
				<div id='loginform'>
					<div id='loginformh'>Access denied</div>
					<div id='loginformt'>Access denied. You must <a href='?'>login</a> to use this tool.</div>
				</div>
			</div>";
			echo htmlFooter();
			exit;
		} else {
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
						$expireTime = time()+60*60*24*7;
						$rtkn = unique_code(32);
						
						$cOptions = array (
							'expires' => $expireTime,
							'path' => null,
							'domain' => null,
							'secure' => true,
							'httponly' => true,
							'samesite' => 'Strict'
						);
						
						$dtoken = bin2hex(openssl_random_pseudo_bytes(16));
						$cookieData = cryptCookie(json_encode(array('rtkn' => $rtkn, 'user' => $udata[0]['userName'], 'token' => $dtoken)), 1);

						setcookie('syncmarks', $cookieData, $cOptions);
						
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
					echo htmlFooter();
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
					echo htmlFooter();
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
		echo htmlFooter();
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
			$db = new PDO(CONFIG['db']['type'].':host='.CONFIG['db']['host'].';dbname='.CONFIG['db']['dbname'], CONFIG['db']['user'], CONFIG['db']['pwd'], $options);
		} elseif(CONFIG['db']['type'] == 'sqlite') {
			$db = new PDO(CONFIG['db']['type'].':'.CONFIG['db']['dbname'], null, null, $options);
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
	$vInfo = db_query("SELECT * FROM `system` ORDER BY `updated` DESC LIMIT 1;")[0];
	
	$olddate = $vInfo['updated'];
	$newdate = filemtime(__FILE__);
	$dbv = 7;

	if($vInfo['db_version'] && $vInfo['db_version'] < $dbv) {
		e_log(8,"Database update needed. Starting DB update...");
		if(CONFIG['db']['type'] == "sqlite") {
			db_query(file_get_contents("./sql/sqlite_update_$dbv.sql"));
		} elseif(CONFIG['db']['type'] == "mysql") {
			db_query(file_get_contents("./sql/mysql_update_$dbv.sql"));
		}
		$aversion = explode ("\n", file_get_contents('./CHANGELOG.md',NULL,NULL,0,30))[2];
	    $aversion = substr($aversion,0,strpos($aversion, " "));
		db_query("INSERT INTO `system`(`app_version`,`db_version`,`updated`) VALUES ('$aversion','$dbv','$newdate');");
	} elseif($vInfo['db_version'] && $vInfo['db_version'] >= $dbv) {
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
		} elseif(CONFIG['db']['type'] == "mysql") {
			e_log(8,"Initialise new MySQL database");
			db_query(file_get_contents("./sql/mysql_init.sql"));
		}

		$bmAdded = round(microtime(true) * 1000);
		$userPWD = password_hash(CONFIG['spwd'],PASSWORD_DEFAULT);
		$query = "INSERT INTO `users` (userName,userType,userHash) VALUES ('".CONFIG['suser']."',2,'$userPWD');";
		db_query($query);
		$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('unfiled_____', 'root________', 0, 'Other Bookmarks', 'folder', NULL, ".$bmAdded.", 1)";
		db_query($query);
		$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('".unique_code(12)."', 'unfiled_____', 0, 'GitHub Repository', 'bookmark', 'https://codeberg.org/Offerel/SyncMarks-Webapp', ".$bmAdded.", 1)";
		db_query($query);
	}
}
?>