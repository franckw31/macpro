<?php
// Include database configuration
include('include/config.php');

// Check if file was uploaded
if(empty($_FILES["fileToUpload"]["tmp_name"])) {
    die("No file was uploaded.");
}

// File upload settings
$target_dir = "images/";
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$max_size = 200 * 1024 * 1024; // 2MB
$aniid = intval($_GET['editid']);

// Validate file
$file_type = $_FILES["fileToUpload"]["type"];
$file_size = $_FILES["fileToUpload"]["size"];
$file_ext = strtolower(pathinfo($_FILES["fileToUpload"]["name"], PATHINFO_EXTENSION));

if(!in_array($file_type, $allowed_types)) {
    die("Error: Only JPG, PNG & GIF files are allowed.");
}

if($file_size > $max_size) {
    die("Error: File size must be less than 2MB.");
}

// Generate unique filename
$new_filename = "member_".$aniid."_".time().".".$file_ext;
$target_file = $target_dir . $new_filename;

// Move uploaded file
if(move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
    // Update database
    $query = mysqli_query($con, "UPDATE membres SET photo = '".mysqli_real_escape_string($con, $new_filename)."' 
              WHERE `id-membre` = $aniid");
    
    if(!$query) {
        unlink($target_file); // Delete uploaded file if DB update fails
        die("Database update failed: ".mysqli_error($con));
    }
    
    // Redirect back to member page
    header("lLocation: vvoir-membre.php?id=$aniid");
    exit();
} else {
    die("Error uploading file. Please try again.");
}
?>
