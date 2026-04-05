<?php
include('panel/include/config.php');
require_once 'serveur-smtp/send.php';

if(isset($_POST['register']))
{
	$username = $_POST['username'];
	$password = $_POST['password'];
	$email = $_POST['email'];
	$country = $_POST['country'];
  $sql = "INSERT INTO `membres` (`pseudo`, `email`, `password`, `country`) VALUES('$username','$email','$password','$country')";
  $qry = mysqli_query($con, $sql) or die("error");
  
  // Envoi de l'email de bienvenue
  $subject = "Bienvenue sur Poker31 !";
  $body = "<h1>Bienvenue $username !</h1><p>Votre compte a été créé avec succès. Vous pouvez maintenant vous connecter.</p>";
  sendRealEmail($email, $subject, $body);

  header("Location: index.php");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Register</title>
    <!-- plugins:css -->
    <link rel="stylesheet" href="vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="vendors/base/vendor.bundle.base.css">
    <!-- endinject -->
    <!-- plugin css for this page -->
    <!-- End plugin css for this page -->
    <!-- inject:css -->
    <link rel="stylesheet" href="css/style.css">
    <!-- endinject -->
    <link rel="shortcut icon" href="images/favicon.png" />
</head>

<body>
    <div class="container-scroller">
        <div class="container-fluid page-body-wrapper full-page-wrapper">
            <div class="content-wrapper d-flex align-items-center auth px-0">
                <div class="row w-100 mx-0">
                    <div class="col-lg-4 mx-auto">
                        <div class="auth-form-light text-left py-5 px-4 px-sm-5">

                            <h4>Nouveau Compte ?</h4>
                            <h6 class="font-weight-light">Enregistrement rapide ...</h6>
                            <form class="pt-3" method="POST" name="register">
                                <div class="form-group">
                                    <input type="text" name="username" class="form-control form-control-lg"
                                        id="username" placeholder="Pseudo">
                                </div>
                                <div class="form-group">
                                    <input type="email" name="email" class="form-control form-control-lg" id="email"
                                        placeholder="Email">
                                </div>
                                <div class="form-group">
                                    <select name="country" class="form-control form-control-lg" id="country">
                                        <option value="">Type de Jeux</option>
                                        <option value="T">Tournois</option>
                                        <option value="C">CashGame</option>
                                        <option value="O">Omaha</option>
                                        <option value="A">Tous</option>

                                    </select>
                                </div>
                                <div class="form-group">
                                    <input type="password" name="password" class="form-control form-control-lg"
                                        id="password" placeholder="Mot de Passe">
                                </div>
                                <div class="mb-4">
                                    <div class="form-check">
                                        <label class="form-check-label text-muted">
                                            <input type="checkbox" name="iagree" value="1" class="form-check-input">
                                            J'approuve la charte de bienveillance
                                        </label>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <input type="submit" name="register"
                                        class="btn btn-block btn-primary btn-lg font-weight-medium auth-form-btn"
                                        value="CREATION" />

                                </div>
                                <div class="text-center mt-4 font-weight-light">
                                    Compte déja créé ? <a href="index.php" class="text-primary">S'identifier</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <!-- content-wrapper ends -->
        </div>
        <!-- page-body-wrapper ends -->
    </div>
    <!-- container-scroller -->
    <!-- plugins:js -->
    <script src="vendors/base/vendor.bundle.base.js"></script>
    <!-- endinject -->
    <!-- inject:js -->
    <script src="js/off-canvas.js"></script>
    <script src="js/hoverable-collapse.js"></script>
    <script src="js/template.js"></script>
    <!-- endinject -->
</body>

</html>