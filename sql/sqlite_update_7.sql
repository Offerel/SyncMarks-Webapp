PRAGMA foreign_keys = OFF;
ALTER TABLE `users` RENAME TO `users_old`;
-- Change users table
CREATE TABLE "users" (
	`userID`	INTEGER NOT NULL UNIQUE,
	`userName`	TEXT NOT NULL UNIQUE,
	`userType`	INTEGER NOT NULL,
	`userHash`	TEXT NOT NULL,
	`userLastLogin`	INT(11),
	`sessionID`	VARCHAR(255) UNIQUE,
	`userOldLogin`	INT(11),
	`uOptions`	TEXT,
	`userMail`	VARCHAR(255),
	PRIMARY KEY(`userID`)
);
INSERT INTO `users` SELECT `userID`,`userName`,`userType`,`userHash`,`userLastLogin`,NULL,NULL,NULL,NULL FROM `users_old`;
DROP TABLE `users_old`;

-- Change bookmark table
CREATE TABLE `bookmarks_tmp` (
	`bmID`	TEXT NOT NULL,
	`bmParentID`	TEXT NOT NULL,
	`bmIndex`	INTEGER NOT NULL,
	`bmTitle`	TEXT,
	`bmType`	TEXT NOT NULL,
	`bmURL`	TEXT,
	`bmAdded`	TEXT NOT NULL,
	`bmModified`	TEXT,
	`userID`	INTEGER NOT NULL,
	`bmAction`	INTEGER,
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE
);
INSERT INTO `bookmarks_tmp` SELECT * FROM `bookmarks`;
DROP TABLE `bookmarks`;
ALTER TABLE `bookmarks_tmp` RENAME TO `bookmarks`;

-- Change clients table
CREATE TABLE `clients_tmp` (
	`cid`	TEXT NOT NULL UNIQUE,
	`cname`	TEXT,
	`ctype`	TEXT NOT NULL,
	`uid`	INTEGER NOT NULL,
	`lastseen`	TEXT NOT NULL DEFAULT 0,
	`fs`	INTEGER NOT NULL DEFAULT 0,
	FOREIGN KEY(`uid`) REFERENCES `users`(`userID`) ON DELETE CASCADE,
	PRIMARY KEY(`cid`)
);
INSERT INTO `clients_tmp` SELECT * FROM `clients`;
DROP TABLE `clients`;
ALTER TABLE `clients_tmp` RENAME TO `clients`;

-- CREATE notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
	`id`	INTEGER NOT NULL,
	`title`	varchar(250) NOT NULL,
	`message`	TEXT NOT NULL,
	`ntime`	varchar(250) NOT NULL DEFAULT NULL,
	`client`	TEXT NOT NULL DEFAULT 0,
	`nloop`	INTEGER NOT NULL DEFAULT 1,
	`publish_date`	varchar(250) NOT NULL,
	`userID`	INTEGER NOT NULL,
	PRIMARY KEY(`id`),
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE
);

-- CREATE notifications table
CREATE TABLE IF NOT EXISTS `notifications_tmp` (
	`id`	INTEGER NOT NULL,
	`title`	varchar(250) NOT NULL,
	`message`	TEXT NOT NULL,
	`ntime`	varchar(250) NOT NULL DEFAULT NULL,
	`client`	TEXT NOT NULL DEFAULT 0,
	`nloop`	INTEGER NOT NULL DEFAULT 1,
	`publish_date`	varchar(250) NOT NULL,
	`userID`	INTEGER NOT NULL,
	PRIMARY KEY(`id`),
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE
);

INSERT INTO `notifications_tmp` SELECT * FROM `notifications`;
DROP TABLE IF EXISTS `notifications`;

DROP TRIGGER IF EXISTS `on_delete_set_default`;

ALTER TABLE `notifications_tmp` RENAME TO `notifications`;

-- Add reset table
CREATE TABLE IF NOT EXISTS `reset` (
	`tokenID`	INTEGER NOT NULL UNIQUE,
	`userID`	INTEGER NOT NULL,
	`tokenTime`	VARCHAR(255) NOT NULL,
	`token`	VARCHAR(255) NOT NULL UNIQUE,
	PRIMARY KEY(`tokenID` AUTOINCREMENT)
);

-- Add system table
CREATE TABLE IF NOT EXISTS `system` (
	`app_version`	varchar(10),
	`db_version`	varchar(10),
	`updated`	varchar(250)
);

-- Add tokens table
CREATE TABLE IF NOT EXISTS `auth_token` (
	`tID`	INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
	`userName`	TEXT NOT NULL,
	`pHash`	VARCHAR(255) NOT NULL,
	`tHash`	VARCHAR(255) NOT NULL,
	`exDate`	VARCHAR(255) NOT NULL
);

-- CREATE ctokens table
CREATE TABLE `c_token` (
	`tID`	INTEGER UNIQUE,
	`cid`	TEXT NOT NULL UNIQUE,
	`tHash`	VARCHAR(255) NOT NULL UNIQUE,
	`exDate`	VARCHAR(255) NOT NULL,
	`userID`	INTEGER NOT NULL,
	`cInfo`	TEXT,
	PRIMARY KEY(`tID` AUTOINCREMENT)
);

-- Create index
CREATE INDEX IF NOT EXISTS `i1` ON `bookmarks` (`bmURL`, `bmTitle`);
CREATE INDEX IF NOT EXISTS `i2` ON `users` ( `userID`);
CREATE INDEX IF NOT EXISTS `i3` ON `clients` (`cid`);

-- Create triggers
CREATE TRIGGER IF NOT EXISTS `on_delete_set_default`
	AFTER DELETE ON `clients`
BEGIN
	UPDATE `notifications` SET `client` = 0 WHERE `client` = old.cid;
END;

CREATE TRIGGER IF NOT EXISTS`delete_userclients `
	AFTER DELETE ON `users`
	FOR EACH ROW
BEGIN
	DELETE FROM `clients` WHERE `uid` = OLD.userID;
END;

CREATE TRIGGER IF NOT EXISTS `delete_usermarks`
	AFTER DELETE ON `users`
	FOR EACH ROW
BEGIN
	DELETE FROM `bookmarks` WHERE `userID` = OLD.userID;
END;

CREATE TRIGGER IF NOT EXISTS `delete_usertokens`
AFTER DELETE ON `users`
FOR EACH ROW
BEGIN
DELETE FROM `auth_token` WHERE `userName` = OLD.userName;
END;

CREATE TRIGGER IF NOT EXISTS `update_tokenchange`
UPDATE ON `auth_token`
BEGIN
DELETE FROM `auth_token` WHERE `exDate` < strftime('%s');
END;

CREATE TRIGGER IF NOT EXISTS `update_usertoken`
UPDATE OF `userName`, `userHash`, `userType` ON `users`
BEGIN
DELETE FROM `auth_token` WHERE `userName` = OLD.userName;
END;

CREATE TRIGGER IF NOT EXISTS  `delete_clientokens`
AFTER DELETE ON `clients`
FOR EACH ROW
BEGIN
DELETE FROM `c_token` WHERE `cid` = OLD.cid;
END

DROP TRIGGER IF EXISTS `delete_userreset`;
DROP TRIGGER IF EXISTS `delete_usernotifications`;

INSERT INTO `system` (`app_version`, `db_version`, `updated`) VALUES ('1.6.6', '7', '1646766932');
PRAGMA foreign_keys = ON;
PRAGMA user_version = 7;