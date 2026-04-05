<?php
session_start();
if (isset($_SESSION['login'])) {
  header("Location: /panel/dashboard.php");
  die();
}


include('config.php');
$msg = "";
$Error_Pass = "";
if (isset($_GET['Verification'])) {
  $raquet = mysqli_query($mysqli, "SELECT * FROM joueurs WHERE CodeV='{$_GET['Verification']}'");
  if (mysqli_num_rows($raquet) > 0) {
    $query = mysqli_query($mysqli, "UPDATE joueurs SET verification='1' WHERE CodeV='{$_GET['Verification']}'");
    if ($query) {
      $rowv = mysqli_fetch_assoc($raquet);
//      header("Location: /panel/dashboard.php?id='{$rowv['id']}'");
	  header("Location: /panel/dashboard.php");
    }else{
      header("Location: /reg/index.php");
    }
  } else {
    header("Location: /reg/index.php");
  }
}
if (isset($_POST['submit'])) {
  $email = mysqli_real_escape_string($mysqli, $_POST['email']);
//  $Pass = mysqli_real_escape_string($mysqli, md5($_POST['Password']));
    $Pass = mysqli_real_escape_string($mysqli, $_POST['Password']);

  $sql = "SELECT * FROM joueurs WHERE email='{$email}' and Password='{$Pass}'";
  $resulte = mysqli_query($mysqli, $sql);
  if (mysqli_num_rows($resulte) === 1) {
    $row = mysqli_fetch_assoc($resulte);
    if ($row['verification'] === '1') {
		$_SESSION['login']=$row['prenom'];
		$_SESSION['id']=$row['id'];
  //    $_SESSION['Email_Session']=$email;
      header("Location: /panel/dashboard.php");
    }else{$msg = "<div class='alert alert-info'>First Verify Your Account</div>";}
  }else{
    $msg = "<div class='alert alert-danger'>Email or Password is not match</div>";
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src="https://kit.fontawesome.com/64d58efce2.js" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="style.css" />
  <title>Sign in & Sign up Form</title>
  <style>
    .alert {
      padding: 1rem;
      border-radius: 5px;
      color: white;
      margin: 1rem 0;
      font-weight: 500;
      width: 65%;
    }

    .alert-success {
      background-color: #42ba96;
    }

    .alert-danger {
      background-color: #fc5555;
    }

    .alert-info {
      background-color: #2E9AFE;
    }

    .alert-warning {
      background-color: #ff9966;
    }
    .Forget-Pass{
      display: flex;
      width: 65%;
    }
    .Forget{
      color: #2E9AFE;
      font-weight: 500;
      text-decoration: none;
      margin-left: auto;
      
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="forms-container">
      <div class="signin-signup">
        <form action="" method="POST" class="sign-in-form">
          <h2 class="title">Authentification</h2>
          <?php echo $msg ?>
          <div class="input-field">
            <i class="fas fa-user"></i>
            <input type="text" name="email" placeholder="Email" />
          </div>
          <div class="input-field">
            <i class="fas fa-lock"></i>
            <input type="password" name="Password" placeholder="Password" />
          </div>
          <div class="Forget-Pass">
          <a href="Forget.php" class="Forget">Mot de passe oublié ?</a></div>
          <input type="submit" name="submit" value="Valider" class="btn solid" />
          <p class="social-text">Accés via vos réseaux sociaux</p>
          <div class="social-media">
            <a href="#" class="social-icon">
              <i class="fab fa-facebook-f"></i>
            </a>
            <a href="#" class="social-icon">
              <i class="fab fa-twitter"></i>
            </a>
            <a href="#" class="social-icon">
              <i class="fab fa-google"></i>
            </a>
            <a href="#" class="social-icon">
              <i class="fab fa-linkedin-in"></i>
            </a>
          </div>
        </form>
      </div>
    </div>

    <div class="panels-container">
      <div class="panel left-panel">
        <div class="content">
          <h3>Nouveau ?</h3>
          <p>
            Si vous n avez pas encore de compte veuillez en créer un IcI
          </p>
          <a href="SignUp.php" class="btn transparent" id="sign-in-btn" style="padding:10px 20px;text-decoration:none">
            Création Compte
          </a>
        </div>
        <img src="img/log.svg" class="image" alt="" />
      </div>
    </div>
  </div>

  <script src="app.js"></script>
</body>

</html>