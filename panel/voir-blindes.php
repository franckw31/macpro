<?php
session_start();
error_reporting(0);
include('include/config.php');
date_default_timezone_set('Europe/Paris');

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

    // Traitement Inscription Rapide Joueur (Intégration)
    if (isset($_POST['submit_player_reg'])) {
        $membre = intval($_POST['membre']);
        $tabl = intval($_POST['table']);
        $sieg = intval($_POST['siege']);
        $acti = $id; // L'ID de l'activité courante est déjà défini plus haut via $_GET['uid']

        // Vérifier si déjà inscrit
        $check_sql = mysqli_query($con, "SELECT `id-participation` FROM `participation` WHERE `id-membre` = '$membre' AND `id-activite` = '$acti'");
        
        if (mysqli_num_rows($check_sql) == 0) {
            // Récupérer les valeurs par défaut de l'activité
            $defaults_sql = mysqli_query($con, "SELECT rake, buyin, bounty, `id_challenge` FROM activite WHERE `id-activite` = '$acti'");
            $defaults_row = mysqli_fetch_array($defaults_sql);
            
            $default_activity_rake = floatval($defaults_row['rake']);
            $default_activity_bounty = floatval($defaults_row['bounty']);
            $default_activity_buyin = floatval($defaults_row['buyin']);
            $default_activity_challenge = floatval($defaults_row['id_challenge']);
            
            // Calcul du cout_in initial
            $initial_cout_in = $default_activity_buyin + $default_activity_bounty + $default_activity_rake;

            // Récupérer le pseudo
            $pseudo_sql = mysqli_query($con, "SELECT `pseudo` FROM `membres` WHERE `id-membre` = '$membre'");
            $pseudo_row = mysqli_fetch_array($pseudo_sql);
            $pseudo = mysqli_real_escape_string($con, $pseudo_row['pseudo']);

            // Insertion
            $insert_sql = "INSERT INTO `participation` (`id-membre`, `nom-membre`, `id-activite`, `id-table`, `id-siege`, `rake`, `cout_in`, `recave`, `id-challenge`) VALUES ('$membre', '$pseudo', '$acti', '$tabl', '$sieg', '$default_activity_rake', '$initial_cout_in', 0, '$default_activity_challenge')";
            
            if(mysqli_query($con, $insert_sql)) {
                 $_SESSION['msg'] = "Joueur inscrit avec succès !";
            } else {
                 $_SESSION['msg'] = "Erreur lors de l'inscription : " . mysqli_error($con);
            }
        } else {
            $_SESSION['msg'] = "Ce joueur est déjà inscrit à cette activité.";
        }
        
        // Rafraîchir la page pour voir le nouveau joueur
        ?>
        <script type="text/javascript">
            window.location.replace("voir-blindes.php?uid=<?php echo $id; ?>");
        </script>
        <?php
    }

    // Traitement Création Rapide Joueur (Nouveau)
    if (isset($_POST['submit_create_player'])) {
        $pseudo = trim($_POST['pseudo']);
        $prenom = trim($_POST['prenom']);
        $auto_register = isset($_POST['auto_register']) ? 1 : 0;
        $acti = $id;

        if (!empty($pseudo)) {
            // Vérifier si le pseudo existe déjà
            $check_sql = mysqli_query($con, "SELECT `id-membre` FROM `membres` WHERE `pseudo` = '" . mysqli_real_escape_string($con, $pseudo) . "'");
            if (mysqli_num_rows($check_sql) > 0) {
                $_SESSION['msg'] = "Le pseudo '$pseudo' existe déjà.";
            } else {
                // Insertion du nouveau membre
                // Note: Ajustez 'creationDate' selon le nom exact de votre colonne date dans la table membres (ex: regDate, creation_date, etc.)
                $insert_sql = "INSERT INTO `membres` (`pseudo`, `fname`, `creationDate`) VALUES ('" . mysqli_real_escape_string($con, $pseudo) . "', '" . mysqli_real_escape_string($con, $prenom) . "', NOW())";
                
                if (mysqli_query($con, $insert_sql)) {
                    $new_member_id = mysqli_insert_id($con);
                    $_SESSION['msg'] = "Joueur '$pseudo' créé avec succès.";

                    // Inscription automatique à l'activité si demandée
                    if ($auto_register == 1) {
                        // Récupérer les valeurs par défaut
                        $defaults_sql = mysqli_query($con, "SELECT rake, buyin, bounty FROM activite WHERE `id-activite` = '$acti'");
                        $defaults_row = mysqli_fetch_array($defaults_sql);
                        
                        $default_activity_rake = floatval($defaults_row['rake']);
                        $default_activity_bounty = floatval($defaults_row['bounty']);
                        $default_activity_buyin = floatval($defaults_row['buyin']);
                        $initial_cout_in = $default_activity_buyin + $default_activity_bounty + $default_activity_rake;

                        // Par défaut Table 1 Siège 1 (ou le premier dispo si on complexifie, ici simple pour l'exemple)
                        $tabl = 1;
                        $sieg = 1;

                        $insert_part_sql = "INSERT INTO `participation` (`id-membre`, `nom-membre`, `id-activite`, `id-table`, `id-siege`, `rake`, `cout_in`, `recave`) VALUES ('$new_member_id', '" . mysqli_real_escape_string($con, $pseudo) . "', '$acti', '$tabl', '$sieg', '$default_activity_rake', '$initial_cout_in', 0)";
                        
                        if(mysqli_query($con, $insert_part_sql)) {
                            $_SESSION['msg'] .= " Et inscrit automatiquement.";
                        }
                    }
                } else {
                    $_SESSION['msg'] = "Erreur création : " . mysqli_error($con);
                }
            }
        } else {
            $_SESSION['msg'] = "Le pseudo est obligatoire.";
        }
        ?>
        <script type="text/javascript">
            window.location.replace("voir-blindes.php?uid=<?php echo $id; ?>");
        </script>
        <?php
    }

    // Traitement Suppression Rapide Participation (Nouveau)
    if (isset($_POST['submit_quick_delete'])) {
        $membre_del = intval($_POST['membre_del']);
        $acti = $id;

        if ($membre_del) {
            // Suppression de la participation
            $del_sql = "DELETE FROM `participation` WHERE `id-membre` = '$membre_del' AND `id-activite` = '$acti'";
            
            if (mysqli_query($con, $del_sql)) {
                $_SESSION['msg'] = "Participation supprimée avec succès.";
            } else {
                $_SESSION['msg'] = "Erreur lors de la suppression : " . mysqli_error($con);
            }
        } else {
            $_SESSION['msg'] = "Veuillez sélectionner un joueur.";
        }
        ?>
        <script type="text/javascript">
            window.location.replace("voir-blindes.php?uid=<?php echo $id; ?>");
        </script>
        <?php
    }

    // Traitement Mise à jour des Gains (Podium)
    if (isset($_POST['submit_gains'])) {
        $acti = $id;
        if (isset($_POST['gains']) && is_array($_POST['gains'])) {
            foreach ($_POST['gains'] as $part_id => $gain_val) {
                $part_id = intval($part_id);
                $gain_val = floatval($gain_val);
                // Mise à jour du gain pour la participation
                mysqli_query($con, "UPDATE `participation` SET `gain` = '$gain_val' WHERE `id-participation` = '$part_id' AND `id-activite` = '$acti'");
            }
            $_SESSION['msg'] = "Gains mis à jour avec succès !";
        }
        ?>
        <script type="text/javascript">
            window.location.replace("voir-blindes.php?uid=<?php echo $id; ?>");
        </script>
        <?php
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
    
    // VOUS POUVEZ SUPPRIMER LE BLOC if (isset($_POST['moins1'])) ICI CAR LE BOUTON A ÉTÉ REMPLACÉ
    
    // REMPLACEMENT : Logique pour avancer à la blinde suivante (au lieu de +1 min)
    if (isset($_POST['next_blind'])) {
        $id = intval($_GET['uid']);
        $now = time();

        // 1. Récupérer toutes les blindes
        $q = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `id-activite` = '$id' ORDER BY `ordre` ASC");
        $blinds = [];
        while($b = mysqli_fetch_assoc($q)) { $blinds[] = $b; }

        // 2. Trouver la blinde active
        $currentIndex = -1;
        
        // Si le jeu est en pause, on se base sur l'heure de pause pour retrouver le niveau actif
        $referenceTime = $now;
        if (!empty($blinds) && isset($blinds[0]['en_pause']) && $blinds[0]['en_pause'] == 1) {
            $hp = strtotime($blinds[0]['heure_pause']);
            if ($hp > 0) {
                $referenceTime = $hp;
            }
        }

        foreach($blinds as $k => $b) {
            if (strtotime($b['fin']) > $referenceTime) {
                $currentIndex = $k;
                break;
            }
        }

        if ($currentIndex !== -1) {
            // Nous sommes dans un niveau actif.
            
            // A. Terminer le niveau actuel immédiatement (pour qu'il ne soit plus sélectionné par le cardevent)
            $currentId = $blinds[$currentIndex]['id'];
            $pastDate = date("Y-m-d H:i:s", $now - 1); // Finir il y a 1 seconde
            mysqli_query($con, "UPDATE `blindes-live` SET `fin` = '$pastDate' WHERE `id` = '$currentId'");

            // B. Recalculer tous les niveaux SUIVANTS à partir de MAINTENANT
            $runningTime = $now;
            
            for ($i = $currentIndex + 1; $i < count($blinds); $i++) {
                $b = $blinds[$i];
                $duration = intval($b['minutes']) * 60;
                $runningTime += $duration;
                $newFin = date("Y-m-d H:i:s", $runningTime);
                $bId = $b['id'];
                mysqli_query($con, "UPDATE `blindes-live` SET `fin` = '$newFin' WHERE `id` = '$bId'");
            }
            
            // S'assurer que le jeu n'est pas en pause
            mysqli_query($con, "UPDATE `blindes-live` SET `en_pause` = '0' WHERE `id-activite` = '$id'");
        }

        ?>
        <script type="text/javascript">
            window.location.replace("voir-blindes.php?uid=<?php echo $id; ?>");
        </script>
        <?php
    }

    // NOUVEAU CODE : Logique pour revenir à la blinde précédente
    if (isset($_POST['prev_blind'])) {
        $id = intval($_GET['uid']);
        $now = time();

        // 1. Récupérer toutes les blindes triées
        $q = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `id-activite` = '$id' ORDER BY `ordre` ASC");
        $blinds = [];
        while($b = mysqli_fetch_assoc($q)) { $blinds[] = $b; }

        // 2. Trouver la blinde active (la première dont la fin est dans le futur)
        $currentIndex = -1;
        
        // Si le jeu est en pause, on se base sur l'heure de pause pour retrouver le niveau actif
        $referenceTime = $now;
        if (!empty($blinds) && isset($blinds[0]['en_pause']) && $blinds[0]['en_pause'] == 1) {
            $hp = strtotime($blinds[0]['heure_pause']);
            if ($hp > 0) {
                $referenceTime = $hp;
            }
        }

        foreach($blinds as $k => $b) {
            if (strtotime($b['fin']) > $referenceTime) {
                $currentIndex = $k;
                break;
            }
        }

        // 3. Déterminer la blinde cible
        if ($currentIndex === -1) {
            // Si le tournoi est fini, on réactive la dernière blinde
            $targetIndex = count($blinds) - 1;
        } elseif ($currentIndex == 0) {
            // Si on est au niveau 1, on le redémarre simplement
            $targetIndex = 0;
        } else {
            // Sinon on recule d'un cran
            $targetIndex = $currentIndex - 1;
            
            // Si la blinde cible a une durée de 0 (ex: pause annulée ou niveau vide) OU est une pause (SB=0 et BB=0), on recule encore
            // On s'arrête si on atteint l'index 0
            while ($targetIndex > 0 && (intval($blinds[$targetIndex]['minutes']) <= 0 || (intval($blinds[$targetIndex]['sb']) == 0 && intval($blinds[$targetIndex]['bb']) == 0))) {
                $targetIndex--;
            }
        }

        // DEBUG LOG
        $log = "PrevBlind: ID=$id, Now=$now, RefTime=$referenceTime, CurrentIndex=$currentIndex, TargetIndex=$targetIndex\n";
        if ($currentIndex >= 0) $log .= "Current: " . json_encode($blinds[$currentIndex]) . "\n";
        if ($targetIndex >= 0) $log .= "Target: " . json_encode($blinds[$targetIndex]) . "\n";
        file_put_contents('debug_prev_blind.txt', $log, FILE_APPEND);

        // 4. Recalculer tout le planning à partir de la cible
        if ($targetIndex >= 0 && !empty($blinds)) {
            // Le nouveau départ est MAINTENANT
            $runningTime = $now;

            foreach($blinds as $k => $b) {
                // On ne touche pas aux blindes déjà terminées avant la cible
                if ($k < $targetIndex) {
                    continue;
                }

                // On calcule la nouvelle fin : Temps courant + Durée de la blinde
                $duration = intval($b['minutes']) * 60;
                $runningTime += $duration;
                $newFin = date("Y-m-d H:i:s", $runningTime);
                $bId = $b['id'];

                // Mise à jour en base
                mysqli_query($con, "UPDATE `blindes-live` SET `fin` = '$newFin' WHERE `id` = '$bId'");
            }
            
            // S'assurer que le jeu n'est pas en pause pour que le cardevent reparte
            mysqli_query($con, "UPDATE `blindes-live` SET `en_pause` = '0' WHERE `id-activite` = '$id'");
        }

        // Rafraîchir la page
        ?>
        <script type="text/javascript">
            window.location.replace("voir-blindes.php?uid=<?php echo $id; ?>&t=" + new Date().getTime());
        </script>
        <?php
    }

    // NOUVEAU CODE : Logique pour RESET la blinde en cours
    if (isset($_POST['reset_blind'])) {
        $id = intval($_GET['uid']);
        $now = time();

        // 1. Récupérer toutes les blindes triées
        $q = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `id-activite` = '$id' ORDER BY `ordre` ASC");
        $blinds = [];
        while($b = mysqli_fetch_assoc($q)) { $blinds[] = $b; }

        // 2. Trouver la blinde active (celle qui est en cours ou qui vient de finir)
        $currentIndex = -1;
        
        // Si le jeu est en pause, on se base sur l'heure de pause pour retrouver le niveau actif
        $referenceTime = $now;
        if (!empty($blinds) && isset($blinds[0]['en_pause']) && $blinds[0]['en_pause'] == 1) {
            $hp = strtotime($blinds[0]['heure_pause']);
            if ($hp > 0) {
                $referenceTime = $hp;
            }
        }

        foreach($blinds as $k => $b) {
            if (strtotime($b['fin']) > $referenceTime) {
                $currentIndex = $k;
                break;
            }
        }

        // 3. Déterminer la blinde cible à redémarrer
        if ($currentIndex === -1) {
            // Si le tournoi est fini (toutes les dates sont passées), on relance la dernière blinde
            $targetIndex = count($blinds) - 1;
        } else {
            // Sinon on relance la blinde actuelle
            $targetIndex = $currentIndex;
        }

        // 4. Recalculer le planning : La blinde cible redémarre MAINTENANT
        if ($targetIndex >= 0 && !empty($blinds)) {
            $runningTime = $now;

            foreach($blinds as $k => $b) {
                // On ne touche pas aux blindes passées avant la cible
                if ($k < $targetIndex) {
                    continue;
                }

                // Calcul de la nouvelle fin : Temps courant + Durée prévue
                $duration = intval($b['minutes']) * 60;
                $runningTime += $duration;
                $newFin = date("Y-m-d H:i:s", $runningTime);
                $bId = $b['id'];

                // Mise à jour en base
                mysqli_query($con, "UPDATE `blindes-live` SET `fin` = '$newFin' WHERE `id` = '$bId'");
            }
            
            // On enlève la pause pour que ça reparte direct
            mysqli_query($con, "UPDATE `blindes-live` SET `en_pause` = '0' WHERE `id-activite` = '$id'");
        }

        ?>
        <script type="text/javascript">
            window.location.replace("voir-blindes.php?uid=<?php echo $id; ?>");
        </script>
        <?php
    }

    if (isset($_POST['pauseresume'])) {
        $id = intval($_GET['uid']);
        
        // CORRECTION : On vérifie l'état réel dans la BDD au lieu de la SESSION
        // Cela évite les bugs si la session est expirée ou désynchronisée
        $check_pause = mysqli_query($con, "SELECT `en_pause` FROM `blindes-live` WHERE `id-activite` = '$id' LIMIT 1");
        $row_pause = mysqli_fetch_array($check_pause);
        $etat_actuel = intval($row_pause['en_pause']);

        if ($etat_actuel == 0) {
            // Si c'est à 0 (En cours), on envoie vers en-pause.php
            ?>
            <script type="text/javascript">
                window.location.replace("/panel/en-pause.php?act=<?php echo $id ?>&sou=/panel/voir-blindes.php?uid=");
            </script> ; <?php
        } else {
            // Si c'est à 1 (En pause), on envoie vers de-pause.php
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
        <script src="https://code.responsivevoice.org/responsivevoice.js?key=RTEc1M0w" onload="try{ responsiveVoice.setDefaultVoice('French Female'); }catch(e){ console.warn('responsiveVoice load onload', e); }"></script>
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
                    pageLength: 8,
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

        <!-- AJOUTER CE BLOC CSS POUR FORCER LES BOUTONS -->
        <style>
            /* REDUCTION TAILLE TIMER DANS L'ONGLET ADMIN */
            #TimerE .cardevent-circle-container {
                width: 44.3vmin !important;
                height: 44.3vmin !important;
            }
            
            /* Réduction du contenu du cardevent pour l'onglet admin */
            #TimerE #level-name { font-size: 2.6vmin !important; }
            #TimerE #cardevent-display { font-size: 13.1vmin !important; }
            #TimerE #cardevent-display.paused { font-size: 6.7vmin !important; }
            #TimerE #level-info { font-size: 5.2vmin !important; }
            #TimerE #car-pause { font-size: 2.2vmin !important; }

            /* Force l'interactivité et la position au-dessus des autres éléments */
            .players-table .btn {
                cursor: pointer !important;
                pointer-events: auto !important;
                position: relative !important;
                z-index: 100 !important;
            }
            
            .players-table .input-group {
                display: flex !important;
                gap: 5px !important;
                align-items: center !important;
                justify-content: center !important;
                flex-wrap: nowrap !important;
                width: 100% !important;
            }

            .players-table .btn i {
                pointer-events: none;
            }

            /* --- CORRECTION : CENTRAGE DU MENU (VERSION RENFORCÉE) --- */
            
            /* 1. On s'assure que le conteneur parent prend toute la largeur */
            #auCentre {
                width: 100% !important;
                text-align: center !important;
                display: block !important;
            }

            /* 2. On configure le menu en Flexbox et on annule les floats existants */
            #bMenu {
                display: flex !important;
                justify-content: center !important; /* Centre horizontalement */
                align-items: center !important;
                flex-wrap: wrap !important;
                gap: 10px !important;
                width: 100% !important;
                margin: 0 auto 20px auto !important;
                float: none !important; /* Annule le float:left de voir-blindes.css */
                padding: 0 !important;
            }

            /* 3. On réinitialise les boutons pour qu'ils obéissent au Flexbox */
            .btnnav {
                float: none !important; /* Annule le float:left des boutons */
                display: inline-block !important;
                margin: 0 !important; /* L'espace est géré par 'gap' dans #bMenu */
                position: static !important; /* Évite les positionnements absolus */
            }
        </style>

        <script>
            // Fallback iOS amélioré (V4)
            if (typeof responsiveVoice !== 'undefined') {
                responsiveVoice.OnVoiceReady = function() {
                    var voice = "French Female"; // Par défaut
                    var voices = responsiveVoice.getVoices();
                    
                    var foundFemale = false;
                    var foundAmelie = null;
                    var foundThomas = null;
                    var foundFrench = null;
                    
                    for (var i = 0; i < voices.length; i++) {
                        var v = voices[i];
                        var name = v.name || "";
                        var lang = v.lang || "";
                        
                        if (name === "French Female") foundFemale = true;
                        if (name.indexOf("Amelie") !== -1) foundAmelie = name;
                        if (name.indexOf("Thomas") !== -1) foundThomas = name;
                        
                        if (!foundFrench && (lang.indexOf("fr") === 0 || name.indexOf("French") !== -1)) {
                            foundFrench = name;
                        }
                    }
                    
                    if (foundFemale) voice = "French Female";
                    else if (foundAmelie) voice = foundAmelie;
                    else if (foundFrench) voice = foundFrench;
                    else if (foundThomas) voice = foundThomas;
                    
                    console.log("Default Voice set to: " + voice);
                    responsiveVoice.setDefaultVoice(voice);
                };
            }
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
        <style>
        /* AJOUT POUR RESTAURER LE STYLE DE L'HORLOGE */
        
        /* 1. L'HEURE */
        #cardevent-display {
            font-size: 80px; /* Taille standard pour cette page */
            color: #f90909ff;  /* Rouge */
            font-weight: bold;
            line-height: 1;
            text-shadow: 0 0 20px rgba(168, 11, 11, 0.46);
            margin-bottom: 10px;
        }
        
        /* Style quand en pause */
        #cardevent-display.paused {
            color: orange;
            font-size: 60px; /* Un peu plus petit si "PAUSE" est écrit */
        }

        /* 2. LES BLINDES */
        #level-info {
            font-size: 40px; /* Taille standard pour cette page */
            color: #fac010ff;  /* Jaune */
            font-weight: bold;
            line-height: 1.2;
        }
        
        /* Style pour les Ante */
        .ante-text {
            color: #4a90e2;
            font-size: 0.8em;
        }

        /* Ajustement du conteneur pour qu'il s'intègre bien */
        #clock-wrapper {
            background: rgba(0, 0, 0, 0.2);
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 10px;
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
                                            <a href="#" id="Joueurs" class="btnnav" onclick="afficher2('Joueurs'); return false;">Joueurs</a>
                                            <a href="#" id="Outils" class="btnnav" onclick="afficher2('Outils'); return false;">30 Sec</a>
                                            <!-- <a href="#" id="t3" class="btnnav" onclick="afficher2('t3'); return false;">divers</a> -->
                                        </div>
                                    <?php }
                                    ;
                                    date_default_timezone_set('Europe/Paris');
                                    ?>
                                    <div id="bSection">
                                        <div id="BlindesE" class="rubrique">                                            

                                        </div>
                                        <!-- Onglet Timer -->
                                        <div id="TimerE" class="rubrique">
                                            <div style="display: flex; gap: 20px; align-items: flex-start;">
                                                <!-- Colonne gauche: Horloge et contrôles -->
                                                <div style="flex: 1; min-width: 400px;">
                                                    <?php $id = intval($_GET['uid']);
                                                    $_SESSION["act"] = $id; ?>
                                                    
                                                    <!-- Lien vers le mode plein écran (Géré en JS pour éviter conflit avec bouton son) -->
                                                    <!-- On n'ouvre PAS le lien si on clique sur l'overlay audio ou un bouton -->
                                                    <div onclick="if(!event.target.closest('#audio-overlay') && !event.target.closest('button')) window.location.href = 'fullscreen-cardevent.php?uid=<?php echo $id; ?>';" style="cursor: pointer;" title="Cliquez pour ouvrir en plein écran">
                                                        <?php include_once('horloge-heure.php'); ?>
                                                    </div>

                                                    <!-- <div style="color:black ; font-size: 140px ; text-align: center;" id="rresponse"> 
                                                    </div> -->
                                                    <div style="color:green ; text-align: center">
                                                        <form method="post">
                                                            <table class="table" style="border: none;">
                                                                <tr>
                                                                    <td colspan="3" style="text-align:center; border: none;">
                                                                        <button type="submit" id="moins" class="btn btn-primaryg btn-block" name="moins">
                                                                             -2 Minutes </button>
                                                                    </td>
                                                                    <td colspan="3" style="text-align:center !important; border: none;">
                                                                        <button type="submit" class="btn btn-primary btn-block" name="pauseresume" style="background-color: #007bff !important; color: white !important; border-color: #007bff !important;">Pause / Resume</button>
                                                                    </td>
                                                                    <td colspan="3" style="text-align:center; border: none;">
                                                                        <button type="submit" class="btn btn-primaryg btn-block" name="plus">+2 Minutes </button>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td colspan="3" style="text-align:center; border: none;">
                                                                        <!-- Remplacement du bouton -1 Minute par Blinde Précédente -->
                                                                        <button type="submit" id="prev_blind" class="btn btn-warning btn-block" name="prev_blind" style="color: black !important; background-color: #ffc107 !important; border-color: #ffc107 !important;">
                                                                            Blinde Précédente
                                                                        </button>
                                                                    </td>
                                                                    <td colspan="3" style="text-align:center; border: none;">
                                                                        <!-- CORRECTION : Changement du name="pauseresume" en name="reset_blind" -->
                                                                        <button type="submit" class="btn btn-primary-rouge btn-block" name="reset_blind">Reset blinde</button>
                                                                    </td>
                                                                    <td colspan="3" style="text-align:center; border: none;">
                                                                        <!-- Ancien bouton +1 Minute remplacé -->
                                                                        <button type="submit" id="next_blind" class="btn btn-warning btn-block" name="next_blind" style="color: black !important; background-color: #ffc107 !important; border-color: #ffc107 !important;">
                                                                            Blinde Suivante 
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </form>
                                                    </div>
                                                    <!-- <?php include_once('horloge-sb.php'); ?>
                                                    <div style="color:orange ; font-size: 90px  ; text-align: center" id="response-sb"></div> -->
                                                    
                                                    <!-- <div style="display: flex; justify-content: center; align-items: center; gap: 15px;">
                                                         On réactive l'affichage du décompte de pause 
                                                                                                                 <div id="wrapper-pause" style="color:white ; font-size: 30px ; text-align: center; white-space: nowrap;">
                                                            <?php include('car-pause.php'); ?>
                                                        </div>
                                                        
                                                         Affichage de l'heure estimée 
                                                        <div id="estim-pause" style="color:#00ff00 ; font-size: 30px ; text-align: center; font-weight: bold;"></div>
                                                    </div> -->

                                                    <script>
                                                        setInterval(function() {
                                                            var text = "";
                                                            var wrapper = document.getElementById('wrapper-pause');
                                                            
                                                            // 1. Récupérer le texte visible
                                                            if (wrapper) {
                                                                text = wrapper.innerText || wrapper.textContent;
                                                            }
                                                            
                                                            // Si le wrapper est vide, chercher par ID spécifique potentiel (au cas où car-pause.php utilise un ID interne)
                                                            if (!text || text.trim() === "") {
                                                                var el = document.getElementById('car-pause') || document.getElementById('cardevent-pause');
                                                                if (el) text = el.innerText || el.textContent;
                                                            }

                                                            var ep = document.getElementById('estim-pause');

                                                            if(text && ep) {
                                                                // Nettoyage
                                                                text = text.trim();
                                                                
                                                                var totalSec = -1;
                                                                
                                                                // Regex pour MM:SS ou HH:MM:SS avec espaces optionnels
                                                                // Ex: "10:00", "10 : 00", "01:10:00"
                                                                var matchTime = text.match(/(\d+)\s*:\s*(\d+)(?:\s*:\s*(\d+))?/);
                                                                
                                                                if (matchTime) {
                                                                    if (matchTime[3]) {
                                                                        // H:M:S
                                                                        totalSec = parseInt(matchTime[1]) * 3600 + parseInt(matchTime[2]) * 60 + parseInt(matchTime[3]);
                                                                    } else {
                                                                        // M:S
                                                                        totalSec = parseInt(matchTime[1]) * 60 + parseInt(matchTime[2]);
                                                                    }
                                                                } else {
                                                                    // Regex pour "XX min"
                                                                    var matchMin = text.match(/(\d+)\s*min/i);
                                                                    if (matchMin) {
                                                                        totalSec = parseInt(matchMin[1]) * 60;
                                                                    }
                                                                }

                                                                if (totalSec >= 0 && totalSec < 86400) {
                                                                    var now = new Date();
                                                                    var estim = new Date(now.getTime() + totalSec * 1000);
                                                                    
                                                                    var h = estim.getHours();
                                                                    var m = estim.getMinutes();
                                                                    if(m < 10) m = '0' + m;
                                                                    
                                                                    ep.innerText = "-> " + h + 'h' + m;
                                                                } else {
                                                                    ep.innerText = ""; 
                                                                }
                                                            }
                                                        }, 1000);
                                                    </script>

                                                    <!-- <?php include_once('horloge-ante.php'); ?>
                                                    <div style="color:blue ; font-size: 50px ; text-align: center" id="response-ante"></div> -->
                                                    
                                                    <div style="color:grey ; font-size: 90px ; text-align: center"></div>
                                                </div>

                                                <!-- Colonne droite: Tableau des joueurs (Style mis à jour) -->
                                                <div style="flex: 1; min-width: 300px; max-width: 100%;">
                                                    <div class="card mb-4" style="border: none; box-shadow: none; border-radius: 8px; overflow: hidden;">
                                                        <div class="card-header"
                                                            onclick="if(!event.target.closest('a')) window.location.href = 'fullscreen-player.php?uid=<?php echo $id; ?>';"
                                                            title="Cliquez pour ouvrir en plein écran"
                                                            style="cursor: pointer; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; border-radius: 8px 8px 0 0; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); border-bottom: 3px solid #5568d3;">
                                                            <i class="fas fa-users" style="margin-right: 10px; font-size: 1.2em;"></i> 
                                                            <strong style="font-size: 1.1em;"> <a href="voir-activite.php?uid=<?php echo $id; ?>" style="color: white; text-decoration: underline;"><?php echo htmlspecialchars($res['titre-activite'], ENT_QUOTES); ?></a></strong>
                                                        </div>
                                                        <div class="card-body" style="padding: 10px; background: linear-gradient(135deg, #fafbfc 0%, #f0f4ff 100%); overflow-x: auto;">
                                                            <!-- AUGMENTATION DE LA TAILLE DE POLICE GLOBALE DU TABLEAU DE 14px A 16px -->
                                                            <table class="table table-striped table-bordered players-table" style="font-size:1.5vmin; width: 100%;">
                                                                <thead style="background: #667eea;">
                                                                    <tr>
                                                                        <th style="color: white !important;">Ordre</th>
                                                                        <th style="color: white !important;">Pseudo</th>
                                                                        <th style="color: white !important;">Recave(s)</th>
                                                                        <th style="color: white !important;">Sorti(e) Par</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="joueurs-list">
                                                                    <?php
                                                                    $id = intval($_GET['uid']);
                                                                    // Récupérer les joueurs avec leurs dates d'élimination
                                                                    // MODIFICATION DU TRI :
                                                                    // 1. D'abord ceux qui sont EN JEU (classement = 0 ou NULL)
                                                                    // 2. Ensuite ceux qui sont ÉLIMINÉS, triés par classement INVERSE (le dernier éliminé en haut de la liste des éliminés) ou par classement ASC selon votre préférence.
                                                                    // Ici : En jeu d'abord, puis par classement décroissant (le 10ème, puis le 9ème...) pour avoir l'historique de sortie.
                                                                    
                                                                    $req = mysqli_query($con, "SELECT p.* FROM `participation` p WHERE p.`id-activite` = '$id' ORDER BY (p.`classement` = 0 OR p.`classement` IS NULL) DESC, p.`classement` ASC, p.`nom-membre` ASC");
                                                                    
                                                                    $totalRecaves = 0;
                                                                    $countJoueurs = 0;
                                                                    $rankingCounter = 1;
                                                                    
                                                                    // AJOUT : Initialisation du tableau pour la liste JS des éliminants potentiels
                                                                    $validEliminators = array();

                                                                    // Récupérer le buyin, recave_montant et jetons de l'activité
                                                                    // MODIFICATION : Ajout de `recave` dans le SELECT pour connaitre le max autorisé
                                                                    $buyinQuery = mysqli_query($con, "SELECT `recave`, `recave_montant` , `jetons` , `recave_jetons` , `buyin`  FROM `activite` WHERE `id-activite` = '$id'");

                                                                    // Vérifier si la requête a réussi
                                                                    if (!$buyinQuery) {
                                                                        die("Erreur de requête : " . mysqli_error($con));
                                                                    }

                                                                    $buyinRow = mysqli_fetch_array($buyinQuery);
                                                                    $buyin = intval($buyinRow['buyin']) ?? 0;
                                                                    $jetons = intval($buyinRow['jetons']) ?? 0;
                                                                    $recave_jetons = intval($buyinRow['recave_jetons']) ?? 0;
                                                                    $recave_montant = intval($buyinRow['recave_montant']) ?? 0;
                                                                    
                                                                    // On stocke le max recave dans une variable JS pour l'utiliser plus bas
                                                                    $maxRecavesAllowed = intval($buyinRow['recave']);
                                                                    echo "<script>var maxRecavesAllowed = " . $maxRecavesAllowed . ";</script>";
                                                                    
                                                                    while ($row = mysqli_fetch_array($req)) {
                                                                        $totalRecaves += intval($row['recave']);
                                                                        $countJoueurs++;

                                                                        // récupérer tous les éliminants enregistrés pour cette participation
                                                                        $elims_html = '';
                                                                        $isDefinitivelyEliminated = false;
                                                                        
                                                                        // CORRECTION : On récupère toutes les éliminations
                                                                        $elim_q = mysqli_query($con, "SELECT * FROM `eliminations` WHERE `id_participation` = '" . intval($row['id-participation']) . "' ORDER BY `created_at` ASC");
                                                                        
                                                                        if ($elim_q && mysqli_num_rows($elim_q) > 0) {
                                                                            $names = [];
                                                                            while ($er = mysqli_fetch_array($elim_q)) {
                                                                                $names[] = htmlspecialchars($er['nom_membre'], ENT_QUOTES);
                                                                                

                                                                                // CORRECTION MAJEURE : Vérification stricte après conversion en entier
                                                                                // Si UNE SEULE élimination est définitive, le joueur est éliminé pour de bon.
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

                                                                        // Vérifier si la requête a réussi
                                                                        if (!$countElimQuery) {
                                                                            die("Erreur de requête : " . mysqli_error($con));
                                                                        }

                                                                        $countElimRow = mysqli_fetch_array($countElimQuery);
                                                                        $elimCount = intval($countElimRow['cnt']);


                                                                        $elimCountDisplay = $elimCount > 0 ? ' <span style="color:red;">(' . $elimCount . ')</span>' : '';
                                                                        $classementDisplay = $rankingCounter == 99 ? '<i class="fa fa-trophy" style="color: gold; font-size: 1.2em;"></i>' : $rankingCounter;
                                                                        
                                                                        // --- LOGIQUE D'ELIMINATION ---
                                                                        // Si éliminé définitivement, on active le statut éliminé
                                                                        $isEliminated = $isDefinitivelyEliminated;
            
                                                                        // MODIFICATION : On ajoute TOUS les joueurs à la liste des éliminants potentiels
                                                                        // On force l'encodage UTF-8 pour éviter que json_encode ne plante
                                                                        $pName = $row['nom-membre'];
                                                                        if (function_exists('mb_convert_encoding')) {
                                                                            // Si la chaîne n'est pas UTF-8 valide, on tente de la convertir depuis ISO-8859-1
                                                                            if (!mb_check_encoding($pName, 'UTF-8')) {
                                                                                $pName = mb_convert_encoding($pName, 'UTF-8', 'ISO-8859-1');
                                                                            }
                                                                        }
                                                                        
                                                                        $validEliminators[] = array(
                                                                            'id' => intval($row['id-participation']),
                                                                            'name' => $pName
                                                                        );

                                                                        // CORRECTION CSS : Ajout de !important pour forcer le gris par dessus le style "striped" du tableau
                                                                        $rowStyle = $isEliminated ? 'opacity:0.6; background-color:#dcdcdc !important;' : '';
                                                                        $rowClass = $isEliminated ? 'eliminated' : '';
                                                                        $disabledAttr = $isEliminated ? 'disabled' : '';

                                                                        // --- Restriction Recave (User 265 uniquement) ---
                                                                        $recaveDisabledAttr = $disabledAttr; // Par défaut, suit l'état d'élimination
                                                                        if ($_SESSION['id'] != 265) {
                                                                            $recaveDisabledAttr = 'disabled'; // Désactivé si ce n'est pas l'admin 265
                                                                        }

                                                                        // Préparation du bouton PLUS (Caché si pas admin 265)
                                                                        $btnPlusDisplay = '';
                                                                        if ($_SESSION['id'] == 265) {
                                                                            $btnPlusDisplay = '<button class="btn btn-primary btn-sm btn-plus" type="button" onclick="addRecave(this)" data-id="' . intval($row['id-participation']) . '" ' . $recaveDisabledAttr . ' style="background-color: #007bff !important; color: white !important; border-color: #007bff !important;">+</button>';
                                                                        }

                                                                        echo '<tr class="' . $rowClass . '" style="' . $rowStyle . '">
                                                                            <td style="text-align:center; font-weight:bold;">' . $classementDisplay . '</td>
                                                                            <td class="pseudo-cell"><span class="actual-pseudo">' . htmlspecialchars($row['nom-membre'], ENT_QUOTES) . '</span>' . $elimCountDisplay . '</td>
                                                                            <td>
                                                                                <div class="input-group" style="width:100%;">
                                                                                    <!-- Input Recave -->
                                                                                    <input type="number" class="form-control recave-input" data-id="' . intval($row['id-participation']) . '" data-member-id="' . intval($membre_id) . '" value="' . intval($row['recave']) . '" ' . $recaveDisabledAttr . ' />
                                                                                    
                                                                                    <!-- Bouton Plus -->
                                                                                    ' . $btnPlusDisplay . '
                                                                                    
                                                                                    <!-- Bouton Sortie (Porte) -->
                                                                                    <button class="btn btn-danger btn-sm" style="color: red !important; font-size:22px !important ;background-color: #f0f0f0 !important; font-weight: bold;"
                                                                                            onclick="confirmDeletePlayer(this)" 
                                                                                            data-id="' . intval($row['id-participation']) . '" 
                                                                                            data-member-id="' . intval($row['id-membre']) . '" 
                                                                                            data-name="' . htmlspecialchars($row['nom-membre'], ENT_QUOTES) . '"
                                                                                            data-activity-id="' . $id . '"> <!-- AJOUT DE CETTE LIGNE -->
                                                                                            <i class="fa fa-trash"></i>
                                                                                      </button>
                                                                                </div>
                                                                            </td>
                                                                            <td><span class="eliminated-by" data-player-id="' . intval($row['id-participation']) . '" style="font-size:12px;color:' . ($isEliminated ? 'red' : 'inherit') . ';font-weight:' . ($isEliminated ? 'bold' : 'normal') . '">';
                                                                            
                                                                            if (!empty($elims_html)) {
                                                                                echo $elims_html;
                                                                            } else {
                                                                                echo '';
                                                                            }
                                                                            echo '</span></td></tr>';
                                                                            $rankingCounter++;
                                                                        }
                                                                        
                                                                        // AJOUT : Injection de la liste des éliminants valides en JS
                                                                        // Sécurisation de l'encodage JSON
                                                                        $jsonOutput = json_encode($validEliminators, JSON_UNESCAPED_UNICODE);
                                                                        
                                                                        if ($jsonOutput === false) {
                                                                            // En cas d'erreur JSON, on log l'erreur et on envoie un tableau vide pour ne pas casser le JS
                                                                            echo '<script>console.error("Erreur PHP json_encode: ' . json_last_error_msg() . '");</script>';
                                                                            $jsonOutput = '[]';
                                                                        }
                                                                        
                                                                        echo '<script>
                                                                            window.validEliminators = ' . $jsonOutput . ';
                                                                            console.log("Liste eliminants chargée : " + window.validEliminators.length + " joueurs disponibles.");
                                                                        </script>';
                                                                        ?>
                                                                    <tr style="background-color: #f0f0f0; font-weight: bold;">
                                                                        <td></td>
                                                                        <td><?php echo $countJoueurs . ' Caves à ' . $buyin . ' €'; ?></td>
                                                                        <td><?php echo $totalRecaves . ' ReCave(s) à ' . $recave_montant . ' €'; ?></td>
                                                                        <td>
                                                                            <?php
                                                                            $countNotDefinitivelyEliminated = 0;
                                                                            $reqForCounting = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-activite` = '$id'");

                                                                            // Vérifier si la requête a réussi
                                                                            if (!$reqForCounting) {
                                                                                die("Erreur de requête : " . mysqli_error($con));
                                                                            }

                                                                            while ($rowForCount = mysqli_fetch_array($reqForCounting)) {
                                                                                $isDefElim = false;
                                                                                $elimCheckQuery = mysqli_query($con, "SELECT * FROM `eliminations` WHERE `id_participation` = '" . intval($rowForCount['id-participation']) . "'");

                                                                                // Vérifier si la requête a réussi
                                                                                if (!$elimCheckQuery) {
                                                                                    die("Erreur de requête : " . mysqli_error($con));
                                                                                }

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
                                                            

                                                            <?php 
                                                            // DEBUG : On convertit l'ID en entier pour être sûr que la comparaison fonctionne
                                                            // (Ex: "265" devient 265)
                                                            $currentUserId = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
                                                            
                                                            if ($currentUserId === 265) { 
                                                            ?>
                                                                <div class="row">
                                                                    <div class="col-md-12 text-center" style="margin-top: 20px; margin-bottom: 20px; text-align: center;">
                                                                        <button type="button" class="btn btn-success btn-lg ;color: black !important;" onclick="validerRecaves()" style="cursor: pointer; z-index: 999; position: relative; font-size: 16px; padding: 10px 30px;background-color: #11a527ff !important; color: white !important">
                                                                            <i class="fa fa-check"></i> Valider Recaves
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            <?php } ?>

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="JoueursE" class="rubrique">
                                            <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
                                                
                                                <!-- Colonne GAUCHE : Gestion des Joueurs (Création / Inscription / Suppression) -->
                                                <div style="flex: 1; min-width: 450px;">
                                                
                                                    <!-- Bloc Création Rapide -->
                                                    <div class="panel panel-white" style="border: none; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); overflow: hidden;">
                                                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; border-bottom: 3px solid #5568d3;">
                                                            <h4 style="margin:0; color: white; font-size: 1.1em; font-weight: bold;">
                                                                <i class="fa fa-user-plus" style="margin-right: 10px;"></i> Création Nouveau Joueur
                                                            </h4>
                                                        </div>
                                                        <div style="padding: 15px; background: #f0f8ff;">
                                                            <form method="post" class="form-inline" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;">
                                                                <div class="form-group" style="flex: 1;">
                                                                    <label for="new_pseudo" style="display: block; margin-bottom: 5px;">Pseudo</label>
                                                                    <input type="text" name="pseudo" id="new_pseudo" class="form-control" placeholder="Pseudo" required style="width: 100%;">
                                                                </div>
                                                                <div class="form-group" style="flex: 1;">
                                                                    <label for="new_prenom" style="display: block; margin-bottom: 5px;">Prénom</label>
                                                                    <input type="text" name="prenom" id="new_prenom" class="form-control" placeholder="Prénom" style="width: 100%;">
                                                                </div>
                                                                <div class="form-group" style="display: flex; align-items: center; padding-bottom: 8px;">
                                                                    <div class="checkbox clip-check check-primary">
                                                                        <input type="checkbox" id="auto_reg" name="auto_register" value="1" checked>
                                                                        <label for="auto_reg"> Inscrire auto.</label>
                                                                    </div>
                                                                </div>
                                                                <div class="form-group">
                                                                    <button type="submit" class="btn btn-success" name="submit_create_player" style="margin-bottom: 0; background-color: #28a745 !important; color: white !important; border-color: #28a745 !important; opacity: 1 !important; visibility: visible !important;">
                                                                        Créer
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>

                                                    <!-- Bloc Inscription Rapide (Existant) -->
                                                    <div class="panel panel-white" style="border: none; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); overflow: hidden;">
                                                        <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 15px; border-bottom: 3px solid #1e7e34;">
                                                            <h4 style="margin:0; color: white; font-size: 1.1em; font-weight: bold;">
                                                                <i class="fa fa-sign-in" style="margin-right: 10px;"></i> Inscription Rapide Joueur
                                                            </h4>
                                                        </div>
                                                        
                                                        <div style="padding: 15px; background: #f0fff4;">
                                                            <?php
                                                            // Récupération des sièges occupés pour le JS
                                                            $occupied_seats_json = '[]';
                                                            $occupied_seats_data = [];
                                                            $occ_sql = mysqli_query($con, "SELECT `id-table`, `id-siege` FROM `participation` WHERE `id-activite` = '$id'");

                                                            // Vérifier si la requête a réussi
                                                            if (!$occ_sql) {
                                                                die("Erreur de requête : " . mysqli_error($con));
                                                            }

                                                            while ($occ_row = mysqli_fetch_array($occ_sql)) {
                                                                $occupied_seats_data[] = ['table' => intval($occ_row['id-table']), 'siege' => intval($occ_row['id-siege'])];
                                                            }
                                                            $occupied_seats_json = json_encode($occupied_seats_data);
                                                            ?>

                                                            <form method="post" class="form-inline" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;">
                                                                <div class="form-group" style="flex: 2;">
                                                                    <label for="membre_select" style="display: block; margin-bottom: 5px;">Joueur</label>
                                                                    <select name="membre" id="membre_select" class="form-control" required style="width: 100%;">
                                                                        <option value="">-- Sélectionner Pseudo --</option>
                                                                        <?php
                                                                        $membres_reg = mysqli_query($con, "SELECT `id-membre`,`pseudo` FROM `membres` ORDER BY `pseudo` ASC");
                                                                        while ($choix = mysqli_fetch_array($membres_reg)) {
                                                                            echo "<option value='" . $choix["id-membre"] . "'>" . htmlspecialchars($choix["pseudo"]) . "</option>";
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                </div>

                                                                <div class="form-group">
                                                                    <button type="submit" class="btn btn-primary" name="submit_player_reg" style="margin-bottom: 0; background-color: #28a745 !important; color: white !important; border-color: #28a745 !important; opacity: 1 !important; visibility: visible !important;">
                                                                        <i class="fa fa-plus"></i> Inscrire
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>

                                                    <!-- Bloc Suppression Rapide (Existant) -->
                                                    <div class="panel panel-white" style="border: none; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); overflow: hidden;">
                                                        <div style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 15px; border-bottom: 3px solid #bd2130;">
                                                            <h4 style="margin:0; color: white; font-size: 1.1em; font-weight: bold;">
                                                                <i class="fa fa-trash" style="margin-right: 10px;"></i> Suppression Rapide Participation
                                                            </h4>
                                                        </div>
                                                        <div style="padding: 15px; background: #fff0f0;">
                                                            <form method="post" class="form-inline" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce joueur de l\'activité ?');">
                                                                <div class="form-group" style="flex: 2;">
                                                                    <label for="membre_del_select" style="display: block; margin-bottom: 5px;">Joueur à supprimer</label>
                                                                    <select name="membre_del" id="membre_del_select" class="form-control" required style="width: 100%;">
                                                                        <option value="">-- Sélectionner Pseudo --</option>
                                                                        <?php
                                                                        // Sélectionner uniquement les joueurs inscrits à cette activité
                                                                        $membres_del_q = mysqli_query($con, "SELECT p.`id-membre`, m.`pseudo` 
                                                                                                            FROM `participation` p 
                                                                                                            JOIN `membres` m ON p.`id-membre` = m.`id-membre` 
                                                                                                            WHERE p.`id-activite` = '$id' 
                                                                                                            ORDER BY m.`pseudo` ASC");
                                                                        while ($choix_del = mysqli_fetch_array($membres_del_q)) {
                                                                            echo "<option value='" . $choix_del["id-membre"] . "'>" . htmlspecialchars($choix_del["pseudo"]) . "</option>";
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                </div>
                                                                <div class="form-group">
                                                                    <button type="submit" class="btn btn-danger" name="submit_quick_delete" style="margin-bottom: 0; background-color: #dc3545 !important; color: white !important; border-color: #dc3545 !important; opacity: 1 !important; visibility: visible !important;">
                                                                        Supprimer
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Colonne DROITE : Finances et Classement -->
                                                <div style="flex: 1; min-width: 450px;">
                                                
                                                    <!-- Bloc Répartition du Prize Pool -->
                                                    <div class="panel panel-white" style="border: none; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); overflow: hidden;">
                                                        <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 15px; border-bottom: 3px solid #1e7e34;">
                                                            <h4 style="margin:0; color: white; font-size: 1.1em; font-weight: bold;">
                                                                <i class="fa fa-money" style="margin-right: 10px;"></i> Répartition du Prize Pool
                                                            </h4>
                                                        </div>
                                                        <div style="padding: 15px; background: #e8f4f8;">
                                                            <?php
                                                            // Calculs pour le Prize Pool
                                                            // On récupère les infos de l'activité
                                                            $act_info_q = mysqli_query($con, "SELECT buyin, recave_montant, bounty, rake FROM activite WHERE `id-activite` = '$id'");

                                                            // Vérifier si la requête a réussi
                                                            if (!$act_info_q) {
                                                                die("Erreur de requête : " . mysqli_error($con));
                                                            }

                                                            $act_info = mysqli_fetch_array($act_info_q);
                                                            
                                                            $pp_buyin = floatval($act_info['buyin']);
                                                            $pp_recave = floatval($act_info['recave_montant']);
                                                            
                                                            // On récupère les comptes de participations
                                                            $stats_q = mysqli_query($con, "SELECT COUNT(*) as nb_joueurs, SUM(recave) as nb_recaves FROM participation WHERE `id-activite` = '$id'");

                                                            // Vérifier si la requête a réussi
                                                            if (!$stats_q) {
                                                                die("Erreur de requête : " . mysqli_error($con));
                                                            }

                                                            $stats = mysqli_fetch_array($stats_q);
                                                            
                                                            $nb_joueurs = intval($stats['nb_joueurs']);
                                                            $nb_recaves = intval($stats['nb_recaves']);
                                                            
                                                            $total_buyin = $nb_joueurs * $pp_buyin;
                                                            $total_recave = $nb_recaves * $pp_recave;
                                                            $grand_total = $total_buyin + $total_recave;
                                                            ?>
                                                            <div class="row text-center" style="font-size: 1.1em;">
                                                                <div class="col-sm-4">
                                                                    <strong>Total Buy-in</strong><br>
                                                                    <?php echo $nb_joueurs; ?> x <?php echo $pp_buyin; ?>€ = <span style="color:blue;"><?php echo number_format($total_buyin, 2); ?> €</span>
                                                                </div>
                                                                <div class="col-sm-4">
                                                                    <strong>Total Recaves</strong><br>
                                                                    <?php echo $nb_recaves; ?> x <?php echo $pp_recave; ?>€ = <span style="color:orange;"><?php echo number_format($total_recave, 2); ?> €</span>
                                                                </div>
                                                                <div class="col-sm-4">
                                                                    <strong>Prize Pool Total</strong><br>
                                                                    <span style="color:green; font-weight:bold; font-size: 1em;"><?php echo number_format($grand_total, 2); ?> €</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Bloc Podium des Joueurs Classés -->
                                                    <div class="panel panel-white" style="border: none; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); overflow: hidden;">
                                                        <div style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 15px; border-bottom: 3px solid #004494;">
                                                            <h4 style="margin:0; color: white; font-size: 1.1em; font-weight: bold;">
                                                                <i class="fa fa-trophy" style="margin-right: 10px;"></i> Podium des Joueurs Classés
                                                            </h4>
                                                        </div>
                                                        
                                                        <div style="padding: 15px; background: #fff;">
                                                            <form method="post">
                                                                <table class="table table-striped table-hover">
                                                                    <thead>
                                                                        <tr>
                                                                            <th style="width: 10%;">Rang</th>
                                                                            <th style="width: 35%;">Joueur</th>
                                                                            <th style="width: 10%; text-align: center;">Recaves</th>
                                                                            <th style="width: 10%; text-align: center;">Bounty</th>
                                                                            <th style="width: 35%;">Gains</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php
                                                                        // Récupérer les joueurs ayant un classement > 0 (Limité aux 9 premiers)
                                                                        // CORRECTION : Ajout de CAST(... AS UNSIGNED) pour trier correctement les nombres (ex: 2 avant 10)
                                                                        $podium_q = mysqli_query($con, "SELECT p.`id-participation`, p.`classement`, p.`nom-membre`, p.`gain`, p.`recave` 
                                                                                                        FROM `participation` p 
                                                                                                        WHERE p.`id-activite` = '$id' AND p.`classement` > 0 
                                                                                                        ORDER BY CAST(p.`classement` AS UNSIGNED) ASC LIMIT 9");
                                                                        
                                                                        if (mysqli_num_rows($podium_q) > 0) {
                                                                            while ($row_pod = mysqli_fetch_array($podium_q)) {
                                                                                $rank = intval($row_pod['classement']);
                                                                                $icon = '';
                                                                                if ($rank == 1) $icon = '🥇 ';
                                                                                elseif ($rank == 2) $icon = '🥈 ';
                                                                                elseif ($rank == 3) $icon = '🥉 ';
                                                                                
                                                                                // Calcul du nombre de Bounty (joueurs éliminés par ce membre)
                                                                                $bounty_count = 0;
                                                                                $player_name_safe = mysqli_real_escape_string($con, $row_pod['nom-membre']);
                                                                                $bounty_query = mysqli_query($con, "SELECT COUNT(*) as cnt FROM `eliminations` e JOIN `participation` p ON e.`id_participation` = p.`id-participation` WHERE p.`id-activite` = '$id' AND e.`nom_membre` = '$player_name_safe'");

                                                                                // Vérifier si la requête a réussi
                                                                                if (!$bounty_query) {
                                                                                    die("Erreur de requête : " . mysqli_error($con));
                                                                                }

                                                                                $b_row = mysqli_fetch_array($bounty_query);
                                                                                $bounty_count = intval($b_row['cnt']);

                                                                                echo '<tr>';
                                                                                // Rang : Police unifiée 15px, alignement milieu
                                                                                echo '<td style="font-weight:bold; font-size:15px; vertical-align: middle;">' . $icon . $rank . '</td>';
                                                                                
                                                                                // Nom : Police unifiée 15px, alignement milieu
                                                                                echo '<td style="font-size:15px; vertical-align: middle;">' . htmlspecialchars($row_pod['nom-membre']) . '</td>';
                                                                                
                                                                                // Recaves : Police unifiée 15px, alignement milieu
                                                                                echo '<td style="text-align:center; font-size:15px; vertical-align: middle;">' . intval($row_pod['recave']) . '</td>';
                                                                                
                                                                                // Bounty : Police unifiée 15px, alignement milieu
                                                                                echo '<td style="text-align:center; color:#d9534f; font-weight:bold; font-size:15px; vertical-align: middle;">' . $bounty_count . ' </td>';
                                                                                
                                                                                // Gain : Input stylisé pour correspondre à la taille du texte (15px)
                                                                                echo '<td style="vertical-align: middle;">
                                                                                        <div class="input-group">
                                                                                            <input type="number" step="1" class="form-control" name="gains[' . $row_pod['id-participation'] . ']" value="' . floatval($row_pod['gain']) . '" placeholder="000" style="font-weight:bold; font-size: 11px; height: 38px;">
                                                                                            
                                                                                        </div>
                                                                                      </td>';
                                                                                echo '</tr>';
                                                                            }
                                                                        } else {
                                                                            echo '<tr><td colspan="5" class="text-center text-muted">Aucun joueur classé pour le moment. (Utilisez le tableau principal pour définir l\'ordre d\'élimination)</td></tr>';
                                                                        }
                                                                        ?>
                                                                    </tbody>
                                                                </table>
                                                                <?php if (mysqli_num_rows($podium_q) > 0) { ?>
                                                                    <div class="text-right">
                                                                        <button type="submit" name="submit_gains" class="btn btn-success" style="background-color: #28a745 !important; color: white !important; border-color: #28a745 !important; opacity: 1 !important; visibility: visible !important;">
                                                                            <i class="fa fa-save"></i> Enregistrer les Gains
                                                                        </button>
                                                                    </div>
                                                                <?php } ?>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                        <div id="OutilsE" class="rubrique">
                                            <?php
                                            // Compte à rebours 30 secondes pour l'onglet outils
                                            ?>
                                            <div id="countdown-container" style="text-align: center; padding-top: 20px;">
                                                <div id="countdown-label" style="font-size: 50px; font-weight: bold; color: #555; margin-bottom: 10px;">
                                                    ⏱️ Compte à rebours
                                                </div>
                                                
                                                <!-- Affichage agrandi -->
                                                <div id="countdown-cardevent" style="font-size: 350px; font-weight: bold; line-height: 1; color: #333; font-family: 'Arial', sans-serif;">
                                                    30
                                                </div>
                                                
                                                <div class="countdown-buttons" style="margin-top: 30px; display: flex; justify-content: center; gap: 20px;">
                                                    <button class="btn btn-success" id="btn-start" style="font-size: 30px; padding: 15px 40px; border-radius: 10px;">▶ START</button>
                                                    <button class="btn btn-warning" id="btn-stop" disabled style="font-size: 30px; padding: 15px 40px; border-radius: 10px; color: white;">⏸ STOP</button>
                                                    <button class="btn btn-primary" id="btn-reset" style="font-size: 30px; padding: 15px 40px; border-radius: 10px;">🔄 RESET</button>
                                                </div>

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
                                                        let alarmSound = new Audio('/30s.mp3');
                                                        alarmSound.load();
                                                        alarmSound.play();

                                                        // Prononcé un message aussi
                                                        if (typeof responsiveVoice !== 'undefined') {
                                                            // Fallback iOS amélioré (V4)
                                                            var voice = "French Female"; // Par défaut
                                                            var voices = responsiveVoice.getVoices();
                                                            
                                                            var foundFemale = false;
                                                            var foundAmelie = null;
                                                            var foundThomas = null;
                                                            var foundFrench = null;
                                                            
                                                            for (var i = 0; i < voices.length; i++) {
                                                                var v = voices[i];
                                                                var name = v.name || "";
                                                                var lang = v.lang || "";
                                                                
                                                                if (name === "French Female") foundFemale = true;
                                                                if (name.indexOf("Amelie") !== -1) foundAmelie = name;
                                                                if (name.indexOf("Thomas") !== -1) foundThomas = name;
                                                                
                                                                if (!foundFrench && (lang.indexOf("fr") === 0 || name.indexOf("French") !== -1)) {
                                                                    foundFrench = name;
                                                                }
                                                            }
                                                            
                                                            if (foundFemale) voice = "French Female";
                                                            else if (foundAmelie) voice = foundAmelie;
                                                            else if (foundFrench) voice = foundFrench;
                                                            else if (foundThomas) voice = foundThomas;
                                                            
                                                            responsiveVoice.speak("Temps écoulé!", voice);
                                                        }
                                                    }

                                                    // Mettre à jour l'affichage
                                                    function updateDisplay() {
                                                        // Logique d'affichage : Entier si > 5s, Dixièmes si <= 5s
                                                        if (countdownTime > 5) {
                                                            timerDisplay.textContent = Math.ceil(countdownTime);
                                                        } else {
                                                            // Affiche 1 décimale (ex: 4.9)
                                                            timerDisplay.textContent = countdownTime.toFixed(1);
                                                        }

                                                        // Ajouter une animation et couleur rouge quand on approche de 0
                                                        if (countdownTime <= 5) {
                                                            timerDisplay.style.color = 'red';
                                                        } else {
                                                            timerDisplay.style.color = '#333';
                                                        }
                                                    }

                                                    // Démarrer le compte à rebours
                                                    btnStart.addEventListener('click', function () {
                                                        if (!isRunning && countdownTime > 0) {
                                                            isRunning = true;
                                                            btnStart.disabled = true;
                                                            btnStop.disabled = false;
                                                            btnReset.disabled = true;

                                                            // On utilise Date.now() pour calculer le temps écoulé précisément
                                                            let lastTime = Date.now();

                                                            // Intervalle (10ms)
                                                            countdownTimer = setInterval(function () {
                                                                let now = Date.now();
                                                                // Correction ici : division par 1000 pour avoir des secondes, pas des déci-secondes
                                                                let deltaTime = (now - lastTime) / 1000; 
                                                                lastTime = now;

                                                                countdownTime -= deltaTime;

                                                                if (countdownTime <= 0) {
                                                                    countdownTime = 0;
                                                                    updateDisplay();
                                                                    clearInterval(countdownTimer);
                                                                    isRunning = false;
                                                                    playAlarm();

                                                                    btnStart.disabled = true;
                                                                    btnStop.disabled = true;
                                                                    btnReset.disabled = false;
                                                                } else {
                                                                    updateDisplay();
                                                                }
                                                            }, 10); // Mise à jour toutes les 10ms pour fluidité
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
                                                        btnStart.disabled = false;
                                                        btnStop.disabled = true;
                                                        btnReset.disabled = false;
                                                    });

                                                    // Initialisation
                                                    updateDisplay();
                                                </script>
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
                    
                    <!-- MODIFICATION : On affiche le bouton SEULEMENT si c'est l'utilisateur 265 -->
                    <?php if ($_SESSION['id'] > 1) { ?>
                        <div class="text-center" style="margin-top:15px; display: flex; justify-content: center; align-items: center;">
                            <button class="btn btn-success" onclick="validerBlindes()" style="background-color: #28a745 !important; color: white !important; border-color: #28a745 !important;">Valider Blindes</button>
                        </div>
                    <?php } ?>

                </div>
            </div>
        </div>

        <!-- Colonne droite: Gestion des sauvegardes -->
        <div style="flex: 1; min-width: 400px;">
            <ol class="breadcrumb mb-4" style="background-color: transparent; padding:  0; margin-bottom: 15px;">
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
        <!-- SUPPRIMER LA LIGNE CI-DESSOUS SI ELLE EXISTE DEJA DANS LE HEAD -->
        <!-- <script src="vendor/jquery/jquery.min.js"></script> -->
        
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
        
        <!-- INCLUSION DU FICHIER JS AVEC TIMESTAMP ANTI-CACHE -->
        <!-- IMPORTANT : Doit être APRES jQuery et APRES main.js -->
        <script src="voir-blindes.js?v=<?php echo time(); ?>"></script>

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

                // Afficher l'onglet spécifié ou Timer par défaut
                <?php if(isset($_GET['tab'])) { ?>
                    console.log('Affichage de l\'onglet spécifié : <?php echo htmlentities($_GET['tab']); ?>');
                    afficher2('<?php echo htmlentities($_GET['tab']); ?>');
                <?php } else { ?>
                    console.log('Affichage de l\'onglet Timer par défaut');
                    afficher2('Timer');
                <?php } ?>
            });
        </script>
    </body>
</html>
<?php } ?>
