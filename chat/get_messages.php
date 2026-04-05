<?php
session_start();
require_once(__DIR__ . '/../config.php');

if (!isset($_SESSION['id'])) {
    die(json_encode(['error' => 'Not logged in']));
}

$current_user_id = $_SESSION['id'];
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$last_sync = isset($_GET['last_sync']) ? $_GET['last_sync'] : '2000-01-01 00:00:00';

// Check if user is admin
$is_admin = false;
$res_admin = mysqli_query($conx, "SELECT `droits` FROM `membres` WHERE `id-membre` = $current_user_id");
if ($row_admin = mysqli_fetch_assoc($res_admin)) {
    if ($current_user_id == 265 || $row_admin['droits'] == '2') {
        $is_admin = true;
    }
}

if (isset($_GET['group_id'])) {
    $group_id = (int)$_GET['group_id'];
    
    // Mark group messages as read for this user
    $update_read_sql = "UPDATE `chat_group_members` SET `last_read_at` = NOW() WHERE `group_id` = $group_id AND `member_id` = $current_user_id";
    mysqli_query($conx, $update_read_sql);

    $sql = "SELECT m.*, mem.pseudo as sender_name, mem.photo as sender_photo FROM `chat_messages` m
            JOIN `membres` mem ON m.sender_id = mem.`id-membre`
            WHERE m.`group_id` = $group_id 
            AND (m.`id` > $last_id OR m.`updated_at` > '$last_sync')
            ORDER BY m.`timestamp` DESC";
} elseif (isset($_GET['contact_id'])) {
    $contact_id = (int)$_GET['contact_id'];
    // Mark messages as read
    $update_sql = "UPDATE `chat_messages` SET `is_read` = 1 WHERE `sender_id` = $contact_id AND `receiver_id` = $current_user_id AND `group_id` IS NULL";
    mysqli_query($conx, $update_sql);

    $sql = "SELECT m.*, mem.pseudo as sender_name, mem.photo as sender_photo FROM `chat_messages` m
            JOIN `membres` mem ON m.sender_id = mem.`id-membre`
            WHERE ((`sender_id` = $current_user_id AND `receiver_id` = $contact_id AND `group_id` IS NULL) 
            OR (`sender_id` = $contact_id AND `receiver_id` = $current_user_id AND `group_id` IS NULL))
            AND (m.`id` > $last_id OR m.`updated_at` > '$last_sync')
            ORDER BY m.`timestamp` DESC";
} else {
    // Global Feed: All private messages for user + all messages from groups user is in
    $sql = "SELECT m.*, mem.pseudo as sender_name, mem.photo as sender_photo, g.name as group_name 
            FROM `chat_messages` m
            JOIN `membres` mem ON m.sender_id = mem.`id-membre`
            LEFT JOIN `chat_groups` g ON m.group_id = g.id
            WHERE (
                (m.receiver_id = $current_user_id AND m.group_id IS NULL) -- Private received
                OR (m.sender_id = $current_user_id AND m.group_id IS NULL) -- Private sent
                OR (m.group_id IN (SELECT group_id FROM chat_group_members WHERE member_id = $current_user_id)) -- Group messages
            )
            AND (m.id > $last_id OR m.updated_at > '$last_sync')
            ORDER BY m.timestamp DESC";
}

$result = mysqli_query($conx, $sql);
if (!$result) {
    die(json_encode(['error' => mysqli_error($conx)]));
}
$messages = [];
while ($row = mysqli_fetch_assoc($result)) {
    if ($row['is_censored'] == 1 && !$is_admin) {
        $row['message'] = "[Message censuré par l'administrateur]";
        $row['image'] = null;
        $row['audio'] = null;
    }
    if (empty($row['sender_photo'])) {
        $row['sender_photo'] = 'man.png';
    }
    $messages[] = $row;
}

echo json_encode($messages);
?>
