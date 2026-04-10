<?php
session_start();
error_reporting(0);
include('include/config.php');

if (strlen($_SESSION['id']) == 0) {
    header('location:logout.php');
} else {
    // config.php
    define('DB_CONFIG', [
        'host'     => 'localhost',
        'user'     => 'root',
        'password' => 'Kookies7*',
        'name'     => 'dbs9616600',
        'charset'  => 'utf8mb4'
    ]);
    $qui = $_SESSION['id'];
    // echo $_SESSION['id'];
    function getDBConnection() {
        static $conn = null;
        if ($conn === null) {
            $conn = mysqli_connect(DB_CONFIG['host'], DB_CONFIG['user'], DB_CONFIG['password'], DB_CONFIG['name']);
            if (!$conn) die('Erreur de connexion : ' . mysqli_connect_error());
            mysqli_set_charset($conn, DB_CONFIG['charset']);
        }
        return $conn;
    }

    function fetchMembres() {
        $conn = getDBConnection();
        
        $id_challenge = isset($_POST['id_challenge']) ? (int)$_POST['id_challenge'] : 0;
        $where_clause = $id_challenge > 0 ? "WHERE p.points > 0 AND c.id_challenge = $id_challenge" : "WHERE p.points > 0";
        
        $query = "SELECT 
                    m.`id-membre`, 
                    m.pseudo,
                    SUM(p.points) as points,
                    SUM(p.tf) as tf,
                    SUM(p.recave) as recave,
                    SUM(p.remise) as remise,                    
                    COUNT(DISTINCT p.`id-participation`) as nb_participations,
                    GROUP_CONCAT(DISTINCT a.`titre-activite` ORDER BY a.`titre-activite` ASC) as activites,
                    GROUP_CONCAT(DISTINCT c.titre_challenge ORDER BY c.titre_challenge ASC) as challenges
                FROM participation p
                JOIN membres m ON p.`id-membre` = m.`id-membre`
                LEFT JOIN activite a ON p.`id-activite` = a.`id-activite`
                LEFT JOIN challenge c ON a.`id_challenge` = c.id_challenge
                $where_clause
                GROUP BY m.`id-membre`, m.pseudo
                ORDER BY points DESC";
        
        $result = mysqli_query($conn, $query);
        if (!$result) {
            error_log("Erreur SQL: " . mysqli_error($conn));
            return [];
        }
        
        $membres = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['activites'] = $row['activites'] ?: 'Aucune';
            $row['challenges'] = $row['challenges'] ?: 'Aucun';
            $membres[] = $row;
        }
        
        return $membres;
    }

    // Ajout de la fonction pour obtenir l'ID du challenge du mois en cours
    function getCurrentMonthChallengeId() {
        $conn = getDBConnection();
        $currentDate = date('Y-m-d');
        $sql = "SELECT id_challenge FROM challenge 
                WHERE '$currentDate' BETWEEN chal_deb AND chal_fin 
                ORDER BY chal_deb DESC LIMIT 1";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            return $row['id_challenge'];
        }
        return 0;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin | Liste des membres</title>
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
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/plugins.css">
    <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet" />
    <style>
        .col-small {
            width: 80px !important;
            text-align: center;
        }
        .current-user {
            color: #0d6efd;
            font-weight: bold;
        }
        tfoot tr th {
            text-align: center !important;
            padding: 8px !important;
        }
        #employeeTable {
            font-size: 20px;
        }
        #employeeTable thead th {
            font-size: 16px;
            font-weight: bold;
        }
        #employeeTable tfoot th {
            font-size: 16px;
            font-weight: bold;
        }
        .form-select, .btn {
            font-size: 16px;
        }
        .breadcrumb {
            font-size: 16px;
        }
        h1.mainTitle, h1.mt-4 {
            font-size: 24px;
        }
        /* Modification des styles de fond */
        .container-fluid.container-fullw.bg-white {
            background-color: transparent !important;
        }
        .panel-white {
            background: transparent !important;
        }
        .card {
            background: transparent !important;
        }
        .card-body {
            background: transparent !important;
        }
        /* Styles du tableau */
        .table {
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 4px;
        }
        .table thead th {
            background-color: #f8f9fa;
        }
        .table tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.25);
        }
        /* Styles pour le formulaire de recherche */
        .dataTables_wrapper .row:first-child {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .dataTables_filter {
            margin: 15px auto;
            padding: 15px 25px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center !important;
            float: none !important;
        }
        /* Style pour la section des boutons */
        .dt-buttons {
            margin: 15px auto;
            padding: 15px 25px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center !important;
            float: none !important;
        }
        .dt-buttons .dt-button {
            margin: 0 4px !important;
        }
        /* Ajustement pour le conteneur des boutons et de la recherche */
        .col:first-child, .col:last-child {
            flex: 0 0 auto;
            width: auto;
        }
        /* Styles responsive */
        @media (max-width: 768px) {
            .col-lg-8 {
                width: 100% !important;
                padding: 0 10px;
            }
            
            #employeeTable {
                font-size: 14px;
            }

            .dataTables_filter {
                width: 100%;
                margin: 10px 0;
            }

            .dataTables_filter input {
                width: 100% !important;
                margin: 5px 0;
            }

            .dt-buttons {
                width: 100%;
                margin: 10px 0;
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 5px;
            }

            .dt-button {
                flex: 1;
                min-width: 80px;
                margin: 2px !important;
                padding: 6px 8px !important;
                font-size: 12px;
            }

            .form-select {
                width: 100% !important;
            }

            .d-flex {
                flex-direction: column;
            }

            .btn-primary {
                width: 100%;
                margin-top: 10px;
            }

            .table-responsive {
                margin: 0 -10px;
                padding: 0 10px;
                width: calc(100% + 20px);
            }

            .col-small {
                width: 60px !important;
            }

            .dataTables_wrapper .row {
                margin: 0;
            }

            .dataTables_paginate {
                width: 100%;
                text-align: center !important;
                margin-top: 15px;
            }

            .dataTables_info {
                text-align: center;
                width: 100%;
                margin-bottom: 10px;
            }
        }

        /* Ajustements pour très petits écrans */
        @media (max-width: 480px) {
            #employeeTable {
                font-size: 12px;
            }

            .col-small {
                width: 45px !important;
            }

            h1.mainTitle, h1.mt-4 {
                font-size: 20px;
            }
        }
        /* Style pour le titre principal */
        h1.mt-4 {
            font-size: 26px;
            font-weight: 700;
            color: #2c3e50;
            text-align: center;
            margin: 30px auto !important;
            padding: 20px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-transform: uppercase;
            letter-spacing: 1px;
            max-width: 600px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @media (max-width: 768px) {
            h1.mt-4 {
                font-size: 24px;
                margin: 15px auto !important;
                padding: 15px;
                width: calc(100% - 30px);
            }
        }
        /* Prévenir le défilement horizontal */
        .wrap-content {
            overflow-x: hidden;
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            max-width: 100%;
            margin: 0;
            padding: 0;
        }

        /* Ajuster les colonnes sur mobile */
        @media (max-width: 768px) {
            #employeeTable {
                width: 100% !important;
                min-width: 500px; /* Largeur minimum pour garder la lisibilité */
            }

            .col-lg-8 {
                padding: 0;
            }

            .container-fluid.px-4 {
                padding: 0 !important;
            }

            .card-body {
                padding: 0.5rem !important;
            }

            /* Optimiser l'espace des colonnes */
            .col-small {
                width: 50px !important;
                min-width: 50px !important;
                padding: 4px !important;
            }

            td, th {
                padding: 4px !important;
                white-space: nowrap;
            }
        }

        /* Empêcher le défilement horizontal */
        html, body {
            overflow-x: hidden;
            width: 100%;
            position: relative;
        }

        .app-content, 
        .wrap-content,
        .container,
        .container-fluid,
        .row,
        .col-md-12,
        .panel-body,
        .card-body {
            max-width: 100%;
            padding-left: 0 !important;
            padding-right: 0 !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
        }

        .table-responsive {
            margin: 0;
            border: none;
            width: 100%;
        }

        @media (max-width: 768px) {
            .container-fluid.px-4 {
                padding: 0 !important;
            }

            #employeeTable {
                display: block;
                width: 100% !important;
            }

            .dataTables_wrapper {
                padding: 0 10px;
            }

            .col-small {
                min-width: auto !important;
            }

            td, th {
                padding: 4px !important;
                font-size: 12px;
            }
        }
        /* Styles pour le formulaire de filtrage */
        form.mb-4 {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 20px auto;
            max-width: 500px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        form.mb-4 .d-flex {
            width: 100%;
            gap: 15px !important;
            justify-content: center !important;
        }

        form.mb-4 .form-select {
            max-width: 300px;
            flex: 1;
        }

        form.mb-4 .btn-primary {
            min-width: 100px;
        }

        @media (max-width: 768px) {
            form.mb-4 {
                margin: 10px;
                padding: 10px;
            }
            
            form.mb-4 .d-flex {
                flex-direction: column;
            }

            form.mb-4 .form-select {
                max-width: 100%;
            }
        }
        /* Adjusted style for the main title */
        h1.mt-4 {
            margin: 0 auto 30px auto !important;
            max-width: 500px;
            width: 100%;
        }
    </style>
    <script type="text/javascript">
        new DataTable('table.display');

        function valid() {
            if (document.adddoc.npass.value != document.adddoc.cfpass.value) {
                alert("Password and Confirm Password Field do not match  !!");
                document.adddoc.cfpass.focus();
                return false;
            }
            return true;
        }

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
                error: function () {}
            });
        }
    </script>
</head>
<body>
    <div id="app">
        <?php include('include/sidebar.php'); ?>
        <div class="app-content">
            <?php include('include/header.php'); ?>
            <div class="main-content">
                <div class="wrap-content container" id="container">
                    <section id="ppage-title">
                        <div class="row">
                            <div class="col-sm-8">
                                <!-- Removed the redundant title -->
                            </div>
                            <ol class="bbreadcrumb"></ol>
                        </div>
                    </section>
                    <div class="container-fluid container-fullw bg-white">
                        <div class="row">
                            <div class="ccol-md-12">
                                <div class="row mmargin-top-30">
                                    <div class="ccol-lg-8 ccol-md-12">
                                        <div class="panel panel-white">
                                            <div class="panel-body">
                                                <div id="layoutSidenav_content">
                                                    <main>
                                                        <div class="container-fluid px-4">
                                                            <h1 class="mt-4">Classement des Challenges</h1>
                                                            <form method="post" class="mb-4">
                                                                <div class="d-flex align-items-center justify-content-start" style="gap: 10px;">
                                                                    <select name="id_challenge" class="form-select" style="width: 300px;">
                                                                        <option value="0">Tous les challenges</option>
                                                                        <?php
                                                                        $conn = getDBConnection();
                                                                        $sql = "SELECT id_challenge, titre_challenge FROM challenge ORDER BY titre_challenge";
                                                                        $result = mysqli_query($conn, $sql);
                                                                        $currentChallengeId = getCurrentMonthChallengeId();
                                                                        while ($challenge = mysqli_fetch_assoc($result)) {
                                                                            $selected = '';
                                                                            if (isset($_POST['id_challenge'])) {
                                                                                $selected = ($_POST['id_challenge'] == $challenge['id_challenge']) ? 'selected' : '';
                                                                            } else {
                                                                                $selected = ($currentChallengeId == $challenge['id_challenge']) ? 'selected' : '';
                                                                            }
                                                                            echo "<option value='{$challenge['id_challenge']}' $selected>{$challenge['titre_challenge']}</option>";
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <button type="submit" class="btn btn-primary ms-2">Filtrer</button>
                                                                </div>
                                                            </form>
                                                            <ol class="breadcrumb mb-4">
                                                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                                                <li class="breadcrumb-item active">Liste des membres</li>
                                                            </ol>
                                                            <div class="card mb-4">
                                                                <div class="card-body p-2">
                                                                    <div class="table-responsive">
                                                                        <table id="employeeTable" class="table table-hover w-100">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th>#</th>
                                                                                    <th style="display:none;">ID</th>                                                                                  
                                                                                    <th>Pseudo</th>
                                                                                    <th>Point(s)</th>
                                                                                    <th class="col-small">Présence(s)</th>
                                                                                    <th class="col-small">TF</th>
                                                                                    <th class="col-small">Recave(s)</th>
                                                                                    <th>Cagnotte</th>
                                                                                    <th class="col-small">Jetons</th>
                                                                                    <th>Activités</th>
                                                                                    <th>Challenges</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <?php foreach(fetchMembres() as $index => $row): ?>
                                                                                <tr class="clickable-row" data-id="<?= $row['id-membre'] ?>">
                                                                                    <td><?= $index + 1 ?></td>
                                                                                    <td style="display:none;"><?= $row['id-membre'] ?></td>
                                                                                    <td><?= ($qui == $row['id-membre']) ? 
                                                                                            '<span class="current-user">'.$row['pseudo'].'</span>' : 
                                                                                            $row['pseudo'] ?></td>
                                                                                    <td><?= $row['points'] ?></td>
                                                                                    <td><?= $row['nb_participations'] ?></td>
                                                                                    <td><?= $row['tf'] ?? 0 ?></td>
                                                                                    <td><?= $row['recave'] ?? 0 ?></td>
                                                                                    <td><?= (($row['nb_participations'] + ($row['recave'] ?? 0)) - ($row['remise'] ?? 0)) * 3 ?> €</td>
                                                                                    <td>
                                                                                        <?php
                                                                                        $presence = $row['nb_participations'];
                                                                                        $jetons = 0;
                                                                                        if ($presence == 1) $jetons = 35000;
                                                                                        elseif ($presence == 2) $jetons = 37500;
                                                                                        elseif ($presence == 3) $jetons = 40000;
                                                                                        elseif ($presence == 4) $jetons = 42500;
                                                                                        elseif ($presence == 5) $jetons = 45000;
                                                                                        elseif ($presence == 6) $jetons = 46000;
                                                                                        elseif ($presence == 7) $jetons = 47000;
                                                                                        elseif ($presence == 8) $jetons = 48000;
                                                                                        elseif ($presence == 9) $jetons = 49000;
                                                                                        elseif ($presence >= 10) $jetons = 50000;
                                                                                        echo $jetons;
                                                                                        ?>
                                                                                    </td>
                                                                                    <td><?= $row['activites'] ?></td>
                                                                                    <td><?= $row['challenges'] ?></td>
                                                                                </tr>
                                                                                <?php endforeach; ?>
                                                                            </tbody>
                                                                            <tfoot>
                                                                                <tr>
                                                                                    <th colspan="3" style="text-align:left">Total:</th>
                                                                                    <th></th>
                                                                                    <th></th>
                                                                                    <th></th>
                                                                                    <th></th>
                                                                                    <th></th>
                                                                                    <th></th>
                                                                                    <th colspan="2"></th>
                                                                                </tr>
                                                                            </tfoot>
                                                                        </table>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </main>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 col-md-12">
                                    <div class="panel panel-white"></div>
                                </div>
                            </div>
                        </div>
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
        jQuery(document).ready(function () {
            Main.init();
            FormElements.init();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="../js/scripts.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
    <script src="../js/datatables-simple-demo.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            const table = $('#employeeTable').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json' },
                dom: '<"row"<"col"B><"col"f>>rt<"row"<"col"i><"col"p>>',
                buttons: ['copy', 'excel', 'pdf', 'print'],
                pageLength: 15,
                order: [[3, 'desc']], // Tri par points décroissant
                columnDefs: [
                    { targets: 3, className: 'points-cell' },
                    { searchable: false, orderable: false, targets: 0 }, // Compteur
                    { visible: false, targets: [1, 9, 10] }, // Cache ID, Activités et Challenges
                    { className: 'ccol-small', targets: [4,5,6,7,8] }
                ],
                responsive: true,
                footerCallback: function(row, data, start, end, display) {
                    var api = this.api();
                    
                    $(row).find('th').each(function(i) {
                        $(this).css('width', $(api.column(i).header()).width());
                    });

                    // Ne pas afficher le total des points (colonne 3)
                    $(api.column(3).footer()).html('');

                    // Présences total (colonne 4)
                    var presencesTotal = api.column(4, {search:'applied'}).data()
                        .reduce((a, b) => parseInt(a) + parseInt(b), 0);
                    $(api.column(4).footer()).html(presencesTotal);

                    // TF total (colonne 5)
                    var tfTotal = api.column(5, {search:'applied'}).data()
                        .reduce((a, b) => parseInt(a || 0) + parseInt(b || 0), 0);
                    $(api.column(5).footer()).html(tfTotal);

                    // Recaves total (colonne 6)
                    var recavesTotal = api.column(6, {search:'applied'}).data()
                        .reduce((a, b) => parseInt(a || 0) + parseInt(b || 0), 0);
                    $(api.column(6).footer()).html(recavesTotal);

                    // Cagnotte total (colonne 7)
                    var cagnotteTotal = api.column(7, {search:'applied'}).data()
                        .reduce((a, b) => {
                            a = a.toString().replace(' €', '');
                            b = b.toString().replace(' €', '');
                            return parseInt(a || 0) + parseInt(b || 0);
                        }, 0);
                    $(api.column(7).footer()).html(cagnotteTotal + ' €');

                    // Jetons total (colonne 8)
                    var jetonsTotal = api.column(8, {search:'applied'}).data()
                        .reduce((a, b) => parseInt(a || 0) + parseInt(b || 0), 0);
                    $(api.column(8).footer()).html(jetonsTotal);
                }
            });

            // Mise à jour automatique des numéros de ligne
            table.on('order.dt search.dt', function () {
                table.column(0, {search:'applied', order:'applied'}).nodes().each( function (cell, i) {
                    cell.innerHTML = i + 1;
                });
            }).draw();

            $('#employeeTable').on('click', 'tr.clickable-row', function() {
                window.location.href = 'voir-membre.php?id=' + $(this).data('id');
            });
        });
    </script>
</body>
</html>
<?php } ?>
