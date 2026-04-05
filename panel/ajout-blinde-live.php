<?php
session_start();
error_reporting(0);
include('include/config.php');

if(strlen($_SESSION['id']==0)) {
    header('location:logout.php');
} else {
    $id_activite = intval($_GET['id-activite']);
    $ordre_param = isset($_GET['ordre']) ? intval($_GET['ordre']) : 0;
    
    if(isset($_POST['submit'])) {
        $ordre = intval($_POST['ordre']);
        $sb = intval($_POST['sb']);
        $bb = intval($_POST['bb']);
        $ante = intval($_POST['ante']);
        $minutes = intval($_POST['duree']);
        
        // Décaler les ordres si l'ordre existe déjà
        $check_ordre = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `id-activite` = '$id_activite' AND `ordre` = '$ordre'");
        
        if(mysqli_num_rows($check_ordre) > 0) {
            mysqli_query($con, "UPDATE `blindes-live` SET `ordre` = `ordre` + 1 WHERE `id-activite` = '$id_activite' AND `ordre` >= '$ordre'");
        }
        
        // Récupérer la blinde précédente (ordre - 1) pour calculer le timestamp de fin
        date_default_timezone_set('Europe/Paris');
        $ordre_precedent = $ordre - 1;
        $req_blinde_precedente = mysqli_query($con, "SELECT `fin` FROM `blindes-live` WHERE `id-activite` = '$id_activite' AND `ordre` = '$ordre_precedent'");
        $blinde_precedente = mysqli_fetch_array($req_blinde_precedente);
        
        if($blinde_precedente && $blinde_precedente['fin']) {
            $start_time = strtotime($blinde_precedente['fin']);
        } else {
            $start_time = time();
        }
        
        $fin_time = $start_time + ($minutes * 60);
        $fin = date('Y-m-d H:i:s', $fin_time);
        
        $sql = mysqli_query($con, "INSERT INTO `blindes-live` (`id-activite`, `ordre`, `sb`, `bb`, `ante`, `minutes`, `fin`) VALUES ('$id_activite', '$ordre', '$sb', '$bb', '$ante', '$minutes', '$fin')");
        
        if($sql) {
            // Mettre à jour les fins des blindes suivantes
            $req_blindes_suivantes = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `id-activite` = '$id_activite' AND `ordre` > '$ordre' ORDER BY `ordre` ASC");
            
            $current_fin = strtotime($fin);
            
            while($blinde_suivante = mysqli_fetch_array($req_blindes_suivantes)) {
                $next_fin_time = $current_fin + ($blinde_suivante['minutes'] * 60);
                $next_fin = date('Y-m-d H:i:s', $next_fin_time);
                
                mysqli_query($con, "UPDATE `blindes-live` SET `fin` = '$next_fin' WHERE `id` = '".$blinde_suivante['id']."'");
                
                $current_fin = $next_fin_time;
            }
            
            $_SESSION['msg'] = "Blinde ajoutée avec succès";
            header("location:/panel/voir-blindes.php?uid=$id_activite&onglet=2");
            exit;
        } else {
            $_SESSION['msg'] = "Erreur lors de l'ajout de la blinde";
        }
    }
    
    $req_activite = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = '$id_activite'");
    $activite = mysqli_fetch_array($req_activite);
    
    $next_ordre = $ordre_param > 0 ? $ordre_param + 1 : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin | Ajouter Blinde Live</title>    
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
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/plugins.css">
    <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
</head>
<body>
    <div id="app">        
        <?php include('include/sidebar.php');?>
        <div class="app-content">                
            <?php include('include/header.php');?>                    
            <div class="main-content" >
                <div class="wrap-content container" id="container">
                    <section id="page-title">
                        <div class="row">
                            <div class="col-sm-8">
                                <h1 class="mainTitle">Admin | Ajouter une Blinde Live</h1>
                            </div>
                            <ol class="breadcrumb">
                                <li><span>Admin</span></li>
                                <li class="active"><span>Ajouter Blinde Live</span></li>
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
                                                <h5 class="panel-title">Ajouter une nouvelle blinde pour: <?php echo htmlspecialchars($activite['titre-activite'], ENT_QUOTES); ?></h5>
                                            </div>
                                            <div class="panel-body">
                                                <p style="color:red;"><?php echo htmlentities($_SESSION['msg']);?></p>
                                                <?php $_SESSION['msg']="";?>
                                                <form role="form" method="post" name="ajout_blinde">
                                                    <div class="form-group">
                                                        <label for="ordre">Ordre</label>
                                                        <input type="number" name="ordre" class="form-control" placeholder="Ordre de la blinde" value="<?php echo $next_ordre; ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="sb">Small Blind (SB)</label>
                                                        <input type="number" step="0.01" name="sb" class="form-control" placeholder="Valeur du SB" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="bb">Big Blind (BB)</label>
                                                        <input type="number" step="0.01" name="bb" class="form-control" placeholder="Valeur du BB" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="ante">Ante</label>
                                                        <input type="number" step="0.01" name="ante" class="form-control" placeholder="Valeur de l'ante" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="duree">Durée (Minutes)</label>
                                                        <input type="number" name="duree" class="form-control" placeholder="15" required>
                                                    </div>
                                                    
                                                    <button type="submit" name="submit" class="btn btn-o btn-primary">Ajouter la Blinde</button>
                                                    <a href="/panel/voir-blindes.php?uid=<?php echo $id_activite; ?>&onglet=2" class="btn btn-secondary">Annuler</a>
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
        <?php include('include/footer.php');?>
        <?php include('include/setting.php');?>
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
    <script>jQuery(document).ready(function() {Main.init();FormElements.init();});</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="../js/scripts.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
    <script src="../js/datatables-simple-demo.js"></script>
</body>
</html>
<?php } ?>
