<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include ('include/config.php');
if (empty($_SESSION['id'])) {
    header('location:logout.php');
} else {

    
    if (isset($_POST['submit'])) {
        $idmembre = (int)$_SESSION['id'];
        $titreactivite = mysqli_real_escape_string($con, $_POST['titre-activite']);
        $date_depart = $_POST['date_depart'] . ' ' . $_POST['heure_depart'] . ':00'; // Combine date et heure
        $ville = mysqli_real_escape_string($con, $_POST['ville']);
        $places = (int)$_POST['places'];
        $rake = (int)$_POST['rake'];
        $buyin = (int)$_POST['buyin'];
        $bounty = (int)$_POST['bounty'];
        $recave = (int)$_POST['recave'];
        $ante = mysqli_real_escape_string($con, $_POST['ante']);
        $longitude = (double)$_POST['longitude'];
        $latitude = (float)$_POST['latitude'];
        $idstructure = (int)$_POST['id_structure'];
        $jetons = (int)$_POST['jetons'];
        $bonus = (int)$_POST['bonus'];
        $addon = (int)$_POST['addon'];
        
        date_default_timezone_set('Arctic/Longyearbyen');
        
        // Vérifier la connexion
        if (!$con) {
            die("Erreur de connexion : " . mysqli_connect_error());
        }
        
        // Utiliser id_challenge = 4 par défaut (comme dans vos données)
        $id_challenge = 4;
        
        $stmt = mysqli_prepare($con, "INSERT INTO `activite` (`id_challenge`, `id_structure`, `id-membre`, `titre-activite`, `date_depart`, `ville`, `lng`, `lat`, `places`, `buyin`, `rake`, `bounty`, `jetons`, `recave`, `addon`, `ante`, `bonus`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            die("Erreur de préparation : " . mysqli_error($con));
        }
        
        // Types: i=int, s=string, d=double
        mysqli_stmt_bind_param($stmt, "iiisdsiiiiiiiiisi", $id_challenge, $idstructure, $idmembre, $titreactivite, $date_depart, $ville, $longitude, $latitude, $places, $buyin, $rake, $bounty, $jetons, $recave, $addon, $ante, $bonus);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['msg'] = "Activité ajoutée avec succés !!";
            $numact = mysqli_insert_id($con);
            mysqli_stmt_close($stmt);

            // --- Création automatique du groupe de chat ---
            $months = ["", "Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"];
            $d_obj = strtotime($date_depart);
            $formatted_date = date('j', $d_obj) . ' ' . $months[intval(date('n', $d_obj))];
            
            $res_org = mysqli_query($con, "SELECT pseudo FROM membres WHERE `id-membre` = '$idmembre'");
            $row_org = mysqli_fetch_assoc($res_org);
            $organizer_name = $row_org ? $row_org['pseudo'] : "Organisateur";
            
            $new_group_name = $formatted_date . " " . $organizer_name;
            
            // 1. Récupérer le dernier groupe pour copier les membres
            $res_last_grp = mysqli_query($con, "SELECT id FROM chat_groups ORDER BY id DESC LIMIT 1");
            if ($res_last_grp && mysqli_num_rows($res_last_grp) > 0) {
                $row_last_grp = mysqli_fetch_assoc($res_last_grp);
                $last_group_id = $row_last_grp['id'];
                
                // 2. Créer le nouveau groupe
                $stmt_grp = mysqli_prepare($con, "INSERT INTO chat_groups (name, created_by) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt_grp, "si", $new_group_name, $idmembre);
                
                if (mysqli_stmt_execute($stmt_grp)) {
                    $new_group_id = mysqli_insert_id($con);
                    mysqli_stmt_close($stmt_grp);
                    
                    // 3. Copier les membres du dernier groupe
                    $res_members = mysqli_query($con, "SELECT member_id FROM chat_group_members WHERE group_id = $last_group_id");
                    while ($member = mysqli_fetch_assoc($res_members)) {
                        $m_id = $member['member_id'];
                        mysqli_query($con, "INSERT IGNORE INTO chat_group_members (group_id, member_id) VALUES ($new_group_id, $m_id)");
                    }
                    
                    // S'assurer que le créateur est aussi dans le groupe s'il n'y était pas
                    mysqli_query($con, "INSERT IGNORE INTO chat_group_members (group_id, member_id) VALUES ($new_group_id, $idmembre)");
                }
            }
            // --- Fin création groupe ---

            header("Location: /panel/creation-blindes-init.php?act=$numact");
            exit;
        } else {
            $_SESSION['msg'] = "Erreur : " . mysqli_stmt_error($stmt);
            echo "Erreur d'exécution : " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    }
    ;
    //Code Deletion
    if (isset($_GET['del'])) {
        $sid = $_GET['id'];
        $del_stmt = mysqli_prepare($con, "DELETE FROM activite WHERE `id-activite` = ?");
        $del_stmt->bind_param("i", $sid);
        $del_stmt->execute();
        $_SESSION['msg'] = "data deleted !!";
    }
    ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Admin | Ajout Activité</title>
    <link
        href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic"
        rel="stylesheet" type="text/css" />
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
        <?php include ('include/sidebar.php'); ?>
        <div class="app-content">
            <?php include ('include/header.php'); ?>
            <!-- end: TOP NAVBAR -->
            <div class="main-content">
                <div class="wwrap-content container" id="container">
                    <!-- start: PAGE TITLE -->
                    <section id="page-title">
                        <div class="row">
                            <!--<div class="col-sm-8">
                                <h1 class="mainTitle">Admin | AJOUTER PARTIE </h1>
                            </div>-->
                            <ol class="breadcrumb">
                                <li>
                                    <span>Admin</span>
                                </li>
                                <li class="active">
                                    <span>Ajouter PARTIE</span>
                                </li>
                            </ol>
                        </div>
                    </section>
                    <!-- end: PAGE TITLE -->
                    <!-- start: BASIC EXAMPLE -->
                    <?php $idorg = $_SESSION['id'];
                        $qorg = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` = $idorg");
                        $result = mysqli_fetch_assoc($qorg);
                        $org = $result['id-membre'];
                        ?>
                    <div class="container-fluid container-fullw bg-white">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="row mmargin-top-30">
                                    <div class="col-lg-6 col-md-12">
                                        <div class="panel panel-white">
                                            <div class="panel-heading">
                                                <h5 class="panel-title">NOUVELLE PARTIE : </h5>
                                            </div>
                                            <div class="panel-body">
                                                <p style="color:red;">
                                                    <?php echo htmlentities($_SESSION['msg']); ?>
                                                    <?php echo htmlentities($_SESSION['msg'] = ""); ?>
                                                    <?php echo "-" . $org . "-"; ?>
                                                </p>
                                                <form role="form" name="dcotorspcl" method="post">
                                                    <div class="card-body">
                                                        <table class="table table-bordered">
                                                            <tr>
                                                                <th>Titre</th>
                                                                <td>
                                                                    <input class="form-control" id="titre-activite"
                                                                        name="titre-activite" type="text"
                                                                        value="<?php echo htmlspecialchars($result['titre-activite']); ?>"
                                                                        required />
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <th>Date</th>
                                                                <td><input class="form-control" id="date_depart"
                                                                        name="date_depart" type="date"
                                                                        value="<?php echo htmlspecialchars($result['date_depart']); ?>">
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <th>Heure</th>
                                                                <td><input class="form-control" id="heure_depart"
                                                                        name="heure_depart" type="time"
                                                                        value="<?php echo htmlspecialchars($result['heure_depart']); ?>">
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <th>Ville</th>
                                                                <td><input class="form-control" id="ville" name="ville"
                                                                        type="text"
                                                                        value="<?php echo htmlspecialchars($result['ville']); ?>"
                                                                        required /></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Longitude</th>
                                                                <td><input class="form-control" id="longitude"
                                                                        name="longitude" type="text"
                                                                        value="<?php echo htmlspecialchars($result['longitude']); ?>"
                                                                        required /></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Latitude</th>
                                                                <td><input class="form-control" id="latitude"
                                                                        name="latitude" type="text"
                                                                        value="<?php echo htmlspecialchars($result['latitude']); ?>"
                                                                        required /></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Nb Joueurs Max</th>
                                                                <td><input class="form-control" id="places"
                                                                        name="places" type="text"
                                                                        value="<?php echo htmlspecialchars($result['def_nbj']); ?>"
                                                                        required /></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Buyin</th>
                                                                <td><input class="form-control" id="buyin" name="buyin"
                                                                        type="text"
                                                                        value="<?php echo htmlspecialchars($result['def_buy']); ?>"
                                                                        required /></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Rake</th>
                                                                <td><input class="form-control" id="rake" name="rake"
                                                                        type="text"
                                                                        value="<?php echo htmlspecialchars($result['def_rak']); ?>"
                                                                        required /></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Bounty</th>
                                                                <td><input class="form-control" id="bounty"
                                                                        name="bounty" type="text"
                                                                        value="<?php echo htmlspecialchars($result['def_bou']); ?>"
                                                                        required /></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Nb Recave</th>
                                                                <td><input class="form-control" id="recave"
                                                                        name="recave" type="text"
                                                                        value="<?php echo htmlspecialchars($result['def_rec']); ?>"
                                                                        required /></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Addon</th>
                                                                <td><input class="form-control" id="addon" name="addon"
                                                                        type="text"
                                                                        value="<?php echo htmlspecialchars($result['def_add']); ?>"
                                                                        required /></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Ante</th>
                                                                <td><input class="form-control" id="ante" name="ante"
                                                                        type="text"
                                                                        value="<?php echo htmlspecialchars($result['def_ant']); ?>"
                                                                        required /></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Structure</th>
                                                                <td><input class="form-control" id="id_structure"
                                                                        name="id_structure" type="text"
                                                                        value="<?php echo htmlspecialchars($result['defstr']); ?>"
                                                                        required />
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <th>Stack</th>
                                                                <td><input class="form-control" id="jetons"
                                                                        name="jetons" type="text"
                                                                        value="<?php echo htmlspecialchars($result['def_jet']); ?>"
                                                                        required /></td>
                                                            </tr>
                                                            <tr>
                                                                <th>Bonus</th>
                                                                <td><input class="form-control" id="bonus" name="bonus"
                                                                        type="text"
                                                                        value="<?php echo htmlspecialchars($result['def_bon']); ?>"
                                                                        required /></td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="4" style="text-align:center ;"><button
                                                                        type="submit" class="btn btn-primary btn-block"
                                                                        name="submit">Creation</button></td>
                                                            </tr>
                                                        </table>
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
            <!-- end: BASIC EXAMPLE -->
            <!-- end: SELECT BOXES -->
        </div>
        <!-- start: FOOTER -->
        <?php include ('include/footer.php'); ?>
        <!-- end: FOOTER -->
        <!-- start: SETTINGS -->
        <?php include ('include/setting.php'); ?>
        <!-- end: SETTINGS -->
    </div>
    <!-- start: MAIN JAVASCRIPTS -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="vendor/modernizr/modernizr.js"></script>
    <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
    <script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="vendor/switchery/switchery.min.js"></script>
    <!-- end: MAIN JAVASCRIPTS -->
    <!-- start: JAVASCRIPTS REQUIRED FOR THIS PAGE ONLY -->
    <script src="vendor/maskedinput/jquery.maskedinput.min.js"></script>
    <script src="vendor/bootstrap-touchspin/jquery.bootstrap-touchspin.min.js"></script>
    <script src="vendor/autosize/autosize.min.js"></script>
    <script src="vendor/selectFx/classie.js"></script>
    <script src="vendor/selectFx/selectFx.js"></script>
    <script src="vendor/select2/select2.min.js"></script>
    <script src="vendor/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
    <script src="vendor/bootstrap-timepicker/bootstrap-timepicker.min.js"></script>
    <!-- end: JAVASCRIPTS REQUIRED FOR THIS PAGE ONLY -->
    <!-- start: CLIP-TWO JAVASCRIPTS -->
    <script src="assets/js/main.js"></script>
    <!-- start: JavaScript Event Handlers for this page -->
    <script src="assets/js/form-elements.js"></script>
    <script>
    jQuery(document).ready(function() {
        Main.init();
        FormElements.init();
    });
    </script>
    <!-- end: JavaScript Event Handlers for this page -->
    <!-- end: CLIP-TWO JAVASCRIPTS -->
</body>

</html>
<?php } ?>
