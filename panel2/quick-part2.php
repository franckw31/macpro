<?php
session_start();
// --- ENABLE ERROR REPORTING FOR DEBUGGING ---
// Uncomment these lines during development, comment out or set to 0 for production
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
error_reporting(0); // Keep errors hidden for production as originally set

include('include/config.php'); // Ensure this file contains your mysqli connection ($con)

// --- Initial Variable Setup & Defaults ---
$gid_part = isset($_GET['part']) ? intval($_GET['part']) : null;
$gid_acti = isset($_GET['acti']) ? intval($_GET['acti']) : null;
$gid_tabl = isset($_GET['tabl']) ? intval($_GET['tabl']) : null;
$gid_sieg = isset($_GET['sieg']) ? intval($_GET['sieg']) : null;
$source = isset($_GET['sour']) ? $_GET['sour'] : '';
$actu = strtotime(date("Y-m-d H:i:s"));
$actu2 = date("Y-m-d"); // Used for date comparisons in queries

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
            } else {
                $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur création joueur rapide: " . htmlspecialchars(mysqli_stmt_error($stmt_create)) . "</div>";
            }
            mysqli_stmt_close($stmt_create);
        } else {
             $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur préparation création joueur: " . htmlspecialchars(mysqli_error($con)) . "</div>";
        }
    } else {
        $_SESSION['feedback'] = "<div class='alert alert-warning'>Le pseudo est requis pour la création rapide.</div>";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- Handle Quick Registration (with Rake & Cout_in Initialization) ---
if (isset($_POST['submit'])) {
    $membre = isset($_POST['membre']) && $_POST['membre'] != '' ? intval($_POST['membre']) : null;
    $acti = isset($_POST['acti']) && $_POST['acti'] != '' ? intval($_POST['acti']) : null;
    $tabl = isset($_POST['table']) ? intval($_POST['table']) : 1;
    $sieg = isset($_POST['siege']) ? intval($_POST['siege']) : 1;

    if ($membre && $acti) {
        // 1. Check if participation already exists
        $check_sql = "SELECT `id-participation` FROM `participation` WHERE `id-membre` = ? AND `id-activite` = ?";
        $stmt_check = mysqli_prepare($con, $check_sql);
        mysqli_stmt_bind_param($stmt_check, "ii", $membre, $acti);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);

        if (mysqli_stmt_num_rows($stmt_check) == 0) {
            mysqli_stmt_close($stmt_check);

            // 2. Fetch activity defaults (buyin and rake)
            $default_activity_rake = 0.0;
            $default_activity_buyin = 0.0;
            $fetch_defaults_sql = "SELECT rake, buyin FROM activite WHERE `id-activite` = ?";
            $stmt_fetch_defaults = mysqli_prepare($con, $fetch_defaults_sql);
            if ($stmt_fetch_defaults) {
                mysqli_stmt_bind_param($stmt_fetch_defaults, "i", $acti);
                if (mysqli_stmt_execute($stmt_fetch_defaults)) {
                    $defaults_result = mysqli_stmt_get_result($stmt_fetch_defaults);
                    if ($defaults_row = mysqli_fetch_assoc($defaults_result)) {
                        $default_activity_rake = floatval($defaults_row['rake'] ?? 0.0);
                        $default_activity_buyin = floatval($defaults_row['buyin'] ?? 0.0);
                    }
                     mysqli_free_result($defaults_result);
                } else {
                     error_log("Erreur execution fetch defaults pour activité ID $acti: " . mysqli_stmt_error($stmt_fetch_defaults));
                }
                mysqli_stmt_close($stmt_fetch_defaults);
            } else {
                 error_log("Erreur préparation fetch defaults pour activité ID $acti: " . mysqli_error($con));
            }

            // Calculate initial cout_in
            $initial_cout_in = $default_activity_buyin + $default_activity_rake;

            // 3. Insert new participation
            // Added rake and cout_in columns to insert
            $sql_quick_reg = "INSERT INTO `participation` (`id-membre`, `id-activite`, `id-table`, `id-siege`, rake, cout_in) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_reg = mysqli_prepare($con, $sql_quick_reg);
            if ($stmt_reg) {
                 // Bind parameters: member(i), activity(i), table(i), seat(i), rake(d), cout_in(d)
                 mysqli_stmt_bind_param($stmt_reg, "iiiidd", $membre, $acti, $tabl, $sieg, $default_activity_rake, $initial_cout_in);

                 if (mysqli_stmt_execute($stmt_reg)) {
                     $_SESSION['feedback'] = "<div class='alert alert-success'>Inscription rapide réussie : Joueur ID = $membre, Activité ID = $acti, Table = $tabl, Siège = $sieg. Rake initialisé à " . number_format($default_activity_rake, 2) . ", Cout In initialisé à " . number_format($initial_cout_in, 2) . ".</div>";
                 } else {
                     $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur inscription rapide: " . htmlspecialchars(mysqli_stmt_error($stmt_reg)) . "</div>";
                 }
                 mysqli_stmt_close($stmt_reg);
            } else {
                 $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur préparation inscription: " . htmlspecialchars(mysqli_error($con)) . "</div>";
            }
        } else {
            mysqli_stmt_close($stmt_check);
            $_SESSION['feedback'] = "<div class='alert alert-warning'>Ce joueur est déjà inscrit à cette activité.</div>";
        }
    } else {
        $_SESSION['feedback'] = "<div class='alert alert-warning'>Veuillez sélectionner un joueur et une activité valides pour l'inscription rapide.</div>";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


// --- Handle Quick Deletion ---
if (isset($_POST['submitsupc'])) {
    // (Code remains the same)
    $membre = isset($_POST['membresup']) && $_POST['membresup'] != '' ? intval($_POST['membresup']) : null;
    $acti = isset($_POST['actisup']) && $_POST['actisup'] != '' ? intval($_POST['actisup']) : null;

    if ($membre && $acti) {
        $sql_quick_del = "DELETE FROM `participation` WHERE `id-membre` = ? AND `id-activite` = ?";
        $stmt_del = mysqli_prepare($con, $sql_quick_del);
        if ($stmt_del) {
            mysqli_stmt_bind_param($stmt_del, "ii", $membre, $acti);
            if (mysqli_stmt_execute($stmt_del)) {
                $affected_rows = mysqli_stmt_affected_rows($stmt_del);
                if ($affected_rows > 0) {
                    $_SESSION['feedback'] = "<div class='alert alert-success'>Suppression rapide réussie: Participation pour Joueur ID = $membre, Activité ID = $acti supprimée.</div>";
                } else {
                     $_SESSION['feedback'] = "<div class='alert alert-info'>Aucune participation trouvée pour Joueur ID = $membre et Activité ID = $acti à supprimer.</div>";
                }
            } else {
                $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur exécution suppression rapide: " . htmlspecialchars(mysqli_stmt_error($stmt_del)) . "</div>";
            }
            mysqli_stmt_close($stmt_del);
        } else {
             $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur préparation suppression rapide: " . htmlspecialchars(mysqli_error($con)) . "</div>";
        }
    } else {
        $_SESSION['feedback'] = "<div class='alert alert-warning'>Veuillez sélectionner un joueur et une activité valides pour la suppression rapide.</div>";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- Handle Individual Participant Deletion ---
if (isset($_POST['delete_participant'])) {
    // (Code remains the same)
     $membre_id = intval($_POST['membre_id']);
    $activite_id = intval($_POST['activite_id']);

    $sql_delete = "DELETE FROM `participation` WHERE `id-membre` = ? AND `id-activite` = ?";
    $stmt_del_ind = mysqli_prepare($con, $sql_delete);

    if ($stmt_del_ind) {
        mysqli_stmt_bind_param($stmt_del_ind, "ii", $membre_id, $activite_id);
        if (mysqli_stmt_execute($stmt_del_ind)) {
             $affected_rows = mysqli_stmt_affected_rows($stmt_del_ind);
             if ($affected_rows > 0) {
                $_SESSION['feedback'] = "<div class='alert alert-success'>Participant (Membre ID: $membre_id, Activité ID: $activite_id) supprimé avec succès</div>";
             } else {
                 $_SESSION['feedback'] = "<div class='alert alert-info'>Aucun participant trouvé à supprimer (Membre ID: $membre_id, Activité ID: $activite_id).</div>";
             }
        } else {
            $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur lors de la suppression du participant: " . htmlspecialchars(mysqli_stmt_error($stmt_del_ind)) . "</div>";
        }
         mysqli_stmt_close($stmt_del_ind);
    } else {
         $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur de préparation de la suppression du participant: " . htmlspecialchars(mysqli_error($con)) . "</div>";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


// --- Handle form submission for updating participations (Main Logic) ---
if (isset($_POST['update_participation'])) {
    // (Code remains the same as previous version - already handles all calculations correctly)
    $batch_error_messages = [];
    $update_success_overall = true;

    $selected_activity_id_for_update = isset($_SESSION['selected_activity']) ? intval($_SESSION['selected_activity']) : null;
    $activity_rake = 0.0;
    $activity_buyin = 0.0;

    if ($selected_activity_id_for_update) {
        // Fetch activity details
        $activity_details_query = "SELECT rake, buyin FROM activite WHERE `id-activite` = ?";
        $stmt_activity = mysqli_prepare($con, $activity_details_query);
        if ($stmt_activity) {
            mysqli_stmt_bind_param($stmt_activity, "i", $selected_activity_id_for_update);
            if (mysqli_stmt_execute($stmt_activity)) {
                $activity_result = mysqli_stmt_get_result($stmt_activity);
                if ($activity_row = mysqli_fetch_assoc($activity_result)) {
                    $activity_rake = floatval($activity_row['rake'] ?? 0.0);
                    $activity_buyin = floatval($activity_row['buyin'] ?? 0.0);
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
        // Prepare UPDATE statement
        $sql_update = "UPDATE participation SET
                       challenger = ?, `id-table` = ?, `id-siege` = ?, cout_in = ?,
                       rake = ?, recave = ?, classement = ?, tf = ?, points = ?,
                       cagnotte = ?
                       WHERE `id-membre` = ? AND `id-activite` = ?";

        $stmt_update = mysqli_prepare($con, $sql_update);

        if ($stmt_update) {
            $calculated_cout_in = $activity_buyin + $activity_rake; // Calculated once
            $row_update_errors = 0;

            if (isset($_POST['participations']) && is_array($_POST['participations'])) {
                foreach ($_POST['participations'] as $index => $participation) {
                    // Verify activity ID consistency
                    $form_activite_id = intval($participation['activite_id']);
                    if ($form_activite_id !== $selected_activity_id_for_update) {
                        $batch_error_messages[] = "Erreur: Incohérence d'ID d'activité pour la ligne #$index. Mise à jour ignorée.";
                        $row_update_errors++;
                        continue;
                    }

                    // Get data from form for this row
                    $membre_id = intval($participation['membre_id']);
                    $challenger = isset($participation['challenger']) ? 1 : 0;
                    $table = intval($participation['table'] ?? 1);
                    $siege = intval($participation['siege'] ?? 1);
                    $tf = isset($participation['tf']) ? 1 : 0;
                    $participation_rake_value = ($participation['rake'] === '' || $participation['rake'] === null) ? null : floatval($participation['rake']);
                    $recave = ($participation['recave'] === '' || $participation['recave'] === null) ? 0 : intval($participation['recave']);
                    $classement_input = $participation['classement'] ?? null;
                    $classement = ($classement_input === '' || $classement_input === null) ? null : intval($classement_input);

                    // Calculate Points
                    $calculated_points = 0;
                    if ($challenger == 1) $calculated_points += 1;
                    if ($tf == 1) $calculated_points += 1;
                    if ($classement !== null && $classement == 1) $calculated_points += 1;

                    // Calculate Cagnotte
                    $calculated_cagnotte = ($challenger == 1) ? (($recave * 3) + 3) : 0;

                    // Bind parameters
                    mysqli_stmt_bind_param($stmt_update, "iiiddiiidiii",
                        $challenger, $table, $siege, $calculated_cout_in,
                        $participation_rake_value, $recave, $classement, $tf,
                        $calculated_points,
                        $calculated_cagnotte,
                        $membre_id, $selected_activity_id_for_update
                    );

                    // Execute statement
                    if (!mysqli_stmt_execute($stmt_update)) {
                        $row_update_errors++;
                        $batch_error_messages[] = "Erreur MAJ Ligne #$index (Membre ID: $membre_id): " . htmlspecialchars(mysqli_stmt_error($stmt_update));
                    }
                } // End foreach
            } else {
                 $batch_error_messages[] = "Aucune donnée de participation reçue pour la mise à jour.";
                 $update_success_overall = false;
            }

            mysqli_stmt_close($stmt_update); // Close statement

            // Set feedback
            if ($update_success_overall) {
                 if ($row_update_errors == 0) {
                     $_SESSION['feedback'] = "<div class='alert alert-success'>Participations mises à jour avec succès pour l'activité ID {$selected_activity_id_for_update}.</div>";
                 } else {
                      $_SESSION['feedback'] = "<div class='alert alert-warning'>Mise à jour terminée pour l'activité ID {$selected_activity_id_for_update} avec {$row_update_errors} erreur(s):<br>" . implode("<br>", array_map('htmlspecialchars', $batch_error_messages)) . "</div>";
                 }
            } // else the initial error message was already set

        } else { // Error preparing statement
            $batch_error_messages[] = "Erreur critique de préparation de la requête UPDATE: " . htmlspecialchars(mysqli_error($con));
            $update_success_overall = false;
        }
    } // End if ($update_success_overall && $selected_activity_id_for_update)

     if (!$update_success_overall) {
          $_SESSION['feedback'] = "<div class='alert alert-danger'>Impossible de lancer/terminer la mise à jour :<br>" . implode("<br>", array_map('htmlspecialchars', $batch_error_messages)) . "</div>";
     }

    header("Location: " . $_SERVER['PHP_SELF']); // Redirect after POST
    exit;
} // End if (isset($_POST['update_participation']))


// --- Retrieve feedback message from session and clear it ---
$session_feedback = $_SESSION['feedback'] ?? null;
unset($_SESSION['feedback']);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Participations</title>
<style>
    /* --- Paste the full responsive CSS --- */
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
    }

    body {
        background: var(--background);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
        color: var(--text-color);
        line-height: 1.6;
        margin: 0;
        padding: 0;
        font-size: 16px;
    }

    .container {
        max-width: 1300px;
        margin: 1.5rem auto;
        padding: 0 1rem;
    }

    .card {
        background: var(--card-bg);
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04),
                    0 1px 2px rgba(0, 0, 0, 0.06);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    h2 {
        margin-top: 0;
        margin-bottom: 1.2rem;
        color: var(--text-color);
        font-size: 1.3rem;
        font-weight: 600;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 0.6rem;
    }

    .form-control {
        display: block;
        width: 100%;
        padding: 0.7rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        margin: 0.2rem 0;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        font-size: 1rem;
        box-sizing: border-box;
        background-color: #fff;
        color: var(--text-color);
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(255, 107, 43, 0.15);
    }

    select.form-control {
        appearance: none;
        background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23555' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.8rem center;
        background-size: 16px 16px;
        padding-right: 2.5rem;
    }

    input:read-only,
    .form-control:disabled {
        background-color: var(--readonly-bg);
        cursor: not-allowed;
        opacity: 0.7;
    }

    .btn {
        padding: 0.7rem 1.4rem;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-block;
        text-align: center;
        vertical-align: middle;
        line-height: 1.5;
    }

    .btn-primary-orange2 {
        background: var(--primary-color);
        color: white;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .btn-primary-orange2:hover,
    .btn-primary-orange2:focus {
        background: var(--primary-hover);
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
        outline: none;
    }

    .btn-block {
        width: 100%;
        display: block;
    }

    .btn-delete {
        background-color: transparent;
        border: none;
        color: var(--danger-color);
        padding: 0.3rem 0.5rem;
        font-size: 1.1rem;
        min-width: auto;
        line-height: 1;
        font-weight: bold;
    }
    .btn-delete:hover,
    .btn-delete:focus {
        color: #a02430;
        background-color: rgba(220, 53, 69, 0.1);
        outline: none;
    }

    .simple-form-table {
        width: 100%;
        border-collapse: collapse;
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

    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .data-table {
        width: 100%;
        min-width: 1150px; /* Adjusted min-width for new column */
        border-collapse: collapse;
        background: white;
        font-size: 0.9rem;
    }

    .data-table thead th {
        background: var(--primary-color);
        color: white;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.8rem;
        padding: 0.8rem 0.8rem; /* Slightly reduced padding */
        text-align: left;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
        border-bottom: 2px solid var(--primary-hover);
    }
     .data-table .activity-header th {
        background-color: #e9ecef;
        color: var(--text-color);
        text-align: left;
        font-weight: bold;
        padding: 0.6rem 0.8rem; /* Slightly reduced padding */
        position: sticky;
        top: 40px; /* Adjust based on main header height */
        z-index: 9;
        border-bottom: 1px solid var(--border-color);
        border-top: 1px solid var(--border-color);
     }

    .data-table tbody tr:nth-child(even) {
        background: rgba(0, 0, 0, 0.015);
    }

    .data-table tbody tr:hover {
        background: rgba(255, 107, 43, 0.05);
    }

    .data-table td {
        padding: 0.6rem 0.8rem; /* Slightly reduced padding */
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
        white-space: nowrap;
    }
     .data-table td.cell-center {
         text-align: center;
     }

    .data-table input[type="number"],
    .data-table input[type="text"] {
        min-width: 60px; /* Adjusted min-width */
        width: auto;
        max-width: 85px; /* Adjusted max-width */
        padding: 0.4rem 0.5rem; /* Adjusted padding */
        border: 1px solid var(--border-color);
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 0.9rem;
        text-align: right;
    }
    .data-table input[type="checkbox"] {
        margin: 0 auto;
        display: block;
        width: 16px;
        height: 16px;
        cursor: pointer;
    }
    .data-table input:read-only {
        background-color: var(--readonly-bg);
        border-color: var(--border-color);
        opacity: 0.8;
        cursor: default;
    }
    .data-table input:disabled,
    .data-table input[type="checkbox"]:disabled {
         cursor: not-allowed;
         opacity: 0.5;
    }

    .data-table tfoot td {
         padding: 1rem;
         text-align: right;
         border-top: 2px solid var(--border-color);
         background-color: #f8f9fa;
    }

    .alert {
        padding: 12px 15px;
        margin-bottom: 1.5rem;
        border: 1px solid transparent;
        border-radius: 6px;
        font-size: 0.95rem;
    }
    .alert-success { color: #0f5132; background-color: #d1e7dd; border-color: #badbcc; }
    .alert-danger { color: #842029; background-color: #f8d7da; border-color: #f5c2c7; }
    .alert-warning { color: #664d03; background-color: #fff3cd; border-color: #ffecb5; }
    .alert-info { color: #055160; background-color: #cff4fc; border-color: #b6effb; }

    /* --- Responsive Adjustments --- */
    @media (max-width: 992px) {
        .container { max-width: 960px; }
        .data-table { min-width: 950px; /* Adjusted */ }
        .simple-form-table th { width: 100px; }
    }

    @media (max-width: 768px) {
        body { font-size: 15px; }
        .container { margin: 1rem auto; padding: 0 0.8rem; }
        .card { padding: 1rem; }
        h2 { font-size: 1.2rem; }

        .simple-form-table th,
        .simple-form-table td { display: block; width: 100%; padding: 0.2rem 0; }
        .simple-form-table th { width: auto; font-weight: 600; margin-top: 0.8rem; padding-right: 0; white-space: normal; }
        .simple-form-table tr:first-child th { margin-top: 0; }
        .simple-form-table td { padding-bottom: 0.8rem; }

         .simple-form-table .btn-container-cell { padding-top: 0.8rem; border-top: 1px solid var(--border-color); margin-top: 0.8rem; }
         .simple-form-table .btn-container { justify-content: center; }
         .simple-form-table .btn-container .btn { width: 100%; max-width: 300px; }

        .data-table { min-width: 750px; /* Adjusted */ font-size: 0.85rem; }
        .data-table thead th { padding: 0.7rem 0.6rem; /* Adjusted */ font-size: 0.75rem; top: 0; }
        .data-table .activity-header th { padding: 0.5rem 0.6rem; /* Adjusted */ top: 36px; }
        .data-table td { padding: 0.5rem 0.6rem; /* Adjusted */ white-space: nowrap; }
        .data-table input[type="number"], .data-table input[type="text"] { font-size: 0.85rem; padding: 0.3rem 0.4rem; /* Adjusted */ max-width: 70px; /* Adjusted */ }

        .data-table tfoot td { padding: 0.8rem; text-align: center; }
        .data-table tfoot .btn { width: 100%; max-width: 320px; padding: 0.8rem 1rem; }
    }

    @media (max-width: 576px) {
        .container { padding: 0 0.5rem; }
        .card { padding: 0.8rem; }
        h2 { font-size: 1.1rem; padding-bottom: 0.5rem; }
        .btn { font-size: 0.85rem; padding: 0.7rem 1rem; }

        .data-table thead th { padding: 0.6rem 0.4rem; /* Adjusted */ }
        .data-table .activity-header th { padding: 0.4rem 0.4rem; /* Adjusted */ }
        .data-table td { padding: 0.4rem 0.4rem; /* Adjusted */ }
        .data-table input[type="number"] { max-width: 60px; /* Adjusted */ }
        .data-table { min-width: 650px; /* Adjusted */ }
    }
</style>

</head>
<body>

<div class="container">

    <?php
        // Display feedback message from session if it exists
        if ($session_feedback) {
            echo $session_feedback; // Message should already contain HTML
        }
    ?>

    <!-- Activity Selection Form -->
    <div class="card">
        <h2>Filtrer par Activité</h2>
        <form method="post">
            <table class="simple-form-table">
                <tr>
                    <th>Activité</th>
                    <td>
                        <?php
                        $safe_actu2_date = mysqli_real_escape_string($con, $actu2);
                        $acti_query = mysqli_query($con, "SELECT `id-activite`,`titre-activite`,`date_depart` FROM `activite` WHERE (`date_depart` >= '$safe_actu2_date') ORDER BY `date_depart` ASC");
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
                        } else {
                            echo "<option value=''>Erreur chargement activités</option>";
                        }
                        echo "</select>";
                        ?>
                    </td>
                    <td class="btn-container-cell">
                        <div class="btn-container">
                            <button type="submit" class="btn btn-primary-orange2" name="submitchoixact">
                                Filtrer
                            </button>
                        </div>
                    </td>
                </tr>
            </table>
        </form>
    </div>

    <!-- Quick Player Creation Form -->
    <div class="card">
         <h2>Création Rapide Joueur</h2>
        <form method="post">
            <table class="simple-form-table">
                <tr>
                    <th>Pseudo *</th>
                    <td>
                        <input class="form-control" id="pseudo" name="pseudo" type="text" required>
                    </td>
                </tr>
                <tr>
                    <th>Prénom</th>
                    <td>
                        <input class="form-control" id="fname" name="fname" type="text">
                    </td>
                 </tr>
                 <tr>
                     <th> </th>
                    <td class="btn-container-cell">
                         <div class="btn-container">
                            <button type="submit" class="btn btn-primary-orange2" name="submitcreaj">
                                Créer Joueur
                            </button>
                         </div>
                    </td>
                </tr>
            </table>
        </form>
    </div>

    <!-- Quick Registration Form -->
    <div class="card">
        <h2>Inscription Rapide Joueur</h2>
        <form method="post">
            <table class="simple-form-table">
                 <tr>
                    <th>Joueur *</th>
                    <td>
                        <?php
                        $membres_reg = mysqli_query($con, "SELECT `id-membre`,`pseudo` FROM `membres` ORDER BY `pseudo` ASC");
                        echo "<select name='membre' class='form-control' required><option value=''>-- Sélectionner Pseudo --</option>";
                        if ($membres_reg) {
                            while ($choix = mysqli_fetch_assoc($membres_reg)) {
                                echo "<option value='" . htmlspecialchars($choix["id-membre"]) . "'>" . htmlspecialchars($choix["pseudo"]) . "</option>";
                            }
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
                        $safe_actu2_date = mysqli_real_escape_string($con, $actu2);
                        $acti_reg = mysqli_query($con, "SELECT `id-activite`,`titre-activite`,`date_depart` FROM `activite` WHERE ( `date_depart` >= '$safe_actu2_date') ORDER BY `date_depart` ASC");
                        echo "<select name='acti' class='form-control' required><option value=''>-- Sélectionner Date --</option>";
                        if ($acti_reg) {
                             $current_selected_activity = isset($_SESSION['selected_activity']) ? $_SESSION['selected_activity'] : null;
                            while ($choix = mysqli_fetch_assoc($acti_reg)) {
                                $selected_attr = ($choix["id-activite"] == $current_selected_activity) ? ' selected' : '';
                                $formatted_date = date("d/m/Y H:i", strtotime($choix["date_depart"]));
                                echo "<option value='" . htmlspecialchars($choix["id-activite"]) . "'$selected_attr>" . $formatted_date . "</option>";
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
                         <select name="table" id="table" class="form-control">
                            <?php for ($t = 1; $t <= 10; $t++): ?>
                             <option value='<?php echo $t; ?>'><?php echo $t; ?></option>
                            <?php endfor; ?>
                         </select>
                     </td>
                 </tr>
                 <tr>
                     <th>Siège</th>
                     <td>
                         <select name="siege" id="siege" class="form-control">
                             <?php for ($s = 1; $s <= 10; $s++): ?>
                              <option value='<?php echo $s; ?>'><?php echo $s; ?></option>
                             <?php endfor; ?>
                         </select>
                     </td>
                 </tr>
                 <tr>
                     <th> </th>
                     <td class="btn-container-cell">
                        <div class="btn-container">
                           <button type="submit" class="btn btn-primary-orange2" name="submit">Inscrire Joueur</button>
                         </div>
                    </td>
                </tr>
            </table>
        </form>
    </div>

     <!-- Quick Delete Form -->
    <div class="card">
        <h2>Suppression Rapide Participation</h2>
        <form method="post">
            <table class="simple-form-table">
                <tr>
                    <th>Joueur *</th>
                    <td>
                         <?php
                         $membres_del = mysqli_query($con, "SELECT `id-membre`,`pseudo` FROM `membres` ORDER BY `pseudo` ASC");
                         echo "<select name='membresup' class='form-control' required><option value=''>-- Sélectionner Pseudo --</option>";
                         if ($membres_del) {
                            while ($choix = mysqli_fetch_assoc($membres_del)) {
                                echo "<option value='" . htmlspecialchars($choix["id-membre"]) . "'>" . htmlspecialchars($choix["pseudo"]) . "</option>";
                            }
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
                         $safe_actu2_date = mysqli_real_escape_string($con, $actu2);
                         $acti_del = mysqli_query($con, "SELECT `id-activite`,`titre-activite`,`date_depart` FROM `activite` WHERE ( `date_depart` >= '$safe_actu2_date') ORDER BY `date_depart` ASC");
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
                     <th> </th>
                     <td class="btn-container-cell">
                        <div class="btn-container">
                            <button type="submit" class="btn btn-primary-orange2" name="submitsupc" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette participation ?');">Supprimer Participation</button>
                         </div>
                    </td>
                </tr>
            </table>
        </form>
    </div>


    <!-- Participation List & Update Form -->
    <div class="card">
        <h2>Liste des Participations <?php echo isset($_SESSION['selected_activity']) ? "(Activité ID: ".$_SESSION['selected_activity'].")" : "(Toutes les activités à venir)"; ?></h2>

        <?php
            $data_found = false; // Placeholder, set true if rows are displayed
        ?>

        <form method="post" id="participation-form">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ch.</th>
                            <th>Joueur</th>
                            <th>T</th>
                            <th>S</th>
                            <th>Buy-in Act.</th>
                            <th>Rake Part.</th>
                            <th>Cout In (Calc.)</th> <!-- Added Cout In Column Header -->
                            <th>Rec.</th>
                            <th>Clas.</th>
                            <th>TF</th>
                            <th>Pts (Calc.)</th>
                            <th>Cagnotte (Calc.)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $selected_activity = isset($_SESSION['selected_activity']) ? intval($_SESSION['selected_activity']) : null;
                        // $data_found initialized above

                        $query_list = "SELECT
                                     p.`id-membre`, m.pseudo, p.`id-activite`, a.`titre-activite`, a.`date_depart`,
                                     p.`id-table`, p.`id-siege`, p.challenger, p.recave, p.classement,
                                     p.cout_in, p.rake AS participation_rake,
                                     p.points, p.cagnotte, p.tf, a.buyin AS activite_buyin
                                   FROM participation p
                                   JOIN membres m ON p.`id-membre` = m.`id-membre`
                                   JOIN activite a ON p.`id-activite` = a.`id-activite`";

                        $safe_actu2_date = mysqli_real_escape_string($con, $actu2);

                        if ($selected_activity !== null) {
                            $query_list .= " WHERE p.`id-activite` = ?";
                        } else {
                            $query_list .= " WHERE a.`date_depart` >= '$safe_actu2_date'";
                        }
                        $query_list .= " ORDER BY a.date_depart DESC, p.`id-table`, p.`id-siege`, m.pseudo";

                        $stmt_list = mysqli_prepare($con, $query_list);

                        if ($stmt_list) {
                            if ($selected_activity !== null) {
                                mysqli_stmt_bind_param($stmt_list, "i", $selected_activity);
                            }

                            if (mysqli_stmt_execute($stmt_list)) {
                                $result = mysqli_stmt_get_result($stmt_list);
                                if (mysqli_num_rows($result) > 0) {
                                    $data_found = true; // Mark data as found
                                    $index = 0;
                                    $current_activity_header = null;

                                    while($row = mysqli_fetch_assoc($result)) {
                                        // Display Activity Header Row
                                        if ($selected_activity === null && $row['id-activite'] !== $current_activity_header) {
                                            $formatted_date_header = date("d/m/Y H:i", strtotime($row["date_depart"]));
                                            echo '<tr class="activity-header"><th colspan="13">Activité: ' . htmlspecialchars($row['titre-activite']) . ' (' . $formatted_date_header . ') - ID: ' . $row['id-activite'] . '</th></tr>'; // colspan = 13
                                            $current_activity_header = $row['id-activite'];
                                        }

                                        // Display Data Row
                                        echo "<tr>";
                                        echo "<td class='cell-center'><input type='checkbox' name='participations[$index][challenger]' value='1' " . ($row['challenger'] ? 'checked' : '') . ($selected_activity ? '' : ' disabled') . "></td>";
                                        echo "<td title='" . htmlspecialchars($row['pseudo']) . "'>" . htmlspecialchars(substr($row['pseudo'], 0, 15)) . (strlen($row['pseudo']) > 15 ? '...' : '')
                                             . "<input type='hidden' name='participations[$index][membre_id]' value='" . htmlspecialchars($row['id-membre']) . "'>"
                                             . "<input type='hidden' name='participations[$index][activite_id]' value='" . htmlspecialchars($row['id-activite']) . "'></td>";
                                        echo "<td><input type='number' name='participations[$index][table]' value='" . htmlspecialchars((string)$row['id-table']) . "' min='1' max='10' " . ($selected_activity ? '' : 'readonly') . "></td>";
                                        echo "<td><input type='number' name='participations[$index][siege]' value='" . htmlspecialchars((string)$row['id-siege']) . "' min='1' max='10' " . ($selected_activity ? '' : 'readonly') . "></td>";
                                        echo "<td><input type='number' name='participations[$index][buyin_display]' value='" . htmlspecialchars((string)$row['activite_buyin']) . "' step='0.01' readonly title='Buy-in Activité'></td>";
                                        echo "<td><input type='number' name='participations[$index][rake]' value='" . htmlspecialchars((string)$row['participation_rake']) . "' step='0.01' placeholder='0.00' " . ($selected_activity ? '' : 'readonly') . "></td>";
                                        // Display Cout In (calculated during update, stored value shown here)
                                        echo "<td><input type='number' name='participations[$index][cout_in_display]' value='" . htmlspecialchars((string)$row['cout_in']) . "' step='0.01' readonly title='Coût Total (Buy-in Act. + Rake Act.)'></td>";
                                        echo "<td><input type='number' name='participations[$index][recave]' value='" . htmlspecialchars((string)$row['recave']) . "' step='1' min='0' placeholder='0' " . ($selected_activity ? '' : 'readonly') . "></td>";
                                        echo "<td><input type='number' name='participations[$index][classement]' value='" . htmlspecialchars((string)$row['classement']) . "' min='0' placeholder='N/A' " . ($selected_activity ? '' : 'readonly') . "></td>";
                                        echo "<td class='cell-center'><input type='checkbox' name='participations[$index][tf]' value='1' " . ($row['tf'] ? 'checked' : '') . ($selected_activity ? '' : ' disabled') . "></td>";
                                        echo "<td><input type='number' name='participations[$index][points_display]' value='" . htmlspecialchars((string)$row['points']) . "' step='1' placeholder='0' readonly title='Calculé: Ch(+1) + TF(+1) + Clas=1(+1)'></td>";
                                        echo "<td><input type='number' name='participations[$index][cagnotte_display]' value='" . htmlspecialchars((string)$row['cagnotte']) . "' step='1' placeholder='0' readonly title='Calculé: Si Ch. = (Recave * 3) + 3, sinon 0'></td>";
                                        echo "<td class='cell-center'>
                                                <form method='post' style='display:inline; margin:0; padding:0;' onsubmit='return confirm(\"Êtes-vous sûr de vouloir supprimer ce participant ?\");'>
                                                    <input type='hidden' name='membre_id' value='" . htmlspecialchars($row['id-membre']) . "'>
                                                    <input type='hidden' name='activite_id' value='" . htmlspecialchars($row['id-activite']) . "'>
                                                    <button type='submit' name='delete_participant' class='btn btn-delete' title='Supprimer ce participant'>
                                                        ×
                                                    </button>
                                                </form>
                                            </td>";
                                        echo "</tr>";
                                        $index++;
                                    }
                                } else {
                                    $data_found = false;
                                }
                                mysqli_free_result($result);
                            } else {
                                 echo "<tr><td colspan='13' class='alert alert-danger'>Erreur execution requête liste: " . htmlspecialchars(mysqli_stmt_error($stmt_list)) . "</td></tr>"; // colspan = 13
                                 $data_found = false;
                            }
                            mysqli_stmt_close($stmt_list);
                        } else {
                             echo "<tr><td colspan='13' class='alert alert-danger'>Erreur préparation requête liste: " . htmlspecialchars(mysqli_error($con)) . "</td></tr>"; // colspan = 13
                             $data_found = false;
                        }

                        // Display info messages based on data_found and selected_activity
                        if (!$data_found) {
                             echo "<tr><td colspan='13' style='text-align: center; padding: 1.5rem;'>Aucun participant trouvé pour " . ($selected_activity ? "l'activité sélectionnée." : "les activités à venir.") . "</td></tr>"; // colspan = 13
                        } elseif (!$selected_activity && $data_found) {
                             echo '<tr><td colspan="13"><div class="alert alert-info" style="margin-top: 1rem; text-align: center;">Filtrez par une activité pour activer la modification groupée.</div></td></tr>'; // colspan = 13
                        }
                        ?>
                    </tbody>
                     <?php if ($data_found && $selected_activity): ?>
                     <tfoot>
                        <tr>
                           <td colspan="13"> {/* colspan = 13 */}
                                <button type="submit" name="update_participation" class="btn btn-primary-orange2">
                                    Mettre à jour les Participations Sélectionnées
                                </button>
                           </td>
                        </tr>
                     </tfoot>
                     <?php endif; ?>
                </table>
            </div> <!-- /table-responsive -->
        </form>
    </div> <!-- /card -->

</div> <!-- /container -->

</body>
</html>
<?php
 if (isset($con) && $con instanceof mysqli) {
     mysqli_close($con);
 }
?>