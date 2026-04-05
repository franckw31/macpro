<?php
session_start();
if (isset($_POST['login'])) {
    $pseudo = $_POST['username'];
    $password = $_POST['password'];
    $remember = $_POST['remember'];
    if (1) {
        setcookie('uname', $pseudo, time() + 60 * 60 * 24 * 10, "/");
        setcookie('password', '', time() + 60 * 60 * 24 * 10, "/");
    }
    ;
    $sql = "SELECT * FROM `membres` WHERE (`pseudo` = '$pseudo' OR `email` = '$pseudo') AND (`password` = '$password' OR `password_ext` = '$password')";
}
;
echo "av inc";
// include ('db.php');
include('panel/include/config.php');
echo "ap inc av qry";
echo $pseudo . $password;
$qry = mysqli_query($con, "SELECT * FROM `membres` WHERE (`pseudo` LIKE '$pseudo' OR `email` LIKE '$pseudo') AND (`password` = '$password' OR `password_ext` = '$password') ORDER BY `pseudo` ASC");
echo "ap qry";
$count = mysqli_num_rows($qry);
echo $count;
if (1) {
    echo "ok";
    $_SESSION['user'] = $username;
    header("Location: index.html");
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

                            <h4>Hello!!! let's get started</h4>
                            <h6 class="font-weight-light">Sign in to continue.</h6>
                            <form class="pt-3" method="post" name="login">
                                <div class="form-group">
                                    <input type="text" name="username" class="form-control form-control-lg"
                                        id="username" placeholder="Username" value="<?php if (isset($_COOKIE['uname']))
                                            echo $_COOKIE['uname']; ?>">
                                </div>
                                <div class="form-group">
                                    <input type="password" name="password" class="form-control form-control-lg"
                                        id="password" placeholder="Password" value="<?php if (isset($_COOKIE['password']))
                                            echo $_COOKIE['password']; ?>">
                                </div>
                                <div class="mt-3">
                                    <input type="submit"
                                        class="btn btn-block btn-primary btn-lg font-weight-medium auth-form-btn"
                                        name="login" value="SIGN IN" />
                                </div>
                                <div class="my-2 d-flex justify-content-between align-items-center">
                                    <div class="form-check">
                                        <label class="form-check-label text-muted">
                                            <input type="checkbox" class="form-check-input">
                                            Keep me signed in
                                        </label>
                                    </div>
                                    <a href="#" class="auth-link text-black">Forgot password?</a>
                                </div>
                                <div class="mb-2">
                                    <button type="button" class="btn btn-block btn-facebook auth-form-btn">
                                        <i class="mdi mdi-facebook mr-2"></i>Connect using facebook
                                    </button>
                                </div>
                                <div class="text-center mt-4 font-weight-light">
                                    Don't have an account? <a href="register.php" class="text-primary">Create</a>
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