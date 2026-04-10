<?php
// https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css 
define('DB_SERVER','db5011397709.hosting-data.io');
define('DB_USER','dbu5472475');
define('DB_PASS' ,'Kookies7*');
define('DB_NAME', 'dbs9616600');
$con = mysqli_connect(DB_SERVER,DB_USER,DB_PASS,DB_NAME);
// Check connection
if (mysqli_connect_errno())
{
 echo "Failed to connect to MySQL: " . mysqli_connect_error();
}


$_SESSION["id"] = 1; // Fill session id with user's id to get user's data like name and image name
$sessionId = $_SESSION["id"];
// $user = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM membres WHERE id-membre = $sessionId"));
$user = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` = 265"));
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
    <meta charset="utf-8">
    <title>Update Image</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  </head>
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
    <form class="form" id = "form" action="" enctype="multipart/form-data" method="post">
      <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
      <div class="upload">
        <img src="/panel/images/<?php echo $user['photo']; ?>" id = "photo">

        <div class="rightRound" id = "upload">
          <input type="file" name="fileImg" id = "fileImg" accept=".jpg, .jpeg, .png">
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

    <!-- <script type="text/javascript">
      document.getElementById("fileImg").onchange = function(){
        document.getElementById("photo").src = URL.createObjectURL(fileImg.files[0]); // Preview new image

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
    </script> -->
   <script type="text/javascript">
      document.getElementById("fileImg").onchange = function(){
        document.getElementById("photo").src = URL.createObjectURL(fileImg.files[0]); // Preview new image

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
    echo $_FILES["fileImg"]["tmp_name"];
     move_uploaded_file($_FILES["fileImg"]["tmp_name"], "/panel/images/titi.jpg");
   if(isset($_FILES["fileImg"]["name"])){
    // move_uploaded_file($_FILES["fileImg"]["tmp_name"], "/panel/images/titi.jpg");
      $id = $_POST["id"];

      $src = $_FILES["fileImg"]["tmp_name"];
      $imageName = uniqid() . $_FILES["fileImg"]["name"];

      $target = "/panel/images/" . $imageName;
            echo "-".$src."-"."        /".$target."/".$id;
      move_uploaded_file($src, $target);

    //   $query = "UPDATE 'members' SET 'photo' = '$imageName' WHERE 'id-membre' = $id";
      $query = "UPDATE `membres` SET `photo` = '$imageName' WHERE `membres`.`id-membre` = 265";
      mysqli_query($con, $query);

      header("Location: index.php");
    }
    ?>
  </body>
</html>