<?php
session_start();
require_once(__DIR__ . '/../config.php');

if (!isset($_SESSION['id']) || !isset($_POST['message_id'])) {
    die(json_encode(['error' => 'Invalid request']));
}

$message_id = (int)$_POST['message_id'];
$current_user_id = $_SESSION['id'];

// Check if user is admin (ID 265 or droits = 2)
$is_admin = false;
$res = mysqli_query($conx, "SELECT `droits` FROM `membres` WHERE `id-membre` = $current_user_id");
if ($row = mysqli_fetch_assoc($res)) {
    if ($current_user_id == 265 || $row['droits'] == '2') {
        $is_admin = true;
    }
}

if (!$is_admin) {
    die(json_encode(['error' => 'Permission denied']));
}

$sql = "UPDATE `chat_messages` SET `is_censored` = 1 WHERE `id` = $message_id";

if (mysqli_query($conx, $sql)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => mysqli_error($conx)]);
}
?>
