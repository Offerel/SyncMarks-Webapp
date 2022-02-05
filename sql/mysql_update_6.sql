-- Create users table
CREATE TABLE IF NOT EXISTS `users` (
	`userID` INT AUTO_INCREMENT PRIMARY KEY,
	`userName` VARCHAR(255) NOT NULL UNIQUE,
	`userType` INT(11) NOT NULL,
	`userHash` TEXT NOT NULL,
	`userLastLogin` INT(11) NULL DEFAULT NULL,
	`sessionID` VARCHAR(255) NULL DEFAULT NULL,
	`userOldLogin` INT(11) NULL DEFAULT NULL,
	`uOptions` TEXT NULL DEFAULT NULL,
	`userMail` VARCHAR(255) NULL DEFAULT NULL
);

-- Create bookmark table
CREATE TABLE IF NOT EXISTS `bookmarks` (
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

-- Create clients table
CREATE TABLE IF NOT EXISTS `clients` (
	`cid`	VARCHAR(255) NOT NULL UNIQUE,
	`cname`	TEXT,
	`ctype`	TEXT NOT NULL,
	`uid`	INTEGER NOT NULL,
	`lastseen`	TEXT NOT NULL,
	`fs`	INTEGER NOT NULL DEFAULT 0,
	PRIMARY KEY(`cid`),
	FOREIGN KEY(`uid`) REFERENCES `users`(`userID`) ON DELETE CASCADE
);

-- Create notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`title`	VARCHAR(255) NOT NULL,
	`message`	TEXT NOT NULL,
	`ntime`	VARCHAR(255) NOT NULL DEFAULT 0,
	`client`	TEXT NOT NULL DEFAULT 0,
	`nloop`	INTEGER NOT NULL DEFAULT 1,
	`publish_date`	VARCHAR(250) NOT NULL,
	`userID`	INTEGER NOT NULL,
	PRIMARY KEY(`id`),
	FOREIGN KEY(`userID`) REFERENCES `users`(`userID`) ON DELETE CASCADE
);

-- Create reset table
CREATE TABLE IF NOT EXISTS `reset` (
	`tokenID` INTEGER NOT NULL AUTO_INCREMENT,
	`userID` INTEGER NOT NULL,
	`tokenTime` VARCHAR(255) NOT NULL,
	`token` VARCHAR(255) NOT NULL,
	PRIMARY KEY (`tokenID`),
	UNIQUE INDEX `autoindex_reset_2` (`token`),
	UNIQUE INDEX `autoindex_reset_1` (`tokenID`)
);

-- Create system table
CREATE TABLE IF NOT EXISTS `system` (
	`app_version`	varchar(10),
	`db_version`	varchar(10),
	`updated`	varchar(250)
);

-- CREATE tokens table
CREATE TABLE IF NOT EXISTS `auth_token` (
	`tID`	INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
	`userName`	TEXT NOT NULL,
	`pHash`	VARCHAR(255) NOT NULL,
	`tHash`	VARCHAR(255) NOT NULL,
	`exDate`	VARCHAR(255) NOT NULL
);

-- Create index
CREATE INDEX IF NOT EXISTS `i1` ON `bookmarks` (`bmURL`(255), `bmTitle`(255));
CREATE INDEX IF NOT EXISTS `i2` ON `users` ( `userID`);
CREATE INDEX IF NOT EXISTS `i3` ON `clients` (`cid`);

-- Create triggers
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `on_delete_set_default` AFTER DELETE
ON `clients` FOR EACH ROW
BEGIN
UPDATE `notifications` SET `client`='0' WHERE `client`= old.cid;
END$$  
DELIMITER ;

DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `delete_userclients` AFTER DELETE
ON `users` FOR EACH ROW
BEGIN
DELETE FROM `clients` WHERE `uid` = OLD.userID;
END$$  
DELIMITER ;

DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `delete_usermarks` AFTER DELETE
ON `users` FOR EACH ROW
BEGIN
DELETE FROM `bookmarks` WHERE `userID` = OLD.userID;
END$$  
DELIMITER ;
--
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `update_tokenchange` UPDATE ON `auth_token` FOR EACH ROW
BEGIN
DELETE FROM `auth_token` WHERE `exDate` < UNIX_TIMESTAMP();
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `delete_usertokens` AFTER DELETE ON `users`
BEGIN
DELETE FROM `auth_token` WHERE `userName` = OLD.userName;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `update_usertoken` UPDATE OF `userName`, `userHash`, `userType` ON `users`
BEGIN
DELETE FROM `auth_token` WHERE `userName` = OLD.userName;
END$$
DELIMITER ;

INSERT INTO `system` (`app_version`, `db_version`, `updated`) VALUES ('1.6.3', '6', '1624442587');