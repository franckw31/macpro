<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');

if(strlen($_SESSION['id']) == 0) {
    header('location:logout.php');
    exit;
} else {
    $id = intval($_GET['id']);
    
    // Récupérer les données du challenge
    $sql = mysqli_query($con, "SELECT * FROM `challenge` WHERE `id_challenge` = '$id'");
    $challenge = mysqli_fetch_array($sql);
    
    if(!$challenge) {
        $_SESSION['msg'] = "Challenge non trouvé!";
        header('location:gestion-challenge.php');
        exit;
    }
    
    // Traiter la soumission du formulaire
    if(isset($_POST['submit'])) {
        $titre = mysqli_real_escape_string($con, $_POST['titre_challenge']);
        $com = mysqli_real_escape_string($con, $_POST['chal_com']);
        $deb = mysqli_real_escape_string($con, $_POST['chal_deb']);
        $fin = mysqli_real_escape_string($con, $_POST['chal_fin']);
        $org = mysqli_real_escape_string($con, $_POST['chal_org']);
        
        $update_sql = mysqli_query($con, "UPDATE challenge SET titre_challenge='$titre', chal_com='$com', chal_deb='$deb', chal_fin='$fin', chal_org='$org' WHERE id_challenge='$id'");
        
        if($update_sql) {
            $_SESSION['msg'] = "Challenge mis à jour avec succès!";
            header('location:voir-challenge.php?id='.$id);
            exit;
        } else {
            $_SESSION['msg'] = "Erreur lors de la mise à jour!";
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin | Modifier Challenge</title>
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
</head>
<body>
    <div id="app">
        <?php include('include/sidebar.php'); ?>
        <div class="app-content">
            <?php include('include/header.php'); ?>
            <div class="main-content">
                <div class="wrap-content container" id="container">
                    <section id="page-title">
                        <div class="row">
                            <div class="col-sm-8">
                                <h1 class="mainTitle">Modifier Challenge</h1>
                            </div>
                            <ol class="breadcrumb">
                                <li>
                                    <span>Admin</span>
                                </li>
                                <li class="active">
                                    <span>Modifier Challenge</span>
                                </li>
                            </ol>
                        </div>
                    </section>

                    <div class="container-fluid container-fullw bg-white">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="row margin-top-30">
                                    <div class="col-lg-8 col-md-12">
                                        <div class="panel panel-white">
                                            <div class="panel-heading">
                                                <h3 class="panel-title">Édition du Challenge</h3>
                                            </div>
                                            <div class="panel-body">
                                                <?php if(isset($_SESSION['msg']) && !empty($_SESSION['msg'])): ?>
                                                    <div class="alert alert-info">
                                                        <?php echo htmlspecialchars($_SESSION['msg']); 
                                                        $_SESSION['msg'] = "";
                                                        ?>
                                                    </div>
                                                <?php endif; ?>

                                                <form role="form" name="editChallenge" method="post" class="form-horizontal">
                                                    <div class="form-group">
                                                        <label class="col-sm-3 control-label">Titre</label>
                                                        <div class="col-sm-9">
                                                            <input type="text" class="form-control" name="titre_challenge" value="<?php echo htmlspecialchars($challenge['titre_challenge']); ?>" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-group">
                                                        <label class="col-sm-3 control-label">Commentaire</label>
                                                        <div class="col-sm-9">
                                                            <textarea class="form-control" name="chal_com" rows="4"><?php echo htmlspecialchars($challenge['chal_com']); ?></textarea>
                                                        </div>
                                                    </div>

                                                    <div class="form-group">
                                                        <label class="col-sm-3 control-label">Date de Début</label>
                                                        <div class="col-sm-9">
                                                            <input type="date" class="form-control" name="chal_deb" value="<?php echo htmlspecialchars($challenge['chal_deb']); ?>" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-group">
                                                        <label class="col-sm-3 control-label">Date de Fin</label>
                                                        <div class="col-sm-9">
                                                            <input type="date" class="form-control" name="chal_fin" value="<?php echo htmlspecialchars($challenge['chal_fin']); ?>" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-group">
                                                        <label class="col-sm-3 control-label">Organisateur</label>
                                                        <div class="col-sm-9">
                                                            <input type="text" class="form-control" name="chal_org" value="<?php echo htmlspecialchars($challenge['chal_org']); ?>">
                                                        </div>
                                                    </div>

                                                    <div class="form-group">
                                                        <div class="col-sm-9 col-sm-offset-3">
                                                            <button type="submit" name="submit" class="btn btn-primary">
                                                                <i class="fa fa-save"></i> Enregistrer
                                                            </button>
                                                            <a href="voir-challenge.php?id=<?php echo $id; ?>" class="btn btn-default">
                                                                <i class="fa fa-times"></i> Annuler
                                                            </a>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
    <script src="assets/js/main.js"></script>
    <script>
        jQuery(document).ready(function () {
            Main.init();
        });
    </script>
</body>
</html>
<?php
}
?>
