<?php
session_start();
require_once(__DIR__ . '/../config.php');

if (!isset($_SESSION['id']) || !isset($_POST['group_name']) || !isset($_POST['members'])) {
    die(json_encode(['error' => 'Invalid request']));
}

$user_id = $_SESSION['id'];
$group_name = mysqli_real_escape_string($conx, $_POST['group_name']);
$creator_id = $_SESSION['id'];
$members = $_POST['members']; // Array of member IDs

if (empty($group_name)) {
    die(json_encode(['error' => 'Group name is required']));
}

// Create the group
$sql = "INSERT INTO `chat_groups` (`name`, `created_by`) VALUES ('$group_name', $creator_id)";
if (mysqli_query($conx, $sql)) {
    $group_id = mysqli_insert_id($conx);
    
    // Add creator as member
    mysqli_query($conx, "INSERT INTO `chat_group_members` (`group_id`, `member_id`) VALUES ($group_id, $creator_id)");
    
    // Add other members
    if (is_array($members)) {
        foreach ($members as $member_id) {
            $member_id = (int)$member_id;
            mysqli_query($conx, "INSERT INTO `chat_group_members` (`group_id`, `member_id`) VALUES ($group_id, $member_id)");
        }
    }
    
    echo json_encode(['success' => true, 'group_id' => $group_id]);
} else {
    echo json_encode(['error' => mysqli_error($conx)]);
}
?>
