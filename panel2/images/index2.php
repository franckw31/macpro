<?php
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
$_SESSION["id"] = 1; // User's session
$sessionId = $_SESSION["id"];
$user = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` = 265"));
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
    <meta charset="utf-8">
    <title>Update Image Profile</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  </head>
  <body>
    <form class="form" id = "form" action="" enctype="multipart/form-data" method="post">
      <div class="upload">
        <?php
        $id = $user["id-membre"];
        $name = $user["pseudo"];
        $image = $user["photo"];
        ?>
        <img src="/panel/images/<?php echo $image; ?>" width = 125 height = 125 title="<?php echo $image; ?>">
        <div class="round">
          <input type="hidden" name="id-membre" value="<?php echo $id; ?>">
          <input type="hidden" name="pseudo" value="<?php echo $name; ?>">
          <input type="file" name="photo" id = "photo" accept=".jpg, .jpeg, .png">
          <i class = "fa fa-camera" style = "color: #fff;"></i>
        </div>
      </div>
    </form>
    <script type="text/javascript">
      document.getElementById("photo").onchange = function(){
          document.getElementById("form").submit();
      };
    </script>
    <?php
    if(isset($_FILES["photo"]["pseudo"])){
      $id = $_POST["id-membre"];
      $name = $_POST["pseudo"];

      $imageName = $_FILES["photo"]["pseudo"];
      $imageSize = $_FILES["photo"]["size"];
      $tmpName = $_FILES["photo"]["tmp_name"];

      // Image validation
      $validImageExtension = ['jpg', 'jpeg', 'png'];
      $imageExtension = explode('.', $imageName);
      $imageExtension = strtolower(end($imageExtension));
      if (!in_array($imageExtension, $validImageExtension)){
        echo
        "
        <script>
          alert('Invalid Image Extension');
          document.location.href = '../updateimageprofile';
        </script>
        ";
      }
      elseif ($imageSize > 1200000){
        echo
        "
        <script>
          alert('Image Size Is Too Large');
          document.location.href = '../updateimageprofile';
        </script>
        ";
      }
      else{
        $newImageName = $name . " - " . date("Y.m.d") . " - " . date("h.i.sa"); // Generate new image name
        $newImageName .= '.' . $imageExtension;
        // $query = "UPDATE tb_user SET image = '$newImageName' WHERE id = $id";
        $query = " UPDATE `membres` SET `photo` = '$newImageName' WHERE `membres`.`id-membre` = 265";
        mysqli_query($con, $query);
        move_uploaded_file($tmpName, '/panel/images/' . $newImageName);
        echo
        "
        <script>
        document.location.href = '../updateimageprofile';
        </script>
        ";
      }
    }
    ?>
  </body>
</html>