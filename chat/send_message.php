<?php
session_start();
require_once(__DIR__ . '/../config.php');

if (!isset($_SESSION['id'])) {
    die(json_encode(['error' => 'Invalid request']));
}

$sender_id = $_SESSION['id'];
$message = isset($_POST['message']) ? mysqli_real_escape_string($conx, $_POST['message']) : '';
$image_path = null;
$audio_path = null;

// Handle Image Upload
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $_FILES['image']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (in_array($ext, $allowed)) {
        $new_filename = uniqid() . '.' . $ext;
        $target = __DIR__ . '/uploads/' . $new_filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $image_path = $new_filename;
        }
    }
}

// Handle Audio Upload
if (isset($_FILES['audio']) && $_FILES['audio']['error'] == 0) {
    $allowed = ['webm', 'wav', 'mp3', 'ogg', 'blob']; // Added blob just in case
    $filename = $_FILES['audio']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // If no extension (common with Blobs), default to webm
    if (empty($ext)) $ext = 'webm';

    if (in_array($ext, $allowed) || empty($ext)) {
        $new_filename = 'voice_' . uniqid() . '.' . $ext;
        $target = __DIR__ . '/uploads/' . $new_filename;
        if (move_uploaded_file($_FILES['audio']['tmp_name'], $target)) {
            $audio_path = $new_filename;
        } else {
            die(json_encode(['error' => 'Failed to move uploaded audio file']));
        }
    } else {
        die(json_encode(['error' => 'Invalid audio format: ' . $ext]));
    }
}

if (empty($message) && empty($image_path) && empty($audio_path)) {
    die(json_encode(['error' => 'Empty message']));
}

$image_val = $image_path ? "'$image_path'" : "NULL";
$audio_val = $audio_path ? "'$audio_path'" : "NULL";

if (isset($_POST['group_id'])) {
    $group_id = (int)$_POST['group_id'];
    $sql = "INSERT INTO `chat_messages` (`sender_id`, `group_id`, `message`, `image`, `audio`) VALUES ($sender_id, $group_id, '$message', $image_val, $audio_val)";
} elseif (isset($_POST['receiver_id'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $sql = "INSERT INTO `chat_messages` (`sender_id`, `receiver_id`, `message`, `image`, `audio`) VALUES ($sender_id, $receiver_id, '$message', $image_val, $audio_val)";
} else {
    die(json_encode(['error' => 'No recipient specified']));
}

if (mysqli_query($conx, $sql)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => mysqli_error($conx)]);
}
?>
