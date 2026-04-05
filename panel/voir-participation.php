<?php
session_start();
error_reporting(0);
include ('include/config.php');
if (strlen($_SESSION['id'] == 0)) {
    header('location:logout.php');
    exit;
} else {
    $id = intval($_GET['id']); // get valuabout:blank#blockede
    if (isset($_POST['submit']) ) {
        echo "aa";
        $id_challenge = '1';
        $id_membre = $_POST['id-membre'];
        $id_membre_vainqueur = $_POST['id-membre-vainqueur'];
        $id_activite = $_POST['id-activite'];
        $id_siege = $_POST['id-siege'];
        $id_table = $_POST['id-table'];
        $option = $_POST['option'];
        $ordre = $_POST['ordre'];
        $valide = $_POST['valide'];
        $commentaire = $_POST['commentaire'];
        $classement = $_POST['classement'];
        $points = $_POST['points'];
        $bounty = $_POST['bounty'];
        $recave = $_POST['recave'];
        $addon = $_POST['addon'];
        $gain = $_POST['gain'];
        $ip_ins = $_POST['ip-ins'];
        $tf = isset($_POST['tf']) ? $_POST['tf'] : 0;
        $points = isset($_POST['points']) ? $_POST['points'] : 0;
        //  	$id_challenge = $_POST['id-challenge'];
        //    $id_membre_vainqueur = $_POST['vainqueur'];
        $id_membre_vainqueur = 700;
        echo "bb" . "(" . $id_challenge . ")" . $id;
        // $sql = mysqli_query($con, "UPDATE `participation` SET 'id-challenge' = NULL, 'ip-mod' = NULL, 'ip-sup' = NULL ,ds = NULL, 'id-membre' = '$id_membre' , 'id-membre-vainqueur' = '$id_membre_vainqueur' , 'id-activite' = '$id_activite' , 'id-siege' = '$id_siege' , 'id-table' = '$id_table' , 'option' = '$option' , ordre = '$ordre' , valide = '$valide' , commentaire = '$commentaire' , classement = '$classement' , points = '$points' , gain = '$gain' , 'ip-ins' = '$ip_ins' WHERE `id-participation` = '$id'");
        if ($option == 'Annule') {
            // $id_table='';$id_siege=''; };
            // $id_membre = 999;
        }
        ;
        echo "cc" . $id_membre_vainqueur;
        //     $sql = mysqli_query($con, "UPDATE `participation` SET `id-membre`='$id_membre',`id-activite`='$id_activite',`id-siege`='$id_siege',`id-table`='$id_table',`option`='$option',`ordre`='$ordre',`valide`='$valide',`commentaire`='$commentaire',`classement`='$classement',`points`='$points',`bounty`='$bounty',`gain`='$gain',`recave`='$recave',`addon`='$addon',`ds`= CURRENT_TIMESTAMP,`ip-ins`='1',`ip-mod`='2',`ip-sup`='3' WHERE `id-participation` = '$id'");

        $sql = mysqli_query($con, "UPDATE `participation` SET 
            `id-membre`='$id_membre',
            `id-activite`='$id_activite',
            `id-siege`='$id_siege',
            `id-table`='$id_table',
            `id-challenge`='$id_challenge',
            `option`='$option',
            `ordre`='$ordre',
            `valide`='$valide',
            `commentaire`='$commentaire',
            `classement`='$classement',
            `points`='$points',
            `tf`='$tf', 
            `bonbon`='$bounty',
            `gain`='$gain',
            `recave`='$recave',
            `addon`='$addon',
            `ds`= CURRENT_TIMESTAMP 
            WHERE `id-participation` = '$id'");


        $_SESSION['msg'] = "MAJ Ok !!";
        echo "dd";
    }
    if (isset($_POST['submit2'])) {
        $compet = $_POST['compet'];
        echo $compet;
        $sql2 = mysqli_query($con, "INSERT INTO `competences-individu` (`id-indiv`, `id-comp`) VALUES ('$id', '$compet')");
        $_SESSION['msg'] = "Competence added successfully !!";
    }
    if (isset($_POST['submit3'])) {
        $lois = $_POST['lois'];
        echo $lois;
        $sql2 = mysqli_query($con, "INSERT INTO `loisirs-individu` (`id-indiv`, `id-lois`) VALUES ('$id', '$lois')");
        $_SESSION['msg'] = "Loisir added successfully !!";
    }

    if (isset($_POST['btnaddon'])) {
        $id_activite = $_POST['id-activite'];
        $id_membre = $_POST['id-membre'];
        $rech = mysqli_query($con, "SELECT `addon` FROM `participation` WHERE ( `id-activite` = '$id_activite' AND `id-membre` = '$id_membre' )");
        $result = mysqli_fetch_array($rech);
        $addon = $result['addon'] + 1;
        echo $addon . "," . $id_activite . "->" . $id_membre;
        $sql = mysqli_query($con, "UPDATE `participation` SET `addon`= $addon,`ds`= CURRENT_TIMESTAMP WHERE ((`participation`.`id-activite` = '$id_activite') AND (`participation`.`id-membre`) = '$id_membre')");
        echo " sql ok";

        $_SESSION['msg'] = "addon added successfully !!";
    }
    if (isset($_POST['btnrecave'])) {
        $id_activite = $_POST['id-activite'];
        $id_membre = $_POST['id-membre'];

        $rech = mysqli_query($con, "SELECT `recave` FROM `participation` WHERE ( `id-activite` = '$id_activite' AND `id-membre` = '$id_membre' )");
        $result = mysqli_fetch_array($rech);
        $recave = $result['recave'] + 1;
        echo $recave . "," . $id_activite . "->" . $id_membre;
        $sql = mysqli_query($con, "UPDATE `participation` SET `recave`= $recave,`ds`= CURRENT_TIMESTAMP WHERE ((`participation`.`id-activite` = '$id_activite') AND (`participation`.`id-membre`) = '$id_membre')");
        echo " sql ok";
        ?> ;
        //
        <script language="JavaScript" type="text/javascript">
            //        window.location.replace("recaves.php?par=<?php echo $id ?>&act=<?php echo $id_activite ?>&sou=<?php echo "voir-activite.php?uid=" ?>"); 
            //    
        </script><?php
        $_SESSION['msg'] = "recave added successfully !!";
    }
    if (isset($_POST['btnelim'])) {
        echo "start";
        $status = 'Elimine';
        $option = $_POST['option'];
        $id_activite = $_POST['id-activite'];
        $recave = $_POST['recave'];
        $id_membre = $_POST['id-membre'];
        $vainqueur = $_POST['vainqueur'];
        $id_membre_vainqueur = $_POST['id-membre-vainqueur'];
        //$id_membre_vainqueur = 700;
        echo "suite";
        $rech = mysqli_query($con, "SELECT `bonbon` FROM `participation` WHERE ( `id-activite` = '$id_activite' AND `id-membre` = '$id_membre_vainqueur' )");
        $result = mysqli_fetch_array($rech);
        echo "ok";
        $bounty = $result['bonbon'] + 1;
        echo $bounty."+";
        echo $recave . "," . $id_activite . "->" . $id_membre."/".$id."/".$vainqueur;
        $sql = mysqli_query($con, "UPDATE `participation` SET `ds`= CURRENT_TIMESTAMP, `option`= 'Elimine', `id-membre-vainqueur`= '$vainqueur' WHERE `id-participation` = '$id'");
        echo " sql ok";
        ?>
        //
        <script language="JavaScript" type="text/javascript">
            //    window.location.replace("eliminiation.php?id=<?php echo $id ?>&ac=<?php echo $id_activite ?>&source=<?php echo "voir-activite.php?uid=" ?>");
            // 
        </script><?php
        $_SESSION['msg'] = "elim added successfully !!";
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <title>Admin | Edition Membre</title>
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
        <!-- <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" /> -->
        <link href="vendor/bootstrap-timepicker/bootstrap-timepicker.min.css" rel="stylesheet" media="screen">
        <link rel="stylesheet" href="assets/css/styles.css">
        <link rel="stylesheet" href="assets/css/plugins.css">
        <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
        <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
        <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
        <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet" />
        <script type="text/javascript">
            $(document).ready(function () {
                $('#example').DataTable({
                    pageLength: 3,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                    }
                });
            });
        </script>
        <script type="text/javascript">
            $(document).ready(function () {
                $('#example2').DataTable({
                    pageLength: 3,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                    }
                });
            });
        </script>
        <script type="text/javascript">
            $(document).ready(function () {
                $('#example3').DataTable({
                    pageLength: 3,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                    }
                });
            });
        </script>
        <link rel="stylesheet" href="css/mes-styles.css">
        <link rel="stylesheet" href="css/les-styles.css">
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
    </head>

    <body>
        <div id="app">
            <?php include ('include/sidebar.php'); ?>
            <div class="app-content">
                <?php include ('include/header.php'); ?>
                <!-- end: TOP NAVBAR -->

                <!-- <div class="calque">
                    Sections et onglets Css
                </div> -->

                <!-- <div id="btn">
                    <div style="background-color:#2b579a;" class="rond">
                    </div>
                    <br />
                    <div style="background-color:#777bb5;width:35px;height:35px;" class="rond">
                    </div>
                    <br />
                    <div style="background-color:#d1ae42;width:25px;height:25px;" class="rond">
                    </div>
                </div> -->
                <div class="main-content">
                    <div class="wrap-content container" id="container">
                        <!-- start: PAGE TITLE -->
                        <section id="page-title">
                            <div class="row">
                                <!-- <div class="col-sm-8">
                                    <h1 class="mainTitle">Admin | Membre</h1>
                                </div> -->
                                <ol class="breadcrumb">
                                    <li>
                                        <span>Admin</span>
                                    </li>
                                    <li class="active">
                                        <span>Edition Membre</span>
                                    </li>
                                </ol>
                            </div>
                        </section>
                        <!-- end: PAGE TITLE -->
                        <!-- start: BASIC EXAMPLE -->

                        <div id="conteneur">
                            <div id="contenu">
                                <div id="auCentre">
                                    <div id="bMenu">
                                        <a href="#" id="css" class="btnnav" onmouseover="afficher('css')">Identité</a>
                                        <a href="#" id="js" class="btnnav" onmouseover="afficher('js')">Compét.</a>
                                        <a href="#" id="php" class="btnnav" onmouseover="afficher('php')">Loisirs</a>
                                        <a href="#" id="ks" class="btnnav" onmouseover="afficher('ks')">Particip.</a>
                                    </div>
                                    <div id="bSection">
                                        <div id="cssE">
                                            <div class="wrap-content container" id="container">
                                                <div class="container-fluid container-fullw bg-white">
                                                    <div class="col-md-12">
                                                        <div class="row mmargin-top-30">
                                                            <div class="panel-white">
                                                                <div class="panel-body">
                                                                    <?php echo htmlentities($_SESSION['msg'] = ""); ?>
                                                                    <div class="form-group">
                                                                        <?php

                                                                        $req = mysqli_query($con, "SELECT * FROM `participation` ");
                                                                        while ($res = mysqli_fetch_array($req)) {
                                                                            $id = $res["id-membre"];
                                                                            $id_participation = $res["id-participation"];
                                                                            // echo "(".$id.")";
                                                                            $req2 = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` = $id");
                                                                            $res2 = mysqli_fetch_array($req2);
                                                                            $nom = $res2["pseudo"];
                                                                            // echo "{".$nom."}";
                                                                            $modif = mysqli_query($con, "UPDATE `participation` SET `nom-membre` = '$nom' WHERE `id-participation` = '$id_participation'");
                                                                        }
                                                                        ;

                                                                        $id = intval($_GET['id']);
                                                                        $sql = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-participation` =  '$id'");
                                                                        while ($row = mysqli_fetch_array($sql)) {
                                                                            $monmembre = $row['id-membre'];
                                                                            $monactivite = $row['id-activite'];
                                                                            $monordre = $row['ordre'];
                                                                            ?>
                                                                            <?php
                                                                            $sql2 = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` =  '$monmembre'");
                                                                            while ($row2 = mysqli_fetch_array($sql2)) {
                                                                                $monemail = $row2['email'];
                                                                                $monCodeV = $row2['CodeV'];
                                                                            }
                                                                            ?>
                                                                            <?php
                                                                            $sql3 = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` =  '$monactivite'");
                                                                            while ($row3 = mysqli_fetch_array($sql3)) {
                                                                                $montitreactivite = $row3['titre-activite'];
                                                                                $madateactivite = $row3['date_depart'];
                                                                                $mavilleactivite = $row3['ville'];
                                                                                $matailleeactivite = $row3['places'];
                                                                            }
                                                                            ?>
                                                                            <form method="post">
                                                                                <table class="table table-bordered">
                                                                                    <tr>
                                                                                        <th>Participation</th>
                                                                                        <td><input class="form-control"
                                                                                                id="id-participation"
                                                                                                name="id-participation"
                                                                                                type="text"
                                                                                                style="text-align:center;font-weight: bold"
                                                                                                value="<?php echo $row['id-participation']; ?>">
                                                                                        </td>
                                                                                        <th>Ordre</th>
                                                                                        <td><input class="form-control"
                                                                                                id="ordre" name="ordre"
                                                                                                type="text"
                                                                                                value="<?php echo $row['ordre']; ?>">
                                                                                        </td>
                                                                                    </tr>
                                                                                    <?php
                                                                                    $sql4 = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` =  $monmembre");
                                                                                    $row4 = mysqli_fetch_array($sql4);
                                                                                    $nommembre = $row4['pseudo'];
                                                                                    // echo "-".$nommembre."-";
                                                                                    ?>

                                                                                    <th><a
                                                                                            href="voir-membre.php?id=<?php echo $monmembre; ?>">Membre
                                                                                            <?php echo ": " . $nommembre; ?></a>
                                                                                    </th>

                                                                                    <!--    <td><input
                                                                                                                                                        class="form-control"
                                                                                                                                                        id="id-membre"
                                                                                                                                                        name="id-membre"
                                                                                                                                                        type="text"
                                                                                                                                                        style="text-align:center;font-weight: bold"
                                                                                                            
                                                                                                                                                        value="<?php echo $row['id-membre']; ?>">
                                                                                                                                            -->

                                                                                    </td>
                                                                                    <td>
                                                                                        <?php
                                                                                        $membres = mysqli_query($con, "SELECT `id-membre`,`pseudo` FROM `membres` ORDER BY `pseudo` ASC");
                                                                                        //    echo "<h align='center' class='jaune'>Pseudo : <select name=id-membre-vainqueur><option value='-Anonyme-'>Choisir ICI --></h>"; 
                                                                                        echo "<align='center' class='rougesurblanc'><select name=id-membre><option value=$monmembre>--> Changer Pseudo IcI <--";
                                                                                        while ($choix = mysqli_fetch_assoc($membres)) {
                                                                                            $listepseudo = $choix['pseudo'];
                                                                                            // if ($listepseudo == $_SESSION['login'])
                                                                                            //     { echo '<option value="'.$choix['pseudo'].'" selected="selected">'.$_SESSION['login'].'</option>  ';}
                                                                                            // else
                                                                                            {
                                                                                                echo "<option value={$choix["id-membre"]}>{$choix["pseudo"]}\n";
                                                                                            }
                                                                                        }
                                                                                        echo "</select>";
                                                                                        ?>


                                                                                    <th><a
                                                                                            href="voir-activite.php?uid=<?php echo $monactivite; ?>">Activitée</a>
                                                                                    </th>

                                                                                    <td><input class="form-control"
                                                                                            id="id-activite" name="id-activite"
                                                                                            type="text"
                                                                                            value="<?php echo $row['id-activite']; ?>">
                                                                                    </td>
                                                                                    </tr>
                                                                                    
                                                                                    
                                                                                    <tr>
                                                                                        <th>Statut</th>
                                                                                        <!-- <td><input
                                                                                                                                                        class="form-control"
                                                                                                                                                        id="option" name="option"
                                                                                                                                                        type="text"
                                                                                                                                                        value="<?php echo $row['option']; ?>">
                                                                                                                                                </td> -->
                                                                                        <td>
                                                                                            <!-- <label for="option"></label> -->
                                                                                            <select name="option" id="option"
                                                                                                class="form-control"
                                                                                                type="text">
                                                                                                <option value=<?php echo $row['option']; ?> selected>
                                                                                                    <?php echo $row['option']; ?></option>
                                                                                                <?php if ($row['option'] !== 'Reservation')
                                                                                                    echo "<option value='Reservation'>Reservation</option>"; ?>
                                                                                                <?php if ($row['option'] <> 'Option')
                                                                                                    echo "<option value='Option'>Option</option>"; ?>
                                                                                                <?php if ($row['option'] <> 'Attente')
                                                                                                    echo "<option value='Attente'>Attente</option>"; ?>
                                                                                                <?php if ($row['option'] <> 'Inscrit')
                                                                                                    echo "<option value='Inscrit'>Inscrit</option>"; ?>
                                                                                                <?php if ($row['option'] <> 'Confirme')
                                                                                                    echo "<option value='Confirme'>Confirme</option>"; ?>
                                                                                                <?php if ($row['option'] <> 'Elimine')
                                                                                                    echo "<option value='Elimine'>Elimine</option>"; ?>
                                                                                                <?php if ($row['option'] <> 'Annule')
                                                                                                    echo "<option value='Annule'>Annule</option>"; ?>
                                                                                                <?php if ($row['option'] <> 'Partage')
                                                                                                    echo "<option value='Partage'>Partage</option>"; ?>
                                                                                                <?php if ($row['option'] <> 'ITM')
                                                                                                    echo "<option value='ITM'>ITM</option>"; ?>
                                                                                                <?php if ($row['option'] <> 'Bulle')
                                                                                                    echo "<option value='Bulle'>Bulle</option>"; ?>
                                                                                            </select>
                                                                                        </td>
                                                                                        <th>Etat</th>
                                                                                        <td><input class="form-control"
                                                                                                id="valide" name="valide"
                                                                                                type="text"
                                                                                                value="<?php echo $row['valide']; ?>">
                                                                                        </td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>Table</th>
                                                                                        <td><input class="form-control"
                                                                                                id="id-table" name="id-table"
                                                                                                type="text"
                                                                                                value="<?php echo $row['id-table']; ?>">
                                                                                        </td>
                                                                                        <th>Siege</th>
                                                                                        <td><input class="form-control"
                                                                                                id="id-siege" name="id-siege"
                                                                                                type="text"
                                                                                                value="<?php echo $row['id-siege']; ?>">
                                                                                        </td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>Recave</th>
                                                                                        <td><input class="form-control"
                                                                                                id="recave" name="recave"
                                                                                                type="text"
                                                                                                value="<?php echo $row['recave']; ?>">
                                                                                        </td>
                                                                                        <th>Addon</th>
                                                                                        <td><input class="form-control"
                                                                                                id="addon" name="addon"
                                                                                                type="text"
                                                                                                value="<?php echo $row['addon']; ?>">
                                                                                        </td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>Classement</th>
                                                                                        <td><input class="form-control"
                                                                                                id="classement"
                                                                                                name="classement" type="text"
                                                                                                value="<?php echo $row['classement']; ?>">
                                                                                        </td>
                                                                                        <th>Bounty</th>
                                                                                        <td><input class="form-control"
                                                                                                id="bounty" name="bounty"
                                                                                                type="text"
                                                                                                value="<?php echo $row['bounty']; ?>">
                                                                                        </td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>Table Finale</th> 
                                                                                        <td><input class="form-control"
                                                                                                id="tf" name="tf"
                                                                                                type="text"
                                                                                                value="<?php echo $row['tf']; ?>">
                                                                                        </td>
                                                                                        <th>Points</th>
                                                                                        <td><input class="form-control"
                                                                                                id="points" name="points"
                                                                                                type="text" 
                                                                                                value="<?php echo $row['points']; ?>">
                                                                                        </td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>Gains</th>
                                                                                        <td><input class="form-control"
                                                                                                id="gain" name="gain"
                                                                                                type="text"
                                                                                                value="<?php echo $row['gain']; ?>">
                                                                                        </td>
                                                                                        <th>Commentaire</th>
                                                                                        <td><input class="form-control"
                                                                                                id="commentaire"
                                                                                                name="commentaire" type="text"
                                                                                                value="<?php echo $row['commentaire']; ?>">
                                                                                        </td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <th>Date inscription</th>
                                                                                        <td><input class="form-control" id="ds"
                                                                                                name="ds" type="timestamp"
                                                                                                value="<?php echo $row['ds']; ?>">
                                                                                        </td>
                                                                                        <th>Sorti par</th>
                                                                                        <!-- <td><input
                                                                                                                                                        class="form-control"
                                                                                                                                                        id="id-membre-vainqueur"
                                                                                                                                                        name="id-membre-vainqueur"
                                                                                                                                                        type="text"
                                                                                                                                                        value="<?php echo $row['id-membre-vainqueur']; ?>">
                                                                                                                                                </td> -->
                                                                                        <td>
                                                                                            <?php
                                                                                            $membres = mysqli_query($con, "SELECT `id-membre`,`nom-membre` FROM `participation` WHERE `id-activite` =  '$monactivite' AND `option` NOT LIKE 'Elimine'  ORDER BY `id-membre` ASC");


                                                                                            //  $membres = mysqli_query($con, "SELECT `id-membre`,`pseudo` FROM `membres` INNER JOIN `participation` ON `membres.id-membre` = `participation.id-membre` ORDER BY `id-membre` ASC");
                                                                                            //    echo "<h align='center' class='jaune'>Pseudo : <select name=id-membre-vainqueur><option value='-Anonyme-'>Choisir ICI --></h>"; 
                                                                                            echo "<align='center' class='rougesurblanc'><select name=vainqueur><option value='-Anonyme-'>--> Choix du Pseudo IcI <--";
                                                                                            while ($choix = mysqli_fetch_assoc($membres)) {
                                                                                                $listepseudo = $choix['nom-membre'];
                                                                                                // if ($listepseudo == $_SESSION['login'])
                                                                                                //     { echo '<option value="'.$choix['pseudo'].'" selected="selected">'.$_SESSION['login'].'</option>  ';}
                                                                                                // else
                                                                                                {
                                                                                                    echo "<option value={$choix["id-membre"]}>{$choix["nom-membre"]}\n";
                                                                                                }
                                                                                            }
                                                                                            echo "</select>";
                                                                                            ?>

                                                                                    </tr>
                                                                                    <tr>
                                                                                        <td colspan="4"></td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <td colspan="2"
                                                                                            style="text-align:center ;">
                                                                                            <button type="submit"
                                                                                                class="btn btn-primary-orange2 btn-block"
                                                                                                name="submit">Mise à
                                                                                                jour participation</button>
                                                                                        </td>
                                                                                        <td colspan="2"
                                                                                            style="text-align:center ;">
                                                                                            <button type="submit"
                                                                                                class="btn btn-primary btn-block"
                                                                                                name="btnrecave">Recave</button>
                                                                                        </td>
                                                                                    </tr>
                                                                                    <td colspan="2" style="text-align:center ;">
                                                                                        <button type="submit"
                                                                                            class="btn btn-primary btn-block"
                                                                                            name="btnaddon">Addon</button>
                                                                                    </td>
                                                                                    <td colspan="2" style="text-align:center ;">
                                                                                        <button type="submit"
                                                                                            class="btn btn-primary-rouge btn-block"
                                                                                            name="btnelim">Valider
                                                                                            Elimination</button>
                                                                                    </td>

                                                                                    </tr>
                                                                                    <!-- <tr>
                                                                                                                                                <td colspan="2"
                                                                                                                                                    style="text-align:center ;">
                                                                                                                                                    <button type="submit"
                                                                                                                                                        class="btn btn-primary btn-block"
                                                                                                                                                        name="btnrecave">Recave</button>
                                                                                                                                                </td>
                                                                                                                                                <td colspan="2"
                                                                                                                                                    style="text-align:center ;">
                                                                                                                                                    <button type="submit"
                                                                                                                                                        class="btn btn-primary btn-block"
                                                                                                                                                        name="btnaddon">Addon</button>
                                                                                                                                                </td>
                                                                                                                                            </tr> -->
                                                                                </table>
                                                                            </form>
                                                                        <?php } ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="jsE">
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <!-- <h5 class="over-title margin-bottom-15">-> <span class="text-bold">Gestion des Competences</span></h5> -->
                                                    <div class="container-fluid container-fullw bg-white">
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <div class="row margin-top-30">
                                                                    <div class="col-lg-8 col-md-12">
                                                                        <div class="panel panel-white">
                                                                            <!--	<div class="panel-heading">
                                                                  <h5 class="panel-title">Ajout Personne</h5>
                                                            </div> -->
                                                                            <div class="panel-body">
                                                                                <div id="layoutSidenav_content">
                                                                                    <main>
                                                                                        <div class="container-fluid px-4">
                                                                                            <!--    <h1 class="mt-4">Gestion des Competences</h1> -->
                                                                                            <ol class="breadcrumb mb-4">
                                                                                                <li class="breadcrumb-item">
                                                                                                    <a
                                                                                                        href="liste-membres.php">Membres</a>
                                                                                                </li>
                                                                                                <li
                                                                                                    class="breadcrumb-item active">
                                                                                                    Competences
                                                                                                </li>
                                                                                            </ol>
                                                                                            <div class="card mb-4">
                                                                                                <!--   <div class="card-header">
                                                                                    <i class="fas fa-table me-1"></i>
                                                                                    Registered User Details
                                                                                </div> -->
                                                                                                <div class="card-body">
                                                                                                    <!-- <table id="datatablesSimple"> -->
                                                                                                    <table id="example"
                                                                                                        class="display"
                                                                                                        style="width:100%">
                                                                                                        <thead>
                                                                                                            <tr>
                                                                                                                <th>Identifiant
                                                                                                                </th>
                                                                                                                <th>Nom
                                                                                                                </th>
                                                                                                                <th>Commentaire
                                                                                                                </th>
                                                                                                                <th>Supprimer
                                                                                                                </th>
                                                                                                            </tr>
                                                                                                        </thead>
                                                                                                        <tbody>
                                                                                                            <?php $ret = mysqli_query($con, "SELECT * FROM `competences-individu` WHERE `id-indiv` = '$id'");
                                                                                                            $cnt = 1;
                                                                                                            while ($row = mysqli_fetch_array($ret)) { ?>
                                                                                                                <?php
                                                                                                                $id2 = $row['id-comp'];
                                                                                                                $sql2 = mysqli_query($con, "SELECT * FROM `competences` WHERE `id` = '$id2'");
                                                                                                                while ($row2 = mysqli_fetch_array($sql2)) { ?>
                                                                                                                    <tr>
                                                                                                                        <td>
                                                                                                                            <?php echo $row2['nom']; ?>
                                                                                                                        </td>
                                                                                                                        <td>
                                                                                                                            <?php echo $row2['commentaire']; ?>
                                                                                                                        </td>
                                                                                                                        <td>
                                                                                                                            <?php echo $row['date']; ?>
                                                                                                                        </td>
                                                                                                                    <?php } ?>
                                                                                                                    <td>
                                                                                                                        <!--<a href="edit-competences.php?id=<?php echo $row['id']; ?>" class="btn btn-transparent btn-xs" tooltip-placement="top" tooltip="Edit"><i class="fa fa-pencil"></i></a>
                                                                                                                                                    <i class="fas fa-edit"></i></a> -->
                                                                                                                        <a href="ajout-competences.php?id=<?php echo $row['id'] ?>&del=deleteind"
                                                                                                                            onClick="return confirm('Are you sure you want to delete?')"
                                                                                                                            class="btn btn-transparent btn-xs tooltips"
                                                                                                                            tooltip-placement="top"
                                                                                                                            tooltip="Remove"><i
                                                                                                                                class="fa fa-times fa fa-white"></i></a>
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
                                                                            <form role="form" name="adddoc" method="post"
                                                                                onSubmit="return valid();">
                                                                                <div class="form-group">
                                                                                    <label for="compet">
                                                                                        Ajout
                                                                                        Competence
                                                                                    </label>
                                                                                    <select name="compet"
                                                                                        class="form-control"
                                                                                        required="true">
                                                                                        <!--		<option value="compet">Select Competence</option> -->
                                                                                        <option value="compet">
                                                                                            Select
                                                                                            Competence
                                                                                        </option>
                                                                                        <?php $ret2 = mysqli_query($con, "select * from competences");
                                                                                        while ($row2 = mysqli_fetch_array($ret2)) {
                                                                                            ?>
                                                                                            <option
                                                                                                value="<?php echo htmlentities($row2['id']); ?>">
                                                                                                <?php echo htmlentities($row2['nom']); ?>
                                                                                            </option>
                                                                                            $indiv=
                                                                                        <?php } ?>
                                                                                    </select>
                                                                                </div>
                                                                                <button type="submit" name="submit2"
                                                                                    id="submit2"
                                                                                    class="btn btn-o btn-primary">
                                                                                    Ajout Comp
                                                                                </button>
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
                                        <div id="phpE">
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <!-- <h5 class="over-title margin-bottom-15">-> <span class="text-bold">Gestion des Competences</span></h5> -->
                                                    <div class="container-fluid container-fullw bg-white">
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <div class="row margin-top-30">
                                                                    <div class="col-lg-8 col-md-12">
                                                                        <div class="panel panel-white">
                                                                            <!--	<div class="panel-heading">
                                                                  <h5 class="panel-title">Ajout Personne</h5>
                                                            </div> -->
                                                                            <div class="panel-body">
                                                                                <div id="layoutSidenav_content">
                                                                                    <main>
                                                                                        <div class="container-fluid px-4">
                                                                                            <!--    <h1 class="mt-4">Gestion des Competences</h1> -->
                                                                                            <ol class="breadcrumb mb-4">
                                                                                                <li class="breadcrumb-item">
                                                                                                    <a
                                                                                                        href="liste-membres.php">Membres</a>
                                                                                                </li>
                                                                                                <li
                                                                                                    class="breadcrumb-item active">
                                                                                                    Loisirs
                                                                                                </li>
                                                                                            </ol>
                                                                                            <div class="card mb-4">
                                                                                                <!--   <div class="card-header">
                                                                                    <i class="fas fa-table me-1"></i>
                                                                                    Registered User Details
                                                                                </div> -->
                                                                                                <div class="card-body">
                                                                                                    <!-- <table id="datatablesSimple"> -->
                                                                                                    <table id="example2"
                                                                                                        class="display"
                                                                                                        style="width:100%">
                                                                                                        <thead>
                                                                                                            <tr>
                                                                                                                <th>Identifiant
                                                                                                                </th>
                                                                                                                <th>Nom
                                                                                                                </th>
                                                                                                                <th>Commentaire
                                                                                                                </th>
                                                                                                                <th>Supprimer
                                                                                                                </th>
                                                                                                            </tr>
                                                                                                        </thead>
                                                                                                        <tbody>
                                                                                                            <?php $ret = mysqli_query($con, "SELECT * FROM `loisirs-individu` WHERE `id-indiv` = '$id'");
                                                                                                            $cnt = 1;
                                                                                                            while ($row = mysqli_fetch_array($ret)) { ?>
                                                                                                                <?php
                                                                                                                $id2 = $row['id-lois'];
                                                                                                                $sql2 = mysqli_query($con, "SELECT * FROM `loisirs` WHERE `id` = '$id2'");
                                                                                                                while ($row2 = mysqli_fetch_array($sql2)) { ?>
                                                                                                                    <tr>
                                                                                                                        <td>
                                                                                                                            <?php echo $row2['nom']; ?>
                                                                                                                        </td>
                                                                                                                        <td>
                                                                                                                            <?php echo $row2['commentaire']; ?>
                                                                                                                        </td>
                                                                                                                        <td>
                                                                                                                            <?php echo $row['date']; ?>
                                                                                                                        </td>
                                                                                                                    <?php } ?>
                                                                                                                    <td>
                                                                                                                        <!--<a href="edit-competences.php?id=<?php echo $row['id']; ?>" class="btn btn-transparent btn-xs" tooltip-placement="top" tooltip="Edit"><i class="fa fa-pencil"></i></a>
                                                                                                                                                    <i class="fas fa-edit"></i></a> -->
                                                                                                                        <a href="ajout-loisirs.php?id=<?php echo $row['id'] ?>&del=deleteind"
                                                                                                                            onClick="return confirm('Are you sure you want to delete?')"
                                                                                                                            class="btn btn-transparent btn-xs tooltips"
                                                                                                                            tooltip-placement="top"
                                                                                                                            tooltip="Remove"><i
                                                                                                                                class="fa fa-times fa fa-white"></i></a>
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
                                                                            <form role="form" name="adddoc" method="post"
                                                                                onSubmit="return valid();">
                                                                                <div class="form-group">
                                                                                    <label for="lois">
                                                                                        Ajout
                                                                                        Loisir
                                                                                    </label>
                                                                                    <select name="lois" class="form-control"
                                                                                        required="true">
                                                                                        <!--		<option value="compet">Select Competence</option> -->
                                                                                        <option value="lois">
                                                                                            Choix
                                                                                            du Loisir
                                                                                        </option>
                                                                                        <?php $ret2 = mysqli_query($con, "select * from loisirs");
                                                                                        while ($row2 = mysqli_fetch_array($ret2)) {
                                                                                            ?>
                                                                                            <option
                                                                                                value="<?php echo htmlentities($row2['id']); ?>">
                                                                                                <?php echo htmlentities($row2['nom']); ?>
                                                                                            </option>
                                                                                            $indiv=
                                                                                        <?php } ?>
                                                                                    </select>
                                                                                </div>
                                                                                <button type="submit" name="submit3"
                                                                                    id="submit3"
                                                                                    class="btn btn-o btn-primary">
                                                                                    Ajout Lois
                                                                                </button>
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
                                        <div id="ksE">
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <!-- <h5 class="over-title margin-bottom-15">-> <span class="text-bold">Gestion des Competences</span></h5> -->
                                                    <div class="container-fluid container-fullw bg-white">
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <div class="row margin-top-30">
                                                                    <div class="col-lg-8 col-md-12">
                                                                        <div class="panel panel-white">
                                                                            <!--	<div class="panel-heading">
                                                            <h5 class="panel-title">Ajout Personne</h5>
                                                            </div> -->
                                                                            <div class="panel-body">
                                                                                <div id="layoutSidenav_content">
                                                                                    <main>
                                                                                        <div class="container-fluid px-4">
                                                                                            <!--    <h1 class="mt-4">Gestion des Competences</h1> -->
                                                                                            <ol class="breadcrumb mb-4">
                                                                                                <li class="breadcrumb-item">
                                                                                                    <a
                                                                                                        href="liste-membres.php">Membres</a>
                                                                                                </li>
                                                                                                <li
                                                                                                    class="breadcrumb-item active">
                                                                                                    Participations
                                                                                                </li>
                                                                                            </ol>
                                                                                            <div class="card mb-4">
                                                                                                <!--   <div class="card-header">
                                                                                    <i class="fas fa-table me-1"></i>
                                                                                    Registered User Details
                                                                                </div> -->
                                                                                                <div class="card-body">
                                                                                                    <!-- <table id="datatablesSimple"> -->

                                                                                                    <table id="example3"
                                                                                                        class="display"
                                                                                                        style="width:100%">
                                                                                                        <thead>
                                                                                                            <tr>
                                                                                                                <th>Date
                                                                                                                </th>
                                                                                                                <th>N°
                                                                                                                    Participation
                                                                                                                </th>
                                                                                                                <th>N°
                                                                                                                    Activite
                                                                                                                </th>
                                                                                                                <th>Accés
                                                                                                                    Activité
                                                                                                                </th>
                                                                                                            </tr>
                                                                                                        </thead>
                                                                                                        <tbody>
                                                                                                            <?php
                                                                                                            echo "/" . $id . "/";
                                                                                                            $req = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-participation` = '$id'");
                                                                                                            while ($row = mysqli_fetch_array($req)) {
                                                                                                                $idm = $row['id-membre'];
                                                                                                                echo "+" . $idm . "+";
                                                                                                            }
                                                                                                            $sql2 = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-membre` = '$idm'");
                                                                                                            while ($row2 = mysqli_fetch_array($sql2)) { ?>
                                                                                                                <tr>
                                                                                                                    <td>
                                                                                                                        <?php echo $row2['ds']; ?>
                                                                                                                    </td>
                                                                                                                    <td>
                                                                                                                        <?php echo $row2['id-participation']; ?>
                                                                                                                    </td>
                                                                                                                    <td>
                                                                                                                        <?php echo $row2['id-activite']; ?>
                                                                                                                    </td>
                                                                                                                    <td>
                                                                                                                        <a href="voir-activite.php?uid=<?php echo $row2['id-activite']; ?>"
                                                                                                                            class="btn btn-transparent btn-xs"
                                                                                                                            tooltip-placement="top"
                                                                                                                            tooltip="Edit"><i
                                                                                                                                class="fa fa-pencil"></i></a>
                                                                                                                    </td>
                                                                                                                </tr>
                                                                                                                <?php
                                                                                                            } ?>
                                                                                                        </tbody>
                                                                                                    </table>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </main>
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
                </div>
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
        <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
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
            function afficher(id) {
                var leCalque = document.getElementById(id);
                var leCalqueE = document.getElementById(id + "E");

                document.getElementById("cssE").className = "rubrique bgImg";
                document.getElementById("jsE").className = "rubrique bgImg";
                document.getElementById("ksE").className = "rubrique bgImg";
                document.getElementById("phpE").className = "rubrique bgImg";

                document.getElementById("css").className = "btnnav";
                document.getElementById("js").className = "btnnav";
                document.getElementById("ks").className = "btnnav";
                document.getElementById("php").className = "btnnav";

                leCalqueE.className += " montrer";
                leCalque.className = "btnnavA";
            }
        </script>
        <script type="text/javascript" language="javascript">
            afficher('css');
        </script>
    </body>

    </html>
<?php } ?>