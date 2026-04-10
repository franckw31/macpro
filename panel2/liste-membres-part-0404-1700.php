<?php
session_start();
error_reporting(0);
include('include/config.php');

if (strlen($_SESSION['id']) == 0) {
    header('location:logout.php');
} else {
    define('DB_CONFIG', [
        'host'     => 'localhost',
        'user'     => 'root', 
        'password' => 'Kookies7*',
        'name'     => 'dbs9616600',
        'charset'  => 'utf8mb4'
    ]);
    
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

    function fetchParticipants($activite_id = null) {
        $conn = getDBConnection();
        
        if (!$activite_id) {
            $sql = "SELECT `id-activite`, ville FROM activite ORDER by date_depart DESC LIMIT 1";
            $result = mysqli_query($conn, $sql);
            $row = mysqli_fetch_assoc($result);
            $activite_id = $row['id-activite'];
        }
        
        $query = "SELECT 
                    m.`id-membre`,
                    m.pseudo,
                    a.buyin,
                    a.bounty,
                    p.rake,
                    p.recave,
                    p.classement,
                    p.gain,
                    (p.gain - (a.buyin + a.bounty + p.rake + IF(p.recave = 1, a.buyin + a.bounty, 0))) as marge,
                    p.`id-participation`,
                    p.`id-activite`
                FROM participation p
                JOIN membres m ON p.`id-membre` = m.`id-membre`
                JOIN activite a ON p.`id-activite` = a.`id-activite`
                WHERE p.`id-activite` = ?
                ORDER BY p.classement ASC";
                
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $activite_id);
        mysqli_stmt_execute($stmt);
        return mysqli_stmt_get_result($stmt);
    }

    $selected_activite = isset($_POST['activite_id']) ? $_POST['activite_id'] : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Admin | Liste des participants</title>
    <link href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,6,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="vendor/themify-icons/themify-icons.min.css">
    <link href="vendor/animate.css/animate.min.css" rel="stylesheet" media="screen">
    <link href="vendor/perfect-scrollbar/perfect-scrollbar.min.css" rel="stylesheet" media="screen">
    <link href="vendor/switchery/switchery.min.css" rel="stylesheet" media="screen">
    <link href="vendor/bootstrap-touchspin/jquery.bootstrap-touchspin.min.css" rel="stylesheet" media="screen">
    <link href="vendor/select2/select2.min.css" rel="stylesheet" media="screen">
    <link href="vendor/bootstrap-datepicker/bootstrap-datepicker3.standalone.min.css rel="stylesheet" media="screen">
    <link href="vendor/bootstrap-timepicker/bootstrap-timepicker.min.css" rel="stylesheet" media="screen">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/plugins.css">
    <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet" />
    <style>
        /* Styles améliorés pour le tableau */
        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            font-size: 0.9rem;
        }
        
        .table thead th {
            background: #1a2a3a;
            color: white;
            font-weight: 600;
            padding: 14px 12px;
            border: none;
            position: sticky;
            top: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }
        
        .table tbody td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: middle;
            color: #333;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .table tbody tr {
            background-color: #fff;
            transition: all 0.2s ease;
        }
        
        .table tbody tr:hover {
            background-color: #f5f9ff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .money {
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
        }

        /* Couleurs pour les valeurs monétaires */
        .positive {
            color: #28a745;
        }
        
        .negative {
            color: #dc3545;
        }
        
        .neutral {
            color: #6c757d;
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
                    <div class="container-fluid container-fullw bg-white">
                        <div class="row">
                            <div class="col-md-12">
                                <h4 class="mt-4 mb-4">Liste des Participants</h4>
                                
                                <form method="post" class="mb-4">
                                    <div class="d-flex align-items-center">
                                        <select name="activite_id" class="form-select me-2" style="width: 300px;">
                                            <?php 
                                            $sql = "SELECT `id-activite`, date_depart, ville 
                                                   FROM activite 
                                                   ORDER BY date_depart DESC";
                                            $result = mysqli_query(getDBConnection(), $sql);
                                            while ($row = mysqli_fetch_assoc($result)) {
                                                $selected = ($selected_activite == $row['id-activite']) ? 'selected' : '';
                                                echo "<option value='{$row['id-activite']}' $selected>" . 
                                                     date('d/m/Y', strtotime($row['date_depart'])) . " - {$row['ville']}</option>";
                                            }
                                            ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary">Filtrer</button>
                                    </div>
                                </form>

                                <div class="table-responsive">
                                    <table id="participantsTable" class="table">
                                        <thead>
                                            <tr>
                                                <th class="text-center">#</th>
                                                <th class="text-center">Class.</th>
                                                <th>Joueur</th>
                                                <th class="text-right">Buyin</th>
                                                <th class="text-right">Bounty</th>
                                                <th class="text-right">Rake</th>
                                                <th class="text-right">Coût-In</th>
                                                <th class="text-center">Recave</th>
                                                <th class="text-right">Coût-Tot</th>
                                                <th class="text-right">Gain</th>
                                                <th class="text-right">Marge</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $result = fetchParticipants($selected_activite);
                                            $i = 1;
                                            while ($row = mysqli_fetch_assoc($result)) {
                                                $cout_in = $row['buyin'] + $row['bounty'] + $row['rake'];
                                                $cout_total = $row['buyin'] + $row['bounty'] + $row['rake'];
                                                if ($row['recave'] == 1) {
                                                    $cout_total += $row['buyin'] + $row['bounty'];
                                                }
                                                $marge = $row['gain'] - $cout_total;
                                                $marge_class = $marge > 0 ? 'positive' : ($marge < 0 ? 'negative' : 'neutral');
                                                
                                                echo "<tr data-id='{$row['id-participation']}'>";
                                                echo "<td class='text-center'>$i</td>";
                                                if ($row['classement'] == 1) {
                                                    echo "<td class='text-center'><span class='badge badge-primary'><i class='fa fa-trophy'></i></span></td>";
                                                } else {
                                                    echo "<td class='text-center'><span class='badge badge-primary'>{$row['classement']}</span></td>";
                                                }
                                                echo "<td>{$row['pseudo']}</td>";
                                                echo "<td class='text-right money'>{$row['buyin']} €</td>";
                                                echo "<td class='text-right money'>{$row['bounty']} €</td>";
                                                echo "<td class='text-right money'>{$row['rake']} €</td>";
                                                echo "<td class='text-right money'>$cout_in €</td>";
                                                echo "<td class='text-center edit-cell' data-field='recave' data-original='{$row['recave']}' data-editable='true'>{$row['recave']}</td>";
                                                echo "<td class='text-right money'>$cout_total €</td>";
                                                echo "<td class='text-right money'>".number_format($row['gain'], 2)." €</td>";
                                                echo "<td class='text-right money $marge_class'>".number_format($marge, 2)." €</td>";
                                                echo "</tr>";
                                                $i++;
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include('include/footer.php'); ?>
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
    jQuery(document).ready(function() {
        // Activer le toggle de la sidebar
        $('.sidebar-toggler').click(function() {
            $('.app').toggleClass('app-sidebar-closed');
        });

        // Activer les dropdowns du menu
        $('.dropdown-toggle').dropdown();
        
        // Activer les sous-menus
        $('.main-navigation-menu li').has('ul').children('a').click(function(e) {
            e.preventDefault();
            $(this).parent().toggleClass('open');
            $(this).next('ul').slideToggle();
        });
    });
    </script>
    <script>
        jQuery(document).ready(function() {
            const table = $('#participantsTable').DataTable({
                language: { 
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json',
                    decimal: ',',
                    thousands: ' '
                },
                dom: '<"top"<"row"<"col-md-6"B><"col-md-6"f>>>rt<"bottom"<"row"<"col-md-6"i><"col-md-6"p>>>',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fa fa-file-excel-o"></i> Excel',
                        className: 'btn btn-success'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fa fa-file-pdf-o"></i> PDF',
                        className: 'btn btn-danger'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fa fa-print"></i> Imprimer',
                        className: 'btn btn-secondary'
                    }
                ],
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],
                order: [[1, 'asc']],
                columnDefs: [
                    { 
                        targets: [3,4,5,6,7,8,9,10], // Ajout de l'index 7 (Recave)
                        className: 'text-right money',
                        render: function(data, type) {
                            if (type === 'display') {
                                var num = data.replace(/[^\d,-]/g, '').replace(',', '.');
                                return parseFloat(num).toLocaleString('fr-FR', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                }) + ' €';
                            }
                            return data;
                        }
                    },
                    { 
                        targets: 10,
                        createdCell: function(td, cellData, rowData, row, col) {
                            var num = parseFloat(cellData.replace(/[^\d,-]/g, '').replace(',', '.'));
                            if (num > 0) {
                                $(td).addClass('positive');
                            } else if (num < 0) {
                                $(td).addClass('negative');
                            } else {
                                $(td).addClass('neutral');
                            }
                        }
                    },
                    { 
                        targets: [0,1,7],
                        className: 'text-center' 
                    }
                ],
                initComplete: function() {
                    $('.dt-buttons button').removeClass('dt-button');
                }
            });

            // Edition des cellules Recave
            $('#participantsTable').on('click', 'td[data-field="recave"]', function() {
                const cell = $(this);
                if (cell.hasClass('editing')) return;
                
                const value = cell.text().trim();
                console.log('Editing recave value:', value); // Debug
                
                cell.data('original-value', value)
                   .addClass('editing')
                   .html(`
                    <div style="display:flex; gap:5px; align-items:center">
                        <select class="form-control input-sm" style="width:70px; height:30px">
                            <option value="0" ${value == 0 ? 'selected' : ''}>0</option>
                            <option value="1" ${value == 1 ? 'selected' : ''}>1</option>
                            <option value="2" ${value == 2 ? 'selected' : ''}>2</option>
                            <option value="3" ${value == 3 ? 'selected' : ''}>3</option>
                            <option value="4" ${value == 4 ? 'selected' : ''}>4</option>
                        </select>
                        <button class="btn btn-xs btn-success validate-btn">
                            <i class="fa fa-check"></i>
                        </button>
                        <button class="btn btn-xs btn-danger cancel-btn">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                   `)
                   .find('select').focus();
            });

            // Gestion de la validation
            $(document).on('click', '.validate-btn', function() {
                const cell = $(this).closest('.edit-cell');
                const value = cell.find('select').val();
                updateCell(cell, value);
            });

            // Gestion de l'annulation
            $(document).on('click', '.cancel-btn', function() {
                const cell = $(this).closest('.edit-cell');
                const originalValue = cell.data('original-value');
                restoreCell(cell, originalValue);
            });

            // Fonction de mise à jour
            function updateCell(cell, value) {
                const field = cell.data('field');
                const id = cell.parent('tr').data('id');
                const originalValue = cell.data('original-value');

                $.ajax({
                    url: 'panel/update_participation.php',
                    
                    // Alternative si le chemin précédent ne fonctionne pas:
                    // url: window.location.pathname.replace('liste-membres-part.php', 'update_participation.php'),
                    method: 'POST',
                    data: { id: id, field: field, value: value },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            cell.removeClass('editing').html(value);
                            table.draw(false);
                        } else {
                            alert('Erreur: ' + response.message);
                            restoreCell(cell, originalValue);
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = 'Erreur lors de la mise à jour';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            errorMsg = response.message || errorMsg;
                        } catch(e) {}
                        alert(errorMsg);
                        restoreCell(cell, originalValue);
                    }
                });
            }

            // Fonction de restauration
            function restoreCell(cell, value) {
                cell.removeClass('editing').html(value);
            }
        });
    </script>
</body>
</html>
<?php } ?>
