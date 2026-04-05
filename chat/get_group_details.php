<?php
session_start();
require_once(__DIR__ . '/../config.php');

if (!isset($_SESSION['id']) || !isset($_GET['group_id'])) {
    die(json_encode(['error' => 'Invalid request']));
}

$group_id = (int)$_GET['group_id'];
$current_user_id = $_SESSION['id'];

// Get group info
$sql_group = "SELECT * FROM `chat_groups` WHERE id = $group_id";
$res_group = mysqli_query($conx, $sql_group);
$group = mysqli_fetch_assoc($res_group);

if (!$group) {
    die(json_encode(['error' => 'Group not found']));
}

// Check if user is member
$sql_check = "SELECT * FROM `chat_group_members` WHERE group_id = $group_id AND member_id = $current_user_id";
$res_check = mysqli_query($conx, $sql_check);
if (mysqli_num_rows($res_check) == 0) {
    die(json_encode(['error' => 'Permission denied']));
}

// Get current members
$sql_members = "SELECT m.`id-membre` as id, m.`pseudo`, m.`photo` FROM `membres` m
                JOIN `chat_group_members` gm ON m.`id-membre` = gm.`member_id`
                WHERE gm.`group_id` = $group_id";
$result_members = mysqli_query($conx, $sql_members);
$current_members = [];
while ($row = mysqli_fetch_assoc($result_members)) {
    $current_members[] = $row;
}

// Get non-members (to add)
$sql_non_members = "SELECT `id-membre` as id, `pseudo`, `photo` FROM `membres` 
                    WHERE `id-membre` NOT IN (SELECT `member_id` FROM `chat_group_members` WHERE `group_id` = $group_id)
                    ORDER BY `pseudo` ASC";
$result_non_members = mysqli_query($conx, $sql_non_members);
$non_members = [];
while ($row = mysqli_fetch_assoc($result_non_members)) {
    $non_members[] = $row;
}

echo json_encode([
    'success' => true,
    'group' => $group,
    'current_members' => $current_members,
    'non_members' => $non_members
]);
?>
