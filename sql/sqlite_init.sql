-- Create users table
CREATE TABLE `users` (
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

-- Create bookmark table
CREATE TABLE "bookmarks" (
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

-- Create clients table
CREATE TABLE `clients` (
	`cid`	TEXT DEFAULT NULL UNIQUE,
	`cname`	TEXT,
	`ctype`	TEXT NOT NULL,
	`uid`	INTEGER NOT NULL,
	`lastseen`	TEXT NOT NULL DEFAULT 0,
	`fs`	INTEGER NOT NULL DEFAULT 0,
	PRIMARY KEY(`cid`),
	FOREIGN KEY(`uid`) REFERENCES `users`(`userID`) ON DELETE CASCADE
);

-- Create notifications table
CREATE TABLE `notifications` (
	`id`	INTEGER NOT NULL,
	`title`	varchar(250) NOT NULL,
	`message`	TEXT NOT NULL,
	`ntime`	varchar(250) NOT NULL DEFAULT NULL,
	`client`	TEXT DEFAULT NULL,
	`nloop`	INTEGER NOT NULL DEFAULT 1,
	`publish_date`	varchar(250) NOT NULL,
	`userID`	INTEGER NOT NULL,
	PRIMARY KEY(`id` AUTOINCREMENT),
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE,
	FOREIGN KEY(`client`) REFERENCES `clients`(`cid`) ON DELETE SET NULL
);

-- Create reset table
CREATE TABLE `reset` (
	`tokenID`	INTEGER NOT NULL UNIQUE,
	`userID`	INTEGER NOT NULL,
	`tokenTime`	VARCHAR(255) NOT NULL,
	`token`	VARCHAR(255) NOT NULL UNIQUE,
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE,
	PRIMARY KEY(`tokenID` AUTOINCREMENT)
);

-- Create system table
CREATE TABLE `system` (
	`app_version`	varchar(10),
	`db_version`	varchar(10),
	`updated`	varchar(250)
);

-- CREATE tokens table
CREATE TABLE `auth_token` (
	`tID`	INTEGER UNIQUE,
	`userName`	TEXT NOT NULL,
	`pHash`	VARCHAR(255),
	`tHash`	VARCHAR(255) NOT NULL,
	`exDate`	VARCHAR(255) NOT NULL,
	FOREIGN KEY(`userName`) REFERENCES `users`(`userName`) ON UPDATE CASCADE ON DELETE CASCADE,
	PRIMARY KEY(`tID` AUTOINCREMENT)
);

-- CREATE ctokens table
CREATE TABLE `c_token` (
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

-- Create index
CREATE INDEX `i1` ON `bookmarks` (`bmURL`, `bmTitle`);
CREATE INDEX `i2` ON `users` ( `userID`);
CREATE INDEX `i3` ON `clients` (`cid`);

-- Create triggers
CREATE TRIGGER IF NOT EXISTS `update_tokenchange`
	UPDATE ON `auth_token`
BEGIN
	DELETE FROM `auth_token` WHERE `exDate` < strftime('%s') OR expired <> 0;
END;

INSERT INTO `system` (`app_version`, `db_version`, `updated`) VALUES ('1.8.0', '9', '1667062229');

PRAGMA user_version = 9;