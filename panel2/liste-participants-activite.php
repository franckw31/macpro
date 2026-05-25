<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

if (strlen($_SESSION['id']) == 0) {
    header('location:logout.php');
} else {
    if (!defined('DB_CONFIG')) {
        define('DB_CONFIG', [
            'host'     => 'localhost',
            'user'     => 'root',
            'password' => 'Kookies7*',
            'name'     => 'dbs9616600',
            'charset'  => 'utf8mb4'
        ]);
    }
    
    include('include/config.php');
    $qui = $_SESSION['id'];

    function getDBConnection() {
        static $conn = null;
        if ($conn === null) {
            $conn = mysqli_connect(DB_CONFIG['host'], DB_CONFIG['user'], DB_CONFIG['password'], DB_CONFIG['name']);
            if (!$conn) die('Erreur de connexion : ' . mysqli_connect_error());
            mysqli_set_charset($conn, DB_CONFIG['charset']);
        }
        return $conn;
    }

    function isUserAuthorized($user_id, $id_activite) {
        static $auth_cache = [];
        $cache_key = "$user_id:$id_activite";
        
        if (isset($auth_cache[$cache_key])) {
            return $auth_cache[$cache_key];
        }
        
        try {
            $conn = getDBConnection();
            
            // Vérifier si l'utilisateur est admin (droits = 2)
            $is_admin = false;
            $admin_sql = "SELECT droits FROM membres WHERE `id-membre` = " . (int)$user_id . " LIMIT 1";
            $admin_check = mysqli_query($conn, $admin_sql);
            
            if ($admin_check && mysqli_num_rows($admin_check) > 0) {
                $admin_row = mysqli_fetch_assoc($admin_check);
                $is_admin = ((int)$admin_row['droits'] == 2);
            }
            
            // Si admin, autoriser directement
            if ($is_admin) {
                $auth_cache[$cache_key] = true;
                return true;
            }
            
            // Sinon, vérifier si l'utilisateur est l'organisateur de l'activité
            $is_organizer = false;
            if ($id_activite > 0) {
                $org_sql = "SELECT `id-membre` FROM activite WHERE `id-activite` = " . (int)$id_activite . " LIMIT 1";
                $organizer_check = mysqli_query($conn, $org_sql);
                
                if ($organizer_check && mysqli_num_rows($organizer_check) > 0) {
                    $organizer_row = mysqli_fetch_assoc($organizer_check);
                    $is_organizer = ((int)$organizer_row['id-membre'] == (int)$user_id);
                }
            }
            
            $auth_cache[$cache_key] = $is_organizer;
            return $is_organizer;
        } catch (Exception $e) {
            error_log("isUserAuthorized exception: " . $e->getMessage());
            return false;
        }
    }

    function formatFrenchDate($dateStr) {
        if (!$dateStr || $dateStr == '0000-00-00 00:00:00') return '-';
        $date = new DateTime($dateStr);
        $day = (int)$date->format('j');
        $month = (int)$date->format('n');
        $hour = (int)$date->format('G');
        $minute = (int)$date->format('i');
        return sprintf('%d-%d %dh%02d', $day, $month, $hour, $minute);
    }

    function fetchParticipants() {
        $conn = getDBConnection();
        
        // Vérifier la structure de la table
        $check_sql = "SHOW COLUMNS FROM participation LIKE 'challenger'";
        $result = mysqli_query($conn, $check_sql);
        if (!$result || mysqli_num_rows($result) === 0) {
            error_log("Colonne challenger manquante - Exécuter fix_database.sql");
            return [];
        }
        
        $id_activite = isset($_REQUEST['id_activite']) ? (int)$_REQUEST['id_activite'] : 0;
        $where_clause = $id_activite > 0 ? "WHERE p.`id-activite` = $id_activite" : "";
        
        // Main query
        $query = "SELECT 
                    m.`id-membre`, 
                    COALESCE(p.challenger, 0) as challenger,
                    m.pseudo,
                    a.buyin,
                    a.bounty,
                    a.rake,
                    (a.buyin + a.bounty + a.rake + (CASE WHEN COALESCE(p.challenger, 0) = 1 THEN 5 ELSE 0 END)) as cout_in,
                    COALESCE(p.recave, 0) as recave,
                    COALESCE(p.classement, 1) as classement,
                    COALESCE(p.tf, 0) as tf,
                    COALESCE(p.points, 0) as points,
                    COALESCE(p.caisse_chal, 0) as caisse_chal,
                    COALESCE(p.anonyme, 0) as anonyme,
                    COALESCE(p.latereg, 0) as latereg,
                    COALESCE(p.jetons, 0) as jetons,
                    COALESCE(p.jetons_bonus_ins, 0) as jetons_bonus_ins,
                    COALESCE(p.jetons_bonus_arrivee, 0) as jetons_bonus_arrivee,
                    (COALESCE(p.jetons, 0) + COALESCE(p.jetons_bonus_ins, 0) + COALESCE(p.jetons_bonus_arrivee, 0)) as jetons_total,
                    p.valide,
                    p.option,
                    COALESCE(p.gain, 0) as gain,
                    p.ds,
                    p.heure_arrivee
                FROM participation p
                JOIN membres m ON p.`id-membre` = m.`id-membre`
                LEFT JOIN activite a ON p.`id-activite` = a.`id-activite`
                $where_clause
                ORDER BY p.ds ASC";
        
        $result = mysqli_query($conn, $query);
        if (!$result) {
            error_log("Erreur SQL: " . mysqli_error($conn));
            return [];
        }
        
        $participants = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $participants[] = $row;
        }
        
        return $participants;
    }
    
    // Récupérer les infos pour les variables JavaScript
    $id_activite_request = isset($_REQUEST['id_activite']) ? (int)$_REQUEST['id_activite'] : 0;
    $rake_for_js = 0;
    $is_authorized_for_js = false;
    
    if ($id_activite_request > 0) {
        try {
            $conn = getDBConnection();
            $rake_query = "SELECT rake FROM activite WHERE `id-activite` = " . $id_activite_request;
            $rake_result = mysqli_query($conn, $rake_query);
            if ($rake_result && mysqli_num_rows($rake_result) > 0) {
                $row = mysqli_fetch_assoc($rake_result);
                $rake_for_js = (int)$row['rake'];
            }
            $is_authorized_for_js = isUserAuthorized($qui, $id_activite_request);
        } catch (Exception $e) {
            error_log("Error retrieving activity info: " . $e->getMessage());
            $is_authorized_for_js = false;
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin | Liste des participants</title>
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
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script>
        var activityRake = <?= json_encode($rake_for_js) ?>;
        var userCanEdit = <?= json_encode($is_authorized_for_js) ?>;
    </script>
    <style>
        /* Base Styles */
        .col-small {
            width: 80px !important;
            text-align: center;
        }
        .current-user {
            color: #0d6efd;
            font-weight: bold;
        }
        
        /* Table Styles */
        #employeeTable {
            font-size: 18px;
            width: 100%;
            border-collapse: collapse;
            background-color: #ffffff;
        }
        #employeeTable thead th {
            font-size: 14px;
            font-weight: bold;
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 10;
            text-align: center;
            padding: 12px 8px;
            border: 1px solid #ddd;
        }
        #employeeTable tfoot th {
            font-size: 14px;
            font-weight: bold;
            background-color: #f8f9fa;
            text-align: center;
            padding: 10px 8px;
            border: 1px solid #ddd;
        }
        #employeeTable td {
            padding: 10px 8px;
            border: 1px solid #ddd;
            text-align: center;
            vertical-align: middle;
        }
        #employeeTable tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        #employeeTable tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.1);
        }
        
        /* Rake Column Color */
        td:nth-child(10) {
            font-weight: bold;
            color: #28a745;
        }

        /* Controls & Layout */
        .table-container {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .table-controls {
            margin: 15px 0;
            display: flex;
            justify-content: flex-end;
        }
        .search-box {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 250px;
        }
        
        h1.mt-4 {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            text-align: center;
            margin: 20px auto !important;
            padding: 15px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-transform: uppercase;
            max-width: 600px;
        }

        form.mb-4 {
            margin: 20px auto;
            max-width: 600px;
            padding: 15px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Responsive Adjustments */
        @media (max-width: 992px) {
            .col-lg-12 {
                padding: 0 15px;
            }
        }

        @media (max-width: 768px) {
            h1.mt-4 {
                font-size: 20px;
                margin: 10px auto !important;
                width: 95%;
            }
            form.mb-4 {
                width: 95%;
                padding: 10px;
            }
            form.mb-4 .d-flex {
                flex-direction: column;
                gap: 10px !important;
            }
            form.mb-4 .form-select, 
            form.mb-4 .btn {
                width: 100% !important;
                max-width: none;
            }
            .search-box {
                width: 100%;
            }
            #employeeTable {
                font-size: 14px;
            }
            #employeeTable thead th {
                font-size: 12px;
                padding: 8px 4px;
            }
            #employeeTable td {
                padding: 6px 4px;
            }
            .col-small {
                width: 50px !important;
                min-width: 50px;
            }
        }

        /* Utility Classes */
        .checkbox-cell {
            text-align: center;
        }
        .checkbox-cell input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .checkbox-cell input[type="checkbox"]:disabled {
            cursor: default;
            opacity: 0.8;
        }
        .bonus-arrivee-checkbox {
            width: 24px;
            height: 24px;
            cursor: pointer;
            accent-color: #28a745;
            opacity: 1 !important;
        }
        .bonus-arrivee-cell {
            text-align: center;
            font-weight: bold;
        }
        .bonus-arrivee-cell input:checked {
            accent-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.25);
        }
        .challenger-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .editable {
            cursor: pointer;
            position: relative;
        }
        .editable:hover::after {
            content: '✎';
            position: absolute;
            right: 2px;
            top: 2px;
            font-size: 10px;
            color: #999;
        }
        
        .editable.disabled {
            cursor: not-allowed;
            opacity: 0.6;
            background-color: #f0f0f0;
        }
        
        .editable.disabled:hover::after {
            content: '🔒';
            color: #ccc;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 12px 20px;
            border-radius: 4px;
            display: none;
            z-index: 10000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .save-all-btn {
            margin: 20px auto;
            display: block;
            padding: 12px 25px;
            font-size: 16px;
            font-weight: bold;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .save-all-btn:hover {
            background: #218838;
        }

        /* ── Trak ── */
        .trak-btn {
            font-size: 14px;
            padding: 2px 7px;
            line-height: 1.4;
            white-space: nowrap;
        }
        #trakModal .note-item {
            border-left: 3px solid #ffc107;
            padding: 6px 10px;
            margin-bottom: 8px;
            background: #fffdf0;
            border-radius: 0 4px 4px 0;
            font-size: 13px;
        }
        #trakModal .note-item .note-meta {
            font-size: 11px;
            color: #888;
            margin-bottom: 3px;
        }
        #trakModal .note-item .note-text {
            white-space: pre-wrap;
        }
        #trakNotesList:empty::after {
            content: 'Aucune note pour ce joueur.';
            color: #aaa;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="notification" id="updateNotification">Modification enregistrée</div>
    <div id="app">
        <?php include('include/sidebar.php'); ?>
        <div class="app-content">
            <?php include('include/header.php'); ?>
            <div class="main-content">
                <div class="wrap-content container" id="container">
                    <div class="container-fluid container-fullw bg-white">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="row margin-top-30">
                                    <div class="col-lg-12 col-md-12">
                                        <div class="panel panel-white">
                                            <div class="panel-body">
                                                <div id="layoutSidenav_content">
                                                    <main>
                                                        <div class="container-fluid px-4">
                                                            <h1 class="mt-4">Liste des Participants</h1>
                                                            <form method="post" class="mb-4">
                                                                <div class="d-flex align-items-center justify-content-start" style="gap: 10px;">
                                                                    <select name="id_activite" class="form-select" style="width: 300px;">
                                                                        <option value="0">Toutes les activités</option>
                                                                        <?php
                                                                        $conn = getDBConnection();
                                                                        $sql = "SELECT `id-activite`, `titre-activite`, date_depart FROM activite ORDER BY date_depart DESC";
                                                                        $result = mysqli_query($conn, $sql);
                                                                        while ($activite = mysqli_fetch_assoc($result)) {
                                                                            $selected = isset($_REQUEST['id_activite']) && $_REQUEST['id_activite'] == $activite['id-activite'] ? 'selected' : '';
                                                                            $date = date('d/m/Y', strtotime($activite['date_depart']));
                                                                            echo "<option value='{$activite['id-activite']}' $selected>{$date} - {$activite['titre-activite']}</option>";
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <button type="submit" class="btn btn-primary ms-2">Filtrer</button>
                                                                </div>
                                                            </form>

                                                            <div class="card mb-4">
                                                                <div class="card-body">
                                                                    <div class="table-controls">
                                                                        <input type="text" id="tableSearch" class="search-box" placeholder="Rechercher...">
                                                                    </div>
                                                                    <div class="table-container">
                                                                        <table id="employeeTable" class="table table-hover">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th>#</th>
                                                                                    <th>Présent</th>
                                                                                    <th>Option</th>
                                                                                    <th>Pseudo</th>
                                                                                    <th>Trak</th>
                                                                                    <th>Latereg</th>
                                                                                    <th>Date Inscription</th>
                                                                                    <th class="col-small">Bonus 1</th>
                                                                                    <th class="col-small">Heure Arrivée</th>
                                                                                    <th class="col-small">Bonus 2</th>
                                                                                    <th class="col-small">Buyin</th>
                                                                                    <th class="col-small">Bounty</th>
                                                                                    <th class="col-small">Rake</th>
                                                                                    <th class="col-small">Coût-In</th>
                                                                                    <th class="col-small">Classement</th>
                                                                                    <th class="col-small">Gains</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <?php foreach(fetchParticipants() as $index => $row): ?>
                                                                                <tr data-id="<?= $row['id-membre'] ?>">
                                                                                    <td><?= $index + 1 ?></td>
                                                                                    <td class="checkbox-cell">
                                                                                        <input type="checkbox" class="present-checkbox" 
                                                                                               <?= $row['option'] == 'Présent' ? 'checked' : '' ?>>
                                                                                    </td>
                                                                                    <td class="editable" data-field="option"><?= $row['option'] ?></td>
                                                                                    <td><?php 
                                                                                        $displayName = ($row['anonyme'] == 1 && $qui != $row['id-membre']) ? 'Anonyme' : $row['pseudo'];
                                                                                        echo ($qui == $row['id-membre']) ? 
                                                                                            '<span class="current-user">'.$displayName.'</span>' : 
                                                                                            $displayName; 
                                                                                    ?></td>
                                                                                    <td style="text-align:center">
                                                                                        <button class="btn btn-sm btn-outline-warning trak-btn"
                                                                                                data-id-cible="<?= $row['id-membre'] ?>"
                                                                                                data-pseudo="<?= htmlspecialchars($displayName, ENT_QUOTES) ?>"
                                                                                                title="Notes sur <?= htmlspecialchars($displayName, ENT_QUOTES) ?>">
                                                                                            📝
                                                                                        </button>
                                                                                    </td>
                                                                                    <td class="checkbox-cell" data-field="latereg">
                                                                                        <input type="checkbox" class="latereg-checkbox" 
                                                                                               <?= $row['latereg'] ? 'checked' : '' ?>>
                                                                                    </td>
                                                                                    <td><?= formatFrenchDate($row['ds']) ?></td>
                                                                                    <td class="col-small"><?= $row['jetons_bonus_ins'] ?></td>
                                                                                    <td class="col-small"><?= $row['heure_arrivee'] && $row['heure_arrivee'] != '0000-00-00 00:00:00' ? date('H:i:s', strtotime($row['heure_arrivee'])) : '-' ?></td>
                                                                                    <td class="bonus-arrivee-cell">
                                                                                        <input type="checkbox" class="bonus-arrivee-checkbox" 
                                                                                               <?= $row['jetons_bonus_arrivee'] == 5000 ? 'checked' : '' ?>>
                                                                                    </td>
                                                                                    <td class="col-small" data-field="buyin"><?= $row['buyin'] ?></td>
                                                                                    <td class="col-small" data-field="bounty"><?= $row['bounty'] ?></td>
                                                                                    <td class="col-small" data-field="rake"><?= $row['rake'] ?></td>
                                                                                    <td class="editable col-small" data-field="cout_in"><?= $row['cout_in'] ?></td>
                                                                                    <td class="editable col-small" data-field="classement"><?= $row['classement'] ?></td>
                                                                                    <td class="editable col-small" data-field="gain"><?= $row['gain'] ?></td>
                                                                                </tr>
                                                                                <?php endforeach; ?>
                                                                            </tbody>
                                                                            <tfoot>
                                                                                <tr>
                                                                                    <th colspan="10" style="text-align:right">Total:</th>
                                                                                    <th class="col-small" data-total-field="buyin"></th>
                                                                                    <th class="col-small" data-total-field="bounty"></th>
                                                                                    <th class="col-small" data-total-field="rake"></th>
                                                                                    <th class="col-small" data-total-field="cout_in"></th>
                                                                                    <th class="col-small"></th>
                                                                                    <th class="col-small" data-total-field="gain"></th>
                                                                                </tr>
                                                                            </tfoot>
                                                                        </table>
                                                                    </div>
                                                                    <button type="button" class="save-all-btn" id="saveAllChanges">
                                                                        Valider toutes les modifications
                                                                    </button>
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
        <!-- ══ Modal Trak ══ -->
        <div class="modal fade" id="trakModal" tabindex="-1" role="dialog" aria-labelledby="trakModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
              <div class="modal-header" style="background:#fff3cd;">
                <h5 class="modal-title" id="trakModalLabel">
                  📝 Trak &mdash; <span id="trakModalPseudo" style="font-weight:bold;"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fermer">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
              <div class="modal-body">
                <h6 style="color:#888;">Notes existantes</h6>
                <div id="trakNotesList" style="max-height:220px;overflow-y:auto;margin-bottom:12px;"></div>
                <hr>
                <h6>Ajouter une note</h6>
                <textarea id="trakNoteInput" class="form-control" rows="3"
                          placeholder="Votre observation horodatée sur ce joueur..."></textarea>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-warning" id="trakSaveBtn">Enregistrer la note</button>
              </div>
            </div>
          </div>
        </div>

        <?php include('include/footer.php'); ?>
        <?php include('include/setting.php'); ?>
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
    <script>
        function showNotification(message) {
            const $notif = $('#updateNotification');
            $notif.text(message).fadeIn();
            setTimeout(() => $notif.fadeOut(), 2000);
        }

        function updateField(id_membre, field, value, callback) {
            const activite_id = $('select[name="id_activite"]').val();
            $.ajax({
                url: 'update_field.php',
                method: 'POST',
                data: {
                    id_membre: id_membre,
                    id_activite: activite_id,
                    field: field,
                    value: value
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Success response:', response);
                    if (response.success) {
                        showNotification('Modification enregistrée');
                        if (callback) callback(true);
                    } else {
                        alert('Erreur : ' + (response.error || 'Erreur inconnue'));
                        console.error('Server error:', response);
                        if (callback) callback(false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', {
                        status: status,
                        error: error,
                        statusCode: xhr.status,
                        responseText: xhr.responseText,
                        contentType: xhr.getResponseHeader('content-type')
                    });
                    
                    // Essayer de parser en JSON même en cas d'erreur
                    try {
                        const data = JSON.parse(xhr.responseText);
                        alert('Erreur serveur: ' + (data.error || xhr.responseText));
                    } catch(e) {
                        alert('Erreur serveur: ' + xhr.responseText);
                    }
                    
                    if (callback) callback(false);
                }
            });
        }

        function calculateTotals() {
            let totals = {
                buyin: 0, bounty: 0, rake: 0, cout_in: 0, gain: 0
            };

            $('#employeeTable tbody tr:visible').each(function() {
                const $row = $(this);
                totals.buyin += parseFloat($row.find('[data-field="buyin"]').text()) || 0;
                totals.bounty += parseFloat($row.find('[data-field="bounty"]').text()) || 0;
                totals.rake += parseFloat($row.find('[data-field="rake"]').text()) || 0;
                totals.cout_in += parseFloat($row.find('[data-field="cout_in"]').text()) || 0;
                totals.gain += parseFloat($row.find('[data-field="gain"]').text()) || 0;
            });

            const $tfoot = $('#employeeTable tfoot tr');
            $tfoot.find('[data-total-field="buyin"]').text(totals.buyin + ' €');
            $tfoot.find('[data-total-field="bounty"]').text(totals.bounty + ' €');
            $tfoot.find('[data-total-field="rake"]').text(totals.rake + ' €');
            $tfoot.find('[data-total-field="cout_in"]').text(totals.cout_in + ' €');
            $tfoot.find('[data-total-field="gain"]').text(totals.gain + ' €');
        }

        jQuery(document).ready(function () {
            Main.init();
            FormElements.init();
            calculateTotals();
            
            // Appliquer la classe "disabled" aux cellules éditables si l'utilisateur n'est pas autorisé
            if (!userCanEdit) {
                $('.editable').addClass('disabled');
                $('#saveAllChanges').prop('disabled', true).css('opacity', '0.5').css('cursor', 'not-allowed');
                $('.present-checkbox').prop('disabled', true);
            }

            // Fonction de recherche simple
            $('#tableSearch').on('keyup', function() {
                const searchText = $(this).val().toLowerCase();
                $('#employeeTable tbody tr').each(function() {
                    const text = $(this).text().toLowerCase();
                    $(this).toggle(text.indexOf(searchText) > -1);
                });
                calculateTotals();
            });

            // Ajouter cette fonction dans le script JavaScript
            function refreshRowData(row) {
                const id_membre = row.data('id');
                const activite_id = $('select[name="id_activite"]').val();
                
                $.ajax({
                    url: 'get_participant_data.php',
                    method: 'GET',
                    data: {
                        id_membre: id_membre,
                        id_activite: activite_id
                    },
                    success: function(response) {
                        try {
                            const data = JSON.parse(response);
                            if (data.success && data.data) {
                                // Mise à jour des cellules
                                row.find('td[data-field="cout_in"]').text(data.data.cout_in);
                                row.find('td[data-field="option"]').text(data.data.option);
                                row.find('.latereg-checkbox').prop('checked', data.data.latereg == 1);
                                row.find('td[data-field="classement"]').text(data.data.classement);
                                row.find('td[data-field="gain"]').text(data.data.gain);
                                row.find('.present-checkbox').prop('checked', data.data.option == 'Présent');
                                
                                // Mettre à jour l'heure d'arrivée
                                let heureArrivee = '-';
                                if (data.data.heure_arrivee && data.data.heure_arrivee != '0000-00-00 00:00:00') {
                                    const time = new Date(data.data.heure_arrivee);
                                    heureArrivee = String(time.getHours()).padStart(2, '0') + ':' + String(time.getMinutes()).padStart(2, '0') + ':' + String(time.getSeconds()).padStart(2, '0');
                                }
                                row.find('td').eq(7).text(heureArrivee); // Colonne Heure Arrivée (indice 7)
                                
                                                                                row.find('.bonus-arrivee-checkbox').prop('checked', data.data.jetons_bonus_arrivee == 5000);
                                
                                calculateTotals();
                            }
                        } catch(e) {
                            console.error('Error refreshing row:', e);
                        }
                    }
                });
            }

            // Click handler for editable cells
            $(document).on('click', '.editable', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Vérifier les permissions
                if (!userCanEdit) {
                    alert('Vous n\'avez pas la permission de modifier cette activité.\nSeuls l\'administrateur et l\'organisateur peuvent modifier les données.');
                    return;
                }
                
                const cell = $(this);
                if (cell.find('input').length) return;
                
                const currentValue = cell.text().trim().replace(' €', '');
                const field = cell.data('field');
                const activite_id = $('select[name="id_activite"]').val();
                
                if (!activite_id) {
                    alert('Veuillez sélectionner une activité');
                    return;
                }

                if (['latereg'].includes(field)) {
                    const newValue = currentValue === 'Oui' ? '0' : '1';
                    updateField(cell.closest('tr').data('id'), field, newValue, function(success) {
                        if (success) refreshRowData(cell.closest('tr')); // Rafraîchir toute la ligne
                    });
                    return;
                }
                
                cell.html(`<input type="text" value="${currentValue}" style="width:100%;text-align:center;">`);
                const input = cell.find('input');
                input.focus();

                input.on('blur', function() {
                    const newValue = $(this).val().trim();
                    if (newValue !== currentValue) {
                        updateField(cell.closest('tr').data('id'), field, newValue, function(success) {
                            if (success) {
                                refreshRowData(cell.closest('tr')); // Rafraîchir toute la ligne
                            } else {
                                cell.text(currentValue);
                            }
                        });
                    } else {
                        cell.text(currentValue);
                    }
                });

                input.on('keypress', function(e) {
                    if (e.which === 13) input.blur();
                });
            });

            // Click handler for latereg checkbox
            $(document).on('change', '.latereg-checkbox', function(e) {
                // Vérifier les permissions
                if (!userCanEdit) {
                    // Restaurer l'état précédent
                    $(this).prop('checked', !$(this).prop('checked'));
                    alert('Vous n\'avez pas la permission de modifier cette activité.\nSeuls l\'administrateur et l\'organisateur peuvent modifier les données.');
                    return;
                }
                
                const checkbox = $(this);
                const row = checkbox.closest('tr');
                const id_membre = row.data('id');
                const newValue = checkbox.prop('checked') ? '1' : '0';
                
                updateField(id_membre, 'latereg', newValue, function(success) {
                    if (success) {
                        refreshRowData(row);
                    } else {
                        checkbox.prop('checked', !checkbox.prop('checked'));
                    }
                });
            });

            // Click handler for present checkbox
            $(document).on('change', '.present-checkbox', function(e) {
                // Vérifier les permissions
                if (!userCanEdit) {
                    // Restaurer l'état précédent
                    $(this).prop('checked', !$(this).prop('checked'));
                    alert('Vous n\'avez pas la permission de modifier cette activité.\nSeuls l\'administrateur et l\'organisateur peuvent modifier les données.');
                    return;
                }
                
                const checkbox = $(this);
                const row = checkbox.closest('tr');
                const id_membre = row.data('id');
                const isChecked = checkbox.prop('checked');
                const newOption = isChecked ? 'Présent' : 'Inscrit';
                const activite_id = $('select[name="id_activite"]').val();
                
                updateField(id_membre, 'option', newOption, function(success) {
                    if (success) {
                        // D'abord mettre à jour le bonus arrivée en base de données
                        $.ajax({
                            url: 'update_bonus_arrivee.php',
                            method: 'POST',
                            data: {
                                id_activite: activite_id
                            },
                            dataType: 'json',
                            success: function(response) {
                                console.log('Bonus arrivée updated:', response);
                                // Puis rafraîchir les données de la ligne
                                refreshRowData(row);
                            },
                            error: function(xhr, status, error) {
                                console.error('Error updating bonus arrivée:', error);
                                // Quand même rafraîchir si la mise à jour du bonus échoue
                                refreshRowData(row);
                            }
                        });
                    } else {
                        checkbox.prop('checked', !checkbox.prop('checked'));
                    }
                });
            });

            // Click handler for bonus arrivée checkbox
            $(document).on('change', '.bonus-arrivee-checkbox', function(e) {
                // Vérifier les permissions
                if (!userCanEdit) {
                    // Restaurer l'état précédent
                    $(this).prop('checked', !$(this).prop('checked'));
                    alert('Vous n\'avez pas la permission de modifier cette activité.\nSeuls l\'administrateur et l\'organisateur peuvent modifier les données.');
                    return;
                }
                
                const checkbox = $(this);
                const row = checkbox.closest('tr');
                const id_membre = row.data('id');
                const newValue = checkbox.prop('checked') ? '5000' : '0';
                
                updateField(id_membre, 'jetons_bonus_arrivee', newValue, function(success) {
                    if (success) {
                        refreshRowData(row);
                    } else {
                        checkbox.prop('checked', !checkbox.prop('checked'));
                    }
                });
            });

            // Save All button handler
            $('#saveAllChanges').on('click', function(e) {
                e.preventDefault();
                console.log('Save All button clicked');
                console.log('userCanEdit:', userCanEdit);
                console.log('Button disabled:', $(this).prop('disabled'));
                
                // Vérifier les permissions
                if (!userCanEdit) {
                    alert('Vous n\'avez pas la permission de modifier cette activité.\nSeuls l\'administrateur et l\'organisateur peuvent modifier les données.');
                    return;
                }
                
                const activite_id = $('select[name="id_activite"]').val();
                console.log('Activity ID:', activite_id);
                
                if (!activite_id) {
                    alert('Veuillez sélectionner une activité');
                    return;
                }

                if (!confirm('Êtes-vous sûr de vouloir valider toutes les modifications ?')) {
                    return;
                }

                const updates = [];
                $('#employeeTable tbody tr').each(function() {
                    const $row = $(this);
                    updates.push({
                        id_membre: $row.data('id'),
                        valide: $row.find('.present-checkbox').prop('checked') ? 'Actif' : 'Inactif'
                    });
                });

                console.log('Updates to send:', updates);
                
                if (!updates.length) {
                    alert('Aucune donnée à mettre à jour');
                    return;
                }

                console.log('Sending AJAX request...');
                console.log('Data to send:', {
                    id_activite: activite_id,
                    updates: JSON.stringify(updates)
                });

                $.ajax({
                    url: 'update_all_participants.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        id_activite: activite_id,
                        updates: JSON.stringify(updates)
                    },
                    success: function(response) {
                        console.log('Raw response:', response);
                        console.log('Response type:', typeof response);
                        
                        if (response.success) {
                            showNotification('Toutes les modifications ont été enregistrées');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            alert('Erreur: ' + (response.error || 'Erreur inconnue'));
                            console.error('Server error:', response);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error details:', {
                            status: status,
                            error: error,
                            statusCode: xhr.status,
                            responseText: xhr.responseText,
                            contentType: xhr.getResponseHeader('content-type')
                        });
                        
                        // Essayer de parser en JSON même en cas d'erreur
                        try {
                            const data = JSON.parse(xhr.responseText);
                            alert('Erreur serveur: ' + (data.error || xhr.responseText));
                        } catch(e) {
                            alert('Erreur serveur: ' + xhr.responseText);
                        }
                    }
                });
            });
        });

        // ══════════════════════════════════════════════
        //  TRAK  – modal de notation horodatée
        // ══════════════════════════════════════════════
        (function($) {
            var currentTrakCible = null;

            // Formater date ISO → dd/mm/yyyy hh:mm
            function fmtDate(dt) {
                if (!dt) return '';
                var d = new Date(dt.replace(' ', 'T'));
                var p = function(n) { return String(n).padStart(2, '0'); };
                return d.getDate() + '/' + p(d.getMonth()+1) + '/' + d.getFullYear()
                     + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
            }

            // Charger et afficher les notes existantes
            function loadTrakNotes(idCible, idActivite) {
                var $list = $('#trakNotesList').html('<em style="color:#aaa">Chargement…</em>');
                $.getJSON('get_trak.php', { id_cible: idCible, id_activite: idActivite || 0 })
                 .done(function(resp) {
                    $list.empty();
                    if (resp.success && resp.notes.length) {
                        $.each(resp.notes, function(i, n) {
                            $list.append(
                                '<div class="note-item">' +
                                  '<div class="note-meta"><strong>' + $('<span>').text(n.auteur_pseudo).html() + '</strong>' +
                                  ' — ' + fmtDate(n.created_at) + '</div>' +
                                  '<div class="note-text">' + $('<span>').text(n.note).html() + '</div>' +
                                '</div>'
                            );
                        });
                    } else {
                        $list.html('<em style="color:#aaa">Aucune note pour ce joueur.</em>');
                    }
                 })
                 .fail(function() {
                    $list.html('<em style="color:red">Erreur lors du chargement.</em>');
                 });
            }

            // Ouvrir le modal au clic sur un bouton Trak
            $(document).on('click', '.trak-btn', function() {
                var $btn       = $(this);
                var idCible    = $btn.data('id-cible');
                var pseudo     = $btn.data('pseudo');
                var idActivite = $('select[name="id_activite"]').val() || 0;

                currentTrakCible = { id: idCible, idActivite: idActivite };

                $('#trakModalPseudo').text(pseudo);
                $('#trakNoteInput').val('');
                loadTrakNotes(idCible, idActivite);
                $('#trakModal').modal('show');
            });

            // Enregistrer la nouvelle note
            $('#trakSaveBtn').on('click', function() {
                if (!currentTrakCible) return;
                var note = $('#trakNoteInput').val().trim();
                if (!note) {
                    alert('Veuillez saisir une note avant d\'enregistrer.');
                    return;
                }
                var $btn = $(this).prop('disabled', true).text('Enregistrement…');
                $.ajax({
                    url: 'save_trak.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        id_cible:    currentTrakCible.id,
                        id_activite: currentTrakCible.idActivite,
                        note:        note
                    },
                    success: function(resp) {
                        if (resp.success) {
                            $('#trakNoteInput').val('');
                            loadTrakNotes(currentTrakCible.id, currentTrakCible.idActivite);
                            showNotification('Note Trak enregistrée ✓');
                        } else {
                            alert('Erreur : ' + (resp.error || 'inconnue'));
                        }
                    },
                    error: function() {
                        alert('Erreur serveur lors de l\'enregistrement.');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Enregistrer la note');
                    }
                });
            });
        })(jQuery);
    </script>
</body>
</html>
<?php } ?>