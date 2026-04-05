<?php
session_start();
require_once(__DIR__ . '/../config.php');

if (!isset($_SESSION['id']) || !isset($_POST['group_id'])) {
    die(json_encode(['error' => 'Invalid request']));
}

$group_id = (int)$_POST['group_id'];
$current_user_id = $_SESSION['id'];

// Check if user is admin (ID 265 or droits = 2)
$is_admin = false;
$res = mysqli_query($conx, "SELECT `droits` FROM `membres` WHERE `id-membre` = $current_user_id");
if ($row = mysqli_fetch_assoc($res)) {
    if ($current_user_id == 265 || $row['droits'] == '2') {
        $is_admin = true;
    }
}

// Check if user is group creator
$is_creator = false;
$activity_id = null;
$res_group = mysqli_query($conx, "SELECT `created_by`, `activity_id` FROM `chat_groups` WHERE `id` = $group_id");
if ($row_group = mysqli_fetch_assoc($res_group)) {
    if ($row_group['created_by'] == $current_user_id) {
        $is_creator = true;
    }
    $activity_id = $row_group['activity_id'];
}

// Check if user is activity organizer
$is_activity_organizer = false;
if (!$is_admin && !$is_creator && $activity_id) {
    $res_activity = mysqli_query($conx, "SELECT `id-membre` FROM `activite` WHERE `id-activite` = $activity_id");
    if ($row_activity = mysqli_fetch_assoc($res_activity)) {
        if ($row_activity['id-membre'] == $current_user_id) {
            $is_activity_organizer = true;
        }
    }
}

if (!$is_admin && !$is_creator && !$is_activity_organizer) {
    die(json_encode(['error' => 'Permission denied']));
}

// Delete group members
mysqli_query($conx, "DELETE FROM `chat_group_members` WHERE `group_id` = $group_id");

// Delete group messages
mysqli_query($conx, "DELETE FROM `chat_messages` WHERE `group_id` = $group_id");

// Delete the group itself
$sql = "DELETE FROM `chat_groups` WHERE `id` = $group_id";

if (mysqli_query($conx, $sql)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => mysqli_error($conx)]);
}
?>
