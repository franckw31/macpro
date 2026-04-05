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
    echo $_SESSION['id'];
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
                    <section id="page-title">
                        <div class="row">
                            <div class="col-sm-8">
                                <h1 class="mainTitle">Admin | Liste des membres</h1>
                            </div>
                            <ol class="breadcrumb"></ol>
                        </div>
                    </section>
                    <div class="container-fluid container-fullw bg-white">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="row margin-top-30">
                                    <div class="col-lg-8 col-md-12">
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
                                                                                    <td><?= ($row['nb_participations'] + ($row['recave'] ?? 0)) * 3 ?> €</td>
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
                pageLength: 10,
                order: [[3, 'desc']], // Tri par points décroissant
                columnDefs: [
                    { targets: 3, className: 'points-cell' },
                    { searchable: false, orderable: false, targets: 0 }, // Compteur
                    { visible: false, targets: [1, 8, 9] }, // Cache ID, Activités et Challenges
                    { className: 'col-small', targets: [4,5,6,7] }
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