PRAGMA foreign_keys = OFF;
-- Change users table
CREATE TABLE "users_tmp" (
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
INSERT INTO `users_tmp` SELECT `userID`,`userName`,`userType`,`userHash`,`userLastLogin`,NULL,NULL,NULL,NULL FROM `users`;
DROP TABLE `users`;
ALTER TABLE `users_tmp` RENAME TO `users`;

-- Change bookmark table
CREATE TABLE `bookmarks_tmp` (
	`bmID`	TEXT NOT NULL,
	`bmParentID`	TEXT,
	`bmIndex`	INTEGER NOT NULL,
	`bmTitle`	TEXT,
	`bmType`	TEXT NOT NULL,
	`bmURL`	TEXT,
	`bmAdded`	TEXT NOT NULL,
	`bmModified`	TEXT,
	`userID`	INTEGER NOT NULL,
	`bmAction`	INTEGER,
	PRIMARY KEY(`bmID`,`userID`),
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE,
	FOREIGN KEY(`bmParentID`,`userID`) REFERENCES "bookmarks"(`bmID`,`userID`) ON DELETE CASCADE
);
INSERT OR IGNORE INTO `bookmarks_tmp` (`bmID`, `bmIndex`, `bmType`, `bmAdded`, `userID`) SELECT 'root________', 0,'folder',0, `userID` FROM users;
INSERT INTO `bookmarks_tmp` SELECT * FROM `bookmarks`;
DROP TABLE `bookmarks`;
ALTER TABLE `bookmarks_tmp` RENAME TO `bookmarks`;

-- Change clients table
CREATE TABLE `clients_tmp` (
	`cid`	TEXT NOT NULL DEFAULT NULL UNIQUE,
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
CREATE TABLE IF NOT EXISTS `notifications_tmp` (
	`id`	INTEGER NOT NULL,
	`title`	varchar(250) NOT NULL,
	`message`	TEXT NOT NULL,
	`ntime`	varchar(250) NOT NULL DEFAULT NULL,
	`client`	TEXT NOT NULL DEFAULT NULL,
	`nloop`	INTEGER NOT NULL DEFAULT 1,
	`publish_date`	varchar(250) NOT NULL,
	`userID`	INTEGER NOT NULL,
	PRIMARY KEY(`id`),
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE,
	FOREIGN KEY(`client`) REFERENCES `clients`(`cid`) ON DELETE SET NULL
);
INSERT INTO `notifications_tmp` SELECT * FROM `notifications`;
DROP TABLE IF EXISTS `notifications`;
ALTER TABLE `notifications_tmp` RENAME TO `notifications`;

-- Add reset table
CREATE TABLE `reset_tmp` (
	`tokenID`	INTEGER NOT NULL UNIQUE,
	`userID`	INTEGER NOT NULL,
	`tokenTime`	VARCHAR(255) NOT NULL,
	`token`	VARCHAR(255) NOT NULL UNIQUE,
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE,
	PRIMARY KEY(`tokenID` AUTOINCREMENT)
);
INSERT INTO `reset_tmp` SELECT * FROM `reset`;
DROP TABLE IF EXISTS `reset`;
ALTER TABLE `reset_tmp` RENAME TO `reset`;

-- Add system table
CREATE TABLE IF NOT EXISTS `system` (
	`app_version`	varchar(10),
	`db_version`	varchar(10),
	`updated`	varchar(250)
);

-- Add tokens table
CREATE TABLE `auth_token_tmp` (
	`tID`	INTEGER UNIQUE,
	`userName`	TEXT NOT NULL,
	`pHash`	VARCHAR(255),
	`tHash`	VARCHAR(255) NOT NULL,
	`exDate`	VARCHAR(255) NOT NULL,
	FOREIGN KEY(`userName`) REFERENCES `users`(`userName`) ON UPDATE CASCADE ON DELETE CASCADE,
	PRIMARY KEY(`tID` AUTOINCREMENT)
);
INSERT INTO `auth_token_tmp` SELECT * FROM `auth_token`;
DROP TABLE IF EXISTS `auth_token`;
ALTER TABLE `auth_token_tmp` RENAME TO `auth_token`;

-- CREATE ctokens table
CREATE TABLE `c_token_tmp` (
	`tID`	INTEGER UNIQUE,
	`cid`	TEXT NOT NULL UNIQUE,
	`tHash`	VARCHAR(255) NOT NULL,
	`exDate`	VARCHAR(255) NOT NULL,
	`userID`	INTEGER NOT NULL,
	`cInfo`	TEXT,
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE,
	PRIMARY KEY(`tID` AUTOINCREMENT),
	FOREIGN KEY(`cid`) REFERENCES `clients`(`cid`) ON DELETE CASCADE
);
INSERT INTO `c_token_tmp` SELECT * FROM `c_token`;
DROP TABLE IF EXISTS `c_token`;
ALTER TABLE `c_token_tmp` RENAME TO `c_token`;

-- Create index
CREATE INDEX IF NOT EXISTS `i1` ON `bookmarks` (`bmURL`, `bmTitle`);
CREATE INDEX IF NOT EXISTS `i2` ON `users` ( `userID`);
CREATE INDEX IF NOT EXISTS `i3` ON `clients` (`cid`);

-- Create triggers
CREATE TRIGGER IF NOT EXISTS `update_tokenchange`
	UPDATE ON `auth_token`
BEGIN
	DELETE FROM `auth_token` WHERE `exDate` < strftime('%s') OR expired <> 0;
END;

DROP TRIGGER IF EXISTS `delete_userreset`;
DROP TRIGGER IF EXISTS `delete_usernotifications`;
DROP TRIGGER IF EXISTS `on_delete_set_default`;
DROP TRIGGER IF EXISTS `delete_userclients`;
DROP TRIGGER IF EXISTS `delete_usermarks`;
DROP TRIGGER IF EXISTS `delete_usertokens`;
DROP TRIGGER IF EXISTS `update_usertoken`;
DROP TRIGGER IF EXISTS `delete_clientokens`;
DROP TRIGGER IF EXISTS `delete_subbm`;

INSERT INTO `system` (`app_version`, `db_version`, `updated`) VALUES ('1.8.0', '9', '1667062229');
PRAGMA foreign_keys = ON;
PRAGMA user_version = 9;