<?php
session_start();
error_reporting(0);
include('include/config.php');
$id = intval($_GET['uid']);
if (strlen($_SESSION['id'] == 0)) {
    header('location:logout.php');
} else {
    if (isset($_POST['submit'])) {
        $titreactivite = $_POST['titre-activite'];
        $date_depart = $_POST['date_depart'];
        $heure_depart = $_POST['heure_depart'];
        $ville = $_POST['ville'];
        $places = $_POST['places'];
        $rake = $_POST['rake'];
        $buyin = $_POST['buyin'];
        $bounty = $_POST['bounty'];
        $recave = $_POST['recave'];
        $addon = $_POST['addon'];
        $ante = $_POST['ante'];
        $idmembre = $_POST['id-membre'];
        $commentaire = $_POST['commentaire'];
        $structure = $_POST['id-structure'];
        $jetons = $_POST['jetons'];
        $addon = $_POST['addon'];
        $msg = mysqli_query($con, "INSERT INTO `activite` ( `titre-activite`, `id-membre`, `date_depart`, `heure_depart`, `ville`, `rue`, `lng`, `lat`, `places`, `reserves`, `options`, `libre`, `commentaire`, `id-structure`, `buyin`, `rake`, `bounty`, `jetons`, `recave`, `addon`, `ante`, `bonus`) VALUES ( '$titreactivite', '$idmembre', '$date_depart', '$heure_depart', '$ville', NULL, NULL, NULL, '$places', NULL, '0', NULL, '$commentaire', '$structure', '$buyin', '$rake', '$bounty', '$jetons', '$recave', '$addon', '$ante', '0')");
        
        if ($msg) {
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
        }

        $_SESSION['msg'] = "Activité ajoutée avec succés !!";
        // header('location:http://poker31.org/panel/liste-activites.php');
        // exit;
    }
    //Code Deletion
    if (isset($_GET['del'])) {
        $sid = $_GET['id'];
        mysqli_query($con, "delete from competences where id = '$sid'");
        $_SESSION['msg'] = "data deleted !!";
    }
    ?>
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" >
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-16" />
        <title>Poker31</title>
        <link rel="stylesheet" href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" type="text/css" />
        <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
        <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
        <link rel="stylesheet" href="vendor/themify-icons/themify-icons.min.css">
        <link rel="stylesheet" href="vendor/animate.css/animate.min.css"  media="screen">
        <link rel="stylesheet" href="vendor/perfect-scrollbar/perfect-scrollbar.min.css"  media="screen">
        <link rel="stylesheet" href="vendor/switchery/switchery.min.css"  media="screen">
        <link rel="stylesheet" href="vendor/bootstrap-touchspin/jquery.bootstrap-touchspin.min.css" media="screen">
        <link rel="stylesheet" href="vendor/select2/select2.min.css" media="screen">
        <link rel="stylesheet" href="vendor/bootstrap-datepicker/bootstrap-datepicker3.standalone.min.css" media="screen">
        <link rel="stylesheet" href="vendor/bootstrap-timepicker/bootstrap-timepicker.min.css" media="screen">
        <link rel="stylesheet" href="assets/css/styles.css">
        <link rel="stylesheet" href="assets/css/plugins.css">
        <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" />
        <link rel="stylesheet" href="css/mes-styles.css">
        <link rel="stylesheet" href="css/les-styles.css">
        <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/luxon/2.3.1/luxon.min.js"></script>
        <script type="text/javascript">$(document).ready(function () {
            $('#example').DataTable({ lengthChange: false, searching: false,sscrollY: 200,scrollx: 1000,order: [[0, 'asc']], pageLength: 5, language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json' } });
        });</script>
        <script>responsiveVoice.setDefaultVoice("French Female")</script>
    </head>
    <body>
        <div id="app">
            <?php include('include/sidebar.php'); ?>
            <div class="app-content">
                <?php include('include/header.php'); ?>
                <!-- end: TOP NAVBAR -->
                <div class="main-content">
                    <div class="wrap-content container" id="container">
                        <!-- start: PAGE TITLE -->
                        <section id="page-title">
                        </section>
                        <!-- end: PAGE TITLE -->
                        <!-- start: BASIC EXAMPLE -->
                        <div id="conteneur">
                            <div id="contenu">
                               <div id="auCentre">
                                    <?php 
                                    $id=intval($_GET['uid']); 

                                    {?>
                                    <div id="bMenu">
                                        <a href="#" id="op1" class="btnnav" onmouseover="afficher1('op1')">Infos</a>
                                        <a href="#" id="op2" class="btnnav" onmouseover="afficher1('op2')">Inscrits</a>
                                        <a href="#" id="op3" class="btnnav" onmouseover="afficher1('op3')">Table 1</a>
                                        <!-- <a href="#" id="t2" class="btnnav" onmouseover="afficher2('t2')">Table 2</a> -->
                                        <!-- <a href="#" id="t3" class="btnnav" onmouseover="afficher2('t3')">Table 3</a> -->
                                        <!-- <a href="#" id="t4" class="btnnav" onmouseover="afficher2('t4')">Table 4</a> -->
                                    </div>
                                    <?php };
                                    ?>

                                    <div id="bSection">


                                    <div id="op1E">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="container-fluid container-fullw bbg-white">
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="row margin-top-0">
                                                                <div class="col-lg-8 col-md-12">
                                                                    <div class="panel panel-wwhite">
                                                                        <div class="panel-body">
                                                                            <div id="layoutSidenav_content">
                                                                                <main>
                                                                                    <div class="container-fluid px-4">
                                                                                        <ol class="breadcrumb mb-4">
                                                                                            <li class="breadcrumb-item">
                                                                                            <a href="voir-activite.php?uid=<?php echo $id; ?>">Retour Activité</a>
                                                                                            </li>
                                                                                            <li class="breadcrumb-item active">
                                                                                                <a href="ssieges.php?&ac=<?php echo $id ?>">Blindes</a>
                                                                                            </li>
                                                                                        </ol>
                                                                                        <div class="card mb-4">
                                                                                            <div class="card-body">
                                                                                                <table
                                                                                                    id="example"
                                                                                                    class="cell-border compact stripe hover"
                                                                                                    style="width:95% ;font-size:12px;">
                                                                                                    <thead>
                                                                                                        <tr>
                                                                                                            <!-- <th>id</th> -->
                                                                                                            <th>Ordre</th>
                                                                                                            <!-- <th>activite</th> -->
                                                                                                            <th>Blindes</th>
                                                                                                            <th>Duree</th>
                                                                                                            <th>Fin</th>
                                                                                                            <th>Ante</th>
                                                                                                            <th>Modifier</th>
                                                                                                        </tr>
                                                                                                    </thead>
                                                                                                    <tbody>
                                                                                                        <?php $ret = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE (`id-activite` = $id ) ");
                                                                                                        $cnt = 1;
                                                                                                        while ($row = mysqli_fetch_array($ret)) { ?>
                                                                                                            <?php
                                                                                                            $id2 = $row['id-activite'];
                                                                                                            $sql2 = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = '$id2' ");
                                                                                                            while ($row2 = mysqli_fetch_array($sql2)) { ?>
                                                                                                                <tr>
                                                                                                                    <!-- <td><?php echo $row['id']; ?></td> -->
                                                                                                                    <td><?php echo $row['ordre']; ?></td>
                                                                                                                    <!-- <td><a href="voir-activite.php?id=<?php echo $row['id-activite']; ?>"  ><?php echo $row2['titre']; ?></a></td> -->
                                                                                                                    <td><?php echo $row['nom']; ?></td> 
                                                                                                                    <td><?php echo $row['duree']; ?></td>
                                                                                                                    <td><?php echo $row['fin']; ?></td>
                                                                                                                    <td><?php echo $row['ante']; ?></td>
                                                                                                                <?php } ?>
                                                                                                                <td>
                                                                                                                    <a href="modif-blinde-live.php?id=<?php echo $row['id']; ?>"  tooltip="Edition"><i class="fa fa-pencil"></i></a>
                                                                                                                    <i class="fas fa-edit"></i></a>
                                                                                                                </td>
                                                                                                            </tr>
                                                                                                            <?php $cnt = $cnt + 1;
                                                                                                        } ?>
                                                                                                    </tbody>
                                                                                                </table>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </main>
                                                                            </div>
                                                                            <!-- <form method="post"> 
                                                                                <table>      
                                                                                    <tr>                            
                                                                                        <td colspan="3" >
                                                                                            <select name="lois" class="form-control" required="true">
                                                                                                <option value="lois">- Participant à Ajouter manuellement -</option>
                                                                                                <?php $ret2 = mysqli_query($con, "select * from membres ORDER BY `pseudo` ASC");
                                                                                                while ($row2 = mysqli_fetch_array($ret2)) {
                                                                                                    ?>
                                                                                                    <option
                                                                                                        value="<?php echo htmlentities($row2['id-membre']); ?>"> <?php echo htmlentities($row2['pseudo']); ?>
                                                                                                    </option>       
                                                                                                <?php } ?>
                                                                                            </select>
                                                                                        </td>                                                                                    
                                                                                        <td style="display:yes" ; colspan="2" >
                                                                                            <select name="activi" value = "activi" class="form-control" required="false">
                                                                                                <option value="<?php echo htmlentities($id); ?>"> <?php echo htmlentities($id); ?></option>
                                                                                            </select>
                                                                                        </td>
                                                                                        <td>
                                                                                            <button
                                                                                                type="submit"
                                                                                                name="submitinsmanu"
                                                                                                id="submitinsmanu"
                                                                                                class="btn btn-o btn-primary">
                                                                                                Ajout
                                                                                            </button>
                                                                                        </td>
                                                                                    </tr>
                                                                                </table>
                                                                            </form> -->
                                                                        
                                                                            <?php $id = intval($_GET['uid']);$_SESSION["act"]=$id; ?>
                                                                            <?php include_once('horloge-heure.php'); ?>  
                                                                                <div style="color:red ; font-size: 120px" id="response"></div>
                                                                            <div class='place-content' style='color:white' > 
                                                                                <a href="modif-horloge.php?act=<?php echo $id;?>&min=-2&sou=http://poker31.org/panel/blindes.php?uid=">-2 Min
                                                                            <!-- </div> -->
                                                                            <!-- <div class='place2-content'>  -->
                                                                                <a href="modif-horloge.php?act=<?php echo $id;?>&min=2&sou=http://poker31.org/panel/blindes.php?uid="> / +2 Min</a>
                                                                            </div>
                                                                            <?php include_once('horloge-sb.php'); ?>  
                                                                                <div style="color:orange ; font-size: 60px" id="response-sb"></div>
                                                                            <?php include_once('horloge-ante.php'); ?>  
                                                                                <div style="color:blue ; font-size: 30px" id="response-ante"></div>
                                                                            <!-- <?php $id = intval($_GET['uid']);$_SESSION["act"]=$id ?> -->
                                                                            <?php include_once('horloge-pause.php'); ?> 
                                                                                <div style="color:brown ; font-size: 30px" id="car-pause"></div>
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
                                </div>
                            </div>
                        </div>                                                                                                                                                       
                    </div>
                </div>
                <!-- end: BASIC EXAMPLE -->
                <!-- end: SELECT BOXES -->
            </div>    
            </div>
            <!-- start: FOOTER -->
            <?php include('include/footer.php'); ?>
            <!-- end: FOOTER -->
            <!-- start: SETTINGS -->
            <?php include('include/setting.php'); ?>
            <!-- end: SETTINGS -->
        </div>
        <!-- start: MAIN JAVASCRIPTS -->
        <script src="vendor/jquery/jquery.min.js"></script>
        <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
        <script src="vendor/modernizr/modernizr.js"></script>
        <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
        <script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
        <script src="vendor/switchery/switchery.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
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
        <script src="assets/js/main.js"></script>
        <script src="assets/js/form-elements.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"> </script>
        <script src="../js/scripts.js"></script>
        <script>
            jQuery(document).ready(function () {
                Main.init();
                FormElements.init();
            });
        </script>
        <!-- end: JavaScript Event Handlers for this page -->
        <!-- end: CLIP-TWO JAVASCRIPTS -->
        <script type="text/javascript" language="javascript">
                                                    
                                                    function afficher1(id) {
                                                        var leCalque = document.getElementById(id);
                                                        var leCalqueE = document.getElementById(id + "E");

                                                        document.getElementById("op1E").className = "rubrique bgImg";
                                                        // document.getElementById("t2E").className = "rubrique bgImg";
                                                        document.getElementById("op2E").className = "rubrique bgImg";
                                                        document.getElementById("op3E").className = "rubrique bgImg";
                                                        // document.getElementById("t3E").className = "rubrique bgImg";
                                                        // document.getElementById("t4E").className = "rubrique bgImg";

                                                        document.getElementById("op1").className = "btnnav";
                                                        // document.getElementById("t2").className = "btnnav";
                                                        document.getElementById("op2").className = "btnnav";
                                                        document.getElementById("op3").className = "btnnav";
                                                        // document.getElementById("t3").className = "btnnav";
                                                        // document.getElementById("t4").className = "btnnav";

                                                        leCalqueE.className += " montrer";
                                                        leCalque.className = "btnnavA";
                                                    }

                                                    function afficher2(id) {
                                                        var leCalque = document.getElementById(id);
                                                        var leCalqueE = document.getElementById(id + "E");

                                                        document.getElementById("infosE").className = "rubrique bgImg";
                                                        document.getElementById("t2E").className = "rubrique bgImg";
                                                        document.getElementById("inscritsE").className = "rubrique bgImg";
                                                        document.getElementById("t1E").className = "rubrique bgImg";
                                                        // document.getElementById("t3E").className = "rubrique bgImg";
                                                        // document.getElementById("t4E").className = "rubrique bgImg";

                                                        document.getElementById("infos").className = "btnnav";
                                                        document.getElementById("t2").className = "btnnav";
                                                        document.getElementById("inscrits").className = "btnnav";
                                                        document.getElementById("t1").className = "btnnav";
                                                        // document.getElementById("t3").className = "btnnav";
                                                        // document.getElementById("t4").className = "btnnav";

                                                        leCalqueE.className += " montrer";
                                                        leCalque.className = "btnnavA";
                                                    }

                                                    function afficher3(id) {
                                                        var leCalque = document.getElementById(id);
                                                        var leCalqueE = document.getElementById(id + "E");

                                                        document.getElementById("infosE").className = "rubrique bgImg";
                                                        document.getElementById("t2E").className = "rubrique bgImg";
                                                        document.getElementById("inscritsE").className = "rubrique bgImg";
                                                        document.getElementById("t1E").className = "rubrique bgImg";
                                                        document.getElementById("t3E").className = "rubrique bgImg";
                                                        // document.getElementById("t4E").className = "rubrique bgImg";

                                                        document.getElementById("infos").className = "btnnav";
                                                        document.getElementById("t2").className = "btnnav";
                                                        document.getElementById("inscrits").className = "btnnav";
                                                        document.getElementById("t1").className = "btnnav";
                                                        document.getElementById("t3").className = "btnnav";
                                                        // document.getElementById("t4").className = "btnnav";

                                                        leCalqueE.className += " montrer";
                                                        leCalque.className = "btnnavA";
                                                    }

                                                    function afficher4(id) {
                                                        var leCalque = document.getElementById(id);
                                                        var leCalqueE = document.getElementById(id + "E");

                                                        document.getElementById("infosE").className = "rubrique bgImg";
                                                        document.getElementById("t2E").className = "rubrique bgImg";
                                                        document.getElementById("inscritsE").className = "rubrique bgImg";
                                                        document.getElementById("t1E").className = "rubrique bgImg";
                                                        document.getElementById("t3E").className = "rubrique bgImg";
                                                        document.getElementById("t4E").className = "rubrique bgImg";

                                                        document.getElementById("infos").className = "btnnav";
                                                        document.getElementById("t2").className = "btnnav";
                                                        document.getElementById("inscrits").className = "btnnav";
                                                        document.getElementById("t1").className = "btnnav";
                                                        document.getElementById("t3").className = "btnnav";
                                                        document.getElementById("t4").className = "btnnav";


                                                        leCalqueE.className += " montrer";
                                                        leCalque.className = "btnnavA";
                                                    }
                                                </script>
                                                <?php
                                                // $onglet = 'inf';
                                                $onglet = $_GET['onglet'];
                                                echo "++".$onglet."++";
                                                if ($onglet == 'inf') {
                                                    ?>
                                                    <script type="text/javascript" language="javascript">
                                                       afficher('infos');
                                                    </script>;
                                                    <?php 
                                                };
                                                if($onglet == 'ins') {
                                                    ?>
                                                    <script type="text/javascript" language="javascript">
                                                        afficher('inscrits');
                                                    </script>;
                                                    <?php
                                                 };
                                                if ($onglet == '1') {
                                                    ?>
                                                    <script type="text/javascript" language="javascript">
                                                        afficher('t1');
                                                    </script>;
                                                    <?php 
                                                };
                                                if ($onglet == '2') {
                                                    ?>
                                                    <script type="text/javascript" language="javascript">
                                                        afficher('t2');
                                                    </script>;
                                                    <?php 
                                                };
                                                if ($onglet == '3') {
                                                    ?>
                                                    <script type="text/javascript" language="javascript">
                                                        afficher('t3');
                                                    </script>;
                                                    <?php 
                                                };
                                                if ($onglet == '4') {
                                                    ?>
                                                    <script type="text/javascript" language="javascript">
                                                        afficher('t4');
                                                    </script>;
                                                    <?php 
                                                };
                                                if ($onglet == '' AND $nbt == "1") {
                                                    ?>
                                                    <script type="text/javascript" language="javascript">
                                                        afficher1('t1');
                                                    </script>;
                                                    <?php 
                                                };

                                                if ($onglet == '' AND $nbt == "2") {
                                                    ?>
                                                    <script type="text/javascript" language="javascript">
                                                        afficher2('t1');
                                                    </script>;
                                                    <?php 
                                                };
                                                
                                                if ($onglet == '' AND $nbt == "3") {
                                                    ?>
                                                    <script type="text/javascript" language="javascript">
                                                        afficher3('t1');
                                                    </script>;
                                                    <?php 
                                                };
                                                if ($onglet == '' AND $nbt == "4") {
                                                    ?>
                                                    <script type="text/javascript" language="javascript">
                                                        afficher4('t1');
                                                    </script>;
                                                    <?php 
                                                };
                                                if ($onglet == '') {
                                                    ?>
                                                    <script type="text/javascript" language="javascript">
                                                        afficher1('op1');
                                                    </script>;
                                                    <?php 
                                                };
                                                 ?>
    </body>
    </html>
<?php } ?>