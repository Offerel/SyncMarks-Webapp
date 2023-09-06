-- Create users table
CREATE TABLE `users` (
  `userID` int(11) NOT NULL,
  `userName` varchar(255) NOT NULL,
  `userType` int(11) NOT NULL,
  `userHash` text NOT NULL,
  `userLastLogin` int(11) DEFAULT NULL,
  `sessionID` varchar(255) DEFAULT NULL,
  `userOldLogin` int(11) DEFAULT NULL,
  `uOptions` text DEFAULT NULL,
  `userMail` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`userID`),
  UNIQUE KEY `userID` (`userID`),
  UNIQUE KEY `userName` (`userName`),
  UNIQUE KEY `sessionID` (`sessionID`),
  KEY `i2` (`userID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Create bookmark table
CREATE TABLE `bookmarks` (
  `bmID` varchar(15) NOT NULL,
  `bmParentID` varchar(15) DEFAULT NULL,
  `bmIndex` int(11) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Create clients table
CREATE TABLE `clients` (
  `cid` varchar(255) NOT NULL,
  `cname` text DEFAULT NULL,
  `ctype` text NOT NULL,
  `uid` int(11) NOT NULL,
  `lastseen` text NOT NULL DEFAULT 0,
  `fs` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`cid`),
  UNIQUE KEY `cid` (`cid`),
  KEY `uid` (`uid`),
  KEY `i3` (`cid`),
  CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`uid`) REFERENCES `users` (`userID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Create notifications table
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `ntime` varchar(255) NOT NULL DEFAULT '0',
  `client` varchar(255) DEFAULT NULL,
  `nloop` int(11) NOT NULL DEFAULT 1,
  `publish_date` varchar(250) NOT NULL,
  `userID` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `userID` (`userID`),
  KEY `client` (`client`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`client`) REFERENCES `clients` (`cid`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Create reset table
CREATE TABLE `reset` (
  `tokenID` int(11) NOT NULL AUTO_INCREMENT,
  `userID` int(11) NOT NULL,
  `tokenTime` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  PRIMARY KEY (`tokenID`),
  UNIQUE KEY `autoindex_reset_2` (`token`),
  UNIQUE KEY `autoindex_reset_1` (`tokenID`),
  KEY `userID` (`userID`),
  CONSTRAINT `reset_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Create system table
CREATE TABLE `system` (
  `app_version` varchar(10) DEFAULT NULL,
  `db_version` varchar(10) DEFAULT NULL,
  `updated` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- CREATE tokens table
CREATE TABLE IF NOT EXISTS `auth_token` (
  `tID` int(11) NOT NULL,
  `userName` varchar(255) NOT NULL,
  `pHash` varchar(255) NOT NULL,
  `tHash` varchar(255) NOT NULL,
  `exDate` int(11) NOT NULL,
  PRIMARY KEY (`tID`),
  KEY `authtoken_fk1` (`userName`),
  CONSTRAINT `authtoken_fk1` FOREIGN KEY (`userName`) REFERENCES `users` (`userName`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- CREATE ctokens table
CREATE TABLE `c_token` (
  `tID` int(11) NOT NULL,
  `cid` varchar(255) DEFAULT NULL,
  `tHash` varchar(255) NOT NULL,
  `exDate` varchar(255) NOT NULL,
  `userID` int(11) NOT NULL,
  `cInfo` text DEFAULT NULL,
  PRIMARY KEY (`tID`),
  UNIQUE KEY `tID` (`tID`),
  UNIQUE KEY `cid` (`cid`),
  KEY `userID` (`userID`),
  CONSTRAINT `c_token_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  CONSTRAINT `c_token_ibfk_2` FOREIGN KEY (`cid`) REFERENCES `clients` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Create index
CREATE INDEX IF NOT EXISTS `i1` ON `bookmarks` (`bmURL`(255), `bmTitle`(255));
CREATE INDEX IF NOT EXISTS `i2` ON `users` ( `userID`);
CREATE INDEX IF NOT EXISTS `i3` ON `clients` (`cid`);

-- Create triggers
@delimiter %%%;
CREATE TRIGGER IF NOT EXISTS
    `bookmarks`.update_tokenchange AFTER 
UPDATE
ON 
    `bookmarks`.`auth_token` FOR EACH row BEGIN
DELETE 
FROM 
    `auth_token` 
WHERE 
    `exDate` < UNIX_TIMESTAMP();
 
END; 
%%%
@delimiter ; 
%%%

INSERT INTO `system` (`app_version`, `db_version`, `updated`) VALUES ('1.8.0', '9', '1646766932');