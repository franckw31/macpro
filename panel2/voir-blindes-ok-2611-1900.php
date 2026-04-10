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
        //$msg=mysqli_query($con,"INSERT INTO `activite` (`id-activite`, `titre-activite`, `id-membre`, `date_depart`, `heure_depart`, `ville`, `rue`, `lng`, `lat`, `places`, `reserves`, `options`, `libre`, `commentaire`, `structure`, `buyin`, `rake`, `bounty`, `jetons`, `recave`, `addon`, `ante`, `bonus`) VALUES (NULL, '-', '', '2022-12-31', '', '?', NULL, NULL, NULL, '8', NULL, '0', NULL, 'Aucun', 'Structure', '25', '5', '0', '40000', '1', '0', '0', '')");
        //$sql=mysqli_query($con,"insert into competences(nom) values('$doctorspecilization')");
        $_SESSION['msg'] = "Activité ajoutée avec succés !!";
        // header('location:http://poker31.org/panel/liste-activites.php');
        // exit;
    }
    //Code Deletion
    if (isset($_POST['modele'])) {
        $modele = $_GET['modele'];

        // echo $particip;
        ?>
        <script type="text/javascript">
            window.location.replace("/index.php");
        </script> ; <?php


        // $sql2 = mysqli_query($con, "INSERT INTO `competences-individu` (`id-indiv`, `id-comp`) VALUES ('$id', '$compet')");
        // $_SESSION['msg'] = "Doctor Specialization added successfully !!";
    }
    if (isset($_GET['del'])) {
        $sid = $_GET['id'];
        mysqli_query($con, "delete from competences where id = '$sid'");
        $_SESSION['msg'] = "data deleted !!";
    }
    if (isset($_POST['moins'])) {
        $id = $_GET['uid'];
        ?>
        <script type="text/javascript">
            window.location.replace("/panel/modif-horloge.php?act=<?php echo $id ?>&min=-2&sou=/panel/voir-blindes.php?uid=");
        </script> ; <?php

    }
    if (isset($_POST['plus'])) {
        $id = $_GET['uid'];
        ?>
        <script type="text/javascript">
            window.location.replace("/panel/modif-horloge.php?act=<?php echo $id ?>&min=+2&sou=/panel/voir-blindes.php?uid=");
        </script> ; <?php

    }
    if (isset($_POST['moins1'])) {
        $id = $_GET['uid'];
        ?>
        <script type="text/javascript">
            window.location.replace("/panel/modif-horloge.php?act=<?php echo $id ?>&min=-1&sou=/panel/voir-blindes.php?uid=");
        </script> ; <?php

    }
    if (isset($_POST['plus1'])) {
        $id = $_GET['uid'];
        ?>
        <script type="text/javascript">
            window.location.replace("/panel/modif-horloge.php?act=<?php echo $id ?>&min=+1&sou=/panel/voir-blindes.php?uid=");
        </script> ; <?php

    }
    if (isset($_POST['pauseresume'])) {
        $id = $_GET['uid'];
        if ($_SESSION["en_pause" . $id] == "0") {
            ?>
            <script type="text/javascript">
                window.location.replace("/panel/en-pause.php?act=<?php echo $id ?>&sou=/panel/voir-blindes.php?uid=");
            </script> ; <?php
        }
        if ($_SESSION["en_pause" . $id] == "1") {
            ?>
            <script type="text/javascript">
                window.location.replace("/panel/de-pause.php?act=<?php echo $id ?>&sou=/panel/voir-blindes.php?uid=");
            </script> ; <?php
        }
    }
    ?>
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr">

    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-16" />
        <!-- <meta http-equiv="refresh" content="60"> -->
        <title>Admin | Edition Membre</title>
        <link
            href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic"
            rel="stylesheet" type="text/css" />
        <link rel="stylesheet" href="voir-blindes.css">
        <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
        <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
        <link rel="stylesheet" href="vendor/themify-icons/themify-icons.min.css">
        <link href="vendor/animate.css/animate.min.css" rel="stylesheet" media="screen">
        <link href="vendor/perfect-scrollbar/perfect-scrollbar.min.css" rel="stylesheet" media="screen">
        <link href="vendor/switchery/switchery.min.css" rel="stylesheet" media="screen">
        <link href="vendor/bootstrap-touchspin/jquery.bootstrap-touchspin.min.css" rel="stylesheet" media="screen">
        <link href="vendor/select2/select2.min.css" rel="stylesheet" media="screen">
        <link href="vendor/bootstrap-datepicker/bootstrap-datepicker3.standalone.min.css" rel="stylesheet" media="screen">
        <!-- <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" /> -->
        <link href="vendor/bootstrap-timepicker/bootstrap-timepicker.min.css" rel="stylesheet" media="screen">
        <link rel="stylesheet" href="assets/css/styles.css">
        <link rel="stylesheet" href="assets/css/plugins.css">
        <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
        <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
        <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/luxon/2.3.1/luxon.min.js"></script>
        <script type="text/javascript">
            $(document).ready(function () {
                $('#example').DataTable({
                    order: [
                        [0, 'asc']
                    ],
                    pageLength: 6,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                    }
                });
            });
        </script>
        <script type="text/javascript">
            $(document).ready(function () {
                $('#example2').DataTable({
                    order: [
                        [0, 'asc']
                    ],
                    pageLength: 6,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                    }
                });
            });
        </script>
        <script type="text/javascript">
            $(document).ready(function () {
                $('#example3').DataTable({
                    pageLength: 6,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                    }
                });
            });
        </script>
        <link rel="stylesheet" href="css/mes-styles.css">
        <link rel="stylesheet" href="css/les-styles.css">

        <script>
            responsiveVoice.setDefaultVoice("French Female")
        </script>
        <!-- <script>responsiveVoice.speak("menu activite")</script> -->

        <script type="text/javascript">
            function valid() {
                if (document.adddoc.npass.value != document.adddoc.cfpass.value) {
                    alert("Password and Confirm Password Field do not match  !!");
                    document.adddoc.cfpass.focus();
                    return false;
                }
                return true;
            }
        </script>
        <script>
            function checkemailAvailability() {
                $("#loaderIcon").show();
                jQuery.ajax({
                    url: "check_availability.php",
                    data: 'emailid=' + $("#docemail").val(),
                    type: "POST",
                    success: function (data) {
                        $("#email-availability-status").html(data);
                        $("#loaderIcon").hide();
                    },
                    error: function () { }
                });
            }
        </script>
        <script>
            // var audio = new Audio("plus1_071016_Alex.WAV");
            // var audio = new Audio("http://glpjt.s3.amazonaws.com/so/av/a12.mp3");                 
            var audio = new Audio("https://s3.amazonaws.com/audio-experiments/examples/elon_mono.wav");

            function playAudio() {
                audio.play();
            }

            function pauseAudio() {
                audio.pause();
            }

            function cancelAudio() {
                audio.pause();
                audio.currentTime = 0;
            }
            //  playAudio();
        </script>
        <style>
            /* Masquer toutes les sections par défaut */
            .rubrique {
                display: none !important;
            }

            /* Afficher uniquement la section active */
            .rubrique.montrer {
                display: block !important;
            }
            
            /* Style pour le bouton actif */
            .btnnavA {
                background-color: #667eea !important;
                color: white !important;
                font-weight: bold !important;
            }
            
            /* Réduire la largeur du bandeau des onglets */
            #bMenu {
                max-width: 900px; /* Limiter la largeur totale */
                margin: 0 auto; /* Centrer le bandeau */
                display: flex;
                justify-content: center;
                gap: 5px; /* Réduire l'espacement entre les onglets */
                padding: 10px;
            }
            
            /* Réduire la taille des boutons d'onglets */
            .btnnav {
                padding: 8px 15px !important; /* Réduire le padding */
                font-size: 16px !important; /* Réduire la taille de la police */
                min-width: auto !important; /* Supprimer la largeur minimale */
                white-space: nowrap; /* Empêcher le retour à la ligne */
            }
            .snapshot-item .btn-danger {
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.snapshot-item .btn-danger i.fa-trash {
    display: inline !important;
    visibility: visible !important;
    opacity: 1 !important;
}

/* Supprimer les règles qui masquent les boutons au hover */
.snapshot-item:hover .btn-danger {
    display: inline-block !important;
}

/* Forcer la visibilité des boutons dans les snapshots */
    .snapshot-item {
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    
    .snapshot-item .btn {
        display: inline-block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    
    .snapshot-item .btn-danger,
    .snapshot-item .btn-delete-snapshot {
        display: inline-block !important;
        visibility: visible !important;
        opacity: 1 !important;
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
    }

    .snapshot-item .btn-danger i.fa-trash,
    .snapshot-item .btn i {
        display: inline !important;
        visibility: visible !important;
        opacity: 1 !important;
    }

    /* Supprimer toutes les règles qui pourraient masquer les boutons */
    .snapshot-item:hover .btn,
    .snapshot-item:hover .btn-danger {
        display: inline-block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    
    /* S'assurer que les conteneurs des boutons sont visibles */
    .snapshot-item > div {
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
        </style>
    </head>

    <body>
        <div id="app">
            <?php include('include/sidebar.php'); ?>
            <div class="app-content">
                <?php include('include/header.php'); ?>
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
                                    $id = intval($_GET['uid']);
                                    $reqnbt = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = '$id' ");
                                    $res = mysqli_fetch_array($reqnbt);
                                    $nbt = $res["nb-tables"];

                                    if (1) { ?>
                                        <!-- Remplacer la section des onglets -->
                                        <div id="bMenu">
                                            <a href="#" id="Timer" class="btnnav" onclick="afficher2('Timer'); return false;">Timer</a>
                                            <a href="#" id="t3" class="btnnav" onclick="afficher2('t3'); return false;">Blindes</a>
                                            <a href="#" id="Structure" class="btnnav" onclick="afficher2('Structure'); return false;">Modèles</a>
                                            <a href="#" id="Outils" class="btnnav" onclick="afficher2('Outils'); return false;">30 Sec</a>
                                            <!-- <a href="#" id="t3" class="btnnav" onclick="afficher2('t3'); return false;">divers</a> -->
                                        </div>
                                    <?php }
                                    ;
                                    date_default_timezone_set('UTC+2');
                                    ?>
                                    <div id="bSection">
                                        <div id="BlindesE" class="rubrique">                                            
                                            
                                        </div>
                                        <div id="TimerE" class="rubrique">
    <div style="display: flex; gap: 20px; align-items: flex-start;">
        <!-- Colonne gauche: Horloge et contrôles -->
        <div style="flex: 1; min-width: 400px;">
            <?php $id = intval($_GET['uid']);
            $_SESSION["act"] = $id; ?>
            <?php include_once('horloge-heure.php'); ?>
            <div style="color:red ; font-size: 200px ; text-align: center;" id="response">
            </div>
            <div style="color:green ; text-align: center">
                <form method="post">
                    <table class="table table-bordered">
                        <tr>
                            <td colspan="3" style="text-align:center ;">
                                <button type="submit" id="moins" class="btn btn-primaryg btn-block" name="moins">
                                    <<< -2 Minutes </button>
                            </td>
                            <td colspan="3" style="text-align:center ;">
                                <button type="submit" class="btn btn-primary btn-block" name="pauseresume">Pause / Resume</button>
                            </td>
                            <td colspan="3" style="text-align:center ;">
                                <button type="submit" class="btn btn-primary-rouge btn-block" name="plus">+2 Minutes >>></button>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3" style="text-align:center ;">
                                <button type="submit" id="moins1" class="btn btn-primaryg btn-block" name="moins1">
                                    <<< -1 Minute </button>
                            </td>
                            <td colspan="3" style="text-align:center ;">
                                <button type="submit" class="btn btn-primary btn-block" name="pauseresume">Reset blinde</button>
                            </td>
                            <td colspan="3" style="text-align:center ;">
                                <button type="submit" class="btn btn-primary-rouge btn-block" name="plus1">+1 Minute >>></button>
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
            <?php include_once('horloge-sb.php'); ?>
            <div style="color:orange ; font-size: 90px  ; text-align: center" id="response-sb"></div>
            <?php include_once('horloge-pause.php'); ?>
            <div style="color:red ; font-size: 30px ; text-align: center" id="car-pause"></div>
            <?php include_once('horloge-ante.php'); ?>
            <div style="color:blue ; font-size: 50px ; text-align: center" id="response-ante"></div>
            <?php include_once('horloge-estim.php'); ?>
            <div style="color:grey ; font-size: 30px ; text-align: center"></div>
        </div>

        <!-- Colonne droite: Tableau des joueurs -->
        <div style="flex: 1; min-width: 500px;">
            <div class="ccol-lg-6 ccol-md-12">
                <h4 class="text-center" style="margin-top:8px; display: flex; justify-content: center; align-items: center; gap: 10px; font-size: 1em;" panel-title>
                    <span style="font-size: 1em; color: #0056b3; font-weight: bold;">Liste des Joueurs - </span>
                    <a href="voir-activite.php?uid=<?php echo $id; ?>" style="font-size: 1em; color: #0056b3; font-weight: bold;"><?php echo htmlspecialchars($res['titre-activite'], ENT_QUOTES); ?></a>
                </h4>
            </div>

            <table class="table table-striped table-bordered players-table" style="font-size:14px;">
                <thead>
                    <tr>
                        <th>Ordre</th>
                        <th>Pseudo</th>
                        <th>Recave(s)</th>
                        <th>Sorti(e) Par</th>
                    </tr>
                </thead>
                <tbody id="joueurs-list">
                    <?php
                    $id = intval($_GET['uid']);
                    // Récupérer les joueurs avec leurs dates d'élimination
                    $req = mysqli_query($con, "SELECT p.*, MAX(e.created_at) as last_elimination_date FROM `participation` p LEFT JOIN `eliminations` e ON p.`id-participation` = e.`id_participation` WHERE p.`id-activite` = '$id' GROUP BY p.`id-participation` ORDER BY (p.`nom-membre-vainqueur` IS NULL OR p.`nom-membre-vainqueur` = '') DESC, last_elimination_date DESC, p.`nom-membre` ASC");
                    $totalRecaves = 0;
                    $countJoueurs = 0;
                    $rankingCounter = 1;

                    // Récupérer le buyin, recave_montant et jetons de l'activité
                    $buyinQuery = mysqli_query($con, "SELECT `recave_montant` , `jetons` , `recave_jetons` , `buyin`  FROM `activite` WHERE `id-activite` = '$id'");
                    $buyinRow = mysqli_fetch_array($buyinQuery);
                    $buyin = intval($buyinRow['buyin']) ?? 0;
                    $jetons = intval($buyinRow['jetons']) ?? 0;
                    $recave_jetons = intval($buyinRow['recave_jetons']) ?? 0;
                    $recave_montant = intval($buyinRow['recave_montant']) ?? 0;
                    
                    while ($row = mysqli_fetch_array($req)) {
                        $totalRecaves += intval($row['recave']);
                        $countJoueurs++;

                        // récupérer tous les éliminants enregistrés pour cette participation
                        $elims_html = '';
                        $isDefinitivelyEliminated = false;
                        $elim_q = mysqli_query($con, "SELECT * FROM `eliminations` WHERE `id_participation` = '" . intval($row['id-participation']) . "' ORDER BY `created_at` ASC");
                        if ($elim_q && mysqli_num_rows($elim_q) > 0) {
                            $names = [];
                            while ($er = mysqli_fetch_array($elim_q)) {
                                $names[] = htmlspecialchars($er['nom_membre'], ENT_QUOTES);
                                if (intval($er['is_definitive']) === 1) {
                                    $isDefinitivelyEliminated = true;
                                }
                            }
                            $elims_html = implode(', ', $names);
                        }

                        // récupérer id-membre depuis table membres
                        $membre_id = 0;
                        $pseudo_clean = mysqli_real_escape_string($con, $row['nom-membre']);
                        $mq = mysqli_query($con, "SELECT `id-membre` FROM `membres` WHERE `pseudo` = '$pseudo_clean' LIMIT 1");
                        if ($mq && mysqli_num_rows($mq) > 0) {
                            $mr = mysqli_fetch_array($mq);
                            $membre_id = intval($mr['id-membre']);
                        }

                        // Compter le nombre de joueurs éliminés par ce pseudo
                        $elimCount = 0;
                        $countElimQuery = mysqli_query($con, "SELECT COUNT(*) as cnt FROM `eliminations` e JOIN `participation` p ON e.`id_participation` = p.`id-participation` WHERE p.`id-activite` = '$id' AND e.`nom_membre` = '" . mysqli_real_escape_string($con, $row['nom-membre']) . "'");
                        if ($countElimQuery) {
                            $countElimRow = mysqli_fetch_array($countElimQuery);
                            $elimCount = intval($countElimRow['cnt']);
                        }

                        $eliminatedBy = isset($row['nom-membre-vainqueur']) ? $row['nom-membre-vainqueur'] : '';
                        $isEliminated = !empty($eliminatedBy) || $isDefinitivelyEliminated;
                        $rowStyle = $isEliminated ? 'opacity:0.5;background-color:#f0f0f0;' : '';
                        $disabledAttr = $isEliminated ? 'disabled' : '';

                        $elimCountDisplay = $elimCount > 0 ? ' <span style="color:red;">(' . $elimCount . ')</span>' : '';
                        $classementDisplay = $rankingCounter == 1 ? '<i class="fa fa-trophy" style="color: gold; font-size: 1.2em;"></i>' : $rankingCounter;
                        
                        echo '<tr style="' . $rowStyle . '">
                            <td style="text-align:center; font-weight:bold;">' . $classementDisplay . '</td>
                            <td class="pseudo-cell">' . htmlspecialchars($row['nom-membre'], ENT_QUOTES) . $elimCountDisplay . '</td>
                            <td>
                                <div class="input-group" style="width:100%;">
                                    <input type="number" class="form-control recave-input" data-id="' . intval($row['id-participation']) . '" data-member-id="' . intval($membre_id) . '" value="' . intval($row['recave']) . '" ' . $disabledAttr . ' />
                                    <button class="btn btn-success btn-sm btn-plus" type="button" data-id="' . intval($row['id-participation']) . '" ' . $disabledAttr . '>+</button>
                                    <button class="btn btn-danger btn-sm btn-trash" type="button" data-id="' . intval($row['id-participation']) . '" data-member-id="' . intval($membre_id) . '" data-name="' . htmlspecialchars($row['nom-membre'], ENT_QUOTES) . '" ' . $disabledAttr . '><i class="fa fa-trash"></i></button>
                                </div>
                            </td>
                            <td><span class="eliminated-by" data-player-id="' . intval($row['id-participation']) . '" style="font-size:12px;color:' . ($isEliminated ? 'red' : 'inherit') . ';font-weight:' . ($isEliminated ? 'bold' : 'normal') . '">';
                        
                        if (!empty($elims_html)) {
                            echo $elims_html;
                        } elseif ($isEliminated) {
                            echo htmlspecialchars($eliminatedBy, ENT_QUOTES);
                        } else {
                            echo '';
                        }
                        echo '</span></td></tr>';
                        $rankingCounter++;
                    }
                    ?>
                    <tr style="background-color: #f0f0f0; font-weight: bold;">
                        <td></td>
                        <td><?php echo $countJoueurs . ' Caves à ' . $buyin . ' €'; ?></td>
                        <td><?php echo $totalRecaves . ' ReCave(s) à ' . $recave_montant . ' €'; ?></td>
                        <td>
                            <?php
                            $countNotDefinitivelyEliminated = 0;
                            $reqForCounting = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-activite` = '$id'");
                            while ($rowForCount = mysqli_fetch_array($reqForCounting)) {
                                $isDefElim = false;
                                $elimCheckQuery = mysqli_query($con, "SELECT * FROM `eliminations` WHERE `id_participation` = '" . intval($rowForCount['id-participation']) . "'");
                                if ($elimCheckQuery && mysqli_num_rows($elimCheckQuery) > 0) {
                                    while ($ec = mysqli_fetch_array($elimCheckQuery)) {
                                        if (intval($ec['is_definitive']) === 1) {
                                            $isDefElim = true;
                                            break;
                                        }
                                    }
                                }
                                if (!$isDefElim) {
                                    $countNotDefinitivelyEliminated++;
                                }
                            }
                            if ($countNotDefinitivelyEliminated > 0) {
                                $totalJetons = ($countJoueurs * $jetons) + ($totalRecaves * $recave_jetons);
                                $stackMoyen = intval($totalJetons / $countNotDefinitivelyEliminated);
                                echo 'Stack Moyen ' . $stackMoyen;
                            }
                            ?>
                        </td>
                    </tr>
                    <tr style="background-color: #e8f4f8; font-weight: bold;">
                        <td></td>
                        <td>Caves = <?php echo $countJoueurs * $buyin . ' €'; ?></td>
                        <td><?php echo 'Recaves = ' . $totalRecaves * $recave_montant . ' €'; ?></td>
                        <td><?php 
                            $totalAmount = ($countJoueurs * $buyin) + ($totalRecaves * $recave_montant);
                            echo 'PricePool = ' . $totalAmount . ' €'; 
                        ?></td>
                    </tr>
                </tbody>
            </table>
            <div class="text-center" style="margin-top:8px; display: flex; justify-content: center; align-items: center; min-height: 50px;">
                <button class="btn btn-primary" onclick="validerRecaves()">Valider Modifications</button>
            </div>
        </div>
    </div>
</div>
                                        <div id="StructureE" class="rubrique">
                                            <div id="layoutSidenav_content">
                                                <main>
                                                    <div class="container-fluid px-4">
                                                        <ol class="breadcrumb mb-4">
                                                            <li class="breadcrumb-item">
                                                                <a href="liste-membres.php">Stuctures</a>
                                                            </li>
                                                            <li class="breadcrumb-item active">
                                                                Sauvegardés
                                                            </li>
                                                        </ol>
                                                        <div class="card mb-4">
                                                            <div class="card-body">
                                                                <table id="example2"
                                                                    class="cell-border compact stripe hover"
                                                                    style="width:95% ;font-size:14px;text-align: center;color:black">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Titre
                                                                            </th>
                                                                            <th>Orga
                                                                            </th>
                                                                            <th>Duree
                                                                            </th>
                                                                            <th>Début
                                                                            </th>
                                                                            <th>Fin Recaves
                                                                            </th>
                                                                            <th>Fin Partie
                                                                            </th>
                                                                            <th>Utiliser
                                                                            </th>
                                                                            <th>Infos
                                                                            </th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php
                                                                        $ret = mysqli_query($con, "SELECT * FROM `structure_modele`");
                                                                        $cnt = 1;
                                                                        date_default_timezone_set('Europe/paris');
                                                                        $actu = date("Y-m-d H:i:s");

                                                                        //$dur=time("H:i:s",$dur);
                                                                        // $cptactu=strtotime($actu);
                                                                        // $cptdeb=strtotime($debpause);
                                                                        $finrec = strtotime($actu) + (60 * 60 * 1.5);
                                                                        //$finpar=$finrec;
                                                                        //$finpar=strtotime($actu)+strtotime($dur);
                                                                        $dt = date($delta);
                                                                        // echo strtotime($actu)."-".strtotime($debpause)."-";
                                                                        $finrec = date("H:i:s", $finrec);
                                                                        //$finpar= date("H:i:s",$finpar);
                                                                        //echo $finrec;
                                                                        while ($row = mysqli_fetch_array($ret)) { ?>
                                                                            <?php
                                                                            $dur = $row['duree'];
                                                                            $parts = explode(':', $dur);
                                                                            $seconds = 0;
                                                                            foreach ($parts as $i => $val) {
                                                                                $seconds += $val * pow(60, 2 - $i);
                                                                            }
                                                                            date_default_timezone_set('Europe/paris');

                                                                            $id2 = $row['id_orga'];
                                                                            $sql3 = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` =  '$id2'");
                                                                            $sql2 = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = '$id2'");
                                                                            $row3 = mysqli_fetch_array($sql3);
                                                                            $orga = $row3['pseudo'];
                                                                            ?>
                                                                            <tr>
                                                                                <td>
                                                                                    <?php echo $row['nom']; ?>
                                                                                </td>
                                                                                <td>
                                                                                    <?php echo $orga; ?>
                                                                                </td>
                                                                                <td>
                                                                                    <?php echo $row['duree']; ?>
                                                                                </td>
                                                                                <td>
                                                                                    <?php
                                                                                    $dep = strtotime($actu);
                                                                                    date_default_timezone_set('Europe/paris');
                                                                                    echo date("H:i:s", $dep); ?>
                                                                                </td>
                                                                                <td>
                                                                                    <?php
                                                                                    $fi2 = strtotime($finrec);
                                                                                    date_default_timezone_set('Europe/paris');
                                                                                    echo date("H:i:s", $fi2); ?>
                                                                                </td>
                                                                                <td>
                                                                                    <?php
                                                                                    date_default_timezone_set('Europe/paris');
                                                                                    $finpar = strtotime($actu) + $seconds;
                                                                                    echo date("H:i:s", $finpar); ?>
                                                                                </td>

                                                                                <td>
                                                                                    <a href="modif-blinde-live.php?id=<?php echo $row['id']; ?>"
                                                                                        tooltip="Edition"><i
                                                                                            class="fa fa-pencil"></i></a>
                                                                                    </a>
                                                                                </td>
                                                                                <td>
                                                                                    <a href="modif-blinde-live.php?id=<?php echo $row['id']; ?>"
                                                                                        tooltip="Edition"><i
                                                                                            class="fa fa-pencil"></i></a>
                                                                                    </a>
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
                                        </div>
                                        <div id="OutilsE" class="rubrique">
                                            <?php
                                            // Compte à rebours 30 secondes pour l'onglet outils
                                            ?>
                                            <div id="countdown-container">
                                                <div id="countdown-label">⏱️ Compte à rebours
                                                </div>
                                                <div id="countdown-cardevent">30
                                                </div>
                                                <div class="countdown-buttons">
                                                    <button class="btn-countdown btn-start" id="btn-start">▶ START</button>
                                                    <button class="btn-countdown btn-stop" id="btn-stop" disabled>⏸
                                                        STOP</button>
                                                    <button class="btn-countdown btn-reset" id="btn-reset">🔄 RESET</button>
                                                    <script>
                                                        let countdownTime = 30;
                                                        let countdownTimer = null;
                                                        let isRunning = false;
                                                        const timerDisplay = document.getElementById('countdown-cardevent');
                                                        const btnStart = document.getElementById('btn-start');
                                                        const btnStop = document.getElementById('btn-stop');
                                                        const btnReset = document.getElementById('btn-reset');

                                                        // Fonction pour jouer le son d'alarme
                                                        function playAlarm() {
                                                            let alarmSound = new Audio('/newtimer/end.mp3');
                                                            alarmSound.load();
                                                            alarmSound.play();

                                                            // Prononcé un message aussi
                                                            if (typeof responsiveVoice !== 'undefined') {
                                                                responsiveVoice.speak("Temps écoulé!", "French Female");
                                                            }
                                                        }

                                                        // Mettre à jour l'affichage
                                                        function updateDisplay() {
                                                            timerDisplay.textContent = countdownTime;

                                                            // Ajouter une animation quand on approche de 0
                                                            if (countdownTime <= 5 && countdownTime > 0) {
                                                                timerDisplay.classList.add('warning');
                                                            } else {
                                                                timerDisplay.classList.remove('warning');
                                                            }
                                                        }

                                                        // Démarrer le compte à rebours
                                                        btnStart.addEventListener('click', function () {
                                                            if (!isRunning && countdownTime > 0) {
                                                                isRunning = true;
                                                                btnStart.disabled = true;
                                                                btnStop.disabled = false;
                                                                btnReset.disabled = true;

                                                                countdownTimer = setInterval(function () {
                                                                    countdownTime--;
                                                                    updateDisplay();

                                                                    if (countdownTime <= 0) {
                                                                        clearInterval(countdownTimer);
                                                                        isRunning = false;
                                                                        timerDisplay.textContent = '0';
                                                                        timerDisplay.classList.add('warning');
                                                                        playAlarm();

                                                                        btnStart.disabled = true;
                                                                        btnStop.disabled = true;
                                                                        btnReset.disabled = false;
                                                                    }
                                                                }, 1000);
                                                            }
                                                        });

                                                        // Arrêter le compte à rebours
                                                        btnStop.addEventListener('click', function () {
                                                            if (isRunning) {
                                                                clearInterval(countdownTimer);
                                                                isRunning = false;
                                                                btnStart.disabled = false;
                                                                btnStop.disabled = true;
                                                                btnReset.disabled = false;
                                                            }
                                                        });

                                                        // Réinitialiser le compte à rebours
                                                        btnReset.addEventListener('click', function () {
                                                            clearInterval(countdownTimer);
                                                            isRunning = false;
                                                            countdownTime = 30;
                                                            updateDisplay();
                                                            timerDisplay.classList.remove('warning');
                                                            btnStart.disabled = false;
                                                            btnStop.disabled = true;
                                                            btnReset.disabled = false;
                                                        });

                                                        // Initialisation
                                                        updateDisplay();
                                                    </script>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="t3E" class="rubrique">
    <div style="display: flex; gap: 20px; align-items: flex-start;">
        <!-- Colonne gauche: Tableau des blindes -->
        <div style="flex: 2; min-width: 600px;">
            <!-- <ol class="breadcrumb mb-4" style="background-color: transparent; padding: 0; margin-bottom: 15px;"> -->
                
            </ol>
            <div class="card mb-4" style="border: none; box-shadow: none; border-radius: 8px; overflow: hidden;">
                <div class="card-header"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); border-bottom: 3px solid #5568d3;">
                    <i class="fas fa-poker-chip" style="margin-right: 10px; font-size: 1.2em;"></i> 
                    <strong style="font-size: 1.1em;">Gestion des Blindes</strong>
                </div>
                <div class="card-body" style="padding: 25px; background: linear-gradient(135deg, #fafbfc 0%, #f0f4ff 100%);">
                    <table id="example" class="blindes-table cell-border compact stripe hover">
                        <thead>
                            <tr>
                                <th style="width: 8%;"><i class="fas fa-coins"></i> Ordre</th>
                                <th style="width: 12%;"><i class="fas fa-coins"></i> SB</th>
                                <th style="width: 12%;"><i class="fas fa-coins"></i> BB</th>
                                <th style="width: 12%;"><i class="fas fa-coins"></i> Ante</th>
                                <th style="width: 12%;"><i class="fas fa-hourglass-half"></i> Durée</th>
                                <th style="width: 18%;"><i class="fas fa-clock"></i> Fin</th>
                                <th style="width: 26%;"><i class="fas fa-cog"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $ret = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE (`id-activite` = $id ) ORDER BY ordre ASC");
                            $cnt = 1;
                            while ($row = mysqli_fetch_array($ret)) { ?>
                                <?php
                                $id2 = $row['id-activite'];
                                $sql2 = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = '$id2' ");
                                while ($row2 = mysqli_fetch_array($sql2)) { ?>
                                    <tr>
                                        <td class="ordre-cell"><?php echo $row['ordre']; ?></td>
                                        <td>
                                            <input type="number" class="form-control blinde-input"
                                                data-id="<?php echo $row['id']; ?>"
                                                data-field="sb"
                                                value="<?php echo intval($row['sb']); ?>" />
                                        </td>
                                        <td>
                                            <input type="number" class="form-control blinde-input"
                                                data-id="<?php echo $row['id']; ?>"
                                                data-field="bb"
                                                value="<?php echo intval($row['bb']); ?>" />
                                        </td>
                                        <td>
                                            <input type="number" class="form-control blinde-input"
                                                data-id="<?php echo $row['id']; ?>"
                                                data-field="ante"
                                                value="<?php echo intval($row['ante']); ?>" />
                                        </td>
                                        <td>
                                            <input type="number" class="form-control duree-input"
                                                data-id="<?php echo $row['id']; ?>"
                                                value="<?php echo intval($row['minutes']); ?>"
                                                placeholder="mm" maxlength="2" min="0" max="99" />
                                        </td>
                                        <td class="fin-cell">
                                            <?php 
                                            $fi = $row['fin'];
                                            $fi = strtotime($fi);
                                            echo date("H:i:s", $fi); 
                                            ?>
                                        </td>
                                    <?php } ?>
                                        <td>
                                            <div class="action-btns">
                                                <a href="ajout-blinde-live.php?id-activite=<?php echo $id; ?>&ordre=<?php echo $row['ordre']; ?>"
                                                    class="add-btn" title="Ajouter">
                                                    <i class="fa fa-plus"></i>
                                                </a>
                                                <a href="#"
                                                    onclick="deleteBlinde(<?php echo $row['id']; ?>, <?php echo $id; ?>); return false;"
                                                    class="delete-btn" title="Supprimer">
                                                    <i class="fa fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php 
                                $cnt = $cnt + 1;
                            } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Colonne droite: Gestion des sauvegardes -->
        <div style="flex: 1; min-width: 400px;">
            <ol class="breadcrumb mb-4" style="background-color: transparent; padding: 0; margin-bottom: 15px;">
            <script src="snapshots_management.js"></script>
            <div class="card mb-4"
                style="border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); border: 1px solid #e8ecf1; overflow: hidden;">
                <div class="card-header"
                    style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 18px 20px; border-radius: 8px 8px 0 0; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);">
                    <i class="fas fa-save" style="margin-right: 10px; font-size: 1.1em;"></i> 
                    <strong style="font-size: 1.05em;">Gestion des Sauvegardes</strong>
                </div>
                <div class="card-body" style="padding: 20px; background-color: #fafbfc;">
                    <h6 style="margin: 0 0 12px 0; font-size: 13px; color: #333; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fas fa-floppy-disk" style="margin-right: 8px; color: #28a745;"></i> Créer Snapshot
                    </h6>
                    <div style="margin-bottom: 18px;">
        <input type="text" id="snapshotName" class="form-control"
            placeholder="ex: V1 - Structure initiale"
            style="font-size: 14px; padding: 12px 15px; border: 2px solid #e0e6ed; border-radius: 6px; color: #333; width: 100%; margin-bottom: 10px;" />
        <button class="btn btn-success" type="button" onclick="saveSnapshot()"
            style="font-size: 13px; padding: 10px 20px; width: 100%; background-color: #28a745 !important; border: none; border-radius: 6px; color: white !important; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 2px 6px rgba(40, 167, 69, 0.2); display: block !important; visibility: visible !important;">
            <i class="fa fa-save" style="margin-right: 8px; color: white !important;"></i> Sauvegarder
        </button>
    </div>
                    <h6 style="margin: 15px 0 12px 0; font-size: 13px; color: #333; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fas fa-history" style="margin-right: 8px; color: #28a745;"></i> Historique
                    </h6>
                    <div id="snapshots-list"
                        style="max-height: 380px; overflow-y: auto; border: 2px solid #e8ecf1; border-radius: 6px; padding: 12px; background-color: #ffffff;">
                        <p style="margin: 0; color: #999; font-size: 12px; text-align: center; padding: 20px 0;">
                            ⏳ Chargement en cours...
                        </p>
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
        <!-- <script src="https://code.jquery.com/jquery-3.7.0.js"></script> -->
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
        <!-- end: JAVASCRIPTS REQUIRED FOR THIS PAGE ONLY -->
        <!-- start: CLIP-TWO JAVASCRIPTS -->
        <script src="assets/js/main.js"></script>
        <!-- start: JavaScript Event Handlers for this page -->
        <script src="assets/js/form-elements.js"></script>

        <script type="text/javascript">
            function incrementRecave(button) {
                var input = button.parentElement.querySelector('.recave-input');
                var currentValue = parseInt(input.value) || 0;
                input.value = currentValue + 1;
            }

            function eliminatePlayer(button) {
                var row = button.closest('tr');
                var playerId = button.parentElement.querySelector('.recave-input').getAttribute('data-id');
                var playerName = row.querySelector('.pseudo-cell').textContent;

                // Récupérer tous les joueurs actifs (non éliminés)
                var activePlayersHtml = '<select id="eliminatorSelect" style="width:100%; padding:8px;">\n';
                activePlayersHtml += '<option value="">-- Sélectionner un joueur --</option>\n';

                var rows = document.querySelectorAll('#joueurs-list tr:not(.total-row)');
                rows.forEach(function (playerRow) {
                    var opacity = playerRow.style.opacity;
                    // Exclure la ligne du joueur éliminé et les joueurs déjà éliminés
                    if (opacity !== '0.5' && playerRow !== row) {
                        var pseudo = playerRow.querySelector('.pseudo-cell').textContent;
                        activePlayersHtml += '<option value="' + pseudo + '">' + pseudo + '</option>\n';
                    }
                });

                activePlayersHtml += '</select>';

                // Afficher une boîte de dialogue personnalisée
                var modal = document.createElement('div');
                modal.innerHTML = `
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;">
            <div style="background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 400px;">
                <h5 style="margin-top: 0;">Quel joueur a éliminé <strong>${playerName}</strong> ?</h5>
                ${activePlayersHtml}
                <div style="text-align: right; margin-top: 15px;">
                    <button class="btn btn-secondary btn-sm" onclick="this.parentElement.parentElement.parentElement.remove()">Annuler</button>
                    <button class="btn btn-primary btn-sm" onclick="confirmElimination('${playerName}', this)">Confirmer</button>
                </div>
            </div>
        </div>
    `;

                document.body.appendChild(modal);
            }

            function confirmElimination(eliminatedPlayer, button) {
                var select = button.parentElement.parentElement.querySelector('#eliminatorSelect');
                var eliminatorName = select.value;

                if (eliminatorName === '') {
                    alert('Veuillez sélectionner un joueur éliminateur');
                    return;
                }

                // Fermer la modal
                button.parentElement.parentElement.parentElement.remove();

                // Trouver et mettre à jour la ligne du joueur éliminé
                var rows = document.querySelectorAll('#joueurs-list tr:not(.total-row)');
                rows.forEach(function (row) {
                    if (row.querySelector('.pseudo-cell').textContent === eliminatedPlayer) {
                        var statusCell = row.querySelector('.eliminated-by');
                        statusCell.textContent = eliminatorName;
                        statusCell.style.color = 'red';
                        statusCell.style.fontWeight = 'bold';

                        // Masquer la ligne du joueur éliminé
                        row.style.opacity = '0.5';
                        row.style.backgroundColor = '#f0f0f0';

                        // Désactiver les inputs
                        var inputs = row.querySelectorAll('input, button');
                        inputs.forEach(function (input) {
                            input.disabled = true;
                        });
                    }
                });
            }

            function validerRecaves() {
                var rows = document.querySelectorAll('#joueurs-list tr');
                var updates = [];
                var classements = [];

                rows.forEach(function (row) {
                    var input = row.querySelector('.recave-input');
                    if (!input) return; // Ignorer les lignes sans input

                    var classementCell = row.querySelector('td:first-child');
                    var classement = classementCell ? classementCell.textContent.trim() : '0';
                    var participationId = input.getAttribute('data-id');
                    var recaveValue = input.value;

                    updates.push({
                        'id-participation': participationId,
                        'recave': recaveValue
                    });

                    classements.push({
                        'id-participation': participationId,
                        'classement': classement
                    });

                    console.log('Row - Classement: ' + classement + ', ID: ' + participationId + ', Recave: ' +
                        recaveValue);
                });

                console.log('Données envoyées:', JSON.stringify(updates));
                console.log('Classements:', JSON.stringify(classements));

                $.ajax({
                    url: 'update_recave.php',
                    type: 'POST',
                    data: {
                        updates: JSON.stringify(updates),
                        classements: JSON.stringify(classements)
                    },
                    dataType: 'json',
                    success: function (response) {
                        console.log('Réponse serveur:', response);
                        alert(response.message);
                        if (response.status === 'success') {
                            location.reload();
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Erreur AJAX:', error);
                        console.error('Réponse du serveur:', xhr.responseText);
                        alert('Erreur lors de la mise à jour.');
                    }
                });
            }
        </script>
        <script type="text/javascript">
            // Gestion par délégation : bouton + et poubelle (corrigé pour utiliser data-member-id)
            document.addEventListener('click', function (e) {
                // + bouton
                var btnPlus = e.target.closest('.btn-plus');
                if (btnPlus) {
                    // Vérifier si le bouton est désactivé
                    if (btnPlus.disabled) {
                        e.preventDefault();
                        return;
                    }
                    var row = btnPlus.closest('tr');
                    var input = row.querySelector('.recave-input');
                    if (input && !input.disabled) {
                        var current = parseInt(input.value) || 0;
                        input.value = current + 1;
                    }
                    return;
                }

                // poubelle
                var btnTrash = e.target.closest('.btn-trash');
                if (btnTrash) {
                    // Vérifier si le bouton est désactivé
                    if (btnTrash.disabled) {
                        e.preventDefault();
                        return;
                    }
                    e.preventDefault();
                    // victimId = id-participation (pour exclure la victime dans la liste)
                    var victimId = btnTrash.getAttribute('data-id');
                    var victimName = btnTrash.getAttribute('data-name');
                    openEliminationModal(victimId, victimName);
                    return;
                }
            });

            function openEliminationModal(victimParticipationId, victimName) {
                // Construire options avec joueurs encore en jeu (exclure victim & définitivement éliminés)
                var rows = document.querySelectorAll('#joueurs-list tr');
                var options = '<option value="" data-member-id="">-- Sélectionner un joueur --</option>';
                rows.forEach(function (r) {
                    var inp = r.querySelector('.recave-input');
                    var pseudoCell = r.querySelector('.pseudo-cell');
                    if (!inp || !pseudoCell) return;
                    var partId = inp.dataset.id; // id-participation
                    var membreId = inp.dataset.memberId; // id-membre !!
                    if (String(partId) === String(victimParticipationId)) return; // exclure victime
                    // Exclure seulement les joueurs DEFINITIVEMENT éliminés (opacity 0.5 = greyed out)
                    if (r.style.opacity === '0.5') return;
                    var pseudo = pseudoCell.textContent.trim();
                    options += '<option value="' + pseudo + '" data-member-id="' + membreId + '">' + pseudo +
                        '</option>';
                });

                // Modal HTML
                var overlay = document.createElement('div');
                overlay.className = 'elimination-modal-overlay';
                overlay.style =
                    'position:fixed;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;';
                overlay.innerHTML = `
        <div style="background:#fff;padding:16px;border-radius:6px;min-width:320px;">
            <h5 style="margin:0 0 10px">Quel joueur a éliminé <strong>${victimName}</strong> ?</h5>
            <select id="eliminatorSelect" style="width:100%;padding:6px;margin-top:6px;">${options}</select>
            <div style="margin-top:12px;padding:10px;border:1px solid #ddd;border-radius:4px;background-color:#f9f9f9;">
                <label style="display:flex;align-items:center;margin:0;cursor:pointer;">
                    <input type="checkbox" id="definitiveElimination" style="margin-right:8px;cursor:pointer;" />
                    <span style="font-size:13px;">Éliminé définitivement et ne pas tenir compte du nombre de recaves max</span>
                </label>
            </div>
            <div style="text-align:right;margin-top:10px;">
                <button class="btn btn-secondary btn-sm" id="elimCancel">Annuler</button>
                <button class="btn btn-primary btn-sm" id="elimConfirm" data-victim-part-id="${victimParticipationId}">Confirmer</button>
            </div>
        </div>
    `;
                document.body.appendChild(overlay);

                // Handlers
                overlay.querySelector('#elimCancel').addEventListener('click', function () {
                    overlay.remove();
                });

                overlay.querySelector('#elimConfirm').addEventListener('click', function () {
                    var select = overlay.querySelector('#eliminatorSelect');
                    var selectedOption = select.options[select.selectedIndex];
                    var eliminatorName = selectedOption.value;
                    var eliminatorId = selectedOption.getAttribute('data-member-id') || '';
                    if (!eliminatorId) {
                        alert('Veuillez sélectionner un joueur éliminateur');
                        return;
                    }
                    var victimPartId = this.getAttribute('data-victim-part-id');
                    var isDefinitiveElim = overlay.querySelector('#definitiveElimination').checked ? 1 : 0;
                    overlay.remove();
                    applyElimination(victimPartId, eliminatorId, eliminatorName, isDefinitiveElim);
                });
            }

            function applyElimination(victimParticipationId, eliminatorMemberId, eliminatorName, isDefinitiveElim) {
                // Mettre à jour l'interface : colonne eliminated-by, griser la ligne et désactiver contrôles
                var rows = document.querySelectorAll('#joueurs-list tr');
                rows.forEach(function (r) {
                    var inp = r.querySelector('.recave-input');
                    if (!inp) return;
                    if (String(inp.dataset.id) === String(victimParticipationId)) {
                        var statusCell = r.querySelector('.eliminated-by');
                        if (statusCell) {
                            statusCell.textContent = eliminatorName;
                            statusCell.setAttribute('data-eliminator-id', eliminatorMemberId);
                            statusCell.style.color = 'red';
                            statusCell.style.fontWeight = 'bold';
                        }
                        r.style.opacity = '0.5';
                        r.style.backgroundColor = '#f0f0f0';
                        var controls = r.querySelectorAll('input, button');
                        controls.forEach(function (c) {
                            c.disabled = true;
                        });
                    }
                });

                // Enregistrer l'élimination côté serveur : envoyer id-membre (eliminatorMemberId) et id-participation (victimParticipationId)
                $.ajax({
                    url: 'record_elimination.php',
                    type: 'POST',
                    data: {
                        victim_id: victimParticipationId,
                        eliminator_id: eliminatorMemberId,
                        eliminator_name: eliminatorName,
                        is_definitive: isDefinitiveElim
                    },
                    dataType: 'json',
                    success: function (resp) {
                        console.log('Réponse élimination:', resp);
                        if (resp && resp.status === 'success') {
                            alert(resp.message);
                            location.reload();
                        } else {
                            alert('Erreur: ' + (resp ? resp.message : 'Réponse vide'));
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('AJAX Error:', error);
                        console.error('Response:', xhr.responseText);
                        alert('Erreur AJAX: ' + error + ' - ' + xhr.responseText);
                    }
                });
            }

            // Fonction pour mettre à jour la durée d'une blinde
            function updateDureBlinde(dureeInput) {
                var blindeId = dureeInput.getAttribute('data-id');
                var newDuree = dureeInput.value.trim();

                // Valider: doit être un nombre entre 0 et 99
                if (newDuree === '') {
                    return; // Ignorer si vide
                }

                var dureeNum = parseInt(newDuree);
                if (isNaN(dureeNum) || dureeNum < 0 || dureeNum > 99) {
                    alert('Format invalide. Entrez un nombre entre 0 et 99');
                    dureeInput.style.borderColor = 'red';
                    return;
                }

                $.ajax({
                    url: 'update_duree_blinde.php',
                    type: 'POST',
                    data: {
                        id: blindeId,
                        duree: newDuree
                    },
                    dataType: 'json',
                    success: function (response) {
                        console.log('Réponse mise à jour durée:', response);
                        if (response.status === 'success') {
                            // Mettre à jour le champ 'fin' dans le DOM
                            var row = dureeInput.closest('tr');
                            var finCell = row.querySelector('td:nth-child(6)');
                            if (finCell && response.new_fin) {
                                finCell.textContent = response.new_fin;
                            }
                            // Mettre à jour aussi le champ durée formatée
                            if (response.new_duree) {
                                dureeInput.value = parseInt(response.new_duree);
                            }

                            dureeInput.style.borderColor = 'green';
                            setTimeout(function () {
                                location.reload();
                            }, 500);
                        } else {
                            alert('Erreur: ' + response.message);
                            dureeInput.style.borderColor = 'red';
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Erreur AJAX durée:', error);
                        alert('Erreur lors de la mise à jour de la durée');
                        dureeInput.style.borderColor = 'red';
                    }
                });
            }

            // Gestion de la modification de la durée des blindes (blur + Enter)
            document.addEventListener('blur', function (e) {
                var dureeInput = e.target.closest('.duree-input');
                if (dureeInput) {
                    updateDureBlinde(dureeInput);
                }
            }, true);

            // Gestion de la touche Entrée
            document.addEventListener('keydown', function (e) {
                var dureeInput = e.target.closest('.duree-input');
                if (dureeInput && e.key === 'Enter') {
                    e.preventDefault();
                    updateDureBlinde(dureeInput);
                }

                var blindeInput = e.target.closest('.blinde-input');
                if (blindeInput && e.key === 'Enter') {
                    e.preventDefault();
                    updateBlindeValue(blindeInput);
                }
            }, true);

            // Gestion de la modification des champs SB, BB, Ante (blur + Enter)
            document.addEventListener('blur', function (e) {
                var blindeInput = e.target.closest('.blinde-input');
                if (blindeInput) {
                    updateBlindeValue(blindeInput);
                }
            }, true);

            // Fonction pour mettre à jour les valeurs SB, BB, Ante
            function updateBlindeValue(blindeInput) {
                var blindeId = blindeInput.getAttribute('data-id');
                var field = blindeInput.getAttribute('data-field'); // 'sb', 'bb', ou 'ante'
                var newValue = blindeInput.value.trim();

                // Valider: doit être un nombre positif
                if (newValue === '') {
                    return; // Ignorer si vide
                }

                var valueNum = parseInt(newValue);
                if (isNaN(valueNum) || valueNum < 0) {
                    alert('Format invalide. Entrez un nombre positif');
                    blindeInput.style.borderColor = 'red';
                    return;
                }

                $.ajax({
                    url: 'update_blindes_values.php',
                    type: 'POST',
                    data: {
                        id: blindeId,
                        field: field,
                        value: newValue
                    },
                    dataType: 'json',
                    success: function (response) {
                        console.log('Réponse mise à jour ' + field + ':', response);
                        if (response.status === 'success') {
                            blindeInput.style.borderColor = 'green';
                            setTimeout(function () {
                                location.reload();
                            }, 500);
                        } else {
                            alert('Erreur: ' + response.message);
                            blindeInput.style.borderColor = 'red';
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Erreur AJAX ' + field + ':', error);
                        alert('Erreur lors de la mise à jour de ' + field);
                        blindeInput.style.borderColor = 'red';
                    }
                });
            }

            // Fonction pour supprimer une blinde
            function deleteBlinde(blindeId, activiteId) {
                if (confirm('Êtes-vous sûr de vouloir supprimer cette blinde ? Les blindes suivantes seront recalculées.')) {
                    $.ajax({
                        url: 'delete_blinde.php',
                        type: 'POST',
                        data: {
                            id: blindeId,
                            id_activite: activiteId
                        },
                        dataType: 'json',
                        success: function (response) {
                            console.log('Réponse suppression:', response);
                            if (response.status === 'success') {
                                alert('Blinde supprimée avec succès');
                                location.reload();
                            } else {
                                alert('Erreur: ' + response.message);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('Erreur AJAX suppression:', error);
                            alert('Erreur lors de la suppression de la blinde');
                        }
                    });
                }
            }
        </script>
        <script>
            jQuery(document).ready(function () {
                Main.init();
                FormElements.init();
            });
        </script>
        <!-- end: JavaScript Event Handlers for this page -->
        <!-- end: CLIP-TWO JAVASCRIPTS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous">
        </script>
        <script src="../js/scripts.js"></script>
        <!-- <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
            <script src="../js/datatables-simple-demo.js"></script> -->
        <script type="text/javascript" language="javascript">
            function afficher1(id) {
                // Masquer tous les contenus d'onglets
                document.querySelectorAll('.rubrique').forEach(function(element) {
                    element.classList.remove('montrer');
                    element.style.display = 'none';
                });

                // Désactiver tous les boutons
                document.querySelectorAll('.btnnav').forEach(function(button) {
                    button.classList.remove('btnnavA');
                });

                // Afficher le contenu de l'onglet sélectionné
                var leCalqueE = document.getElementById(id + "E");
                if (leCalqueE) {
                    leCalqueE.classList.add('montrer');
                    leCalqueE.style.display = 'block';
                }

                // Activer le bouton correspondant
                var leCalque = document.getElementById(id);
                if (leCalque) {
                    leCalque.classList.add('btnnavA');
                }
            }

            function afficher2(id) {
                console.log('Affichage de l\'onglet:', id);
                
                // Masquer tous les contenus d'onglets
                var sections = document.querySelectorAll('.rubrique');
                sections.forEach(function(element) {
                    element.classList.remove('montrer');
                    element.style.display = 'none';
                });

                // Désactiver tous les boutons
                var buttons = document.querySelectorAll('.btnnav');
                buttons.forEach(function(button) {
                    button.classList.remove('btnnavA');
                });

                // Afficher le contenu de l'onglet sélectionné
                var leCalqueE = document.getElementById(id + "E");
                if (leCalqueE) {
                    leCalqueE.classList.add('montrer');
                    leCalqueE.style.display = 'block';
                    console.log('Section affichée:', id + "E");
                } else {
                    console.error('Section non trouvée:', id + "E");
                }

                // Activer le bouton correspondant
                var leCalque = document.getElementById(id);
                if (leCalque) {
                    leCalque.classList.add('btnnavA');
                }
            }

            // Initialisation au chargement de la page
            document.addEventListener('DOMContentLoaded', function() {
                console.log('Initialisation des onglets');
                
                // Masquer TOUS les contenus par défaut
                document.querySelectorAll('.rubrique').forEach(function(element) {
                    element.classList.remove('montrer');
                    element.style.display = 'none';
                });

                // Désactiver tous les boutons
                document.querySelectorAll('.btnnav').forEach(function(button) {
                    button.classList.remove('btnnavA');
                });

                // Afficher UNIQUEMENT l'onglet Timer au démarrage
                console.log('Affichage de l\'onglet Timer par défaut');
                afficher2('Timer');
            });
        </script>
    </body>

    </html>
<?php } ?>