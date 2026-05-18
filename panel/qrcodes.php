<?php
session_start();
error_reporting(0);
include(__DIR__ . '/include/config.php');

if (strlen($_SESSION['login']) == 0) {
    $_SESSION['redirect'] = 'panel/qrcodes.php';
    header('location:logout.php');
    exit;
} else {
    if (!isset($_SESSION['id']) || $_SESSION['id'] == 0) {
        $login = $_SESSION['login'];
        $q_u = mysqli_query($con, "SELECT `id-membre` FROM membres WHERE pseudo = '$login'");
        if ($r_u = mysqli_fetch_array($q_u)) {
            $_SESSION['id'] = $r_u['id-membre'];
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <title>Admin | QR Codes</title>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimum-scale=1.0, maximum-scale=1.0">
        <link href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" />
        <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
        <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
        <link rel="stylesheet" href="vendor/themify-icons/themify-icons.min.css">
        <link href="vendor/animate.css/animate.min.css" rel="stylesheet" media="screen">
        <link href="vendor/perfect-scrollbar/perfect-scrollbar.min.css" rel="stylesheet" media="screen">
        <link href="vendor/switchery/switchery.min.css" rel="stylesheet" media="screen">
        <link href="vendor/bootstrap-touchspin/jquery.bootstrap-touchspin.min.css" rel="stylesheet" media="screen">
        <link href="vendor/select2/select2.min.css" rel="stylesheet" media="screen">
        <link href="vendor/bootstrap-datepicker/bootstrap-datepicker3.standalone.min.css" rel="stylesheet" media="screen">
        <link href="vendor/bootstrap-timepicker/bootstrap-timepicker.min.css" rel="stylesheet" media="screen">
        <link rel="stylesheet" href="assets/css/styles.css">
        <link rel="stylesheet" href="assets/css/plugins.css">
        <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
        <link rel="stylesheet" href="assets/css/modern-dashboard.css">
    </head>

    <body>
        <div id="app">
            <?php
            $fiche = $_SESSION['id'];
            include('include/sidebar.php');
            ?>
            <div class="app-content">
                <?php include('include/header.php'); ?>

                <div class="main-content">
                    <div class="wrap-content container" id="container">
                        <section id="page-title">
                            <div class="row">
                                <div class="col-sm-12 text-center">
                                    <span class="mainDescription">.</span>
                                    <h2 class="mainTitle" style="color:white">Gestion des QR Codes</h2>
                                </div>
                            </div>
                        </section>

                        <div class="row">
                            <div class="col-sm-4">
                                <a href="/qrcode/index.php" class="dashboard-card card-blue">
                                    <div class="card-icon"><i class="fa fa-qrcode"></i></div>
                                    <div class="card-title">Création QR & NFC Membre</div>
                                    <div class="card-description">Générer des QRcodes ou NFC</div>
                                </a>
                            </div>
                            <div class="col-sm-4">
                                <a href="/qrcode/verify_membre.php" class="dashboard-card card-purple">
                                    <div class="card-icon"><i class="fa fa-user"></i></div>
                                    <div class="card-title">Verif Membre</div>
                                    <div class="card-description">Identifier un membre par scan</div>
                                </a>
                            </div>
                            <div class="col-sm-4">
                                <a href="/qrcode/liste_assignations.php" class="dashboard-card card-blue">
                                    <div class="card-icon"><i class="fa fa-list"></i></div>
                                    <div class="card-title">Liste & Assignations PokeCard</div>
                                    <div class="card-description">Voir les QR codes et leurs propriétaires</div>
                                </a>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-4">
                                <a href="/qrcode/read_qrcode.php" class="dashboard-card card-green">
                                    <div class="card-icon"><i class="fa fa-upload"></i></div>
                                    <div class="card-title">Création PokeCard</div>
                                    <div class="card-description">Scanner ou charger un QR code</div>
                                </a>
                            </div>
                            <div class="col-sm-4">
                                <a href="/qrcode/verify_qrcode.php" class="dashboard-card card-orange">
                                    <div class="card-icon"><i class="fa fa-check"></i></div>
                                    <div class="card-title">Verif PokeCard</div>
                                    <div class="card-description">Contrôler et valider les QR codes</div>
                                </a>
                            </div>
                            <div class="col-sm-4">
                                <a href="/qrcode/affectation.php" class="dashboard-card card-teal">
                                    <div class="card-icon"><i class="fa fa-link"></i></div>
                                    <div class="card-title">Affectation PokeCard -> Membre</div>
                                    <div class="card-description">Lier un QR code à un membre</div>
                                </a>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-4">
                                <a href="/qrcode/affectation_collection_activite.php" class="dashboard-card card-orange">
                                    <div class="card-icon"><i class="fa fa-calendar"></i></div>
                                    <div class="card-title">Affectation Collection / Activité</div>
                                    <div class="card-description">Associer des collections QR à une activité</div>
                                </a>
                            </div>
                            <div class="col-sm-4">
                                <a href="/panel/tombolas.php?sort=pseudo" class="dashboard-card card-green">
                                    <div class="card-icon"><i class="fa fa-ticket"></i></div>
                                    <div class="card-title">Tombolas</div>
                                    <div class="card-description">Gestion des tombolas et tickets</div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include('include/footer.php'); ?>
            <?php include('include/setting.php'); ?>
        </div>

        <script src="vendor/jquery/jquery.min.js"></script>
        <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
        <script src="vendor/modernizr/modernizr.js"></script>
        <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
        <script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
        <script src="vendor/switchery/switchery.min.js"></script>
        <script src="vendor/maskedinput/jquery.maskedinput.min.js"></script>
        <script src="vendor/bootstrap-touchspin/jquery.bootstrap-touchspin.min.js"></script>
        <script src="vendor/autosize/autosize.min.js"></script>
        <script src="vendor/selectFx/classie.js"></script>
        <script src="vendor/selectFx/selectFx.js"></script>
        <script src="vendor/select2/select2.min.js"></script>
        <script src="vendor/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
        <script src="vendor/bootstrap-timepicker/bootstrap-timepicker.min.js"></script>
        <script src="assets/js/main.js"></script>
        <script src="assets/js/form-elements.js"></script>
        <script>
            jQuery(document).ready(function () {
                Main.init();
                FormElements.init();
            });
        </script>
    </body>

    </html>
<?php }