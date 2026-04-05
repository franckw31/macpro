<?php
session_start();
include('include/config.php');

if(isset($_FILES["fileToUpload"])) {
    $id = $_POST['id'];
    $target_dir = "images/";
    $timestamp = time();
    $file_extension = strtolower(pathinfo($_FILES["fileToUpload"]["name"], PATHINFO_EXTENSION));
    $new_filename = "user_" . $id . "_" . $timestamp . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // Check file type
    $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
    if(!in_array($file_extension, $allowed_types)) {
        $_SESSION['error'] = "Seuls les fichiers JPG, JPEG, PNG & GIF sont autorisés.";
        header("Location: voir-membre.php?id=" . $id);
        exit();
    }
    
    // Check file size (5MB max)
    if ($_FILES["fileToUpload"]["size"] > 5000000) {
        $_SESSION['error'] = "Le fichier est trop volumineux.";
        header("Location: voir-membre.php?id=" . $id);
        exit();
    }
    
    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
        // Update database with new filename
        $sql = "UPDATE membres SET photo = ? WHERE `id-membre` = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $new_filename, $id);
        
        if(mysqli_stmt_execute($stmt)) {
            $_SESSION['msg'] = "Photo mise à jour avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la mise à jour de la base de données.";
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Erreur lors du téléchargement du fichier.";
    }
} else {
    $_SESSION['error'] = "Aucun fichier sélectionné.";
}

header("Location: voir-membre.php?id=" . $id);
exit();
