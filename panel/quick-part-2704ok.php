de trier les colonnes de la liste des pa<?php
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
            $fetch_defaults_sql = "SELECT rake, buyin, bounty FROM activite WHERE `id-activite` = ?";
            $stmt_fetch_defaults = mysqli_prepare($con, $fetch_defaults_sql);
            if ($stmt_fetch_defaults) {
                mysqli_stmt_bind_param($stmt_fetch_defaults, "i", $acti);
                if (mysqli_stmt_execute($stmt_fetch_defaults)) {
                    $defaults_result = mysqli_stmt_get_result($stmt_fetch_defaults);
                    if ($defaults_row = mysqli_fetch_assoc($defaults_result)) {
                        $default_activity_rake = floatval($defaults_row['rake'] ?? 0.0);
                        $default_activity_bounty = floatval($defaults_row['bounty'] ?? 0.0);
                        $default_activity_buyin = floatval($defaults_row['buyin'] ?? 0.0);
                    }
                     mysqli_free_result($defaults_result);
                } else { error_log("Erreur execution fetch defaults pour activité ID $acti: " . mysqli_stmt_error($stmt_fetch_defaults)); }
                mysqli_stmt_close($stmt_fetch_defaults);
            } else { error_log("Erreur préparation fetch defaults pour activité ID $acti: " . mysqli_error($con)); }

            // Calculate cout_in = buyin + bounty + rake + (5 if challenger)
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


// --- Handle Quick Deletion ---
if (isset($_POST['submitsupc'])) {
    $membre = isset($_POST['membresup']) && $_POST['membresup'] != '' ? intval($_POST['membresup']) : null;
    $acti = isset($_POST['actisup']) && $_POST['actisup'] != '' ? intval($_POST['actisup']) : null;
    if ($membre && $acti) {
        $sql_quick_del = "DELETE FROM `participation` WHERE `id-membre` = ? AND `id-activite` = ?";
        $stmt_del = mysqli_prepare($con, $sql_quick_del);
        if ($stmt_del) {
            mysqli_stmt_bind_param($stmt_del, "ii", $membre, $acti);
            if (mysqli_stmt_execute($stmt_del)) {
                $affected_rows = mysqli_stmt_affected_rows($stmt_del);
                if ($affected_rows > 0) { $_SESSION['feedback'] = "<div class='alert alert-success'>Suppression rapide réussie: Participation pour Joueur ID = $membre, Activité ID = $acti supprimée.</div>"; }
                else { $_SESSION['feedback'] = "<div class='alert alert-info'>Aucune participation trouvée pour Joueur ID = $membre et Activité ID = $acti à supprimer.</div>"; }
            } else { $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur exécution suppression rapide: " . htmlspecialchars(mysqli_stmt_error($stmt_del)) . "</div>"; }
            mysqli_stmt_close($stmt_del);
        } else { $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur préparation suppression rapide: " . htmlspecialchars(mysqli_error($con)) . "</div>"; }
    } else { $_SESSION['feedback'] = "<div class='alert alert-warning'>Veuillez sélectionner un joueur et une activité valides pour la suppression rapide.</div>"; }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


// --- Handle form submission for updating participations ---
if (isset($_POST['update_participation'])) {
    $batch_error_messages = [];
    $update_success_overall = true;

    $selected_activity_id_for_update = isset($_SESSION['selected_activity']) ? intval($_SESSION['selected_activity']) : null;
    $activity_buyin_for_calc = 0.0; // Renamed to avoid confusion

    if ($selected_activity_id_for_update) {
        $activity_details_query = "SELECT buyin FROM activite WHERE `id-activite` = ?";
        $stmt_activity = mysqli_prepare($con, $activity_details_query);
        if ($stmt_activity) {
            mysqli_stmt_bind_param($stmt_activity, "i", $selected_activity_id_for_update);
            if (mysqli_stmt_execute($stmt_activity)) {
                $activity_result = mysqli_stmt_get_result($stmt_activity);
                if ($activity_row = mysqli_fetch_assoc($activity_result)) {
                    $activity_buyin_for_calc = floatval($activity_row['buyin'] ?? 0.0);
                } else {
                    $batch_error_messages[] = "Détails (buyin) de l'activité ID {$selected_activity_id_for_update} non trouvés.";
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
                       cagnotte = ?, remise = ?
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

                    // Calculate cout_in = buyin + bounty + rake + (5 if challenger)
                    $calculated_cout_in = $base_activity_buyin + $raw_bounty + $participation_rake_value + ($challenger ? 5 : 0);

                    $calculated_points = 0;
                    if ($challenger == 1) {
                        $calculated_points = 1;
                        if ($tf == 1) $calculated_points += 1;
                        if ($classement !== null && $classement == 1) $calculated_points += 1;
                    }

                    $calculated_cagnotte = ($challenger == 1) ? (($recave * 3) + 3) : 0;
                    // Apply remise deduction if remise is checked
                    if (isset($participation['remise']) && $participation['remise']) {
                        $calculated_cagnotte = max(0, $calculated_cagnotte - 3);
                    }

                    $remise = isset($participation['remise']) ? 1 : 0;
                    mysqli_stmt_bind_param($stmt_update, "iiiddiiidiiii",
                        $challenger, $table, $siege, $calculated_cout_in,
                        $participation_rake_value, $recave, $classement, $tf,
                        $calculated_points,
                        $calculated_cagnotte,
                        $remise,
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
          $_SESSION['feedback'] = "<div class='alert alert-danger'>Impossible de lancer/terminer la mise à jour :<br>" . implode("<br>", array_map('htmlspecialchars', $batch_error_messages)) . "</div>";
     }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
} // End if (isset($_POST['update_participation']))


// --- Retrieve feedback message from session and clear it ---
$session_feedback = $_SESSION['feedback'] ?? null;
unset($_SESSION['feedback']);

// --- Variables needed for Price Pool Calculation (Fetch only if an activity is selected) ---
$selected_activity = isset($_SESSION['selected_activity']) ? intval($_SESSION['selected_activity']) : null;
$activity_buyin_for_pricepool = 0.0; // Initialize
$total_buyin_sum = 0.0; // Initialize
$total_recave_sum = 0; // Initialize
$price_pool = 0.0; // Initialize
$total_participants = 0; // Initialize for prize pool form

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

    // Fetch sums needed for price pool from participation table for the selected activity
    $stmt_sums = mysqli_prepare($con, "SELECT COUNT(*) as participant_count, SUM(a.buyin) as total_buyin, SUM(p.recave) as total_recave
                                      FROM participation p
                                      JOIN activite a ON p.`id-activite` = a.`id-activite`
                                      WHERE p.`id-activite` = ?");
     if ($stmt_sums) {
        mysqli_stmt_bind_param($stmt_sums, "i", $selected_activity);
        mysqli_stmt_execute($stmt_sums);
        $sums_result = mysqli_stmt_get_result($stmt_sums);
        if ($sums_row = mysqli_fetch_assoc($sums_result)) {
            $total_participants = intval($sums_row['participant_count'] ?? 0); // Get participant count here
            $total_buyin_sum = floatval($sums_row['total_buyin'] ?? 0.0); // Sum of activity.buyin for each participant
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Template CSS -->
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
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet" />
    <!-- <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" /> -->

    <!-- Specific CSS from original quick-part.php -->
    <style>
        /* --- Paste the full responsive CSS from original quick-part.php --- */
        :root {
            --primary-color: #ff6b2b; /* Orange */
            --primary-hover: #ff8651;
            --background: #f5f7fa;    /* Light grey */
            --card-bg: #ffffff;       /* White */
            --text-color: #2d3748;    /* Dark grey/blue */
            --border-color: #e2e8f0;   /* Light grey border */
            --danger-color: #dc3545;   /* Red */
            --danger-border: #dc3545;
            --readonly-bg: #e9ecef;    /* Background for readonly inputs */
            --total-row-bg: #e9f5ff; /* Light blue for total row */
        }

        /* Apply background to body *within* the theme structure */
        .main-content .container-fullw {
             background-color: var(--background) !important; /* Override theme white */
        }

        /* Keep body font, but theme might override */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
            color: var(--text-color);
            line-height: 1.6;
            font-size: 16px; /* Base size */
        }

        /* Adjust container padding within theme */
        .wrap-content.container {
            max-width: 1400px; /* Slightly wider for totals */
            margin: 1.5rem auto;
            padding: 0 1rem; /* Use theme's container padding */
        }

        /* Use theme's panel/card styling as base, but apply specific overrides */
        .panel.panel-white, .card { /* Target both theme panel and bootstrap card */
            background: var(--card-bg) !important; /* Ensure white background */
            border-radius: 10px !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04),
                        0 1px 2px rgba(0, 0, 0, 0.06) !important;
            padding: 1.5rem !important;
            margin-bottom: 1.5rem !important;
            border: none !important; /* Remove theme borders if any */
        }
        .panel-body {
             padding: 0 !important; /* Remove default panel-body padding */
        }


        h2 { /* Style section titles */
            margin-top: 0;
            margin-bottom: 1.2rem;
            color: var(--text-color);
            font-size: 1.3rem;
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.6rem;
        }

        /* Form Controls - Adapt to theme but keep specific styles */
        .form-control {
            display: block;
            width: 100%;
            padding: 0.7rem 1rem !important; /* Use specific padding */
            border: 1px solid var(--border-color) !important;
            border-radius: 6px !important;
            margin: 0.2rem 0;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            font-size: 1rem !important; /* Use specific font size */
            box-sizing: border-box;
            background-color: #fff !important;
            color: var(--text-color) !important;
            height: auto !important; /* Override theme height */
            box-shadow: none !important; /* Override theme shadow */
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 3px rgba(255, 107, 43, 0.15) !important;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23555' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.8rem center;
            background-size: 16px 16px;
            padding-right: 2.5rem !important;
        }

        input:read-only,
        .form-control:disabled {
            background-color: var(--readonly-bg) !important;
            cursor: not-allowed;
            opacity: 0.7;
        }

        /* Buttons - Adapt to theme but keep specific styles */
        .btn {
            padding: 0.7rem 1.4rem !important;
            border: none !important;
            border-radius: 6px !important;
            cursor: pointer;
            font-weight: 600 !important;
            font-size: 0.9rem !important;
            transition: all 0.2s ease;
            text-transform: uppercase !important;
            letter-spacing: 0.5px;
            display: inline-block;
            text-align: center;
            vertical-align: middle;
            line-height: 1.5 !important;
            box-shadow: none !important; /* Override theme shadow */
        }

        .btn-primary-orange2 { /* Custom button class */
            background: var(--primary-color) !important;
            color: white !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .btn-primary-orange2:hover,
        .btn-primary-orange2:focus {
            background: var(--primary-hover) !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
            outline: none;
        }

        .btn-block {
            width: 100%;
            display: block;
        }

        .btn-delete { /* Specific delete button */
            background-color: transparent !important;
            border: none;
            color: var(--danger-color) !important;
            padding: 0.3rem 0.5rem !important;
            font-size: 1.1rem !important;
            min-width: auto;
            line-height: 1 !important;
            font-weight: bold !important;
            text-transform: none !important; /* Override uppercase */
            letter-spacing: normal !important; /* Override spacing */
        }
        .btn-delete:hover,
        .btn-delete:focus {
            color: #a02430 !important;
            background-color: rgba(220, 53, 69, 0.1) !important;
            outline: none;
            transform: none !important; /* Override hover transform */
            box-shadow: none !important; /* Override hover shadow */
        }

        /* Simple Form Table (for quick add/delete forms) */
        .simple-form-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0; /* Remove margin inside card */
        }
        .simple-form-table th,
        .simple-form-table td {
            padding: 0.5rem 0.2rem;
            text-align: left;
            vertical-align: middle;
            border: none;
        }
        .simple-form-table th {
            width: 120px;
            font-weight: 600;
            padding-right: 1rem;
            white-space: nowrap;
        }
        .simple-form-table .btn-container-cell {
            padding-top: 0.8rem;
        }
         .simple-form-table .btn-container {
            display: flex;
            justify-content: flex-end;
         }

        /* Main Data Table Styles */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .data-table { /* Target the specific table class */
            width: 100%;
            min-width: 1200px;
            border-collapse: collapse;
            background: white;
            font-size: 0.9rem;
            margin-bottom: 0; /* Remove default table margin */
        }

        .data-table thead th {
            background: var(--primary-color) !important; /* Orange header */
            color: white !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px;
            font-size: 0.8rem !important;
            padding: 0.8rem 0.8rem !important;
            text-align: left;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 2px solid var(--primary-hover) !important;
        }
         .data-table .activity-header th { /* Specific header for activity rows */
            background-color: #e9ecef !important;
            color: var(--text-color) !important;
            text-align: left;
            font-weight: bold !important;
            padding: 0.6rem 0.8rem !important;
            position: sticky;
            top: 40px; /* Adjust based on main header height */
            z-index: 9;
            border-bottom: 1px solid var(--border-color) !important;
            border-top: 1px solid var(--border-color) !important;
            text-transform: none !important; /* Override uppercase */
         }

        .data-table tbody tr:nth-child(even) {
            background: rgba(0, 0, 0, 0.015);
        }

        .data-table tbody tr:hover {
            background: rgba(255, 107, 43, 0.05);
        }

        .data-table td {
            padding: 0.6rem 0.8rem !important;
            border-bottom: 1px solid var(--border-color) !important;
            vertical-align: middle;
            white-space: nowrap;
        }
         .data-table td.cell-center,
         .data-table th.cell-center {
             text-align: center;
         }
         .data-table td.cell-right,
         .data-table th.cell-right {
            text-align: right;
         }


        .data-table input[type="number"],
        .data-table input[type="text"] { /* Inputs within the main table */
            min-width: 60px;
            width: auto;
            max-width: 85px;
            padding: 0.4rem 0.5rem !important;
            border: 1px solid var(--border-color) !important;
            border-radius: 4px !important;
            box-sizing: border-box;
            font-size: 0.9rem !important;
            text-align: right;
            height: auto !important;
            box-shadow: none !important;
            background-color: #fff !important;
            color: var(--text-color) !important;
        }
        .data-table input[type="checkbox"] {
            margin: 0 auto;
            display: block;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        .data-table input:read-only {
            background-color: var(--readonly-bg) !important;
            border-color: var(--border-color) !important;
            opacity: 0.8;
            cursor: default;
        }
        .data-table input:disabled,
        .data-table input[type="checkbox"]:disabled {
             cursor: not-allowed;
             opacity: 0.5;
        }

        /* Footer and Total Row */
        .data-table tfoot td {
             padding: 1rem !important;
             text-align: right;
             border-top: 2px solid var(--primary-color) !important;
             background-color: #f8f9fa !important;
             font-weight: bold !important;
        }
        .data-table tfoot tr.total-row td {
             background-color: var(--total-row-bg) !important;
             font-weight: bold !important;
             color: var(--text-color) !important;
             border-top: 2px solid var(--primary-color) !important;
             padding: 0.8rem 0.8rem !important;
             text-align: center; /* Center align all total cells by default */
        }
         .data-table tfoot tr.total-row td:nth-child(2) { /* Target the 'Total (x):' cell */
            text-align: left; /* Align Total label left */
            font-weight: bold;
         }
         /* Right align specific total cells */
         .data-table tfoot tr.total-row td:nth-child(5), /* Buyin Act */
         .data-table tfoot tr.total-row td:nth-child(6), /* Rake Part */
         .data-table tfoot tr.total-row td:nth-child(7), /* Cout In */
         .data-table tfoot tr.total-row td:nth-child(8), /* Recave */
         .data-table tfoot tr.total-row td:nth-child(12) { /* Cagnotte */
             text-align: right;
         }
         /* Styling for the update button cell in footer */
         .data-table tfoot td.update-button-cell {
             background-color: #f8f9fa !important; /* Match default footer background */
             text-align: right;
             padding: 1rem !important;
         }


        /* Alerts - Use theme's alert styles if possible, or keep these */
        .alert {
            padding: 12px 15px;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
            border-radius: 6px !important; /* Use specific radius */
            font-size: 0.95rem;
        }
        .alert-success { color: #0f5132; background-color: #d1e7dd; border-color: #badbcc; }
        .alert-danger { color: #842029; background-color: #f8d7da; border-color: #f5c2c7; }
        .alert-warning { color: #664d03; background-color: #fff3cd; border-color: #ffecb5; }
        .alert-info { color: #055160; background-color: #cff4fc; border-color: #b6effb; }

         /* Price Pool Display */
        .pricepool-display {
            text-align: right;
            font-size: 1.1rem;
            font-weight: bold;
            margin-top: 1rem;
            padding: 0.8rem;
            background-color: var(--total-row-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }


        /* --- Responsive Adjustments --- */
        @media (max-width: 992px) {
            .wrap-content.container { max-width: 960px; }
            .data-table { min-width: 950px; }
            .simple-form-table th { width: 100px; }
        }

        @media (max-width: 768px) {
            body { font-size: 15px; }
            .wrap-content.container { margin: 1rem auto; padding: 0 0.8rem; }
            .panel.panel-white, .card { padding: 1rem !important; }
            h2 { font-size: 1.2rem; }

            .simple-form-table th,
            .simple-form-table td { display: block; width: 100%; padding: 0.2rem 0; }
            .simple-form-table th { width: auto; font-weight: 600; margin-top: 0.8rem; padding-right: 0; white-space: normal; }
            .simple-form-table tr:first-child th { margin-top: 0; }
            .simple-form-table td { padding-bottom: 0.8rem; }

             .simple-form-table .btn-container-cell { padding-top: 0.8rem; border-top: 1px solid var(--border-color); margin-top: 0.8rem; }
             .simple-form-table .btn-container { justify-content: center; }
             .simple-form-table .btn-container .btn { width: 100%; max-width: 300px; }

            .data-table { min-width: 750px; font-size: 0.85rem; }
            .data-table thead th { padding: 0.7rem 0.6rem !important; font-size: 0.75rem !important; top: 0; }
            .data-table .activity-header th { padding: 0.5rem 0.6rem !important; top: 36px; }
            .data-table td { padding: 0.5rem 0.6rem !important; white-space: nowrap; }
            .data-table input[type="number"], .data-table input[type="text"] { font-size: 0.85rem !important; padding: 0.3rem 0.4rem !important; max-width: 70px; }

            .data-table tfoot td { padding: 0.8rem !important; text-align: center; } /* Center by default */
            .data-table tfoot tr.total-row td:first-child { text-align: center; }
            .data-table tfoot tr.total-row td:nth-child(2){ text-align: center; }
             /* Right align specific totals */
            .data-table tfoot tr.total-row td:nth-child(5),
            .data-table tfoot tr.total-row td:nth-child(6),
            .data-table tfoot tr.total-row td:nth-child(7),
            .data-table tfoot tr.total-row td:nth-child(8),
            .data-table tfoot tr.total-row td:nth-child(12) {
                text-align: right;
            }
            .data-table tfoot td.update-button-cell { text-align: center; }
            .data-table tfoot .btn { width: 100%; max-width: 320px; padding: 0.8rem 1rem !important; }
            .pricepool-display { text-align: center; font-size: 1rem; }
        }

        @media (max-width: 576px) {
            .wrap-content.container { padding: 0 0.5rem; }
            .panel.panel-white, .card { padding: 0.8rem !important; }
            h2 { font-size: 1.1rem; padding-bottom: 0.5rem; }
            .btn { font-size: 0.85rem !important; padding: 0.7rem 1rem !important; }

            .data-table thead th { padding: 0.6rem 0.4rem !important; }
            .data-table .activity-header th { padding: 0.4rem 0.4rem !important; }
            .data-table td { padding: 0.4rem 0.4rem !important; }
            .data-table input[type="number"] { max-width: 60px; }
            .data-table { min-width: 650px; }
        }
        /* Add margin between cards */
        .card + .card {
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div id="app">
        <?php include('include/sidebar.php'); ?>
        <div class="app-content">
            <?php include('include/header.php'); ?>
            <!-- start: MAIN CONTAINER -->
            <div class="main-content" >
                <div class="wrap-content container" id="container">
                    <!-- start: PAGE TITLE -->
                    <section id="page-title">
                        <div class="row">
                            <div class="col-sm-8">
                                <h1 class="mainTitle">Admin | Gestion des Participations</h1>
                            </div>
                            <ol class="breadcrumb">
                                <li><span>Admin</span></li>
                                <li class="active"><span>Gestion Participations</span></li>
                            </ol>
                        </div>
                    </section>
                    <!-- end: PAGE TITLE -->

                    <!-- start: BASIC EXAMPLE -->
                    <div class="container-fluid container-fullw bg-white">
                        <div class="row">
                            <div class="col-md-12">
                                <?php
                                    // Display feedback message from session if it exists
                                    if ($session_feedback) {
                                        echo $session_feedback; // Message should already contain HTML
                                    }
                                ?>

                                <!-- Activity Selection Form -->
                                <div class="panel panel-white card">
                                    <div class="panel-body">
                                        <h2>Filtrer par Activité</h2>
                                        <form method="post">
                                            <table class="simple-form-table">
                                                <tr>
                                                    <th>Activité</th>
                                                    <td>
                                                        <?php
                                                        // Calculate date 7 days ago
                                                        $three_days_ago = date('Y-m-d', strtotime('-7 days', strtotime($actu2)));
                                                        $safe_three_days_ago_date = mysqli_real_escape_string($con, $three_days_ago);

                                                        // Modify query to include last 3 days
                                                        $acti_query = mysqli_query($con, "SELECT `id-activite`,`titre-activite`,`date_depart` FROM `activite` WHERE (`date_depart` >= '$safe_three_days_ago_date') ORDER BY `date_depart` ASC");
                                                        echo "<select name='acti' class='form-control'>";
                                                        echo "<option value='-Anonyme-'>-- Afficher Toutes --</option>";
                                                        $current_selected_activity = isset($_SESSION['selected_activity']) ? $_SESSION['selected_activity'] : null;
                                                        if ($acti_query) {
                                                            while ($choix = mysqli_fetch_assoc($acti_query)) {
                                                                $selected_attr = ($choix["id-activite"] == $current_selected_activity) ? ' selected' : '';
                                                                $formatted_date = date("d/m/Y H:i", strtotime($choix["date_depart"]));
                                                                echo "<option value='" . htmlspecialchars($choix["id-activite"]) . "'$selected_attr>" . $formatted_date . " (" . htmlspecialchars($choix["titre-activite"]) . ")</option>";
                                                            }
                                                            mysqli_free_result($acti_query);
                                                        } else { echo "<option value=''>Erreur chargement activités</option>"; }
                                                        echo "</select>";
                                                        ?>
                                                    </td>
                                                    <td class="btn-container-cell">
                                                        <div class="btn-container">
                                                            <button type="submit" class="btn btn-primary-orange2" name="submitchoixact"> Filtrer </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </form>
                                    </div>
                                </div>

                                <!-- Quick Player Creation Form -->
                                <div class="panel panel-white card">
                                    <div class="panel-body">
                                        <h2>Création Rapide Joueur</h2>
                                        <form method="post">
                                            <table class="simple-form-table">
                                                <tr>
                                                    <th>Pseudo *</th>
                                                    <td> <input class="form-control" id="pseudo" name="pseudo" type="text" required> </td>
                                                </tr>
                                                <tr>
                                                    <th>Prénom</th>
                                                    <td> <input class="form-control" id="fname" name="fname" type="text"> </td>
                                                 </tr>
                                                 <tr>
                                                     <th>&nbsp;</th>
                                                    <td class="btn-container-cell">
                                                         <div class="btn-container">
                                                            <button type="submit" class="btn btn-primary-orange2" name="submitcreaj"> Créer Joueur </button>
                                                         </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </form>
                                    </div>
                                </div>

                                <!-- Quick Registration Form -->
                                <div class="panel panel-white card">
                                    <div class="panel-body">
                                        <h2>Inscription Rapide Joueur</h2>
                                        <?php
                                        // --- Fetch occupied seats for the selected activity ---
                                        $occupied_seats_json = '[]'; // Default to empty JSON array
                                        if ($selected_activity !== null) {
                                            $occupied_seats_query = "SELECT `id-table`, `id-siege` FROM `participation` WHERE `id-activite` = ?";
                                            $stmt_occupied = mysqli_prepare($con, $occupied_seats_query);
                                            if ($stmt_occupied) {
                                                mysqli_stmt_bind_param($stmt_occupied, "i", $selected_activity);
                                                if (mysqli_stmt_execute($stmt_occupied)) {
                                                    $occupied_result = mysqli_stmt_get_result($stmt_occupied);
                                                    $occupied_seats_data = [];
                                                    while ($seat_row = mysqli_fetch_assoc($occupied_result)) {
                                                        $occupied_seats_data[] = ['table' => $seat_row['id-table'], 'siege' => $seat_row['id-siege']];
                                                    }
                                                    mysqli_free_result($occupied_result);
                                                    $occupied_seats_json = json_encode($occupied_seats_data);
                                                } else {
                                                    error_log("Erreur execution fetch occupied seats: " . mysqli_stmt_error($stmt_occupied));
                                                }
                                                mysqli_stmt_close($stmt_occupied);
                                            } else {
                                                 error_log("Erreur préparation fetch occupied seats: " . mysqli_error($con));
                                            }
                                        }
                                        ?>
                                        <form method="post">
                                            <table class="simple-form-table">
                                                 <tr>
                                                    <th>Joueur *</th>
                                                    <td>
                                                        <?php
                                                        $membres_reg = mysqli_query($con, "SELECT `id-membre`,`pseudo` FROM `membres` ORDER BY `pseudo` ASC");
                                                        echo "<select name='membre' class='form-control' required><option value=''>-- Sélectionner Pseudo --</option>";
                                                        if ($membres_reg) {
                                                            while ($choix = mysqli_fetch_assoc($membres_reg)) { echo "<option value='" . htmlspecialchars($choix["id-membre"]) . "'>" . htmlspecialchars($choix["pseudo"]) . "</option>"; }
                                                             mysqli_free_result($membres_reg);
                                                        } else { echo "<option value=''>Erreur chargement</option>"; }
                                                        echo "</select>";
                                                        ?>
                                                    </td>
                                                 </tr>
                                                 <tr>
                                                    <th>Activité *</th>
                                                    <td>
                                                        <?php
                                                         // Calculate date 7 days ago
                                                         $seven_days_ago = date('Y-m-d', strtotime('-7 days', strtotime($actu2)));
                                                         $safe_seven_days_ago_date = mysqli_real_escape_string($con, $seven_days_ago);
                                                         // Modify query to include last 7 days
                                                        $acti_reg = mysqli_query($con, "SELECT `id-activite`,`titre-activite`,`date_depart` FROM `activite` WHERE (`date_depart` >= '$safe_seven_days_ago_date') ORDER BY `date_depart` ASC");
                                                        echo "<select name='acti' id='acti_reg_select' class='form-control' required><option value=''>-- Sélectionner Date --</option>";
                                                        if ($acti_reg) {
                                                             $current_selected_activity = isset($_SESSION['selected_activity']) ? $_SESSION['selected_activity'] : null;
                                                            while ($choix = mysqli_fetch_assoc($acti_reg)) {
                                                                $selected_attr = ($choix["id-activite"] == $current_selected_activity) ? ' selected' : '';
                                                                $formatted_date = date("d/m/Y H:i", strtotime($choix["date_depart"]));
                                                                echo "<option value='" . htmlspecialchars($choix["id-activite"]) . "'$selected_attr>" . $formatted_date . " (" . htmlspecialchars($choix['titre-activite']) . ")</option>";
                                                            }
                                                             mysqli_free_result($acti_reg);
                                                        } else { echo "<option value=''>Erreur chargement</option>"; }
                                                        echo "</select>";
                                                        ?>
                                                    </td>
                                                 </tr>
                                                 <tr>
                                                     <th>Table</th>
                                                     <td>
                                                         <select name="table" id="table_reg_select" class="form-control" <?php echo $selected_activity === null ? 'disabled' : ''; ?>>
                                                             <option value=''>-- Choisir Table --</option>
                                                             <?php for ($t = 1; $t <= 10; $t++): echo "<option value='$t'>Table $t</option>"; endfor; ?>
                                                         </select>
                                                     </td>
                                                 </tr>
                                                 <tr>
                                                     <th>Siège</th>
                                                     <td>
                                                         <select name="siege" id="siege_reg_select" class="form-control" disabled>
                                                             <option value=''>-- Choisir Siège --</option>
                                                             <?php /* Options will be populated by JavaScript */ ?>
                                                         </select>
                                                     </td>
                                                 </tr>
                                                 <tr>
                                                     <th>&nbsp;</th>
                                                     <td class="btn-container-cell">
                                                        <div class="btn-container">
                                                           <button type="submit" class="btn btn-primary-orange2" name="submit" <?php echo $selected_activity === null ? 'disabled' : ''; ?>>Inscrire Joueur</button>
                                                         </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </form>
                                    </div>
                                </div>

                                 <!-- Quick Delete Form -->
                                <div class="panel panel-white card">
                                    <div class="panel-body">
                                        <h2>Suppression Rapide Participation</h2>
                                        <form method="post">
                                            <table class="simple-form-table">
                                                <tr>
                                                    <th>Joueur *</th>
                                                    <td>
                                                         <?php
                                                         $activity_id = isset($_SESSION['selected_activity']) ? $_SESSION['selected_activity'] : (isset($_POST['actisup']) ? $_POST['actisup'] : null);
                                                         $activity_filter = $activity_id ? "WHERE p.`id-activite` = " . intval($activity_id) : "";
                                                         $membres_del = mysqli_query($con, "SELECT DISTINCT m.`id-membre`, m.`pseudo` 
                                                           FROM `membres` m
                                                           JOIN `participation` p ON m.`id-membre` = p.`id-membre`
                                                           $activity_filter
                                                           ORDER BY m.`pseudo` ASC");
                                                         echo "<select name='membresup' class='form-control' required><option value=''>-- Sélectionner Pseudo --</option>";
                                                         if ($membres_del) {
                                                            while ($choix = mysqli_fetch_assoc($membres_del)) { echo "<option value='" . htmlspecialchars($choix["id-membre"]) . "'>" . htmlspecialchars($choix["pseudo"]) . "</option>"; }
                                                            mysqli_free_result($membres_del);
                                                         } else { echo "<option value=''>Erreur chargement</option>"; }
                                                         echo "</select>";
                                                         ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th>Activité *</th>
                                                    <td>
                                                         <?php
                                                         // Calculate date 7 days ago
                                                         $seven_days_ago = date('Y-m-d', strtotime('-7 days', strtotime($actu2)));
                                                         $safe_seven_days_ago_date = mysqli_real_escape_string($con, $seven_days_ago);
                                                         $acti_del = mysqli_query($con, "SELECT `id-activite`,`titre-activite`,`date_depart` FROM `activite` WHERE ( `date_depart` >= '$safe_seven_days_ago_date') ORDER BY `date_depart` ASC");
                                                         echo "<select name='actisup' class='form-control' required><option value=''>-- Sélectionner Date --</option>";
                                                         if ($acti_del) {
                                                             $current_selected_activity = isset($_SESSION['selected_activity']) ? $_SESSION['selected_activity'] : null;
                                                             while ($choix = mysqli_fetch_assoc($acti_del)) {
                                                                $selected_attr = ($choix["id-activite"] == $current_selected_activity) ? ' selected' : '';
                                                                $formatted_date = date("d/m/Y H:i", strtotime($choix["date_depart"]));
                                                                echo "<option value='" . htmlspecialchars($choix["id-activite"]) . "'$selected_attr>" . $formatted_date . "</option>";
                                                             }
                                                              mysqli_free_result($acti_del);
                                                         } else { echo "<option value=''>Erreur chargement</option>"; }
                                                         echo "</select>";
                                                         ?>
                                                    </td>
                                                 </tr>
                                                 <tr>
                                                     <th>&nbsp;</th>
                                                     <td class="btn-container-cell">
                                                        <div class="btn-container">
                                                            <button type="submit" class="btn btn-primary-orange2" name="submitsupc" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette participation ?');">Supprimer Participation</button>
                                                         </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </form>
                                    </div>
                                </div>


                                <!-- Participation List & Update Form -->
                                <div class="panel panel-white card">
                                    <div class="panel-body">
                                        <h2>Liste des Participations <?php echo $selected_activity ? "(Activité ID: ".$selected_activity.")" : "(Toutes les activités à venir)"; ?></h2>

                                        <?php
                                            $data_found = false;
                                            // Initialize totals
                                            $total_challengers = 0;
                                            $total_buyin_activite = 0.0; // Sum of activity buyins for displayed rows
                                            $total_rake_participation = 0.0;
                                            $total_cout_in = 0.0;
                                            $total_recave = 0;
                                            $total_cagnotte = 0;
                                            $total_bounty = 0.0; // Initialize bounty total
                                            // $total_participants already calculated if activity selected
                                        ?>

                                        <form method="post" id="participation-form">
                                            <div class="table-responsive">
                                                <table class="data-table">
                                                    <thead>
                                    <tr>
                                        <th class="cell-center">Ch.</th>
                                        <th>Joueur</th>
                                        <th class="cell-center">T</th>
                                        <th class="cell-center">S</th>
                                        <th class="cell-right">Buy-in</th>
                                        <th class="cell-right">Bounty</th> 
                                        <th class="cell-right">Rake</th>
                                        <th class="cell-right">Cout</th>
                                        <th class="cell-right">Recaves</th>
                                        <th class="cell-center">Clas.</th>
                                        <th class="cell-center">TF</th>
                                        <th class="cell-right">Pts</th>
                                        <th class="cell-center">Remise</th>
                                        <th class="cell-right">Cagnotte</th>
                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                        // $selected_activity defined earlier
                                                        // $data_found, totals initialized above

                                                        $query_list = "SELECT
                                                                     p.`id-membre`, m.pseudo, p.`id-activite`, a.`titre-activite`, a.`date_depart`,
                                                                     p.`id-table`, p.`id-siege`, p.challenger, p.recave, p.classement,
                                                                     p.cout_in, p.rake AS participation_rake, a.bounty,
                                                                     p.points, p.cagnotte, p.tf, p.remise, a.buyin AS activite_buyin
                                                                   FROM participation p
                                                                   JOIN membres m ON p.`id-membre` = m.`id-membre`
                                                                   JOIN activite a ON p.`id-activite` = a.`id-activite`";

                                                        $safe_actu2_date = mysqli_real_escape_string($con, $actu2);
                                                        $participants_in_list = 0; // Counter for displayed rows

                                                        if ($selected_activity !== null) {
                                                            $query_list .= " WHERE p.`id-activite` = ?";
                                                        } else {
                                                            $query_list .= " WHERE a.`date_depart` >= '$safe_actu2_date'";
                                                        }
                                                        // Sort by Challenger DESC, then Pseudo ASC, then other criteria
                                                        $query_list .= " ORDER BY p.challenger DESC, m.pseudo ASC, a.date_depart DESC, p.`id-table` ASC, p.`id-siege` ASC";

                                                        $stmt_list = mysqli_prepare($con, $query_list);

                                                        if ($stmt_list) {
                                                            if ($selected_activity !== null) {
                                                                mysqli_stmt_bind_param($stmt_list, "i", $selected_activity);
                                                            }

                                                            if (mysqli_stmt_execute($stmt_list)) {
                                                                $result = mysqli_stmt_get_result($stmt_list);
                                                                if (mysqli_num_rows($result) > 0) {
                                                                    $data_found = true;
                                                                    $index = 0;
                                                                    $current_activity_header = null;
                                                                    $participants_in_list = mysqli_num_rows($result); // Count displayed rows

                                                                    while($row = mysqli_fetch_assoc($result)) {
                                                                        // Accumulate totals for displayed rows
                                                                        if ($row['challenger']) $total_challengers++;
                                                                        $total_buyin_activite += floatval($row['activite_buyin'] ?? 0.0); // Add for every row displayed
                                                                        $total_rake_participation += floatval($row['participation_rake'] ?? 0.0);
                                                                        $total_cout_in += floatval($row['cout_in'] ?? 0.0);
                                                                        $total_recave += intval($row['recave'] ?? 0);
                                                                        $total_cagnotte += intval($row['cagnotte'] ?? 0);

                                                                        // Display Activity Header Row
                                                                        if ($selected_activity === null && $row['id-activite'] !== $current_activity_header) {
                                                                            $formatted_date_header = date("d/m/Y H:i", strtotime($row["date_depart"]));
                                                                            // Output 13 cells to match header count, avoiding colspan in tbody
                                                                            echo '<tr class="activity-header">';
                                                                            echo '  <td style="font-weight: bold; background-color: #e9ecef !important;">Activité: ' . htmlspecialchars($row['titre-activite']) . ' (' . $formatted_date_header . ') - ID: ' . $row['id-activite'] . '</td>'; // Style added to mimic header
                                                                            for ($i = 0; $i < 12; $i++) { echo '<td style="background-color: #e9ecef !important;"></td>'; } // Add 12 empty styled cells
                                                                            echo '</tr>';
                                                                            $current_activity_header = $row['id-activite'];
                                                                        }

                                                                        // Display Data Row
                                                                        // Prepare raw numeric values for data-sort attributes
                                                                        $raw_activite_buyin = floatval($row['activite_buyin'] ?? 0.0);
                                                                        $raw_participation_rake = floatval($row['participation_rake'] ?? 0.0);
                                                                        $raw_cout_in = floatval($row['cout_in'] ?? 0.0);
                                                                        $raw_recave = intval($row['recave'] ?? 0);
                                                                        $raw_classement = ($row['classement'] === null || $row['classement'] === '') ? 9999 : intval($row['classement']); // Use a high number for null/empty classement for sorting purposes
                                                                        $raw_tf = intval($row['tf'] ?? 0); // Sort 0/1
                                                                        $raw_points = intval($row['points'] ?? 0);
                                                                        $raw_cagnotte = intval($row['cagnotte'] ?? 0);

                                                                        echo "<tr>";
                                                                        // Column 0: Ch. (Checkbox - now sortable using hidden span)
                                                                        $challenger_sort_value = $row['challenger'] ? 1 : 0;
                                                                        echo "<td class='cell-center'><span class='sort-value' style='display: none;'>" . $challenger_sort_value . "</span><input type='checkbox' name='participations[$index][challenger]' value='1' " . ($row['challenger'] ? 'checked' : '') . ($selected_activity ? '' : ' disabled') . "></td>";
                                                                        // Column 1: Joueur (Text - default sorting)
                                                                        // Removed data-sort attribute
                                                                        echo "<td title='" . htmlspecialchars($row['pseudo']) . "'>" . htmlspecialchars(substr($row['pseudo'], 0, 15)) . (strlen($row['pseudo']) > 15 ? '...' : '')
                                                                             . "<input type='hidden' name='participations[$index][membre_id]' value='" . htmlspecialchars($row['id-membre']) . "'>"
                                                                             . "<input type='hidden' name='participations[$index][activite_id]' value='" . htmlspecialchars($row['id-activite']) . "'></td>";
                                                                        // Column 2: T (Dropdown 1-11)
                                                                        echo "<td class='cell-center'><span class='sort-value' style='display: none;'>" . $row['id-table'] . "</span>";
                                                                        echo "<select name='participations[$index][table]' " . ($selected_activity ? '' : 'disabled') . ">";
                                                                        for ($t = 1; $t <= 11; $t++) {
                                                                            echo "<option value='$t' " . ($row['id-table'] == $t ? 'selected' : '') . ">$t</option>";
                                                                        }
                                                                        echo "</select></td>";
                                                                        
                                                                        // Column 3: S (Dropdown 1-11)
                                                                        echo "<td class='cell-center'><span class='sort-value' style='display: none;'>" . $row['id-siege'] . "</span>";
                                                                        echo "<select name='participations[$index][siege]' " . ($selected_activity ? '' : 'disabled') . ">";
                                                                        for ($s = 1; $s <= 11; $s++) {
                                                                            echo "<option value='$s' " . ($row['id-siege'] == $s ? 'selected' : '') . ">$s</option>";
                                                                        }
                                                                        echo "</select></td>";
                                                                        
                                                                        // Column 4: Buy-in Act. (Readonly Input - numeric sort using hidden span with class)
                                                                        echo "<td class='cell-right'><span class='sort-value' style='display: none;'>" . $raw_activite_buyin . "</span><input type='number' name='participations[$index][buyin_display]' value='" . htmlspecialchars(number_format($raw_activite_buyin, 2, '.', '')) . "' step='0.01' readonly title='Buy-in Activité'></td>";
                                                                        
                                                                        // Column 5: Bounty (Readonly Input - numeric sort using hidden span with class)
                                                                        $raw_bounty = floatval($row['bounty'] ?? 0.0);
                                                                        $total_bounty += $raw_bounty; // Accumulate bounty total
                                                                        echo "<td class='cell-right'><span class='sort-value' style='display: none;'>" . $raw_bounty . "</span><input type='number' name='participations[$index][bounty_display]' value='" . htmlspecialchars(number_format($raw_bounty, 2, '.', '')) . "' step='0.01' readonly title='Bounty'></td>";
                                                                        
                                                                        // Column 6: Rake Part. (Dropdown with specific values)
                                                                        echo "<td class='cell-right'><span class='sort-value' style='display: none;'>" . $raw_participation_rake . "</span>";
                                                                        echo "<select name='participations[$index][rake]' " . ($selected_activity ? '' : 'disabled') . ">";
                                                                        $rake_values = [0, 5, 10, 12, 15, 20];
                                                                        foreach ($rake_values as $value) {
                                                                            $selected = (abs($raw_participation_rake - $value) < 0.01) ? 'selected' : '';
                                                                            echo "<option value='$value' $selected>$value</option>";
                                                                        }
                                                                        echo "</select></td>";
                                                                        // Column 7: Cout In (Readonly Input - numeric sort using hidden span with class)
                                                                        echo "<td class='cell-right'><span class='sort-value' style='display: none;'>" . $raw_cout_in . "</span><input type='number' name='participations[$index][cout_in_display]' value='" . htmlspecialchars(number_format($raw_cout_in, 2, '.', '')) . "' step='0.01' readonly title='onne
                                                                         (BuyIn Act.+Rake Part.[+5 si Ch])'></td>";
                                                                        // Column 7: Rec. (Dropdown - numeric sort using hidden span with class)
                                                                        echo "<td class='cell-right'><span class='sort-value' style='display: none;'>" . $raw_recave . "</span>";
                                                                        echo "<select class='recave-select' name='participations[$index][recave]' " . ($selected_activity ? '' : 'disabled') . ">";
                                                                        for ($v = 0; $v <= 4; $v++) {
                                                                            echo "<option value='$v' " . ($raw_recave == $v ? 'selected' : '') . ">$v</option>";
                                                                        }
                                                                        echo "</select></td>";
                                                                        // Column 8: Clas. (Input - numeric sort using hidden span with class, handle null/empty)
                                                                        echo "<td class='cell-center'><span class='sort-value' style='display: none;'>" . $raw_classement . "</span><select name='participations[$index][classement]' " . ($selected_activity ? '' : 'disabled') . "><option value=''>N/A</option>";
                                                                        for ($c = 0; $c <= 50; $c++) {
                                                                            $selected = ($row['classement'] !== null && $row['classement'] == $c) ? 'selected' : '';
                                                                            echo "<option value='$c' $selected>$c</option>";
                                                                        }
                                                                        echo "</select></td>";
                                                                        // Column 9: TF (Checkbox - numeric sort using hidden span with class 0/1)
                                                                        echo "<td class='cell-center'><span class='sort-value' style='display: none;'>" . $raw_tf . "</span><input type='checkbox' name='participations[$index][tf]' value='1' " . ($row['tf'] ? 'checked' : '') . ($selected_activity ? '' : ' disabled') . "></td>";
                                                                        // Column 10: Pts (Readonly Input - numeric sort using hidden span with class)
                                                                        echo "<td class='cell-right'><span class='sort-value' style='display: none;'>" . $raw_points . "</span><input type='number' name='participations[$index][points_display]' value='" . htmlspecialchars((string)$raw_points) . "' step='1' placeholder='0' readonly title='Calc: 0 si non Ch, sinon 1+TF(+1)+Clas1(+1)'></td>";
                                                                        // Column 11: Cagnotte (Readonly Input - numeric sort using hidden span with class)
                                        $remise_checked = $row['remise'] ? 'checked' : '';
                                        echo "<td class='cell-center'><span class='sort-value' style='display: none;'>" . $row['remise'] . "</span><input type='checkbox' name='participations[$index][remise]' value='1' $remise_checked " . ($selected_activity ? '' : ' disabled') . "></td>";
                                        echo "<td class='cell-center'><span class='sort-value' style='display: none;'>" . $raw_cagnotte . "</span><input type='number' name='participations[$index][cagnotte_display]' value='" . htmlspecialchars((string)$raw_cagnotte) . "' step='1' placeholder='0' readonly title='Calc: Si Ch.=(Recave*3)+3, sinon 0'></td>";
                                                                        echo "</tr>";
                                                                        $index++;
                                                                    }
                                                                } else {
                                                                    $data_found = false;
                                                                }
                                                                mysqli_free_result($result);
                                                            } else {
                                                                 // Output 13 cells for error message
                                                                 echo '<tr>';
                                                                 echo '  <td><div class="alert alert-danger" style="margin-bottom: 0;">Erreur execution requête liste: ' . htmlspecialchars(mysqli_stmt_error($stmt_list)) . '</div></td>';
                                                                 for ($i = 0; $i < 12; $i++) { echo '<td></td>'; }
                                                                 echo '</tr>';
                                                                 $data_found = false;
                                                            }
                                                            mysqli_stmt_close($stmt_list);
                                                        } else {
                                                             // Output 13 cells for error message
                                                             echo '<tr>';
                                                             echo '  <td><div class="alert alert-danger" style="margin-bottom: 0;">Erreur préparation requête liste: ' . htmlspecialchars(mysqli_error($con)) . '</div></td>';
                                                             for ($i = 0; $i < 12; $i++) { echo '<td></td>'; }
                                                             echo '</tr>';
                                                             $data_found = false;
                                                        }

                                                        // Display info messages or no data message
                                                        if (!$data_found) {
                                                             // Output 13 cells to match header count
                                                             echo '<tr>';
                                                             echo '  <td style="text-align: center; padding: 1.5rem;">Aucun participant trouvé pour ' . ($selected_activity ? "l'activité sélectionnée." : "les activités à venir.") . '</td>';
                                                             for ($i = 0; $i < 12; $i++) { echo '<td></td>'; } // Add 12 empty cells
                                                             echo '</tr>';
                                                        } elseif (!$selected_activity && $data_found) {
                                                             // Output 13 cells, place alert in the first one
                                                             echo '<tr>';
                                                             echo '  <td><div class="alert alert-info" style="margin-top: 1rem; text-align: center; margin-bottom: 0;">Filtrez par une activité pour activer la modification groupée.</div></td>';
                                                             for ($i = 0; $i < 12; $i++) { echo '<td></td>'; } // Add 12 empty cells
                                                             echo '</tr>';
                                                        }
                                                        ?>
                                                    </tbody>
                                                     <?php if ($data_found): // Show Footer if data exists ?>
                                                     <tfoot>
                                                         <tr class="total-row">
                                                             <td class="cell-center"><?php echo $total_challengers; ?></td>
                                                             <td>Total (<?php echo $participants_in_list; ?>):</td>
                                                             <td colspan="2"></td> 
                                                             <td class="cell-right"><?php echo number_format($total_buyin_activite, 2, '.', ' '); ?></td>
                                                             <td class="cell-right"><?php echo number_format($total_bounty, 2, '.', ' '); ?></td>
                                                             <td class="cell-right"><?php echo number_format($total_rake_participation, 2, '.', ' '); ?></td>
                                                             <td class="cell-right"><?php echo number_format($total_cout_in, 2, '.', ' '); ?></td>
                                                             <td class="cell-right"><?php echo $total_recave; ?></td>
                                                             <td></td>
                                                             <td colspan="1"></td>  
                                                             <td></td>
                                                             <td class="cell-center">0</td>
                                                             <td class="cell-right"><?php echo "C = C + ".$total_cagnotte; ?></td>
                                                         </tr>
                                                         <?php if ($selected_activity): // Show Update button only if activity is selected ?>
                                                         <tr >
                                                           <td colspan="13" class="update-button-cell">
                                                                <button type="submit" name="update_participation" class="btn btn-primary-orange2">
                                                                    Mettre à jour les Participations Sélectionnées
                                                                </button>
                                                           </td>
                                                        </tr>
                                                        <?php endif; ?>
                                                     </tfoot>
                                                     <?php endif; // End if($data_found) ?>
                                                </table>
                                            </div> <!-- /table-responsive -->
                                        </form>

                                        <?php
                                        // Display Price Pool if an activity is selected and data was found
                                        if ($selected_activity !== null && $data_found) {
                                             // Use the pre-calculated $price_pool based on DB sums for accuracy
                                             echo "<div class='pricepool-display'>";
                                             echo "Price Pool Estimé: " . number_format($price_pool, 2, '.', ' ') . " ";
                                             echo "<span style='font-size: 0.8em; font-weight: normal;'> (Total Buyins Act.: " . number_format($total_buyin_sum, 2, '.', ' ') . " + (Total Recaves: " . $total_recave_sum . " * Buyin Act.: " . number_format($activity_buyin_for_pricepool, 2, '.', ' ') . "))</span>";
                                             echo "</div>";
                                        }
                                        ?>
                                    </div>
                                </div> <!-- /panel -->

                                <!-- Prize Pool Distribution Form -->
                                <div class="panel panel-white card">
                                    <div class="panel-body">
                                        <h2>Distribution du Price Pool</h2>
                                        <form method="post">
                                            <table class="simple-form-table">
                                                <tr>
                                                    <th>Nombre de joueurs payés</th>
                                                    <td>
                                                        <input class="form-control" type="number" id="nb_joueurs_payes" name="nb_joueurs_payes" value="<?php echo isset($_POST['nb_joueurs_payes']) ? htmlspecialchars($_POST['nb_joueurs_payes']) : ($total_participants > 0 ? round($total_participants * 0.25) : 1); ?>" min="1" max="<?php echo $total_participants > 0 ? $total_participants : 1; ?>" <?php echo $selected_activity === null || !$data_found ? 'disabled' : ''; ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th></th>
                                                    <td class="btn-container-cell">
                                                        <div class="btn-container">
                                                            <button type="submit" class="btn btn-primary-orange2" name="submit_distribution" <?php echo $selected_activity === null || !$data_found ? 'disabled' : ''; ?>>Calculer la distribution</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </form>
                                    </div>
                                </div>

                                <!-- Prize Pool Distribution Table -->
                                <div class="panel panel-white card">
                                    <div class="panel-body">
                                        <h2>Résultat Distribution</h2>
                                        <?php
                                        if (isset($_POST['submit_distribution']) && $selected_activity !== null && $data_found) {
                                            $nb_joueurs_payes = intval($_POST['nb_joueurs_payes']);
                                            if ($nb_joueurs_payes > 0 && $nb_joueurs_payes <= $total_participants) {
                                                $distributions = array();
                                                // Define distributions based on number of paid players
                                                switch ($nb_joueurs_payes) {
                                                    case 1: $distributions = array(1.00); break; // Added case 1
                                                    case 2: $distributions = array(0.60, 0.40); break;
                                                    case 3: $distributions = array(0.50, 0.30, 0.20); break;
                                                    case 4: $distributions = array(0.45, 0.30, 0.15, 0.10); break;
                                                    case 5: $distributions = array(0.40, 0.27, 0.15, 0.10, 0.08); break;
                                                    case 6: $distributions = array(0.35, 0.25, 0.15, 0.11, 0.08, 0.06); break;
                                                    case 7: $distributions = array(0.33, 0.23, 0.15, 0.11, 0.08, 0.06, 0.04); break;
                                                    case 8: $distributions = array(0.33, 0.23, 0.14, 0.10, 0.07, 0.05, 0.04, 0.04); break;
                                                    // Add more cases as needed
                                                    default:
                                                        // Basic distribution for larger numbers (example: linear decrease)
                                                        $base_share = 1.0 / $nb_joueurs_payes;
                                                        for ($i = 0; $i < $nb_joueurs_payes; $i++) {
                                                            // This is a placeholder, replace with actual desired distribution logic
                                                            $distributions[] = $base_share;
                                                        }
                                                        // Adjust last elements slightly to ensure sum is 1 (due to potential float issues)
                                                        $current_sum = array_sum($distributions);
                                                        if (abs($current_sum - 1.0) > 0.0001 && count($distributions) > 0) {
                                                            $distributions[count($distributions)-1] += (1.0 - $current_sum);
                                                        }
                                                        // echo "<div class='alert alert-warning'>Distribution par défaut appliquée pour {$nb_joueurs_payes} joueurs.</div>";
                                                        break;
                                                }


                                                if (!empty($distributions)) {
                                                    $total_distribution_check = array_sum($distributions);
                                                    if (abs($total_distribution_check - 1) > 0.01) { // Increased tolerance slightly
                                                        echo "<div class='alert alert-danger'>Erreur: La somme des pourcentages de distribution (".number_format($total_distribution_check*100, 2)."%) ne correspond pas à 100%. Vérifiez la logique de distribution.</div>";
                                                    } else {
                                                        echo "<div class='table-responsive'>";
                                                        echo "<table class='data-table'>"; // Use data-table class for consistency
                                                        echo "<thead><tr><th>Position</th><th>Pourcentage</th><th>Gain Estimé (Arrondi à 5)</th></tr></thead>";
                                                        echo "<tbody>";
                                                        $total_gain_distributed = 0;
                                                        for ($i = 0; $i < count($distributions); $i++) {
                                                            $pourcentage = $distributions[$i] * 100;
                                                            $gain_estime_raw = $price_pool * $distributions[$i];
                                                            // Round to nearest 5, ensuring minimum is 5 if raw > 0
                                                            $gain_estime_rounded = ($gain_estime_raw > 0) ? max(5, round($gain_estime_raw / 5) * 5) : 0;
                                                            $total_gain_distributed += $gain_estime_rounded;
                                                            echo "<tr><td class='cell-center'>" . ($i + 1) . "</td><td class='cell-right'>" . number_format($pourcentage, 2) . "%</td><td class='cell-right'>" . number_format($gain_estime_rounded, 2) . " €</td></tr>";
                                                        }
                                                        echo "</tbody>";
                                                        echo "<tfoot><tr class='total-row'><td colspan='2' style='text-align:right;'>Total Distribué (Arrondi):</td><td class='cell-right'>" . number_format($total_gain_distributed, 2) . " €</td></tr></tfoot>";
                                                        echo "</table>";
                                                        echo "</div>";
                                                        if (abs($total_gain_distributed - $price_pool) > 5) { // Warning if rounding difference is significant
                                                             echo "<div class='alert alert-warning' style='margin-top:1rem;'>Note: Le total distribué arrondi (".number_format($total_gain_distributed, 2)." €) peut différer légèrement du Price Pool estimé (".number_format($price_pool, 2)." €) en raison de l'arrondi à 5 €.</div>";
                                                        }
                                                    }
                                                } else {
                                                     echo "<div class='alert alert-warning'>Aucune distribution définie pour {$nb_joueurs_payes} joueurs payés.</div>";
                                                }
                                            } else {
                                                echo "<div class='alert alert-warning'>Nombre de joueurs payés invalide. Veuillez entrer un nombre entre 1 et " . $total_participants . ".</div>";
                                            }
                                        } elseif (isset($_POST['submit_distribution']) && $selected_activity === null) {
                                            echo "<div class='alert alert-info'>Veuillez sélectionner une activité pour calculer la distribution.</div>";
                                        } elseif (isset($_POST['submit_distribution']) && !$data_found) {
                                            echo "<div class='alert alert-info'>Aucun participant trouvé pour l'activité sélectionnée, impossible de calculer la distribution.</div>";
                                        } else {
                                            echo "<div class='alert alert-info'>Entrez le nombre de joueurs payés et cliquez sur 'Calculer la distribution'.</div>";
                                        }
                                        ?>
                                    </div>
                                </div>


                            </div>
                        </div>
                    </div>
                    <!-- end: BASIC EXAMPLE -->

                </div>
            </div>
            <!-- end: MAIN CONTAINER -->
            <?php include('include/footer.php'); ?>
            <?php include('include/setting.php'); ?>
        </div>
    </div><!-- /app -->

    <!-- Template JS -->
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
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <!-- <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script> -->
    <!-- <script src="../js/datatables-simple-demo.js"></script> -->

    <script>
        jQuery(document).ready(function() {
            Main.init();
            FormElements.init();

            // Initialize DataTables ONLY if a single activity is selected
            // Check if the table exists AND if we are NOT in the 'All Activities' view
            if ($('.data-table').length > 0 && !isAllActivitiesView) {
                // Initialize DataTables with sorting enabled, relying on data-sort attributes
                 $('.data-table').DataTable({
                    "paging": false, // Disable pagination
                    "info": false,   // Disable info text
                    "language": {
                        "search": "Rechercher Joueur:",
                        "zeroRecords": "Aucun joueur trouvé",
                        "emptyTable": "Aucune participation à afficher"
                    },
                    "ordering": true, // Keep sorting enabled
                    "columnDefs": [
                        // Disable sorting for specific columns
                        { "orderable": false, "targets": [0, 2, 3] },
                        // Define orthogonal data rendering for numeric columns (4-10)
                        // Use the hidden span's text for sorting and type detection
                        {
                            "targets": [4, 5, 6, 7, 8, 9, 10], // Columns with hidden spans
                            "render": function ( data, type, row ) {
                                if ( type === 'sort' || type === 'type' ) {
                                    // Find the hidden span and return its text content
                                    var hiddenValue = $(data).find('.sort-value').text();
                                    // Convert to number for correct numeric sorting
                                    return Number(hiddenValue);
                                }
                                // For display and other types, return the original cell data (which includes the input)
                                return data;
                            }
                        }
                    ],
                    // Default sort: Challenger DESC (col 0), then Player Name ASC (col 1)
                    "order": [[ 0, "desc" ], [ 1, "asc" ]]
                });
            }

            // Specific JS from original quick-part.php for seat selection
            const occupiedSeats = <?php echo $occupied_seats_json; ?>;
            const tableSelect = document.getElementById('table_reg_select');
            const siegeSelect = document.getElementById('siege_reg_select');
            // const activitySelect = document.getElementById('acti_reg_select'); // Not needed if relying on filter button

            // Function to update siege options based on selected table
            function updateSiegeOptions() {
                const selectedTable = tableSelect ? parseInt(tableSelect.value) : null;
                if (!siegeSelect) return; // Exit if siege select doesn't exist

                siegeSelect.innerHTML = '<option value="">-- Choisir Siège --</option>'; // Clear existing options

                if (!selectedTable) {
                    siegeSelect.disabled = true;
                    return;
                }

                const occupiedSeatsForTable = occupiedSeats
                    .filter(seat => seat.table === selectedTable)
                    .map(seat => seat.siege);

                let availableSeatsFound = false;
                for (let s = 1; s <= 10; s++) { // Assuming 10 seats max
                    if (!occupiedSeatsForTable.includes(s)) {
                        const option = document.createElement('option');
                        option.value = s;
                        option.textContent = 'Siège ' + s;
                        siegeSelect.appendChild(option);
                        availableSeatsFound = true;
                    }
                }

                siegeSelect.disabled = !availableSeatsFound;
                 if (!availableSeatsFound) {
                     siegeSelect.innerHTML = '<option value="">-- Aucun siège libre --</option>';
                 }
            }

            // Update recave select styling when challenger checkbox changes
            $(document).on('change', 'input[name^="participations["][name$="[challenger]"]', function() {
                const recaveSelect = $(this).closest('tr').find('.recave-select');
                if (this.checked) {
                    recaveSelect.css({'color': 'blue', 'font-weight': 'bold'});
                } else {
                    recaveSelect.css({'color': '', 'font-weight': ''});
                }
            });

            // Apply initial styling
            $('input[name^="participations["][name$="[challenger]"]:checked').each(function() {
                $(this).closest('tr').find('.recave-select')
                    .css({'color': 'blue', 'font-weight': 'bold'});
            });

            // Event listener for table selection change
            if (tableSelect) {
                tableSelect.addEventListener('change', updateSiegeOptions);
                // Initial call in case activity/table is pre-selected on load
                updateSiegeOptions();
            }

        });
    </script>
</body>
</html>
<?php
 if (isset($con) && $con instanceof mysqli) {
     mysqli_close($con);
 }
?>
