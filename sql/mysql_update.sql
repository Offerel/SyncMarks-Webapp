PRAGMA foreign_keys = OFF;
-- Change tables
CREATE TABLE `users_tmp` (
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

CREATE TABLE `auth_token_tmp` (
	`tID`	INT UNIQUE,
	`userName`	TEXT NOT NULL,
	`pHash`	VARCHAR(255),
	`tHash`	VARCHAR(255) NOT NULL,
	`exDate`	INT NOT NULL,
	PRIMARY KEY(`tID` AUTOINCREMENT),
	FOREIGN KEY(`userName`) REFERENCES `users`(`userName`) ON UPDATE CASCADE ON DELETE CASCADE
);
INSERT INTO `auth_token_tmp` SELECT * FROM `auth_token`;
DROP TABLE IF EXISTS `auth_token`;
ALTER TABLE `auth_token_tmp` RENAME TO `auth_token`;

ALTER TABLE `bookmarks` RENAME TO `bookmarks_tmp`;
CREATE TABLE `bookmarks` (
  `bmID` varchar(15) NOT NULL,
  `bmParentID` varchar(15) DEFAULT NULL,
  `bmIndex` int(10) unsigned NOT NULL,
  `bmTitle` text DEFAULT NULL,
  `bmType` text NOT NULL,
  `bmURL` text DEFAULT NULL,
  `bmAdded` text NOT NULL,
  `bmModified` text DEFAULT NULL,
  `userID` int(11) NOT NULL,
  `bmAction` int(11) DEFAULT NULL,
  PRIMARY KEY (`bmID`,`userID`),
  KEY `userID` (`userID`),
  KEY `bmParentID` (`bmParentID`,`userID`),
  KEY `i1` (`bmURL`(255),`bmTitle`(255)),
  CONSTRAINT `bookmarks_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  CONSTRAINT `bookmarks_ibfk_2` FOREIGN KEY (`bmParentID`, `userID`) REFERENCES `bookmarks` (`bmID`, `userID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT OR IGNORE INTO `bookmarks` (`bmID`, `bmIndex`, `bmType`, `bmAdded`, `userID`) SELECT 'root________', 0,'folder',0, `userID` FROM users;
INSERT INTO `bookmarks` SELECT * FROM `bookmarks_tmp`;
DROP TABLE `bookmarks_tmp`;

CREATE TABLE IF NOT EXISTS `clients_tmp` (
	`cid`	VARCHAR(255) NOT NULL UNIQUE,
	`cname`	TEXT,
	`ctype`	TEXT NOT NULL,
	`userID`	INT NOT NULL,
	`lastseen`	INT NOT NULL DEFAULT 0,
	PRIMARY KEY(`cid`),
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE
);
INSERT INTO `clients_tmp`(`cid`, `cname`, `ctype`, `userID`, `lastseen`) SELECT (`cid`, `cname`, `ctype`, `uid`, `lastseen`) FROM `clients`;
DROP TABLE `clients`;
ALTER TABLE `clients_tmp` RENAME TO `clients`;

CREATE TABLE IF NOT EXISTS `c_token_tmp` (
	`tID`	INTEGER UNIQUE,
	`cid`	TEXT NOT NULL UNIQUE,
	`tHash`	VARCHAR(255) NOT NULL,
	`exDate`	INT NOT NULL,
	`userID`	INT NOT NULL,
	`cInfo`	TEXT,
	PRIMARY KEY(`tID` AUTOINCREMENT),
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE,
	FOREIGN KEY(`cid`) REFERENCES `clients`(`cid`) ON DELETE CASCADE
);
INSERT INTO `c_token_tmp` SELECT * FROM `c_token`;
DROP TABLE `c_token`;
ALTER TABLE `c_token_tmp` RENAME TO `c_token`;

CREATE TABLE `pages` (
	`pid`	INT NOT NULL,
	`ptitle`	varchar(250) NOT NULL,
	`purl`	TEXT NOT NULL,
	`ntime`	INT NOT NULL,
	`cid`	TEXT DEFAULT NULL,
	`nloop`	INT NOT NULL DEFAULT 1,
	`publish_date`	INT NOT NULL,
	`userID`	INT NOT NULL,
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE,
	FOREIGN KEY(`cid`) REFERENCES `clients`(`cid`) ON DELETE SET NULL,
	PRIMARY KEY(`pid`)
);
INSERT INTO `pages`(`pid`, `ptitle`, `purl`, `ntime`, `cid`, `nloop`. `publish_date`, `userID`) SELECT (`id`, `title`, `message`, `ntime`, `client`, `nloop`. `publish_date`, `userID`) FROM `notifications`;
DROP TABLE IF EXISTS `notifications`;

CREATE TABLE `reset_tmp` (
	`tokenID`	INT NOT NULL UNIQUE,
	`userID`	INT NOT NULL,
	`tokenTime`	INT NOT NULL,
	`token`	VARCHAR(255) NOT NULL UNIQUE,
	PRIMARY KEY(`tokenID` AUTOINCREMENT),
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE
);
INSERT INTO `reset_tmp` SELECT * FROM `reset`;
DROP TABLE `reset`;
ALTER TABLE `reset_tmp` RENAME TO `reset`;

CREATE TABLE `system_tmp` (
	`app_version`	varchar(10),
	`db_version`	INT,
	`updated`	INT
);
INSERT INTO `system_tmp` SELECT * FROM `system`;
DROP TABLE IF EXISTS `system`;
ALTER TABLE `system_tmp` RENAME TO `system`;

-- Create index
CREATE INDEX IF NOT EXISTS `i1` ON `bookmarks` (`bmURL`(255), `bmTitle`(255));
CREATE INDEX IF NOT EXISTS `i2` ON `users` ( `userID`);
CREATE INDEX IF NOT EXISTS `i3` ON `clients` (`cid`);

-- Triggers
DROP TRIGGER IF EXISTS `delete_userreset`;
DROP TRIGGER IF EXISTS `delete_usernotifications`;
DROP TRIGGER IF EXISTS `on_delete_set_default`;
DROP TRIGGER IF EXISTS `delete_userclients`;
DROP TRIGGER IF EXISTS `delete_usermarks`;
DROP TRIGGER IF EXISTS `delete_usertokens`;
DROP TRIGGER IF EXISTS `update_usertoken`;
DROP TRIGGER IF EXISTS `delete_clientokens`;
DROP TRIGGER IF EXISTS `delete_subbm`;

DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `update_tokenchange` 
	UPDATE ON `auth_token`
	FOR EACH ROW
BEGIN
	DELETE FROM `auth_token` WHERE `exDate` < UNIX_TIMESTAMP();
	END$$
DELIMITER ;

INSERT INTO `system` (`app_version`, `db_version`, `updated`) VALUES ('1.8.3', '10', '1667852693');
PRAGMA foreign_keys = ON;
PRAGMA user_version = 10;