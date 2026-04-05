<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require(__DIR__ . '/../config.php');

$sql = "CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

if (mysqli_query($conx, $sql)) {
    echo "Table chat_messages created successfully or already exists.<br>";
} else {
    echo "Error creating table chat_messages: " . mysqli_error($conx) . "<br>";
}

// Add group_id to chat_messages if it doesn't exist
$check_col = mysqli_query($conx, "SHOW COLUMNS FROM `chat_messages` LIKE 'group_id'");
if (mysqli_num_rows($check_col) == 0) {
    mysqli_query($conx, "ALTER TABLE `chat_messages` ADD `group_id` INT(11) DEFAULT NULL AFTER `receiver_id` ");
    echo "Column group_id added to chat_messages.<br>";
}

// Create chat_groups table
$sql_groups = "CREATE TABLE IF NOT EXISTS `chat_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `activity_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

if (mysqli_query($conx, $sql_groups)) {
    echo "Table chat_groups created successfully.<br>";
}

// Add activity_id column if it doesn't exist
$check_col = mysqli_query($conx, "SHOW COLUMNS FROM `chat_groups` LIKE 'activity_id'");
if (mysqli_num_rows($check_col) == 0) {
    mysqli_query($conx, "ALTER TABLE `chat_groups` ADD `activity_id` INT(11) DEFAULT NULL");
    echo "Column activity_id added to chat_groups.<br>";
}

// Create chat_group_members table
$sql_group_members = "CREATE TABLE IF NOT EXISTS `chat_group_members` (
  `group_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `joined_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`group_id`, `member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

if (mysqli_query($conx, $sql_group_members)) {
    echo "Table chat_group_members created successfully.<br>";
}

// Add last_read_at to chat_group_members if it doesn't exist
$check_col_read = mysqli_query($conx, "SHOW COLUMNS FROM `chat_group_members` LIKE 'last_read_at'");
if (mysqli_num_rows($check_col_read) == 0) {
    mysqli_query($conx, "ALTER TABLE `chat_group_members` ADD `last_read_at` DATETIME DEFAULT '1970-01-01 00:00:00' AFTER `joined_at` ");
    echo "Column last_read_at added to chat_group_members.<br>";
}
?>
