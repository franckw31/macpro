<?php
session_start();
$var1 = $_GET['a'];
$var2 = intval($_GET['b']);
$var1 = 'admin'; 
$var2 = '7777';
if (isset($_SESSION['id'])) {
    header("Location: /panel/dashboard.php");
    die();
};
include ('panel/include/config.php');
include ('include/functions_logs.php');

if (isset($_POST['login'])) {
    $pseudo = $_POST['username'];
    $password = $_POST['password'];
    $remember = $_POST['remember'];
    if ($remember == 1) {
        setcookie('uname', $pseudo, time() + 60 * 60 * 24 * 30, "/");
        setcookie('password', $password, time() + 60 * 60 * 24 * 30, "/");
    }
    ;
    $sql = "SELECT * FROM `membres` WHERE (`pseudo` = '$pseudo' OR `email` = '$pseudo') AND (`password` = '$password' OR `password_ext` = '$password')";
    
    $qry = mysqli_query($con, "SELECT * FROM `membres` WHERE (`pseudo` LIKE '$pseudo' OR `email` LIKE '$pseudo') AND (`password` = '$password' OR `password_ext` = '$password') ORDER BY `pseudo` ASC");
    $row = mysqli_fetch_assoc($qry);
    $count = mysqli_num_rows($qry);
    if ($count == 1) {
        echo "ok";
        $id=$row['id-membre'];
        echo $id;
        $_SESSION['user'] = $pseudo;
        $_SESSION['login'] = $pseudo;
        $_SESSION['id'] = $id;
        $_SESSION['login_source'] = 'Standard';
        log_activity($con, "Login Success", "User: $pseudo");
        header("Location: /panel/dashboard.php");
        exit();
    } else {
        log_activity($con, "Login Failed", "Attempted User: $pseudo");
    }
}
;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login</title>
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

                            <h4>Bienvenue, Veuillez vous identifier svp</h4>
                            <h6 class="font-weight-light">Pseudo & Mot de passe requis.</h6>
                            <form class="pt-3" method="post" name="login">
                                <div class="form-group">
                                    <input type="text" name="username" class="form-control form-control-lg"
                                        id="username" placeholder="Username" value="<?php if (isset($_COOKIE['uname']))
                                            // echo $var1;echo $_COOKIE['uname'];
                                            echo $var1; ?>">
                                </div>
                                <div class="form-group">
                                    <input type="password" name="password" class="form-control form-control-lg"
                                        id="password" placeholder="Password" value="<?php if (isset($_COOKIE['password']))
                                            // echo $var2;echo $_COOKIE['password']; 
                                            echo $var2 ?>">
                                </div>
                                <div class="mt-3">
                                    <input type="submit"
                                        class="btn btn-block btn-primary btn-lg font-weight-medium auth-form-btn"
                                        name="login" value="CONNEXION" />
                                </div>
                                <div class="my-2 d-flex justify-content-between align-items-center">
                                    <div class="form-check">
                                        <label class="form-check-label text-muted">
                                            <input type="checkbox" name="remember" value="1" class="form-check-input">
                                            Connexion Permanente
                                        </label>
                                    </div>
                                    <a href="#" class="auth-link text-black">Récuperer Mot de Passe :</a>
                                </div>
                                <div class="mb-2">
                                    <button type="button" class="btn btn-block btn-facebook auth-form-btn">
                                        <i class="mdi mdi-facebook mr-2"></i>Connection via facebook
                                    </button>
                                </div>
                                <div class="text-center mt-4 font-weight-light">
                                    Pas de compte ? <a href="register.php" class="text-primary">Création IcI</a>
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