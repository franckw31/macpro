<?php
session_start();
// --- ENABLE ERROR REPORTING FOR DEBUGGING ---
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
error_reporting(0); // Production setting

include(__DIR__ . '/../asup-config.php'); // Ensure DB connection ($con)

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
    $auto_register = isset($_POST['auto_register']) && $_POST['auto_register'] == '1';
    $selected_activity_id = isset($_SESSION['selected_activity']) ? intval($_SESSION['selected_activity']) : null;

    if (!empty($pseudo)) {
        // Check if pseudo already exists (with whitespace handling)
        $check_pseudo_sql = "SELECT `id-membre` FROM `membres` WHERE LOWER(TRIM(`pseudo`)) = LOWER(TRIM(?))";
        $stmt_check = mysqli_prepare($con, $check_pseudo_sql);
        if ($stmt_check) {
            mysqli_stmt_bind_param($stmt_check, "s", $pseudo);
            if (mysqli_stmt_execute($stmt_check)) {
                mysqli_stmt_store_result($stmt_check);
                if (mysqli_stmt_num_rows($stmt_check) > 0) {
                    $_SESSION['feedback'] = "<div class='alert alert-warning'>Le pseudo '" . htmlspecialchars($pseudo) . "' existe déjà.</div>";
                } else {
                    // Create new player
                    $sql_create_player = "INSERT INTO `membres` (`pseudo`, `fname`) VALUES (?, ?)";
                    $stmt_create = mysqli_prepare($con, $sql_create_player);
                    if ($stmt_create) {
                        mysqli_stmt_bind_param($stmt_create, "ss", $pseudo, $fname);
                        if (mysqli_stmt_execute($stmt_create)) {
                            $new_player_id = mysqli_insert_id($con);
                            
                            // Auto-register to activity if requested
                            if ($auto_register && $selected_activity_id) {
                                $register_sql = "INSERT INTO `participation` (`id-membre`, `id-activite`, `id-table`, `id-siege`) VALUES (?, ?, 1, 1)";
                                $stmt_register = mysqli_prepare($con, $register_sql);
                                if ($stmt_register) {
                                    mysqli_stmt_bind_param($stmt_register, "ii", $new_player_id, $selected_activity_id);
                                    if (mysqli_stmt_execute($stmt_register)) {
                                        $_SESSION['feedback'] = "<div class='alert alert-success'>Joueur créé et inscrit à l'activité : " . htmlspecialchars($pseudo) . "</div>";
                                    } else {
                                        $_SESSION['feedback'] = "<div class='alert alert-warning'>Joueur créé mais erreur lors de l'inscription à l'activité : " . htmlspecialchars(mysqli_stmt_error($stmt_register)) . "</div>";
                                    }
                                    mysqli_stmt_close($stmt_register);
                                }
                            } else {
                                $_SESSION['feedback'] = "<div class='alert alert-success'>Joueur créé : " . htmlspecialchars($pseudo) . "</div>";
                            }
                        } else {
                            $_SESSION['feedback'] = "<div class='alert alert-danger'>Erreur création joueur : " . htmlspecialchars(mysqli_stmt_error($stmt_create)) . "</div>";
                        }
                        mysqli_stmt_close($stmt_create);
                    }
                }
                mysqli_stmt_close($stmt_check);
            }
        }
    } else {
        $_SESSION['feedback'] = "<div class='alert alert-warning'>Le pseudo est requis pour la création rapide.</div>";
    }
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
    if (!isset($_SESSION['selected_activity'])) {
        $_SESSION['feedback'] = "<div class='alert alert-warning'>Veuillez sélectionner une activité.</div>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Ajout de la gestion du champ win
    $sql_update = "UPDATE participation SET 
        challenger = ?,
        `id-table` = ?,
        `id-siege` = ?,
        rake = ?,
        recave = ?,
        classement = ?,
        tf = ?,
        win = ?,
        remise = ?,
        gain = ?,
        points = ?,
        cagnotte = ?,
        cout_in = ?
        WHERE `id-participation` = ?";

    $stmt_update = mysqli_prepare($con, $sql_update);
    if ($stmt_update) {
        foreach ($_POST['participations'] as $participation) {
            if (!isset($participation['id_participation'])) continue;

            // Get form values
            $challenger = isset($participation['challenger']) ? 1 : 0;
            $table = intval($participation['table']);
            $siege = intval($participation['siege']);
            $rake = floatval($participation['rake']);
            $recave = intval($participation['recave']);
            // Forcer classement à un entier (0 si vide)
            $classement = ($participation['classement'] !== '' && $participation['classement'] !== null) ? intval($participation['classement']) : 0;
            $tf = isset($participation['tf']) ? 1 : 0;
            $win = isset($participation['win']) ? 1 : 0;
            $remise = isset($participation['remise']) ? 1 : 0;
            $gain = isset($participation['gain']) ? floatval(str_replace(',', '.', $participation['gain'])) : 0;
            $points = isset($participation['points']) ? intval($participation['points']) : 0;
            $cagnotte = ($gain > 0) ? ($gain / 10) : 0;
            $buyin = isset($participation['buyin_display']) ? floatval($participation['buyin_display']) : 0;
            $cout_in = $buyin + $rake + ($challenger ? 5 : 0);

            // 14 paramètres à SET + 1 pour WHERE = 15
            mysqli_stmt_bind_param($stmt_update, "iiidiiiididdii",
                $challenger,
                $table,
                $siege,
                $rake,
                $recave,
                $classement,
                $tf,
                $win,
                $remise,
                $gain,
                $points,
                $cagnotte, // <-- ici la cagnotte calculée selon le gain
                $cout_in,
                $participation['id_participation']
            );

            mysqli_stmt_execute($stmt_update);
        }
        mysqli_stmt_close($stmt_update);
        $_SESSION['feedback'] = "<div class='alert alert-success'>Participations mises à jour avec succès.</div>";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


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
    <link rel="stylesheet" href="quick-part-style.css">
    <!-- Specific CSS from original quick-part.php -->
 
</head>
<body>
    <div id="app">
    <?php include('include/sidebar.php'); ?>
    <div class="app-content">
    <?php include('/include/header.php'); ?>
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
                                                        $three_days_ago = date('Y-m-d', strtotime('-30 days', strtotime($actu2)));
                                                        $safe_three_days_ago_date = mysqli_real_escape_string($con, $three_days_ago);

                                                        // Modify query to include last 3 days
                                                        $acti_query = mysqli_query($con, "SELECT `id-activite`,`titre-activite`,`date_depart` FROM `activite` WHERE (`date_depart` >= '$safe_three_days_ago_date') ORDER BY `date_depart` DESC");
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
                                                <?php if ($selected_activity !== null): ?>
                                                <tr>
                                                    <th>Inscrire à l'activité</th>
                                                    <td>
                                                        <div class="checkbox">
                                                            <label>
                                                                <input type="checkbox" name="auto_register" value="1" checked>
                                                                Inscrire directement à l'activité filtrée (ID: <?php echo $selected_activity; ?>)
                                                            </label>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                                <tr>
                                                    <th>&nbsp;</th>
                                                    <td class="btn-container-cell">
                                                        <div class="btn-container">
                                                            <button type="submit" class="btn btn-primary-orange2" name="submitcreaj">Créer Joueur</button>
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
                                                        echo "<select name='membre' id='membre_select' class='form-control' required>
                                                            <option value=''>-- Sélectionner Pseudo --</option>";
                                                        if ($membres_reg) {
                                                            while ($choix = mysqli_fetch_assoc($membres_reg)) {
                                                                echo "<option value='" . htmlspecialchars($choix["id-membre"]) . "'>" 
                                                                    . htmlspecialchars($choix["pseudo"]) . "</option>";
                                                            }
                                                            mysqli_free_result($membres_reg);
                                                        }
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
                                            $total_buyin_activite = 0.0; // Sum of activity.buyins for displayed rows
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
            <th class="cell-center">Win</th> <!-- Nouvelle colonne Win -->
            <th class="cell-right">Pts</th>
            <th class="cell-center">Remise</th>
            <th class="cell-right">Cagnotte</th>
            <th class="cell-right">Gain</th>
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
    p.points, p.cagnotte, p.tf, p.remise, a.buyin AS activite_buyin,
    COALESCE(p.gain, 0) as gain,  # Ajout ici
    p.`id-participation`
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
                        $is_win = ($raw_classement === 1) ? 1 : 0; // Determine win status

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
                        // Nouvelle colonne Win (modifiable)
                        echo "<td class='cell-center'><span class='sort-value' style='display: none;'>" . $is_win . "</span>";
                        echo "<input type='checkbox' name='participations[$index][win]' value='1' " . ($is_win ? 'checked' : '') . ($selected_activity ? '' : ' disabled') . " title='Win = 1 si Clas. = 1'>";
                        echo "</td>";
                        // Column 10: Pts (Readonly Input - numeric sort using hidden span with class)
                        // Calcul de la valeur par défaut pour PTS
                        if (floatval($row['gain']) > 0) {
                            $default_points = floatval($row['gain']) / 10;
                        } else {
                            $default_points = $raw_activite_buyin / 10;
                        }

                        // Affichage du champ éditable PTS avec la valeur par défaut calculée
                        echo "<td class='cell-right'><span class='sort-value' style='display: none;'>" . $default_points . "</span>
<input type='number' name='participations[$index][points]' value='" . htmlspecialchars((string)$default_points) . "' step='1' placeholder='0' title='Points personnalisables'></td>";
                        // Column 11: Cagnotte (Readonly Input - numeric sort using hidden span with class)
                                        $remise_checked = $row['remise'] ? 'checked' : '';
                                        echo "<td class='cell-center'><span class='sort-value' style='display: none;'>" . $row['remise'] . "</span><input type='checkbox' name='participations[$index][remise]' value='1' $remise_checked " . ($selected_activity ? '' : ' disabled') . "></td>";
                                        echo "<td class='cell-center'><span class='sort-value' style='display: none;'>" . $raw_cagnotte . "</span><input type='number' name='participations[$index][cagnotte_display]' value='" . htmlspecialchars((string)$raw_cagnotte) . "' step='1' placeholder='0' readonly title='Calc: Si Ch.=(Recave*3)+3, sinon 0'></td>";
                                        // Column: Gain
                                        echo "<td class='cell-right'>";
                                        echo "<input type='number' 
                                            name='participations[$index][gain]' 
                                            value='" . number_format(floatval($row['gain']), 2, '.', '') . "' 
                                            class='gain-input'
                                            style='width: 80px;' 
                                            step='0.01' " . 
                                            ($selected_activity ? '' : ' disabled') . ">";
                                        echo "<input type='hidden' name='participations[$index][id_participation]' value='" . $row['id-participation'] . "'>";
                                        echo "</td>";
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
                                                             <td class="cell-right">
                                                                <?php 
                                                                    $total_gains = 0;
                                                                    if (isset($result)) {
                                                                        // Réexécuter la requête pour les totaux
                                                                        $stmt_total = mysqli_prepare($con, "SELECT SUM(COALESCE(gain, 0)) as total_gains 
                                                                            FROM participation 
                                                                            WHERE `id-activite` = ?");
                                                                        if ($stmt_total) {
                                                                            mysqli_stmt_bind_param($stmt_total, "i", $selected_activity);
                                                                            mysqli_stmt_execute($stmt_total);
                                                                            $result_total = mysqli_stmt_get_result($stmt_total);
                                                                            if ($row_total = mysqli_fetch_assoc($result_total)) {
                                                                                $total_gains = floatval($row_total['total_gains']);
                                                                            }
                                                                            mysqli_free_result($result_total);
                                                                            mysqli_stmt_close($stmt_total);
                                                                        }
                                                                    }
                                                                    echo number_format($total_gains, 2, ',', ' ') . ' €';
                                                                ?>
                                                            </td>
                                                         </tr>
                                                         <tr>
                                                            <td colspan="15" class="text-center">
                                                                <input type="hidden" name="update_participation" value="1">
                                                                <button type="submit" class="btn btn-primary-orange2">
                                                                    Mettre à jour les Participations
                                                                </button>
                                                            </td>
                                                        </tr>
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

                                <!-- Prize Pool Distribution -->
                                <div class="panel panel-white card">
                                    <div class="panel-body">
                                        <h2>Répartition du Prize Pool</h2>
                                        <?php if ($selected_activity !== null && $data_found): 
                                            // Calculate default number of paid players based on total participants
                                            $default_nb_joueurs_payes = min(ceil($total_participants * 0.3), 8);
                                            
                                            // Use posted value if available, otherwise use default
                                            $nb_joueurs_payes = isset($_POST['nb_joueurs_payes']) ? 
                                                min(max(1, intval($_POST['nb_joueurs_payes'])), 8) : 
                                                $default_nb_joueurs_payes;
                                            ?>
                                            
                                            <form method="post" class="mb-3">
                                                <table class="simple-form-table">
                                                    <tr>
                                                        <th>Nombre de Joueurs Payés</th>
                                                        <td>
                                                            <select name="nb_joueurs_payes" class="form-control" style="max-width: 100px;">
                                                                <?php for($i = 1; $i <= 8; $i++): ?>
                                                                    <option value="<?php echo $i; ?>" <?php echo ($i == $nb_joueurs_payes) ? 'selected' : ''; ?>>
                                                                        <?php echo $i; ?> jr<?php echo ($i > 1) ? 's' : ''; ?>
                                                                    </option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </td>
                                                        <td class="btn-container-cell">
                                                            <div class="btn-container">
                                                                <button type="submit" class="btn btn-primary-orange2">Recalculer</button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </form>

                                            <?php
                                            // Define distribution percentages based on number of paid players
                                            $distributions = [];
                                            switch($nb_joueurs_payes) {
                                                case 1: $distributions = [1.00]; break;
                                                case 2: $distributions = [0.65, 0.35]; break;
                                                case 3: $distributions = [0.50, 0.30, 0.20]; break;
                                                case 4: $distributions = [0.40, 0.30, 0.20, 0.10]; break;
                                                case 5: $distributions = [0.35, 0.25, 0.20, 0.12, 0.08]; break;
                                                case 6: $distributions = [0.32, 0.23, 0.17, 0.13, 0.09, 0.06]; break;
                                                case 7: $distributions = [0.29, 0.21, 0.16, 0.13, 0.10, 0.07, 0.04]; break;
                                                case 8: $distributions = [0.27, 0.20, 0.15, 0.12, 0.10, 0.08, 0.05, 0.03]; break;
                                                default: $distributions = [1.00]; break;
                                            }

                                            echo "<div class='table-responsive'>";
                                            echo "<table class='data-table distribution-table'>";
                                            echo "<thead><tr>";
                                            echo "<th class='cell-center'>Position</th>";
                                            echo "<th class='cell-right'>Pourcentage</th>";
                                            echo "<th class='cell-right'>Gain Estimé</th>";
                                            echo "</tr></thead>";
                                            echo "<tbody>";

                                            for($i = 0; $i < count($distributions); $i++) {
                                                $gain_estime = $price_pool * $distributions[$i];
                                                // Arrondir au multiple de 5 supérieur, minimum 5€
                                                $gain_arrondi = ($gain_estime > 0) ? max(5, round($gain_estime / 5) * 5) : 0;
                                                
                                                echo "<tr>";
                                                echo "<td class='cell-center'>" . ($i + 1) . "</td>";
                                                echo "<td class='cell-right'>" . number_format($distributions[$i] * 100, 1) . "%</td>";
                                                echo "<td class='cell-right'>" . number_format($gain_arrondi, 2) . " €</td>";
                                                echo "</tr>";
                                            }

                                            echo "</tbody></table></div>";
                                            ?>
                                        <?php else: ?>
                                            <div class="alert alert-info">Sélectionnez une activité pour voir la répartition du Prize Pool.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Podium Display -->
<div class="panel panel-white card">
    <div class="panel-body">
        <h2>Podium des Joueurs Classés</h2>
        <?php
        if ($selected_activity !== null && $data_found) {
            // Query to get ranked players for the selected activity
            $podium_query = "
                SELECT m.pseudo, p.classement, p.challenger, p.tf, p.points 
                FROM participation p 
                JOIN membres m ON p.`id-membre` = m.`id-membre` 
                WHERE p.`id-activite` = ? AND p.classement IS NOT NULL 
                ORDER BY p.classement ASC";
            
            $stmt_podium = mysqli_prepare($con, $podium_query);
            if ($stmt_podium) {
                mysqli_stmt_bind_param($stmt_podium, "i", $selected_activity);
                if (mysqli_stmt_execute($stmt_podium)) {
                    $podium_result = mysqli_stmt_get_result($stmt_podium);
                    
                    if (mysqli_num_rows($podium_result) > 0) {
                        echo "<div class='table-responsive'>";
                        echo "<table class='data-table podium-table'>";
                        echo "<thead>";
                        echo "<tr>";
                        echo "<th class='cell-center'>Position</th>";
                        echo "<th>Joueur</th>";
                        echo "<th class='cell-center'>Challenge</th>";
                        echo "<th class='cell-center'>TF</th>";
                        echo "<th class='cell-right'>Points</th>";
                        echo "</tr>";
                        echo "</thead>";
                        echo "<tbody>";
                        
                        $position = 1;
                        while ($player = mysqli_fetch_assoc($podium_result)) {
                            $rowClass = '';
                            switch ($position) {
                                case 1: $rowClass = 'background-color: #FFD700;'; break; // Gold
                                case 2: $rowClass = 'background-color: #C0C0C0;'; break; // Silver
                                case 3: $rowClass = 'background-color: #CD7F32;'; break; // Bronze
                                default: $rowClass = ($position <= $nb_joueurs_payes) ? 'background-color: #E8F5E9;' : ''; // Light green for paid positions
                            }
                            
                            echo "<tr style='$rowClass'>";
                            echo "<td class='cell-center'>" . 
                                ($position == 1 ? '🏆' : $position) . "</td>";
                            echo "<td>" . htmlspecialchars($player['pseudo']) . "</td>";
                            echo "<td class='cell-center'>" . ($player['challenger'] ? '✓' : '') . "</td>";
                            echo "<td class='cell-center'>" . ($player['tf'] ? '✓' : '') . "</td>";
                            echo "<td class='cell-right'>" . $player['points'] . "</td>";
                            echo "</tr>";
                            
                            $position++;
                        }
                        echo "</tbody></table></div>";
                    } else {
                        echo "<div class='alert alert-info'>Aucun joueur classé pour cette activité.</div>";
                    }
                    mysqli_free_result($podium_result);
                } else {
                    echo "<div class='alert alert-danger'>Erreur lors de la récupération du classement: " . htmlspecialchars(mysqli_stmt_error($stmt_podium)) . "</div>";
                }
                mysqli_stmt_close($stmt_podium);
            } else {
                echo "<div class='alert alert-danger'>Erreur de préparation de la requête: " . htmlspecialchars(mysqli_error($con)) . "</div>";
            }
        } else {
            echo "<div class='alert alert-info'>Sélectionnez une activité pour voir le podium.</div>";
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

            // Ajout de la variable manquante
            const isAllActivitiesView = <?php echo isset($_SESSION['selected_activity']) ? 'false' : 'true'; ?>;
            const occupiedSeats = <?php echo isset($occupied_seats_json) ? $occupiedSeats_json : '[]'; ?>;

            // Initialize DataTables with row filtering
            if ($('.data-table').length > 0) {
                $('.data-table').DataTable({
                    "paging": false,
                    "info": false,
                    "language": {
                        "search": "Rechercher Joueur:",
                        "zeroRecords": "Aucun joueur trouvé",
                        "emptyTable": "Aucune participation à afficher"
                    },
                    "ordering": true,
                    "columnDefs": [
                        { "orderable": false, "targets": [0, 2, 3] },
                        {
                            "targets": [4, 5, 6, 7, 8, 9, 10, -1],
                            "render": function(data, type, row) {
                                if (type === 'sort' || type === 'type') {
                                    const value = $(data).find('input').val();
                                    return parseFloat(value) || 0;
                                }
                                return data;
                            }
                        }
                    ],
                    "order": [[0, "desc"], [1, "asc"]],
                    "initComplete": function() {
                        // Add filter inputs for each column
                        this.api().columns().every(function() {
                            var column = this;
                            var header = $(column.header());
                            
                            // Skip filter for certain columns
                            if (header.hasClass('cell-center') || header.hasClass('cell-right')) {
                                return;
                            }
                            
                            var input = $('<input type="text" placeholder="Filtrer..." style="width:100%"/>')
                                .appendTo(header)
                                .on('keyup change', function() {
                                    if (column.search() !== this.value) {
                                        column.search(this.value).draw();
                                    }
                                });
                        });
                    }
                });
            }

            // Specific JS from original quick-part.php for seat selection
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

    // Initialize alpha keyboard
    function initAlphaKeyboard() {
        const keyboard = $('.alpha-keyboard');
        const keys = $('.alpha-key');
        const membreSelect = $('#membre_select');
        
        // Ensure keyboard is visible and interactive
        keyboard.css({
            'display': 'flex',
            'pointer-events': 'auto'
        });

        // Bind click handlers
        keys.off('click').on('click', function() {
            const selectedLetter = $(this).data('letter').toUpperCase();
            console.log('Key clicked:', selectedLetter, this);

            // Visual feedback
            keys.removeClass('active');
            $(this).addClass('active');
            console.log('Active state applied to:', this);

                // Iterate through options and show/hide based on the selected letter
                let visibleCount = 0;
                membreSelect.find('option').each(function() {
                    const option = $(this);
                    const pseudo = option.text().toUpperCase();
                    
                    // Always show the default "Select Pseudo" option
                    if (option.val() === '') {
                        option.show();
                        return true;
                    }

                    if (selectedLetter === '') {
                        // Show all options if "Tous" is clicked
                        option.show();
                        visibleCount++;
                    } else {
                        // Hide options that don't start with the selected letter
                        if (pseudo.startsWith(selectedLetter)) {
                            option.show();
                            visibleCount++;
                            console.log('Showing:', option.text());
                        } else {
                            option.hide();
                        }
                    }
                });

                console.log('Total visible options:', visibleCount);
                
                // Reset the select value if the currently selected option is hidden
                if (membreSelect.find('option:selected').is(':hidden')) {
                    membreSelect.val('');
                }
                
                // Refresh select2 if it exists
                if (membreSelect.hasClass('select2-hidden-accessible')) {
                    membreSelect.select2();
                }
            });

    // Pour le bouton "Tous"
    $('.alpha-key-special').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        $('.alpha-key').removeClass('active');
        $(this).addClass('active');
        $('#membre_select option').show();
        $('#membre_select').val('').focus();
    });
        });
        
    // Add sidebar toggle functionality using event delegation
    $(document).on('click', '.sidebar-toggler', function(e) {
        e.preventDefault();
        $('#app').toggleClass('app-sidebar-closed');
    });
    </script>
</body>
</html>
<?php
 if (isset($con) && $con instanceof mysqli) {
     mysqli_close($con);
 }
?>
