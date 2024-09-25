PRAGMA foreign_keys = OFF;
-- Change tables
CREATE TABLE `users_tmp` (
	`userID`	INTEGER NOT NULL UNIQUE,
	`userName`	TEXT NOT NULL UNIQUE,
	`userType`	INTEGER NOT NULL,
	`userHash`	TEXT NOT NULL,
	`userLastLogin`	INTEGER,
	`sessionID`	VARCHAR(255) UNIQUE,
	`userOldLogin`	INTEGER,
	`uOptions`	TEXT,
	`userMail`	VARCHAR(255),
	PRIMARY KEY(`userID`)
);
INSERT INTO `users_tmp` SELECT `userID`,`userName`,`userType`,`userHash`,`userLastLogin`,NULL,NULL,NULL,NULL FROM `users`;
DROP TABLE `users`;
ALTER TABLE `users_tmp` RENAME TO `users`;

CREATE TABLE `auth_token_tmp` (
	`tID`	INTEGER UNIQUE,
	`userName`	TEXT NOT NULL,
	`pHash`	VARCHAR(255),
	`tHash`	VARCHAR(255) NOT NULL,
	`exDate`	INTEGER NOT NULL,
	PRIMARY KEY(`tID` AUTOINCREMENT),
	FOREIGN KEY(`userName`) REFERENCES `users`(`userName`) ON UPDATE CASCADE ON DELETE CASCADE
);
INSERT INTO `auth_token_tmp` SELECT * FROM `auth_token`;
DROP TABLE IF EXISTS `auth_token`;
ALTER TABLE `auth_token_tmp` RENAME TO `auth_token`;

CREATE TABLE `bookmarks_tmp` (
	`bmID`	TEXT NOT NULL,
	`bmParentID`	TEXT DEFAULT NULL,
	`bmIndex`	INTEGER NOT NULL,
	`bmTitle`	TEXT,
	`bmType`	TEXT NOT NULL,
	`bmURL`	TEXT,
	`bmAdded`	INTEGER NOT NULL,
	`bmModified`	INTEGER,
	`userID`	INTEGER NOT NULL,
	`bmAction`	INTEGER,
	`bmSort` INTEGER DEFAULT NULL,
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE,
	FOREIGN KEY(`bmParentID`,`userID`) REFERENCES `bookmarks`(`bmID`,`userID`) ON DELETE CASCADE,
	PRIMARY KEY(`bmID`,`userID`)
);
INSERT OR IGNORE INTO `bookmarks_tmp` (`bmID`, `bmIndex`, `bmType`, `bmAdded`, `userID`) SELECT 'root________', 0,'folder',0, `userID` FROM users;
INSERT INTO `bookmarks_tmp` SELECT * FROM `bookmarks`;
DROP TABLE `bookmarks`;

CREATE TABLE `clients_tmp` (
	`cid`	TEXT NOT NULL,
	`cname`	TEXT,
	`ctype`	TEXT NOT NULL,
	`userID`	INTEGER NOT NULL,
	`lastseen`	INTEGER NOT NULL DEFAULT 0,
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE,
	PRIMARY KEY(`cid`)
);
INSERT INTO `clients_tmp`(`cid`, `cname`, `ctype`, `userID`, `lastseen`) SELECT (`cid`, `cname`, `ctype`, `uid`, `lastseen`) FROM `clients`;
DROP TABLE `clients`;
ALTER TABLE `clients_tmp` RENAME TO `clients`;

CREATE TABLE `c_token_tmp` (
	`tID`	INTEGER UNIQUE,
	`cid`	TEXT NOT NULL UNIQUE,
	`tHash`	VARCHAR(255) NOT NULL,
	`exDate`	INTEGER NOT NULL,
	`userID`	INTEGER NOT NULL,
	`cInfo`	TEXT,
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE,
	FOREIGN KEY(`cid`) REFERENCES `clients`(`cid`) ON DELETE CASCADE,
	PRIMARY KEY(`tID` AUTOINCREMENT)
);
INSERT INTO `c_token_tmp` SELECT * FROM `c_token`;
DROP TABLE IF EXISTS `c_token`;
ALTER TABLE `c_token_tmp` RENAME TO `c_token`;

CREATE TABLE `pages` (
	`pid`	INTEGER NOT NULL,
	`ptitle`	varchar(250) NOT NULL,
	`purl`	TEXT NOT NULL,
	`ntime`	INTEGER NOT NULL,
	`cid`	TEXT DEFAULT NULL,
	`nloop`	INTEGER NOT NULL DEFAULT 1,
	`publish_date`	INTEGER NOT NULL,
	`userID`	INTEGER NOT NULL,
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE,
	FOREIGN KEY(`cid`) REFERENCES `clients`(`cid`) ON DELETE SET NULL,
	PRIMARY KEY(`pid`)
);
INSERT INTO `pages`(`pid`, `ptitle`, `purl`, `ntime`, `cid`, `nloop`. `publish_date`, `userID`) SELECT (`id`, `title`, `message`, `ntime`, `client`, `nloop`. `publish_date`, `userID`) FROM `notifications`;
DROP TABLE IF EXISTS `notifications`;

CREATE TABLE `reset_tmp` (
	`tokenID`	INTEGER NOT NULL UNIQUE,
	`userID`	INTEGER NOT NULL,
	`tokenTime`	INTEGER NOT NULL,
	`token`	VARCHAR(255) NOT NULL UNIQUE,
	PRIMARY KEY(`tokenID` AUTOINCREMENT),
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE
);
INSERT INTO `reset_tmp` SELECT * FROM `reset`;
DROP TABLE IF EXISTS `reset`;
ALTER TABLE `reset_tmp` RENAME TO `reset`;

CREATE TABLE `system_tmp` (
	`app_version`	varchar(10),
	`db_version`	INTEGER,
	`updated`	INTEGER
);
INSERT INTO `system_tmp` SELECT * FROM `system`;
DROP TABLE IF EXISTS `system`;
ALTER TABLE `system_tmp` RENAME TO `system`;

-- Create index
CREATE INDEX IF NOT EXISTS `i1` ON `bookmarks` (`bmURL`, `bmTitle`);
CREATE INDEX IF NOT EXISTS `i2` ON `users` ( `userID`);
CREATE INDEX IF NOT EXISTS `i3` ON `clients` (`cid`);

-- Views
CREATE VIEW `ClientsV` AS 
	SELECT t.tid, t.cid, c.cname, tHash as token, datetime(exDate,'unixepoch', 'localtime') as expire, u.userID, u.userName, cInfo, CASE c.lastseen 
	WHEN 0 
		THEN NULL 
	ELSE datetime(c.lastseen/1000,'unixepoch', 'localtime') 
	END lastseen
FROM `c_token` t
INNER JOIN "clients" c ON t.cid = c.cid
INNER JOIN users u ON c.userID = u.userID
ORDER BY expire

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

INSERT INTO `system` (`app_version`, `db_version`, `updated`) VALUES ('1.10.0', '11', '1727281092');
PRAGMA foreign_keys = ON;
PRAGMA user_version = 11;
PRAGMA journal_mode=WAL;