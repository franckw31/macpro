<html>
  <head>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <style media="screen">
    .upload{
      width: 75px;
      position: relative;
      margin: auto;
      text-align: center;
    }
    .upload img{
      border-radius: 40%;
      border: 6px solid #DCDCDC;
      width: 100px;
      height: 100px;
    }
    .upload .rightRound{
      position: absolute;
      bottom: 0;
      right: 0;
      background: #00B4FF;
      width: 25px;
      height: 25px;
      line-height: 25px;
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
      width: 25px;
      height: 25px;
      line-height: 25px;
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
      define('DB_SERVER','db5011397709.hosting-data.io');
      define('DB_USER','dbu5472475');
      define('DB_PASS' ,'Kookies7*');
      define('DB_NAME', 'dbs9616600');
      $con = mysqli_connect(DB_SERVER,DB_USER,DB_PASS,DB_NAME);
      ?>


    <form class="form" id = "form" action="" method="post" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
      <div class="upload">
       <img src="/panel/images/<?php echo $user['photo']; ?>" id = "photo">

       <div class="rightRound" id = "upload">
          <input type="file" name="fileToUpload" id="fileToUpload">
          <i class = "fa fa-camera"></i>
       </div>   
          <div class="leftRound" id = "cancel" style = "display: none;">
           <i class = "fa fa-times"></i>
       </div>
          <div class="rightRound" id = "confirm" style = "display: none;">
       <input type="submit">
       <i class = "fa fa-check"></i>
        </div>
      </div>
    </form>


<script type="text/javascript">
      document.getElementById("fileToUpload").onchange = function(){
        document.getElementById("photo").src = URL.createObjectURL(fileToUpload.files[0]); // Preview new image

        document.getElementById("cancel").style.display = "block";
        document.getElementById("confirm").style.display = "block";

        document.getElementById("upload").style.display = "none";
      }

      var userImage = document.getElementById("photo").src;
      document.getElementById("cancel").onclick = function(){
        document.getElementById("photo").src = userImage; // Back to previous image

        document.getElementById("cancel").style.display = "none";
        document.getElementById("confirm").style.display = "none";

        document.getElementById("upload").style.display = "block";
      }
    </script> 
    <?php

$target_dir = "images/";
$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
$aimg=$_FILES["fileToUpload"]["name"];
$uploadOk = 1;

$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

// Check if image file is a actual image or fake image
if(isset($_POST["submit"])) {
  $check = getimagesize($_FILES["fileToUpload"]["tmp_name"]);
 
  if($check !== false) {
  //  echo "File is an image - " . $check["mime"] . ".";
    $uploadOk = 1;
	$aniid=$_GET['editid'];
	
  //	$query=mysqli_query($con, "update joueurs set photo='tit' where id=1");
  //	$query=mysqli_query($con, "update joueurs set photo='$aimg' where id='$aniid'");
  } else {
    echo "File is not an image.";
    $uploadOk = 0;
  }
 }

 // Check if file already exists
 if (file_exists($target_file)) {
 // echo "Sorry, file already exists.";
  $uploadOk = 1;
 }

 // Check file size
 if ($_FILES["fileToUpload"]["size"] > 10000000) {
  echo "Sorry, your file is too large.";
  $uploadOk = 1;
 }

 // Allow certain file formats
 if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "PNG"
 && $imageFileType != "gif" ) {
  echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
  $uploadOk = 0;
 }

 // Check if $uploadOk is set to 0 by an error
 if ($uploadOk == 0) {
  echo "Sorry, your file was not uploaded.";
 // if everything is ok, try to upload file
 } else {
  // move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file);
  // $query=mysqli_query($con, "UPDATE `membres` SET `photo` = '$aimg' WHERE `membres`.`id-membre` = 265");

  if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
 //   echo "The file ". htmlspecialchars( basename( $_FILES["fileToUpload"]["name"])). " has been uploaded.";
	// $query=mysqli_query($con, "update membres set photo='$aimg' where id-membre='$aniid'");
  $query=mysqli_query($con, "UPDATE `membres` SET `photo` = '$aimg' WHERE `membres`.`id-membre` = 265");
  
	header('Location: http://poker31.org');
  } else {
    echo "Sorry, there was an error uploading your file.";
  }
 };

 ?>
 <!-- <script type="text/javascript">window.location.replace("http://www.poker31.org/panel/manage-individu.php");</script>  -->

