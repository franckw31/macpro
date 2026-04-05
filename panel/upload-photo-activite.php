<html>
  <head>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <style media="screen">
    .upload{
      width: 140px;
      position: relative;
      margin: auto;
      text-align: center;
    }
    .upload img{
      border-radius: 50%;
      border: 8px solid #DCDCDC;
      width: 125px;
      height: 125px;
    }
    .upload .rightRound{
      position: absolute;
      bottom: 0;
      right: 0;
      background: #00B4FF;
      width: 32px;
      height: 32px;
      line-height: 33px;
      text-align: center;
      border-radius: 50%;
      overflow: hidden;
      cursor: pointer;
    }
    .upload .leftRound{
      position: absolute;
      bottom: 0;
      left: 0;
      background: red;
      width: 32px;
      height: 32px;
      line-height: 33px;
      text-align: center;
      border-radius: 50%;
      overflow: hidden;
      cursor: pointer;
    }
    .upload .fa{
      color: white;
    }
    .upload input{
      position: absolute;
      transform: scale(2);
      opacity: 0;
    }
    .upload input::-webkit-file-upload-button, .upload input[type=submit]{
      cursor: pointer;
    }
  </style>
    <body>
      <?php
session_start();
include('include/config.php');

if(isset($_FILES["fileToUpload"])) {
    $aniid = intval($_GET['editid']); // Get and sanitize the ID
    $target_dir = "images/";
    $timestamp = time();
    $file_extension = strtolower(pathinfo($_FILES["fileToUpload"]["name"], PATHINFO_EXTENSION));
    $new_filename = "activity_" . $aniid . "_" . $timestamp . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // Check file type
    $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
    if(!in_array($file_extension, $allowed_types)) {
        $_SESSION['error'] = "Seuls les fichiers JPG, JPEG, PNG & GIF sont autorisés.";
        header("Location: voir-activite.php?uid=" . $aniid);
        exit();
    }
    
    // Check file size (5MB max)
    if ($_FILES["fileToUpload"]["size"] > 5000000) {
        $_SESSION['error'] = "Le fichier est trop volumineux.";
        header("Location: voir-activite.php?uid=" . $aniid);
        exit();
    }
    
    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
        // Update database with new filename
        $stmt = mysqli_prepare($conn, "UPDATE activite SET photo = ? WHERE `id-activite` = ?");
        mysqli_stmt_bind_param($stmt, 'si', $new_filename, $aniid);
        
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

header("Location: voir-activite.php?uid=" . $aniid);
mysqli_close($conn);
exit();
?>
  <script type="text/javascript">window.location.replace("voir-activite.php?uid=<?php echo $aniid; ?>");</script>
</body>
</html>

