<?php
/**
 * SyncMarks
 *
 * @version 1.4.1
 * @author Offerel
 * @copyright Copyright (c) 2021, Offerel
 * @license GNU General Public License, version 3
 */
session_start();
include_once "config.inc.php.dist";
include_once "config.inc.php";
set_error_handler("e_log");

checkDB($database,$suser,$spwd);

if(isset($_GET['reset'])){
	$reset = filter_var($_GET['reset'], FILTER_SANITIZE_STRING);
	
	$headers = "From: SyncMarks <$sender>";
	switch($reset) {
		case "request":
			$user = filter_var($_GET['u'], FILTER_SANITIZE_STRING);
			e_log(8,"Passwort Reset request for '$user'");
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
				if(!mail($mail, "Passwort request confirmation",$message,$headers)) {
					e_log(1,"Error sending password reset request to user");
				}
			}
			
			die(json_encode("1"));
			break;
		case "chancel":
			$token = filter_var($_GET['t'], FILTER_SANITIZE_STRING);
			$query = "SELECT `r`.`userID`, `u`.`userName`, `u`.`userMail`, `r`.`tokenTime`, `r`.`token` FROM `reset` `r` INNER JOIN `users` `u` ON `u`.`userID` = `r`.`userID` WHERE `token` = '$token';";
			$result = db_query($query)[0];
			e_log(8,"Passwort Reset chancel for token '$token', '".$result['userName']."'");
			$query = "DELETE FROM `reset` WHERE `token` = '$token';";
			if(db_query($query)) {
				e_log(8,"Request removed successful");
				$message = "Hello ".$result['userName'].",\r\nYour password request is chanceled, You can login with your old credentials at ".$_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'].". If you want to make sure, that your account is healthy, you should change your password to a new one after logging in.";
				if(!mail($result['userMail'], "Password request chanceled",$message,$headers)) {
					e_log(1,"Error sending remove chancel to ".$result['userName']);
				}
			}
			echo htmlHeader();
			echo "<div id='loginbody'>
				<div id='loginform'>
					<div id='loginformh'>Welcome to SyncMarks</div>
					<div id='loginformt'>Password reset chanceled. You can login now <a href='".$_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']."'>login</a> with your new password.</div>
				</div>
			</div>";
			echo htmlFooter();
			die();
			break;
		case "confirm":
			$token = filter_var($_GET['t'], FILTER_SANITIZE_STRING);
			$query = "SELECT `r`.`userID`, `u`.`userName`, `u`.`userMail`, `r`.`tokenTime`, `r`.`token` FROM `reset` `r` INNER JOIN `users` `u` ON `u`.`userID` = `r`.`userID` WHERE `token` = '$token';";
			$result = db_query($query)[0];
			e_log(8,"Passwort Reset confirmation for token '$token', '".$result['userName']."'");
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

if(!isset($_SESSION['sauth']) || isset($_SESSION['fauth'])) checkLogin($realm);

if(!isset($userData)) $userData = getUserdata();

if(isset($_POST['caction'])) {
	switch($_POST['caction']) {
		case "addmark":
			$bookmark = json_decode($_POST['bookmark'], true);
			e_log(8,"Try to add entry '".$bookmark['title']."'");
			e_log(9,$_POST['bookmark']);
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			if(array_key_exists('url',$bookmark)) $bookmark['url'] = validate_url($bookmark['url']);
			if(strtolower(getClientType($_SERVER['HTTP_USER_AGENT'])) != "firefox") $bookmark = cfolderMatching($bookmark);
			if($bookmark['type'] == 'bookmark' && isset($bookmark['url'])) {
				$response = json_encode(addBookmark($userData, $bookmark));
				$ctime = round(microtime(true) * 1000);
				updateClient($client, strtolower(getClientType($_SERVER['HTTP_USER_AGENT'])), $userData, $ctime, true);
				die($response);
			} else if($bookmark['type'] == 'folder') {
				$response = addFolder($userData, $bookmark);
				$ctime = round(microtime(true) * 1000);
				updateClient($client, strtolower(getClientType($_SERVER['HTTP_USER_AGENT'])), $userData, $ctime, true);
				die($response);
			} else {
				e_log(1,"This bookmark is not added, some parameters are missing");
				die(false);
			}
			break;
		case "movemark":
			$bookmark = json_decode($_POST['bookmark'],true);
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$ctime = round(microtime(true) * 1000);
			$response = json_encode(moveBookmark($userData, $bookmark));
			updateClient($client, strtolower(getClientType($_SERVER['HTTP_USER_AGENT'])), $userData, $ctime, true);
			die($response);
			break;
		case "editmark":
			$bookmark = json_decode(rawurldecode($_POST['bookmark']),true);
			(array_key_exists('url',$bookmark)) ? die(editBookmark($bookmark, $userData)) : die(editFolder($bookmark, $userData));
			break;
		case "delmark":
			$bookmark = json_decode(rawurldecode($_POST['bookmark']),true);
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$ctime = round(microtime(true) * 1000);
			e_log(8,print_r($bookmark,true));
			$index = (isset($bookmark['index'])) ? "AND `bmIndex` = ".$bookmark['index']:"";
			e_log(8,"Try to identify bookmark");
			if(isset($bookmark['url'])) {
				$url = prepare_url($bookmark['url']);
				$query = "SELECT `bmID` FROM `bookmarks` WHERE `bmType` = 'bookmark'$index AND `bmURL` = '$url' AND `userID` = ".$userData['userID'].";";
			} else {
				$query = "SELECT `bmID` FROM `bookmarks` WHERE `bmType` = 'folder'$index AND `bmTitle` = '".$bookmark['title']."' AND `userID` = ".$userData['userID'].";";
			}

			$bData = db_query($query);
			if(count($bData) == 1) {
				die(json_encode(delMark($bData[0]['bmID'])));
			} else {
				$message = "No unique bookmark found, bookmark not removed";
				e_log(2,$message);
				die(json_encode($message));
			}
			break;
		case "startup":
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$ctype = getClientType($_SERVER['HTTP_USER_AGENT']);
			$ctime = round(microtime(true) * 1000);
			die(json_encode(getChanges($client, $ctype, $userData, $ctime),JSON_UNESCAPED_SLASHES));
			break;
		case "cfolder":
			$ctime = round(microtime(true) * 1000);
			$fname = filter_var($_POST['fname'], FILTER_SANITIZE_STRING);
			$fbid = filter_var($_POST['fbid'], FILTER_SANITIZE_STRING);
			die(cfolder($ctime,$fname,$fbid,$userData));
			break;
		case "import":
			$jmarks = json_decode($_POST['bookmark'],true);
			$jerrmsg = "";
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
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
				$filename = "import_".substr($client,0,8)."_".time().".json";
				file_put_contents($filename,urldecode($_POST['bookmark']),true);
				die(json_encode($jerrmsg));
			}

			$client = $_POST['client'];
			$ctype = getClientType($_SERVER['HTTP_USER_AGENT']);
			$ctime = round(microtime(true) * 1000);
			delUsermarks($userData['userID']);
			$armarks = parseJSON($jmarks);
			updateClient($client, $ctype, $userData, $ctime, true);
			die(json_encode(importMarks($armarks,$userData['userID'])));
			break;
		case "export":
			e_log(8,"Browser requested bookmark import...");
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$ctype = getClientType($_SERVER['HTTP_USER_AGENT']);
			$ctime = round(microtime(true) * 1000);
			$bookmarks = json_encode(getBookmarks($userData));
			if($loglevel = 9 && $cexpjson == true) {
				$filename = "export_".substr($client,0,8)."_".time().".json";
				file_put_contents($filename,$bookmarks,true);
			}
			echo $bookmarks;
			e_log(8,count(json_decode($bookmarks))." bookmarks send to client.");
			updateClient($client, $ctype, $userData, $ctime, true);
			die();
			break;
		case "getpurl":
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$url = validate_url($_POST['url']);
			$target = (isset($_POST['tg'])) ? filter_var($_POST['tg'], FILTER_SANITIZE_STRING) : '0';
			$ctime = time();
			$title = getSiteTitle($url);
			e_log(8,"Received new pushed URL: ".$url);
			$uidd = $userData['userID'];
			$query = "INSERT INTO `notifications` (`title`,`message`,`ntime`,`client`,`nloop`,`publish_date`,`userID`) VALUES ('$title', '$url', $ctime, '$target', 1, $ctime, $uidd)";
			$erg = db_query($query);
			if($erg !== 0) echo("URL successfully pushed.");
			break;
		case "lsnc":
			e_log(8,"Get clients lastseen date.");
			$query = "SELECT MAX(`lastseen`) as lastseen FROM `clients` WHERE `uid` = ".$userData['userID'].";";
			$lastSeen = db_query($query)[0]['lastseen'];
			die($lastSeen);
			break;
		case "rmessage":
			$message = filter_var($_POST['message'], FILTER_VALIDATE_INT);
			$loop = filter_var($_POST['lp'], FILTER_SANITIZE_STRING) == 'aNoti' ? 1 : 0;
			e_log(8,"Try to delete notification $message");
			$query = "DELETE FROM `notifications` WHERE `userID` = ".$userData['userID']." AND `id` = $message;";
			$count = db_query($query);
			($count === 1) ? e_log(8,"Notification successfully removed") : e_log(9,"Error, removing notification");
			die(notiList($userData['userID'], $loop));
			break;
		case "soption":
			$option = filter_var($_POST['option'], FILTER_SANITIZE_STRING);
			$value = filter_var(filter_var($_POST['value'], FILTER_SANITIZE_NUMBER_INT), FILTER_VALIDATE_INT);
			e_log(8,"Option received: ".$option.":".$value);
			$oOptionsA = json_decode($userData['uOptions'],true);
			$oOptionsA[$option] = $value;
			$query = "UPDATE `users` SET `uOptions`='".json_encode($oOptionsA)."' WHERE `userID`=".$userData['userID'].";";
			$count = db_query($query);
			($count === 1) ? e_log(8,"Option saved") : e_log(9,"Error, saving option");
			echo $count;
			break;
		case "getclients":
			e_log(8,"Try to get list of clients");
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$query = "SELECT `cid`, IFNULL(`cname`, `cid`) `cname`, `ctype`, `lastseen` FROM `clients` WHERE `uid` = ".$userData['userID']." AND NOT `cid` = '$client' ORDER BY 2 COLLATE NOCASE ASC;";
			$clientList = db_query($query);
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
			e_log(8,"Get testrequest from addon options page");
			$client = filter_var($_POST['client'], FILTER_SANITIZE_STRING);
			$type = getClientType($_SERVER['HTTP_USER_AGENT']);
			$time = round(microtime(true) * 1000);
			die(updateClient($client, $type, $userData, $time));
			break;
		case "gname":
			e_log(8,"Request clientname");
			$client = filter_var($_POST['cl'], FILTER_SANITIZE_STRING);
			$query = "SELECT cname, ctype FROM clients WHERE cid = '$client' and uid = ".$userData['userID'].";";
			$clientData = db_query($query)[0];
			e_log(8,"Send name '".$clientData['cname']."' back to client");
			die(json_encode($clientData));
			break;
		case "gurls":
			$client = (isset($_POST['client'])) ? filter_var($_POST['client'], FILTER_SANITIZE_STRING) : '0';
			e_log(8,"Request pushed sites for client $client");
			$query = "SELECT * FROM `notifications` WHERE `nloop` = 1 AND `userID` = ".$userData['userID']." AND `client` IN ('".$client."','0');";
			$uOptions = json_decode($userData['uOptions'],true);
			$notificationData = db_query($query);
			e_log(8,"Found ".count($notificationData)." links. Will push them to the client.");
			if (!empty($notificationData)) {
				foreach($notificationData as $key => $notification) {
					$myObj[$key]['title'] = html_entity_decode($notification['title'],ENT_QUOTES,'UTF-8');
					$myObj[$key]['url'] = $notification['message'];
					$myObj[$key]['nkey'] = $notification['id'];
					$myObj[$key]['nOption'] = $uOptions['notifications'];
				}
				die(json_encode($myObj));
			} else {
				die();
			}
			break;
		case "durl":
			e_log(8,"Hide notification");
			$notification = filter_var($_POST['durl'], FILTER_VALIDATE_INT);
			$query = "UPDATE `notifications` SET `nloop`= 0, `ntime`= '".time()."' WHERE `id` = $notification AND `userID` = ".$userData['userID'];
			die(db_query($query));
			break;
		case "bmedt":
			$title = filter_var($_POST['title'], FILTER_SANITIZE_STRING);
			$id = filter_var($_POST['id'], FILTER_SANITIZE_STRING);
			e_log(8,"Edit entry '$title'");
			$url = strlen($_POST['url']) > 4 ? '\''.validate_url($_POST['url']).'\'' : 'NULL';
			$query = "UPDATE `bookmarks` SET `bmTitle` = '$title', `bmURL` = $url, `bmAdded` = '".round(microtime(true) * 1000)."' WHERE `bmID` = '$id' AND `userID` = ".$userData['userID'].";";
			$count = db_query($query);
			($count > 0) ? die(true) : die(false);
			break;
		case "bmmv":
			$id = filter_var($_POST['id'], FILTER_SANITIZE_STRING);
			e_log(8,"Move bookmark $id");
			$folder = filter_var($_POST['folder'], FILTER_SANITIZE_STRING);
			$query = "SELECT MAX(bmIndex)+1 AS 'index' FROM `bookmarks` WHERE `bmParentID` = '$folder';";
			$folderData = db_query($query);
			$query = "UPDATE `bookmarks` SET `bmIndex` = ".$folderData[0]['index'].", `bmParentID` = '$folder', `bmAction` = 2, `bmAdded` = '".round(microtime(true) * 1000)."' WHERE `bmID` = '$id' AND `userID` = ".$userData['userID'].";";
			$count = db_query($query);
			($count > 0) ? die(true) : die(false);
			break;
		case "arename":
			$client = filter_var($_POST['cido'], FILTER_SANITIZE_STRING);
			$name = filter_var($_POST['nname'], FILTER_SANITIZE_STRING);
			e_log(8,"Rename client $client to $name");
			$query = "UPDATE `clients` SET `cname` = '".$name."' WHERE `uid` = ".$userData['userID']." AND `cid` = '".$client."';";
			$count = db_query($query);
			($count > 0) ? die(bClientlist($userData['userID'])) : die(false);
			break;
		case "adel":
			$client = filter_var($_POST['cido'], FILTER_SANITIZE_STRING);
			e_log(8,"Delete client $client");
			$query = "DELETE FROM `clients` WHERE `uid` = ".$userData['userID']." AND `cid` = '$client';";
			$count = db_query($query);
			($count > 0) ? die(bClientlist($userData['userID'])) : die(false);
			break;
		case "cmail":
			e_log(8,"Change e-mail for ".$userData['userName']);
			$nmail = filter_var($_POST['mail'],FILTER_SANITIZE_EMAIL);
			if(filter_var($nmail, FILTER_VALIDATE_EMAIL)) {
				$query = "UPDATE `users` SET `userMail` = '$nmail' WHERE `userID` = ".$userData['userID'].";";
				die(json_encode(db_query($query)));
			} else {
				e_log(1,"No valid E-Mail. Stop changing E-Mail");
				die(json_encode("No valid mail address. Mail not changed."));
			}
			die();
			break;
		case "muedt":
			if($userData['userType'] < 2) {
				e_log(1,"Stop userchange, no sufficent privileges.");
				die();
			}
			$del = false;
			$headers = "From: SyncMarks <$sender>";
			$url = $_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'];
			$variant = filter_var($_POST['type'], FILTER_VALIDATE_INT);
			$password = gpwd(16);
			$userLevel = filter_var($_POST['userLevel'], FILTER_VALIDATE_INT);
			$user = filter_var($_POST['nuser'], FILTER_SANITIZE_STRING);

			switch($variant) {
				case 1:
					$pwd = password_hash($password,PASSWORD_DEFAULT);
					e_log(8,"Adding new user $user");
					$query = "INSERT INTO `users` (`userName`,`userMail`,`userType`,`userHash`) VALUES ('$user', '$user', '$userLevel', '$pwd')";
					$nuid = db_query($query);
					if($nuid > 0) {
						if(filter_var($user, FILTER_VALIDATE_EMAIL)) {
							$response = $nuid;
							$message = "Hello,\r\na new account with the following credentials is created and stored encrypted on for SyncMarks:\r\nUsername: $user\r\nPassword: $password\r\n\r\nYou can login at $url";
							if(!mail ($user, "Account created",$message,$headers)) {
								e_log(1,"Error sending data for created user account to user");
								$response = "User created successful, E-Mail could not send";
							}
						} else {
							$response = $nuid;
						}
						$bmAdded = round(microtime(true) * 1000);
						$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('unfiled_____', 'root________', 0, 'Other Bookmarks', 'folder', NULL, ".$bmAdded.", $nuid)";
						db_query($query);
						$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('".unique_code(12)."', 'unfiled_____', 0, 'GitHub Repository', 'bookmark', 'https://github.com/Offerel', ".$bmAdded.", $nuid)";
						db_query($query);
					} else {
						$response = "User creation failed";
					}
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
					die(json_encode($response));
					break;
				case 3:
					e_log(8,"Delete user $user");
					$uID = filter_var($_POST['userSelect'], FILTER_VALIDATE_INT);
					$query = "DELETE FROM users WHERE userID = $uID;";
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
					die(json_encode($response));
					break;
				default:
					$message = "Unknown action for managing users";
					e_log(1,$message);
					die($message);
			}
			break;
		case "mlog":
			e_log(8,"Try to show logfile");
			if($userData['userType'] > 1) {
				die(file_get_contents($logfile));
			} else {
				$message = "Not allowed to read server logfile.";
				e_log(2,$message);
				die($message);
			}
			break;
		case "mclear":
			e_log(8,"Clear logfile");
			if($userData['userType'] > 1) file_put_contents($logfile,"");
			die();
			break;
		case "madd":
			$bmParentID = filter_var($_POST['folder'], FILTER_SANITIZE_STRING);
			$bmURL = validate_url(trim($_POST['url']));
			e_log(8,"Try to add manually new bookmark ".$bmURL);
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
				$message = "Titel is missing, add bookmark failed";
				e_log(1,$message);
				die($message);
			} else {
				$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('".$bmID."', '".$bmParentID."', ".$bmIndex.", '".$bmTitle."', 'bookmark', '".$bmURL."', ".$bmAdded.", ".$userID.")";
				db_query($query);
			}
			if(!isset($_POST['rc'])) {
				e_log(8,"Manually added bookmark.");
				die(bmTree($userData));
			} else {
				die(e_log(8,"Roundcube added bookmark."));
			}
			break;
		case "mdel":
			$bmID = filter_var($_POST['id'], FILTER_SANITIZE_STRING);
			$delMark = delMark($bmID);
			if($delMark != 0) {
				if(!isset($_POST['rc'])) {
					e_log(8,"Deleted bookmark $bmID");
					die();
				} else {
					die(e_log(8,"Bookmark $bmID deleted by Roundcube"));
				}
			} else {
				die(e_log(2,"There was an problem removing the bookmark, please check the logfile"));
			}
			break;
		case "pupdate":
			e_log(8,"Userchange: Updating user password started");
			$opassword = filter_var($_POST['opassword'], FILTER_SANITIZE_STRING);
			$npassword = filter_var($_POST['npassword'], FILTER_SANITIZE_STRING);
			$cpassword = filter_var($_POST['cpassword'], FILTER_SANITIZE_STRING);

			if($opassword != "" && $npassword !="" && $cpassword !="") {
				e_log(8,"Userchange: Data complete entered");
				if(password_verify($opassword,$userData['userHash'])) {
					e_log(8,"Userchange: Verify original password");
					if($npassword === $cpassword) {
						e_log(8,"Userchange: New and confirmed password");
						if($npassword != $opassword) {
							$password = password_hash($npassword,PASSWORD_DEFAULT);
							$query = "UPDATE `users` SET `userHash`='$password' WHERE `userID`=".$userData['userID'].";";
							db_query($query);
							e_log(8,"Userchange: Password changed");
						} else {
							e_log(2,"Userchange: Old and new password identical, user not changed");
						}
					}
				} else {
					e_log(2,"Userchange: Old password missmatch");
				}
			} else {
				e_log(2,"Userchange: Data missing, process failed");
			}

			unset($_SESSION['sauth']);
			$_SESSION['fauth'] = true;
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

			if(password_verify($password,$userData['userHash'])) {
				$token = edcrpt('en', $ptoken);
				$device = edcrpt('en', $pdevice);
				$pbEnable = filter_var($pbe,FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
		
				$oOptionsA = json_decode($userData['uOptions'],true);
				$oOptionsA['pAPI'] = $token;
				$oOptionsA['pDevice'] = $device;
				$oOptionsA['pbEnable'] = $pbEnable;
		
				$query = "UPDATE `users` SET `uOptions`='".json_encode($oOptionsA)."' WHERE `userID`=".$userData['userID'].";";
				$count = db_query($query);
				($count === 1) ? e_log(8,"Option saved") : e_log(9,"Error, saving option");
				header("location:".$_SERVER['PHP_SELF']);
				die();
			}
			else {
				e_log(1,"Password missmatch. Pushbullet not updated.");
				die("Password missmatch. Pushbullet not updated.");
			}
			die();
			break;
		case "uupdate":
			e_log(8,"Userchange: Updating user name started");
			$opassword = filter_var($_POST['opassword'], FILTER_SANITIZE_STRING);
			$username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);

			if($opassword != "") {
				e_log(8,"Userchange: Data complete entered");
				if(password_verify($opassword,$userData['userHash'])) {
					e_log(8,"Userchange: Verify original password");
					$query = "UPDATE `users` SET `userName`='$username' WHERE `userID`=".$userData['userID'].";";
					db_query($query);
					e_log(8,"Userchange: Username changed");
				}
				else {
					e_log(2,"Userchange: Failed to verify original password");
				}
			}
			else {
				e_log(2,"Userchange: Data missing");
			}
			unset($_SESSION['sauth']);
			$_SESSION['fauth'] = true;
			e_log(8,"User logged out");
			echo htmlHeader();
			echo "<div id='loginbody'>
				<div id='loginform'>
					<div id='loginformh'>Logout successful</div>
					<div id='loginformt'>User logged out. <a href='".$_SERVER['PHP_SELF']."'>Login</a> again</div>
				</div>
			</div>";
			echo htmlFooter();
			die();
			break;
		case "fexport":
			$format = filter_var($_POST['type'], FILTER_SANITIZE_STRING);
			switch($format) {
				case "html":
					e_log(2,"Exporting in html format for download");
					die(html_export($userData));
					break;
				default:
					die(e_log(2,"Unknown export format, exit process"));
			}
			exit;
			break;
		case "checkdups":
			e_log(8,"Checking for duplicated bookmarks by url");
			$query = "SELECT `bmID`, `bmTitle`, `bmURL` FROM `bookmarks` WHERE `userID` = ".$userData['userID']." AND `bmAction` ISNULL OR `bmAction` = 2 GROUP BY `bmURL` HAVING COUNT(`bmURL`) > 1;";
			$dubData = db_query($query);
			foreach($dubData as $key => $dub) {
				$query = "SELECT `bmID`, `bmParentID`, `bmTitle`, `bmAdded` FROM `bookmarks` WHERE `bmURL` = '".$dub['bmURL']."' AND `userID` = ".$userData['userID']." AND `bmAction` ISNULL OR `bmAction` = 2 ORDER BY `bmParentID`, `bmIndex`;";
				$subData = db_query($query);
				foreach($subData as $index => $entry) {
					$subData[$index]['fway'] = fWay($entry['bmParentID'], $userData['userID'],'');
				}
				$dubData[$key]['subs'] = $subData;
			}
			die(json_encode($dubData));
			break;
		case "logout":
			e_log(8,"Logout user ".$_SESSION['sauth']);
			unset($_SESSION['sauth']);
			$_SESSION['fauth'] = true;
			e_log(8,"User logged out");
			echo htmlHeader();
			echo "<div id='loginbody'>
				<div id='loginform'>
					<div id='loginformh'>Logout successful</div>
					<div id='loginformt'>User logged out. <a href='".$_SERVER['PHP_SELF']."'>Login</a> again</div>
				</div>
			</div>";
			echo htmlFooter();
			exit;
			break;
		case "maddon":
			$rResponse['bookmarks'] = showBookmarks($userData, 1);
			$rResponse['folders'] = getUserFolders($userData['userID']);
			die(json_encode($rResponse));
			break;
		case "getUsers":
			if($userData['userType'] == 2) {
				$query = "SELECT `userID`, `userName`, `userType` FROM `users` ORDER BY `userName`;";
				$uData = db_query($query);
				die(json_encode($uData));
			} else {
				die(json_encode('Editing users not allowed'));
			}
			break;
		default:
			die(json_encode("Unknown Action"));
	}
	exit;
}

if(isset($_GET['link'])) {
	$url = validate_url($_GET["link"]);
	e_log(9,"Bookmarklet URL: " . $url);

	if(!empty($_GET["title"])) {
		$title = $_GET["title"];
	} else {
		$title = getSiteTitle($url);
	}

	$bookmark['url'] = $url;
	$bookmark['nfolder'] = 'unfiled_____';
	$bookmark['title'] = $title;
	$bookmark['id'] = unique_code(12);
	$bookmark['type'] = 'bookmark';
	$bookmark['added'] = round(microtime(true) * 1000);
	
	$options = json_decode($userData['uOptions'],true);
	if(strlen($options['pAPI']) > 1 && strlen($options['pDevice']) > 1 && $options['pbEnable'] == "1") {
		pushlink($title,$url,$userData);
	} else {
		e_log(9,"Cant send push, missing data. Please check options");
	}
	
	$res = addBookmark($userData, $bookmark);
	if($res == 1) {
		if(isset($_GET['client']) && $_GET['client'] == 'Android') {
			echo("URL is added successfully.");
		} else {
			echo "<script>window.onload = function() { window.close();}</script>";
		}
	} else {
		echo $res;
	}
	die();
}

echo htmlHeader();
echo htmlForms($userData);
echo showBookmarks($userData, 2);
echo htmlFooter();

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
	global $userData;
	$count = 0;
	e_log(8,"Delete bookmark '$bmID'");
	$query = "UPDATE `bookmarks` SET `bmAction`= 1, `bmAdded`= '".round(microtime(true) * 1000)."' WHERE `bmID` = '$bmID' AND `userID` = ".$userData['userID'].";";
	$count = db_query($query);

	$query = "SELECT `bmParentID`, `bmIndex`, `bmURL` FROM `bookmarks` WHERE `bmID` = '$bmID' AND `userID` = ".$userData['userID'].";";
	$dData = db_query($query)[0];

	$query = "SELECT * FROM `bookmarks` WHERE `bmParentID` = '".$dData['bmParentID']."' AND `userID` = ".$userData['userID']." AND `bmIndex` > ".$dData['bmIndex']." ORDER BY bmIndex;";
	$sData = db_query($query);
	
	foreach ($sData as &$sMark) {
		$nIndex = $sMark['bmIndex'] - 1;
		$query = "UPDATE `bookmarks` SET `bmIndex`= $nIndex WHERE `bmID` = '".$sMark['bmID']."' AND `userID` = ".$userData['userID'].";";
		$count = db_query($query);
	}

	if(!isset($dData['bmURL'])) {
		$query = "DELETE FROM `bookmarks` WHERE `bmParentID` = '$bmID' AND `userID` = ".$userData['userID'].";";
		db_query($query);
	}

	return $count;
}

function cfolder($ctime,$fname,$fbid,$ud) {
	e_log(8,"Request to create folder $fname");
	$query = "SELECT `bmParentID`  FROM `bookmarks` WHERE `bmID` = '$fbid' AND `userID` = ".$ud['userID'];
	$pdata = db_query($query);
	$res = '';
	$parentid = $pdata[0]['bmParentID'];

	if(count($pdata) == 1) {
		e_log(8,"Try to get index folder");
		$query = "SELECT MAX(`bmIndex`)+1 as nIndex FROM `bookmarks` WHERE `bmParentID` = '$parentid' AND `userID` = ".$ud['userID'];
		$idata = db_query($query);

		if(count($idata) == 1) {
			e_log(8,"Add new folder to database");
			$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmAdded`,`userID`) VALUES ('".unique_code(12)."', '$parentid', ".$idata[0]['nIndex'].", '$fname', 'folder', $ctime, ".$ud["userID"].")";
			if(db_query($query) < 1)
				$res = "Adding folder failed.";
			else {
				$res = 1;
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

function pushlink($title,$url,$userdata) {
	$pddata = json_decode($userdata['uOptions'],true);
	$token = edcrpt('de', $pddata['pAPI']);
	$device = edcrpt('de', $pddata['pDevice']);
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
	global $enckey, $enchash;
    $output = false;
    $encrypt_method = "AES-256-CBC";
    $key = hash('sha256', $enckey);
    $iv = substr(hash('sha256', $enchash), 0, 16);

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

function html_export($userData) {
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

	$umarks = makeHTMLExport(getBookmarks($userData));
	do {
		$start = strpos($umarks,"%ID");
		$end = strpos($umarks,"\n",$start);
		$len = $end - $start;
		$umarks = substr_replace($umarks, "", $start, $len);
	} while (strpos($umarks,"%ID") > 0);

	$content.="$umarks\r\n</DL><p>";

	return $content;
}

function editFolder($bm, $ud) {
	e_log(8,"Edit folder request, try to find the folder...");
	$query = "SELECT * FROM `bookmarks` WHERE `bmIndex` >= ".$bm['index']." AND `bmType` = 'folder' AND `bmParentID` = '".$bm['parentId']."' AND `userID` = ".$ud['userID'].";";
	$fData = db_query($query);

	if(count($fData) == 1) {
		e_log(8,"Unique folder found, edit the folder");
		$query = "UPDATE `bookmarks` SET `bmAction` = NULL, `bmTitle` = '".$bm['title']."' WHERE `bmID` = '".$fData[0]['bmID']."' AND userID = ".$ud["userID"].";";
		$count = db_query($query);
	} else {
		e_log(8,"Folder not found, chancel operation and send error to client.");
		$count = 0;
	}
	return $count;
}

function editBookmark($bm, $ud) {
	e_log(8,"Edit bookmark request, try to find the bookmark first by url...");
	$query = "SELECT `bmID`  FROM `bookmarks` WHERE `bmURL` = '".$bm['url']."' AND `userID` = ".$ud['userID'];
	$bmData = db_query($query);

	if(count($bmData) == 1) {
		e_log(8,"Unique entry found, edit the title of the bookmark.");
		$query = "UPDATE `bookmarks` SET `bmTitle` = '".$bm['title']."' WHERE `bmID` = '".$bmData[0]['bmID']."' AND userID = ".$ud["userID"].";";
		$count = db_query($query);
	} else {
		e_log(8,"No unique bookmark found, try to find now by title...");
		$query = "SELECT `bmID`  FROM `bookmarks` WHERE `bmTitle` = '".$bm['title']."' AND `userID` = ".$ud['userID'];
		$bmData = db_query($query);

		if(count($bmData) == 1) {
			e_log(8,"Unique entry found, edit the url of the bookmark.");
			$query = "UPDATE `bookmarks` SET `bmURL` = '".$bm['url']."' WHERE `bmID` = '".$bmData[0]['bmID']."' AND userID = ".$ud["userID"].";";
			$count = db_query($query);
		} else {
			e_log(8,"No Unique entry found, chancel operation and send error to client.");
			$count = 0;
		}
	}

	return $count;
}

function moveBookmark($ud, $bm) {
	e_log(8,"Bookmark seems to be moved, checking current folder data");
	$query = "SELECT `bmID`, `bmParentID` FROM `bookmarks` WHERE `bmType` = 'folder' AND `bmTitle` = '".$bm['nfolder']."' AND `userID` = ".$ud['userID'].";";
	$folderData = db_query($query)[0];
	
	if(is_null($folderData['bmID'])) {
		e_log(2,"Folder not found, can`t move bookmark.");
		return "Folder not found, bookmark not moved.";
	}

	if(array_key_exists("url", $bm)) {
		e_log(8,"Checking bookmark data before moving it");
		$query = "SELECT * FROM `bookmarks` WHERE `userID`= ".$ud["userID"]." AND `bmURL` = '".$bm["url"]."';";
		$oldData = db_query($query)[0];
		
		if (!empty($folderData) && !empty($oldData)) {
			if(($folderData['bmParentID'] != $oldData['bmParentID']) || ($oldData['bmIndex'] != $bm['index'])) {
				e_log(8,"Folder or Position changed, moving bookmark");
				$query = "DELETE FROM `bookmarks` WHERE `bmID` = '".$oldData["bmID"]."'";
				db_query($query);
				e_log(8,"Re-Add bookmark on new position");
				$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`,`bmAction`) VALUES ('".$oldData["bmID"]."', '".$bm['folder']."', ".$bm['index'].", '".$oldData['bmTitle']."', '".$oldData['bmType']."', '".$oldData['bmURL']."', ".$oldData['bmAdded'].", ".$ud["userID"].",2)";
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
		e_log(8,"url key not found");
	}
}

function addFolder($ud, $bm) {
	$count = 0;
	e_log(8,"Try to find if this folder exists already");
	$query = "SELECT COUNT(*) AS bmCount, bmAction, bmID  FROM `bookmarks` WHERE `bmTitle` = '".$bm['title']."' AND `bmParentID` = '".$bm['folder']."' AND `userID` = ".$ud['userID'].";";
	$res = db_query($query)[0];

	if($res["bmAction"]) {
		e_log(8,"Remove temporary entry ".$res["bmID"]);
		$query = "DELETE FROM `bookmarks` WHERE `bmID` = '".$res["bmID"]."' AND `userID` = ".$ud['userID'].";";
		$count = db_query($query);
	}

	if($res["bmCount"] > 0 && $count != 1) {
		e_log(8,"Folder not added, it exists already for this user, exit request");
		return false;
	}
	
	e_log(8,"Get folder data for adding folder");
	$query = "SELECT IFNULL(MAX(`bmIndex`),-1) + 1 AS `nindex`, `bmParentId` FROM `bookmarks` WHERE `bmParentId` = '".$bm['folder']."' AND `userID` = ".$ud['userID'].";";
	$folderData = db_query($query);
	
	if (!empty($folderData)) {
		$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmAdded`,`userID`) VALUES ('".$bm['id']."', '".$bm['folder']."', ".$folderData[0]['nindex'].", '".$bm['title']."', '".$bm['type']."', ".$bm['added'].", ".$ud["userID"].")";
		db_query($query);
		return true;
	}
	else {
		e_log(1,"Couldn't add folder");
		return false;
	}
}

function addBookmark($ud, $bm) { 
	e_log(8,"Check if bookmark already exists for user.");
	$query = "SELECT `bmID`, COUNT(*) AS `bmcount`, MAX(`bmAction`) AS `bmaction` FROM `bookmarks` WHERE `bmUrl` = '".$bm['url']."' AND `bmParentID` = '".$bm["nfolder"]."' AND `userID` = ".$ud["userID"].";";
	$bmExistData = db_query($query);
	if($bmExistData[0]["bmcount"] > 0) {
		if($bmExistData[0]["bmaction"] == 1) {
			e_log(8,"Undelete removed bookmark.");
			$query = "UPDATE `bookmarks` SET `bmAction` = NULL WHERE `bmID` = '".$bmExistData[0]["bmID"]."' AND userID = ".$ud["userID"].";";
			$count = db_query($query);
			$message = "Bookmark not added at server, it already exists for this user, bookmark undeleted now.";
			e_log(8,$message);
			return $count;
		}
		else {
			$message = "Bookmark not added at server, it already exists";
			e_log(8,$message);
			return $message;
		}
	}
	e_log(8,"Get folder for adding bookmark");
	$query = "SELECT `bmID` FROM `bookmarks` WHERE `bmID` = '".$bm["folder"]."' AND `userID` = ".$ud['userID']." UNION ALL SELECT 'unfiled_____' WHERE NOT EXISTS (SELECT 1 FROM `bookmarks` WHERE `bmID` = '".$bm["folder"]."');";
	$folderID = db_query($query)[0]['bmID'];

	e_log(8,"Get new index for bookmark");
	$query = "SELECT IFNULL(MAX(`bmIndex`),-1) + 1 AS `nindex` FROM `bookmarks` WHERE `userID` = ".$ud['userID']." AND `bmParentID` = '$folderID';";
	$nindex = db_query($query)[0]['nindex'];
	
	$title = htmlspecialchars($bm['title'],ENT_QUOTES,'UTF-8');
	e_log(8,"Add bookmark '".$title."'");
	$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('".$bm['id']."', '$folderID', $nindex, '".$title."', '".$bm['type']."', '".$bm['url']."', ".$bm['added'].", ".$ud["userID"].");";
	if(db_query($query) < 1 ) {
		$message = "Adding bookmark failed";
		e_log(1,$message);
		return $message;
	} else {
		return 1;
	}
}

function getChanges($cl, $ct, $ud, $time) {
	$uid = $ud["userID"];
	e_log(8,"Browser startup sync started, get client data");
	$query = "SELECT `lastseen` FROM `clients` WHERE `cid` = '".$cl."' AND `uid` = $uid AND `ctype` = '".$ct."';";
	$clientData = db_query($query)[0];

	if($clientData) {
		$lastseen = $clientData["lastseen"];
		e_log(8,"Get changed bookmarks for client $cl");
		$query = "SELECT a.`bmParentID` as fdID, (SELECT `bmTitle` FROM `bookmarks` WHERE `bmID` = a.`bmParentID`) as fdName, (SELECT `bmIndex` FROM `bookmarks` WHERE `bmID` = a.`bmParentID`) as fdIndex, `bmID`, `bmIndex`, `bmTitle`, `bmType`, `bmURL`, `bmAdded`, `bmModified`, `bmAction` FROM `bookmarks` a WHERE (bmAdded >= $lastseen AND userID = $uid) OR (bmAction = 1 AND bmAdded >= $lastseen AND userID = $uid);";
		$bookmarkData = db_query($query);
		foreach($bookmarkData as $key => $entry) {
			$bookmarkData[$key]['bmTitle'] = html_entity_decode($entry['bmTitle'],ENT_QUOTES,'UTF-8'); 
		}
	}
	else {
		e_log(2,"Client not found in database, registering now");
		updateClient($cl, $ct, $ud, $time, true);
		return "New client registered for user.";
	}

	if (!empty($bookmarkData)) {
		global $cexpjson;
		updateClient($cl, $ct, $ud, $time, true);
		e_log(8,"Try to find bookmarks, which could be completely deleted");
		$query = "SELECT `bmID` FROM `bookmarks` WHERE `bmAdded` <= (SELECT MIN(`lastseen`) FROM `clients` WHERE `uid` = $uid AND `lastseen` > 1) AND `bmAction` = 1;";
		$removeMarks = db_query($query);

		if (!empty($removeMarks)) {
			e_log(8,count($removeMarks)." are deletable from the database");
			foreach($removeMarks as $bookmark) {
				$query = "DELETE FROM `bookmarks` WHERE `bmID` = '".$bookmark["bmID"]."';";
				db_query($query);
			}
			e_log(8,"Try to compacting database");
			db_query("VACUUM");
		}
		else {
			e_log(8,"No bookmarks found to delete from the database");
		}

		$filename = "changes_".substr($cl,0,8)."_".time().".json";
		if($cexpjson && $loglevel = 9) file_put_contents($filename,json_encode($bookmarkData),true);

		e_log(8,"Found ".count($bookmarkData)." changes. Sending them to the client");
		return $bookmarkData;
	}
	else {
		e_log(8,"No bookmarks changed since last sync");
		return "No bookmarks added, removed or changed since the client was last seen.";
	}
}

function updateClient($cl, $ct, $ud, $time, $sync = false) {
	$uid = $ud["userID"];
	$query = "SELECT * FROM `clients` WHERE `cid` = '".$cl."' AND uid = ".$uid.";";
	$clientData = db_query($query);

	if (!empty($clientData) && $sync) {
		e_log(8,"Updating lastlogin for client $cl.");
		$query = "UPDATE `clients` SET `lastseen`= '".$time."' WHERE `cid` = '".$cl."';";
		db_query($query);
	} else if(empty($clientData)) {
		e_log(8,"New client detected. Register client $cl for user ".$ud["userName"]);
		$query = "INSERT INTO `clients` (`cid`,`cname`,`ctype`,`uid`,`lastseen`) VALUES ('".$cl."','".$cl."', '".$ct."', ".$uid.", '0')";
		db_query($query);
	}
	
	return "Client updated.";
}

function bmTree($userData) {
	e_log(8,"Build HTML tree from bookmarks");
	$bmTree = makeHTMLTree(getBookmarks($userData));
	
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
	e_log(8,"Get titel from site ".$url);
	$src = file_get_contents($url);
	if(strlen($src) > 0) {
		preg_match("/\<title\>(.*)\<\/title\>/i",$src,$title_arr);
		$title = (strlen($title_arr[1]) > 0) ? strval($title_arr[1]) : 'unknown';
		e_log(8,"Titel for site is '$title'");
		return  htmlspecialchars(mb_convert_encoding($title,"UTF-8"),ENT_QUOTES,'UTF-8');
	} else {
		return "unknown";
	}
}

function getUserdata() {
	$query = "SELECT * FROM `users` WHERE `userName`='".$_SESSION['sauth']."'";
	$userData = db_query($query);
	if (!empty($userData)) {
		return $userData[0];
	} else {
		unset($_SESSION['fauth']);
	}
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
	if(isset($_SESSION['sauth'])) $user = $_SESSION['sauth'];
	$line = "[".date("d-M-Y H:i:s")."] - [$mode] - $user - ".$_SERVER['REMOTE_ADDR']." - $message\n";

	if($level <= $loglevel) {
		file_put_contents($logfile, $line, FILE_APPEND);
	}
}

function delUsermarks($uid) {
	$query = "DELETE FROM `bookmarks` WHERE `UserID`=".$uid;
	db_query($query);
}

function minFile($infile) {
	$outfile = $infile;
	$infile = pathinfo($infile);
	$minfile = $infile['filename'].'.min.'.$infile['extension'];
	$outfile = (file_exists($minfile)) ? $minfile : $outfile;
	return $outfile;
}

function htmlHeader() {
	$htmlHeader = "<!DOCTYPE html>
		<html lang='en'>
			<head>
				<meta name='viewport' content='width=device-width, initial-scale=1'>
				<script src='".minfile("bookmarks.js")."'></script>
				<link type='text/css' rel='stylesheet' href='".minfile("bookmarks.css")."'>
				<!-- <link type='text/css' rel='stylesheet' href='font-awesome/css/font-awesome.min.css'> -->
				<link rel='shortcut icon' type='image/x-icon' href='./images/bookmarks.ico'>
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
		<button>&#8981;</button><input type='search' name='bmsearch' value=''>
		<div id='mprofile'>SyncMarks</div>
	</div>";

	return $htmlHeader;
}

function htmlForms($userData) {
	$version = explode ("\n", file_get_contents('./CHANGELOG.md',NULL,NULL,0,30))[2];
	$version = substr($version,0,strpos($version, " "));
	$clink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	$bookmarklet = "javascript:void function(){window.open('$clink?title='+document.title+'&link='+encodeURIComponent(document.location.href),'bWindow','width=480,height=245',replace=!0)}();";
	$userName = $userData['userName'];
	$userMail = $userData['userMail'];
	$userID = $userData['userID'];
	$userOldLogin = date("d.m.y H:i",$userData['userOldLogin']);
	$admenu = ($userData['userType'] == 2) ? "<hr><li class='menuitem' id='mlog'>Logfile</li><li class='menuitem' id='mngusers'>Users</li>":"";
	$logform = ($userData['userType'] == 2) ? "<div id=\"logfile\"><div id=\"close\"><button id='mclear'>clear</button> <button id='mclose'>&times;</button></div><div id='lfiletext'></div></div>":"";

	$uOptions = json_decode($userData['uOptions'],true);
	$oswitch = ($uOptions['notifications'] == 1) ? " checked":"";
	$oswitch =  "<label class='switch' title='Enable/Disable Notifications'><input id='cnoti' type='checkbox'$oswitch><span class='slider round'></span></label>";

	$pbswitch = ($uOptions['pbEnable'] == 1) ? " checked":"";
	$pbswitch = "<label class='switch' title='Enable/Disable Pushbullet'><input id='pbe' name='pbe' value='1' type='checkbox'$pbswitch><span class='slider round'></span></label>";
	$pAPI = edcrpt('de',json_decode($userData['uOptions'],true)['pAPI']);
	$pDevice = edcrpt('de',json_decode($userData['uOptions'],true)['pDevice']);

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

	$mngclientform = "<div id='mngcform' class='mmenu'>".bClientlist($userID)."</div>";

	$nmessagesform = "
	<div id='nmessagesform' class='mmenu'>
		<div class='tab'>
		<button class='tablinks active' data-val='aNoti'>Active</button>
		<button class='tablinks' data-val='oNoti'>Archived</button>
		$oswitch
		</div>
		<div id='aNoti' class='tabcontent'style='display: block'>
		<div class='NotiTable'>
			<div class='NotiTableBody'>
			".notiList($userID, 1)."
			</div>
		</div>
		</div>
		<div id='oNoti' class='tabcontent' style='display: none'>
		<div class='NotiTable'>
			<div class='NotiTableBody'>
			".notiList($userID, 0)."
			</div>
		</div>
		</div>
	</div>";

	$pbulletform = "
	<div id='pbulletform' class='mbmdialog'>
		<h6>Pushbullet</h6>
		<div class='dialogdescr'>Maintain your API Token and Device ID.</div>
		<form action='".$_SERVER['PHP_SELF']."' method='POST'>$pbswitch
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
		<form action='".$_SERVER['PHP_SELF']."' method='POST'>					
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
		<form action='".$_SERVER['PHP_SELF']."' method='POST'>
			<input placeholder='Username' required type='text' name='username' id='username' autocomplete='username' value='$userName'>
			<input placeholder='Password' required type='password' id='oopassword' name='opassword' autocomplete='current-password' value='' />
			<div class='dbutton'><button class='mdcancel' type='reset' value='Reset'>Cancel</button><button type='submit' name='caction' value='uupdate'>Save</button></div>
		</form>
	</div>";

	$mainmenu = "
	<div id='mainmenu' class='mmenu'>
		<ul>
			<li id='meheader'><span class='appv'><a href='https://github.com/Offerel/SyncMarks-Webapp'>SyncMarks $version</a></span><span class='logo'>&nbsp;</span><span class='text'>$userName<br>Last login: $userOldLogin</span></li>
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

function showBookmarks($userData, $mode) {
	$bmTree = bmTree($userData);
	$htmlData = "<div id='bookmarks'>$bmTree</div>";
	if($mode === 2) $htmlData.= "<div id='hmarks' style='display: none'>$bmTree</div>";
	return $htmlData;
}

function bClientlist($uid) {
	$query = "SELECT * FROM `clients` WHERE `uid` = $uid ORDER BY `lastseen` DESC;";
	$clientData = db_query($query);
	
	$clientList = "<ul>";
	foreach($clientData as $key => $client) {
		$cname = $client['cid'];
		if(isset($client['cname'])) $cname = $client['cname'];
		$timestamp = $client['lastseen'] / 1000;
		$lastseen = (date('D, d. M. Y H:i', $timestamp));
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
		if($aNoti['client'] == "0")
			$cl = "All";
		else
			$cl = $aNoti['client'];
		$notiList.= "<div class='NotiTableRow'>
					<div class='NotiTableCell'>
						<span><a class='link' title='".$aNoti['title']."' href='".$aNoti['message']."'>".$aNoti['title']."</a></span>
						<span class='nlink'>".$aNoti['message']."</span>
						<span class='ndate'>".date("d.m.Y H:i",$aNoti['publish_date'])." | $cl</span>
					</div>
					<div class='NotiTableCell'><a class='fa fa-trash' data-message='".$aNoti['id']."' href='#'></a></div>
				</div>";
	}
	return $notiList;
}

function htmlFooter() {
	$htmlFooter = "<script src='bookmarksf.js'></script></body></html>";
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
			$title = html_entity_decode($bm['bmTitle'],ENT_QUOTES,'UTF-8'); 
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

function importMarks($bookmarks,$uid) {
	e_log(8,"Starting import browser bookmarks");
	foreach ($bookmarks as $bookmark) {
		$title = htmlspecialchars($bookmark['bmTitle'],ENT_QUOTES,'UTF-8');
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
	
	$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`bmModified`,`userID`) VALUES (?,?,?,?,?,?,?,?,?)";
	$response = db_query($query,$data);

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

function getBookmarks($userData) {
	$query = "SELECT * FROM `bookmarks` WHERE `bmAction` IS NULL AND `userID` = ".$userData['userID'].";";
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

function checkLogin($realm) {
	e_log(8,"Check login");
	if(count($_GET) != 0 || count($_POST) != 0) {
		unset($_SESSION['cr']);
		if(isset($_POST['login']) && isset($_POST['username']) && isset($_POST['password'])) {
			$user = $_POST['username'];
			$pw = $_POST['password'];
			$_SESSION['cr'] = true;
		}

		if(isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
			$user = $_SERVER['PHP_AUTH_USER'];
			$pw = $_SERVER['PHP_AUTH_PW'];
			$_SESSION['cr'] = true;
		}

		if (!isset($_SESSION['cr'])) {
			header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");
			header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
			header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
			header("Cache-Control: post-check=0, pre-check=0", false);
			header("Pragma: no-cache");
			header('WWW-Authenticate: Basic realm="'.$realm.'", charset="UTF-8"');
			http_response_code(401);
			unset($_SESSION['fauth']);
			echo htmlHeader();
			echo "<div id='loginbody'>
				<div id='loginform'>
					<div id='loginformh'>Access denied</div>
					<div id='loginformt'>Access denied. You must <a href='".$_SERVER['PHP_SELF']."'>login</a> to use this tool.</div>
				</div>
			</div>";
			echo htmlFooter();
			exit;
		} else {
			if(isset($_SESSION['cr'])) {
				$query = "SELECT * FROM `users` WHERE `userName`= '$user';";
				$udata = db_query($query);
				if(count($udata) == 1) {
					if(password_verify($pw, $udata[0]['userHash'])) {
						$seid = session_id();
						$aTime = time();
						$oTime = $udata[0]['userLastLogin'];
						$uid = $udata[0]['userID'];
						$_SESSION['sauth'] = $udata[0]['userName'];
						unset($_SESSION['fauth']);
						e_log(8,"Login successfully");
						if($seid != $udata[0]['sessionID']) {
							e_log(8,"Save session to database.");
							$query = "UPDATE `users` SET `userLastLogin` = $aTime, `sessionID` = '$seid', `userOldLogin` = '$oTime' WHERE `userID` = $uid;";
							db_query($query);
						}
					} else {
						session_destroy();
						unset($_SESSION['sauth']);
						$_SESSION['fauth'] = true;
						header('WWW-Authenticate: Basic realm="'.$realm.'", charset="UTF-8"');
						http_response_code(401);
						e_log(8,"Login failed. Password missmatch");
						echo htmlHeader();
						$lform = "<div id='loginbody'>
							<div id='loginform'>
								<div id='loginformh'>Login failed</div>
								<div id='loginformt'>You must <a href='".$_SERVER['PHP_SELF']."'>authenticate</a> to use this tool.";
								if(filter_var($udata[0]['userMail'], FILTER_VALIDATE_EMAIL)) $lform.= "<br />Forgot your password? You can try to <a data-reset='$user' id='preset' href='#'>reset</a> it.";
						$lform.= "</div></div>
						</div>";
						echo $lform;
						echo htmlFooter();
						exit;
					}
				} else {
					unset($_SESSION['sauth']);
					$_SESSION['fauth'] = true;
					session_destroy();
					if(!isset($_POST['login'])) {
						header('WWW-Authenticate: Basic realm="'.$realm.'", charset="UTF-8"');
						http_response_code(401);
					} else {
						echo htmlHeader();
						echo "<div id='loginbody'>
								<div id='loginform'>
									<div id='loginformh'>Login failed</div>
									<div id='loginformt'>You must <a href='".$_SERVER['PHP_SELF']."'>authenticate</a> to use this tool.</div>
								</div>
							</div>";
						echo htmlFooter();
					}
					e_log(8,"Login failed. Credential missmatch");
					exit;
				}
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
					<button name='login' value='login'>Login</button>
				</div>
			</div>
			</form>
		</div>";
		echo htmlFooter();
		exit;
	}

}

function db_query($query, $data=null) {
	global $database;
	e_log(9,$query);
	$options = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_CASE => PDO::CASE_NATURAL,
		PDO::ATTR_ORACLE_NULLS => PDO::NULL_EMPTY_STRING
	];
	try {
		$db = new PDO('sqlite:'.$database, null, null, $options);
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
			$statement = $db->prepare($query);
			try {
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

function checkDB($database,$suser,$spwd) {
	if(!file_exists($database)) {
		if(!file_exists(dirname($database))) {
			if(!mkdir(dirname($database),0777,true)) {
				$message = "Directory for database couldn't created, please check privileges";
				e_log(1,$message);
				die($message);
			} else {
				e_log(8,"Directory for database created, initialize database now");
			}
		}
		e_log(8,"Initialise new database");
		db_query(file_get_contents("./sql/db_init.sql"));

		$bmAdded = round(microtime(true) * 1000);
		$userPWD = password_hash($spwd,PASSWORD_DEFAULT);
		$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('unfiled_____', 'root________', 0, 'Other Bookmarks', 'folder', NULL, ".$bmAdded.", 1)";
		db_query($query);
		$query = "INSERT INTO `bookmarks` (`bmID`,`bmParentID`,`bmIndex`,`bmTitle`,`bmType`,`bmURL`,`bmAdded`,`userID`) VALUES ('".unique_code(12)."', 'unfiled_____', 0, 'GitHub Repository', 'bookmark', 'https://github.com/Offerel', ".$bmAdded.", 1)";
		db_query($query);
		$query = "INSERT INTO `users` (userName,userType,userHash) VALUES ('$suser',2,'$userPWD');";
		db_query($query);
		file_put_contents("state",$newdate,true);
	} else {
		$vInfo = db_query("SELECT * FROM `system` ORDER BY `updated` DESC LIMIT 1;")[0];
		$olddate = $vInfo['updated'];
		$newdate = filemtime(__FILE__);

		if($olddate != $newdate) {
			e_log(8,"SyncMarks update dedected. Check database version");
			if($vInfo['db_version'] < 4) {
				e_log(8,"Database update needed. Starting DB update...");
				db_query(file_get_contents("./sql/db_update_4.sql"));
				db_query("UPDATE `system` SET `updated` = '$newdate' WHERE `updated` = '$olddate';");
			} else {
				e_log(8,"Database up to date. Write new state to DB");
				db_query("UPDATE `system` SET `updated` = '$newdate' WHERE `updated` = '$olddate';");
			}
		}
		
	}
}
?>