-- Tables
CREATE TABLE `users` (
	`userID`	INTEGER NOT NULL UNIQUE,
	`userName`	TEXT NOT NULL UNIQUE,
	`userType`	INTEGER NOT NULL,
	`userHash`	TEXT NOT NULL,
	`userLastLogin`	INTEGER DEFAULT NULL,
	`sessionID`	VARCHAR(255) UNIQUE,
	`userOldLogin`	INTEGER DEFAULT NULL,
	`uOptions`	TEXT,
	`userMail`	VARCHAR(255) DEFAULT NULL,
	PRIMARY KEY(`userID`)
);

CREATE TABLE `auth_token` (
	`tID`	INTEGER UNIQUE,
	`userName`	TEXT NOT NULL,
	`pHash`	VARCHAR(255),
	`tHash`	VARCHAR(255) NOT NULL,
	`exDate`	INTEGER NOT NULL,
	PRIMARY KEY(`tID` AUTOINCREMENT),
	FOREIGN KEY(`userName`) REFERENCES `users`(`userName`) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE `bookmarks` (
	`bmID`	TEXT NOT NULL,
	`bmParentID`	TEXT DEFAULT NULL,
	`bmIndex`	INTEGER NOT NULL,
	`bmTitle`	TEXT,
	`bmType`	TEXT NOT NULL,
	`bmURL`	TEXT,
	`bmAdded`	INTEGER NOT NULL,
	`bmModified`	INTEGER,
	`userID`	INTEGER NOT NULL,
	`bmSort` INTEGER DEFAULT NULL,
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE,
	FOREIGN KEY(`bmParentID`,`userID`) REFERENCES `bookmarks`(`bmID`,`userID`) ON DELETE CASCADE,
	PRIMARY KEY(`bmID`,`userID`)
);

CREATE TABLE `clients` (
	`cid`	TEXT NOT NULL,
	`cname`	TEXT,
	`ctype`	TEXT NOT NULL,
	`userID`	INTEGER NOT NULL,
	`lastseen`	INTEGER NOT NULL DEFAULT 0,
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE,
	PRIMARY KEY(`cid`)
);

CREATE TABLE `c_token` (
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

CREATE TABLE `reset` (
	`tokenID`	INTEGER NOT NULL UNIQUE,
	`userID`	INTEGER NOT NULL,
	`tokenTime`	INTEGER NOT NULL,
	`token`	VARCHAR(255) NOT NULL UNIQUE,
	PRIMARY KEY(`tokenID` AUTOINCREMENT),
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE
);

CREATE TABLE `system` (
	`app_version`	varchar(10),
	`db_version`	INTEGER,
	`updated`	INTEGER
);

-- Create index
CREATE INDEX `i1` ON `bookmarks` (`bmURL`, `bmTitle`);
CREATE INDEX `i2` ON `users` ( `userID`);
CREATE INDEX `i3` ON `clients` (`cid`);

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
ORDER BY expire;

-- Create triggers
CREATE TRIGGER IF NOT EXISTS `update_tokenchange`
	UPDATE ON `auth_token`
BEGIN
	DELETE FROM `auth_token` WHERE `exDate` < strftime('%s') OR expired <> 0;
END;

INSERT INTO `system` (`app_version`, `db_version`, `updated`) VALUES ('1.10.0', '11', '1727281092');

PRAGMA user_version = 11;

PRAGMA foreign_keys = ON;

PRAGMA journal_mode=WAL;