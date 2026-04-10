<?php
session_start();
// --- ENABLE ERROR REPORTING FOR DEBUGGING ---
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
error_reporting(0); // Production setting

include('include/config.php'); // Ensure DB connection ($con)

// --- Initial Variable Setup & Defaults ---
$gid_part = isset($_GET['part']) ? intval($_GET['part']) : null;
$gid_acti = isset($_GET['acti']) ? intval($_GET['acti']) : null;
$gid_tabl = isset($_GET['tabl']) ? intval($_GET['tabl']) : null;
$gid_sieg = isset($_GET['sieg']) ? intval($_GET['sieg']) : null;
$source = isset($_GET['sour']) ? $_GET['sour'] : '';
$actu = strtotime(date("Y-m-d H:i:s"));
$actu2 = date("Y-m-d");

// --- Handle Activity Choice Submission ---
if (isset($_POST['submitchoixact'])) {
    $acti_choice = $_POST['acti'];
    if ($acti_choice != '-Anonyme-' && filter_var($acti_choice, FILTER_VALIDATE_INT)) {
        $_SESSION['selected_activity'] = intval($acti_choice);
    } else {
         unset($_SESSION['selected_activity']);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- Handle Quick Player Creation ---
if (isset($_POST['submitcreaj'])) {
    $pseudo = trim($_POST['pseudo']);
    $fname = trim($_POST['fname']);
    if (!empty($pseudo)) {
        $sql_create_player = "INSERT INTO `membres` (`pseudo`, `fname`) VALUES (?, ?)";
        $stmt_create = mysqli_prepare($con, $sql_create_player);
        if ($stmt_create) {
            mysqli_stmt_bind_param($stmt_create, "ss", $pseudo, $fname);
            if (mysqli_stmt_execute($stmt_create)) {
                $new_player_id = mysqli_insert_id($con);
                $_SESSION['feedback'] = "<div class='alert alert-success'>Joueur rapide créé : Pseudo = " . htmlspecialchars($pseudo) . ", Prénom = " . htmlspecialchars($fname) . " (ID: $new_player_id)</div>";
            } else { $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur création joueur rapide: " . htmlspecialchars(mysqli_stmt_error($stmt_create)) . "</div>"; }
            mysqli_stmt_close($stmt_create);
        } else { $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur préparation création joueur: " . htmlspecialchars(mysqli_error($con)) . "</div>"; }
    } else { $_SESSION['feedback'] = "<div class='alert alert-warning'>Le pseudo est requis pour la création rapide.</div>"; }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- Handle Quick Registration (Initializes rake/cout_in from activity) ---
if (isset($_POST['submit'])) {
    $membre = isset($_POST['membre']) && $_POST['membre'] != '' ? intval($_POST['membre']) : null;
    $acti = isset($_POST['acti']) && $_POST['acti'] != '' ? intval($_POST['acti']) : null;
    $tabl = isset($_POST['table']) ? intval($_POST['table']) : 1;
    $sieg = isset($_POST['siege']) ? intval($_POST['siege']) : 1;

    if ($membre && $acti) {
        $check_sql = "SELECT `id-participation` FROM `participation` WHERE `id-membre` = ? AND `id-activite` = ?";
        $stmt_check = mysqli_prepare($con, $check_sql);
        mysqli_stmt_bind_param($stmt_check, "ii", $membre, $acti);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);

        if (mysqli_stmt_num_rows($stmt_check) == 0) {
            mysqli_stmt_close($stmt_check);

            $default_activity_rake = 0.0;
            $default_activity_buyin = 0.0;
            $default_activity_bounty = 0.0;
            $fetch_defaults_sql = "SELECT rake, buyin, bounty FROM activite WHERE `id-activite` = ?";
            $stmt_fetch_defaults = mysqli_prepare($con, $fetch_defaults_sql);
            if ($stmt_fetch_defaults) {
                mysqli_stmt_bind_param($stmt_fetch_defaults, "i", $acti);
                if (mysqli_stmt_execute($stmt_fetch_defaults)) {
                    $defaults_result = mysqli_stmt_get_result($stmt_fetch_defaults);
                    if ($defaults_row = mysqli_fetch_assoc($defaults_result)) {
                        $default_activity_rake = floatval($defaults_row['rake'] ?? 0.0);
                        $default_activity_buyin = floatval($defaults_row['buyin'] ?? 0.0);
                        $default_activity_bounty = floatval($defaults_row['bounty'] ?? 0.0);
                    }
                    mysqli_free_result($defaults_result);
                } else { error_log("Erreur execution fetch defaults pour activité ID $acti: " . mysqli_stmt_error($stmt_fetch_defaults)); }
                mysqli_stmt_close($stmt_fetch_defaults);
            } else { error_log("Erreur préparation fetch defaults pour activité ID $acti: " . mysqli_error($con)); }

            $initial_cout_in = $default_activity_buyin + $default_activity_bounty + $default_activity_rake + ($challenger ? 5 : 0);

            $sql_quick_reg = "INSERT INTO `participation` (`id-membre`, `id-activite`, `id-table`, `id-siege`, rake, cout_in) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_reg = mysqli_prepare($con, $sql_quick_reg);
            if ($stmt_reg) {
                 mysqli_stmt_bind_param($stmt_reg, "iiiidd", $membre, $acti, $tabl, $sieg, $default_activity_rake, $initial_cout_in);
                 if (mysqli_stmt_execute($stmt_reg)) {
                     $_SESSION['feedback'] = "<div class='alert alert-success'>Inscription rapide réussie : Joueur ID = $membre, Activité ID = $acti, Table = $tabl, Siège = $sieg. Rake init.: " . number_format($default_activity_rake, 2) . ", Cout In init.: " . number_format($initial_cout_in, 2) . ".</div>";
                 } else { $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur inscription rapide: " . htmlspecialchars(mysqli_stmt_error($stmt_reg)) . "</div>"; }
                 mysqli_stmt_close($stmt_reg);
            } else { $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur préparation inscription: " . htmlspecialchars(mysqli_error($con)) . "</div>"; }
        } else {
            mysqli_stmt_close($stmt_check);
            $_SESSION['feedback'] = "<div class='alert alert-warning'>Ce joueur est déjà inscrit à cette activité.</div>";
        }
    } else { $_SESSION['feedback'] = "<div class='alert alert-warning'>Veuillez sélectionner un joueur et une activité valides pour l'inscription rapide.</div>"; }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- Handle form submission for updating participations ---
if (isset($_POST['update_participation'])) {
    $batch_error_messages = [];
    $update_success_overall = true;

    $selected_activity_id_for_update = isset($_SESSION['selected_activity']) ? intval($_SESSION['selected_activity']) : null;
    $activity_buyin_for_calc = 0.0;
    $activity_bounty_for_calc = 0.0;

    if ($selected_activity_id_for_update) {
        $activity_details_query = "SELECT buyin, bounty FROM activite WHERE `id-activite` = ?";
        $stmt_activity = mysqli_prepare($con, $activity_details_query);
        if ($stmt_activity) {
            mysqli_stmt_bind_param($stmt_activity, "i", $selected_activity_id_for_update);
            if (mysqli_stmt_execute($stmt_activity)) {
                $activity_result = mysqli_stmt_get_result($stmt_activity);
                if ($activity_row = mysqli_fetch_assoc($activity_result)) {
                    $activity_buyin_for_calc = floatval($activity_row['buyin'] ?? 0.0);
                    $activity_bounty_for_calc = floatval($activity_row['bounty'] ?? 0.0);
                } else {
                    $batch_error_messages[] = "Détails de l'activité ID {$selected_activity_id_for_update} non trouvés.";
                    $update_success_overall = false;
                }
                mysqli_free_result($activity_result);
            } else {
                 $batch_error_messages[] = "Erreur execution requête Détails Activité: " . htmlspecialchars(mysqli_stmt_error($stmt_activity));
                 $update_success_overall = false;
            }
            mysqli_stmt_close($stmt_activity);
        } else {
            $batch_error_messages[] = "Erreur préparation requête Détails Activité: " . htmlspecialchars(mysqli_error($con));
            $update_success_overall = false;
        }
    } else {
        $batch_error_messages[] = "Veuillez d'abord filtrer par une activité pour effectuer la mise à jour.";
        $update_success_overall = false;
    }

    if ($update_success_overall && $selected_activity_id_for_update) {
        $sql_update = "UPDATE participation SET
                       challenger = ?, `id-table` = ?, `id-siege` = ?, cout_in = ?,
                       rake = ?, recave = ?, classement = ?, tf = ?, points = ?,
                       cagnotte = ?
                       WHERE `id-membre` = ? AND `id-activite` = ?";

        $stmt_update = mysqli_prepare($con, $sql_update);

        if ($stmt_update) {
            $base_activity_buyin = $activity_buyin_for_calc;
            $row_update_errors = 0;

            if (isset($_POST['participations']) && is_array($_POST['participations'])) {
                foreach ($_POST['participations'] as $index => $participation) {
                    $form_activite_id = intval($participation['activite_id']);
                    if ($form_activite_id !== $selected_activity_id_for_update) {
                        $batch_error_messages[] = "Erreur: Incohérence d'ID d'activité pour la ligne #$index. Mise à jour ignorée.";
                        $row_update_errors++;
                        continue;
                    }

                    $membre_id = intval($participation['membre_id']);
                    $challenger = isset($participation['challenger']) ? 1 : 0;
                    $table = intval($participation['table'] ?? 1);
                    $siege = intval($participation['siege'] ?? 1);
                    $tf = isset($participation['tf']) ? 1 : 0;
                    $participation_rake_value = ($participation['rake'] === '' || $participation['rake'] === null) ? 0.0 : floatval($participation['rake']);
                    $recave = ($participation['recave'] === '' || $participation['recave'] === null) ? 0 : intval($participation['recave']);
                    $classement_input = $participation['classement'] ?? null;
                    $classement = ($classement_input === '' || $classement_input === null) ? null : intval($classement_input);

                    $calculated_cout_in = $base_activity_buyin + $activity_bounty_for_calc + $participation_rake_value + ($challenger ? 5 : 0);

                    $calculated_points = 0;
                    if ($challenger == 1) {
                        $calculated_points = 1;
                        if ($tf == 1) $calculated_points += 1;
                        if ($classement !== null && $classement == 1) $calculated_points += 1;
                    }

                    $calculated_cagnotte = ($challenger == 1) ? (($recave * 3) + 3) : 0;

                    mysqli_stmt_bind_param($stmt_update, "iiiddiiidiii",
                        $challenger, $table, $siege, $calculated_cout_in,
                        $participation_rake_value, $recave, $classement, $tf,
                        $calculated_points,
                        $calculated_cagnotte,
                        $membre_id, $selected_activity_id_for_update
                    );

                    if (!mysqli_stmt_execute($stmt_update)) {
                        $row_update_errors++;
                        $batch_error_messages[] = "Erreur MAJ Ligne #$index (Membre ID: $membre_id): " . htmlspecialchars(mysqli_stmt_error($stmt_update));
                    }
                } // End foreach
            } else {
                 $batch_error_messages[] = "Aucune donnée de participation reçue pour la mise à jour.";
                 $update_success_overall = false;
            }

            mysqli_stmt_close($stmt_update);

            if ($update_success_overall) {
                 if ($row_update_errors == 0) { $_SESSION['feedback'] = "<div class='alert alert-success'>Participations mises à jour avec succès pour l'activité ID {$selected_activity_id_for_update}.</div>"; }
                 else { $_SESSION['feedback'] = "<div class='alert alert-warning'>Mise à jour terminée pour l'activité ID {$selected_activity_id_for_update} avec {$row_update_errors} erreur(s):<br>" . implode("<br>", array_map('htmlspecialchars', $batch_error_messages)) . "</div>"; }
            }
        } else {
            $batch_error_messages[] = "Erreur critique de préparation de la requête UPDATE: " . htmlspecialchars(mysqli_error($con));
            $update_success_overall = false;
        }
    }

     if (!$update_success_overall) {
          $error_message = "Impossible de lancer/terminer la mise à jour :<br>" . implode("<br>", array_map('htmlspecialchars', $batch_error_messages));
          $_SESSION['feedback'] = "<div class='alert alert-danger'>$error_message</div>";
     }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
} 

// --- Retrieve feedback message from session and clear it ---
$session_feedback = $_SESSION['feedback'] ?? null;
unset($_SESSION['feedback']);

// --- Variables needed for Price Pool Calculation ---
$selected_activity = isset($_SESSION['selected_activity']) ? intval($_SESSION['selected_activity']) : null;
$activity_buyin_for_pricepool = 0.0;
$total_buyin_sum = 0.0;
$total_recave_sum = 0; 
$price_pool = 0.0;
$total_participants = 0;

if ($selected_activity !== null) {
    // Fetch activity buyin for price pool calculation
    $stmt_bp = mysqli_prepare($con, "SELECT buyin FROM activite WHERE `id-activite` = ?");
    if($stmt_bp) {
        mysqli_stmt_bind_param($stmt_bp, "i", $selected_activity);
        mysqli_stmt_execute($stmt_bp);
        $bp_result = mysqli_stmt_get_result($stmt_bp);
        if ($bp_row = mysqli_fetch_assoc($bp_result)) {
            $activity_buyin_for_pricepool = floatval($bp_row['buyin'] ?? 0.0);
        }
        mysqli_free_result($bp_result);
        mysqli_stmt_close($stmt_bp);
    }

    // Fetch sums needed for price pool
    $stmt_sums = mysqli_prepare($con, "SELECT COUNT(*) as participant_count, SUM(a.buyin) as total_buyin, SUM(p.recave) as total_recave
                                      FROM participation p
                                      JOIN activite a ON p.`id-activite` = a.`id-activite` 
                                      WHERE p.`id-activite` = ?");
     if ($stmt_sums) {
        mysqli_stmt_bind_param($stmt_sums, "i", $selected_activity);
        mysqli_stmt_execute($stmt_sums);
        $sums_result = mysqli_stmt_get_result($stmt_sums);
        if ($sums_row = mysqli_fetch_assoc($sums_result)) {
            $total_participants = intval($sums_row['participant_count'] ?? 0);
            $total_buyin_sum = floatval($sums_row['total_buyin'] ?? 0.0);
            $total_recave_sum = intval($sums_row['total_recave'] ?? 0);
        }
        mysqli_free_result($sums_result);
        mysqli_stmt_close($stmt_sums);

        // Calculate Price Pool
        $price_pool = $total_buyin_sum + ($total_recave_sum * $activity_buyin_for_pricepool);
     }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Admin | Gestion des Participations</title>
    <!-- Head content remains the same as original -->
    <style>
        /* Styles remain the same as original */
    </style>
</head>
<body>
    <div id="app">
        <?php include('include/sidebar.php'); ?>
        <div class="app-content">
            <?php include('include/header.php'); ?>
            <!-- Container and forms remain the same structure -->
            
            <!-- Participation List -->
            <div class="panel panel-white card">
                <div class="panel-body">
                    <h2>Liste des Participations <?php echo $selected_activity ? "(Activité ID: ".$selected_activity.")" : "(Toutes les activités à venir)"; ?></h2>
                    
                    <form method="post" id="participation-form">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th class="cell-center">Ch.</th>
                                        <th>Joueur</th>
                                        <th class="cell-center">T</th>
                                        <th class="cell-center">S</th>
                                        <th class="cell-right">Buy-in Act.</th>
                                        <th class="cell-right">Bounty</th>
                                        <th class="cell-right">Rake Part.</th> 
                                        <th class="cell-right">Cout In</th>
                                        <th class="cell-right">Rec. (0-4)</th>
                                        <th class="cell-center">Clas.</th>
                                        <th class="cell-center">TF</th>
                                        <th class="cell-right">Pts</th>
                                        <th class="cell-right">Cagnotte</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $total_challengers = 0;
                                    $total_buyin_activite = 0.0;
                                    $total_rake_participation = 0.0;
                                    $total_cout_in = 0.0;
                                    $total_recave = 0;
                                    $total_cagnotte = 0;
                                    $total_bounty = 0.0;
                                    ?>

                                    <!-- Participation rows remain the same structure -->
                                </tbody>
