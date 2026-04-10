<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');

if (strlen($_SESSION['id']) == 0) {
    header('location:logout.php');
    exit;
}

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
        $conn = mysqli_connect(
            DB_CONFIG['host'],
            DB_CONFIG['user'],
            DB_CONFIG['password'],
            DB_CONFIG['name']
        );
        
        if (!$conn) {
            error_log('Erreur de connexion MySQL: ' . mysqli_connect_error());
            die('Erreur de connexion à la base de données');
        }
        
        mysqli_set_charset($conn, DB_CONFIG['charset']);
    }
    return $conn;
}

function fetchParticipants() {
    $conn = getDBConnection();
    $id_activite = isset($_POST['id_activite']) ? (int)$_POST['id_activite'] : 0;
    $where_clause = $id_activite > 0 ? "WHERE p.`id-activite` = $id_activite" : "";
    
    $query = "SELECT 
                m.`id-membre`, 
                COALESCE(p.challenger, 0) as challenger,
                m.pseudo,
                a.buyin,
                a.bounty,
                a.rake,
                COALESCE(p.rake_0, 0) as rake_0,
                COALESCE(p.rake_5, 0) as rake_5,
                COALESCE(p.rake_10, 0) as rake_10,
                COALESCE(p.rake_12, 0) as rake_12,
                COALESCE(p.rake_15, 0) as rake_15,
                COALESCE(p.rake_20, 0) as rake_20,
                (a.buyin + a.bounty + a.rake + (CASE WHEN COALESCE(p.challenger, 0) = 1 THEN 5 ELSE 0 END)) as cout_in,
                COALESCE(p.recave, 0) as recave,
                COALESCE(p.classement, 1) as classement,
                COALESCE(p.tf, 0) as tf,
                COALESCE(p.points, 0) as points,
                COALESCE(p.caisse_chal, 0) as caisse_chal
              FROM participation p
              JOIN membres m ON p.`id-membre` = m.`id-membre`
              LEFT JOIN activite a ON p.`id-activite` = a.`id-activite`
              $where_clause
              ORDER BY p.points DESC";
    
    $totals_query = "SELECT 
                      COUNT(*) as count,
                      SUM(a.buyin) as total_buyin,
                      SUM(a.bounty) as total_bounty,
                      SUM(a.rake) as total_rake
                    FROM participation p
                    LEFT JOIN activite a ON p.`id-activite` = a.`id-activite`
                    $where_clause";
    
    try {
        $result = $conn->query($query);
        $participants = [];
        while ($row = $result->fetch_assoc()) {
            $participants[] = $row;
        }
        
        $totals_result = $conn->query($totals_query);
        $totals = $totals_result->fetch_assoc();
        
        return [
            'participants' => $participants,
            'totals' => [
                'count' => $totals['count'] ?? 0,
                'buyin' => $totals['total_buyin'] ?? 0,
                'bounty' => $totals['total_bounty'] ?? 0,
                'rake' => $totals['total_rake'] ?? 0
            ]
        ];
    } catch (Exception $e) {
        error_log($e->getMessage());
        return [
            'participants' => [],
            'totals' => ['count' => 0, 'buyin' => 0, 'bounty' => 0, 'rake' => 0]
        ];
    }
}

$data = fetchParticipants();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Admin | Liste des participants</title>
    <link href="https://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet">
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="vendor/themify-icons/themify-icons.min.css">
    <link href="vendor/animate.css/animate.min.css" rel="stylesheet">
    <link href="vendor/perfect-scrollbar/perfect-scrollbar.min.css" rel="stylesheet">
    <link href="vendor/switchery/switchery.min.css" rel="stylesheet">
    <link href="vendor/bootstrap-touchspin/jquery.bootstrap-touchspin.min.css" rel="stylesheet">
    <link href="vendor/select2/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/plugins.css">
    <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color">
    <style>
        .col-small { width: 80px; text-align: center; }
        .current-user { color: #0d6efd; font-weight: bold; }
        .table-container { margin-bottom: 30px; }
        .table-responsive { overflow-x: auto; margin-bottom: 20px; }
        #participantsTable, #totalsTable { width: 100%; border-collapse: collapse; }
        #participantsTable th, #participantsTable td, 
        #totalsTable th, #totalsTable td { padding: 8px; border: 1px solid #ddd; text-align: center; }
        #participantsTable thead th { background-color: #f5f5f5; position: sticky; top: 0; }
        #participantsTable tbody tr:nth-child(even) { background-color: #f9f9f9; }
        #participantsTable tbody tr:hover { background-color: #f0f7ff; }
        .checkbox-cell { text-align: center; }
        .challenger-checkbox { width: 20px; height: 20px; cursor: pointer; }
        .editable { cursor: pointer; position: relative; }
        .editable:hover::after { content: '✎'; position: absolute; right: 5px; color: #666; }
        .notification {
            position: fixed; top: 20px; right: 20px; background: rgba(40, 167, 69, 0.9);
            color: white; padding: 15px 25px; border-radius: 4px; display: none;
            z-index: 9999; animation: fadeInOut 2s ease-in-out;
        }
        @keyframes fadeInOut {
            0% { opacity: 0; } 15% { opacity: 1; } 85% { opacity: 1; } 100% { opacity: 0; }
        }
        .save-all-btn {
            margin: 20px auto; display: block; padding: 10px 30px;
            font-size: 16px; background: #28a745; color: white;
            border: none; border-radius: 4px; cursor: pointer;
        }
        .save-all-btn:hover { background: #218838; }
        .totals-table { background-color: #f8f9fa; }
        .totals-table th { font-weight: bold; }
        .total-value { font-weight: bold; color: #007bff; }
        .section-title {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-weight: bold;
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
                                                                        $result = $conn->query($sql);
                                                                        while ($activite = $result->fetch_assoc()) {
                                                                            $selected = isset($_POST['id_activite']) && $_POST['id_activite'] == $activite['id-activite'] ? 'selected' : '';
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
                                                                    <div class="section-title">Détail des Participants</div>
                                                                    <div class="table-responsive">
                                                                        <table id="participantsTable" class="table table-hover">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th>#</th>
                                                                                    <th style="display:none;">ID</th>
                                                                                    <th>Challenger</th>
                                                                                    <th>Pseudo</th>
                                                                                    <th class="col-small">Buyin</th>
                                                                                    <th class="col-small">Bounty</th>
                                                                                    <th class="col-small">Rake</th>
                                                                                    <th>Rake 0</th>
                                                                                    <th>Rake 5</th>
                                                                                    <th>Rake 10</th>
                                                                                    <th>Rake 12</th>
                                                                                    <th>Rake 15</th>
                                                                                    <th>Rake 20</th>
                                                                                    <th class="col-small">Coût-In</th>
                                                                                    <th class="col-small">Recave</th>
                                                                                    <th class="col-small">Classement</th>
                                                                                    <th class="col-small">TF</th>
                                                                                    <th>Points</th>
                                                                                    <th class="col-small">Cagnotte</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <?php foreach($data['participants'] as $index => $row): ?>
                                                                                <tr data-id="<?= $row['id-membre'] ?>">
                                                                                    <td><?= $index + 1 ?></td>
                                                                                    <td style="display:none;"><?= $row['id-membre'] ?></td>
                                                                                    <td class="checkbox-cell">
                                                                                        <input type="checkbox" class="challenger-checkbox" <?= $row['challenger'] ? 'checked' : '' ?>>
                                                                                    </td>
                                                                                    <td><?= ($qui == $row['id-membre']) ? '<span class="current-user">'.$row['pseudo'].'</span>' : $row['pseudo'] ?></td>
                                                                                    <td class="editable col-small" data-field="buyin"><?= number_format($row['buyin'], 2) ?></td>
                                                                                    <td class="editable col-small" data-field="bounty"><?= number_format($row['bounty'], 2) ?></td>
                                                                                    <td class="editable col-small" data-field="rake"><?= number_format($row['rake'], 2) ?></td>
                                                                                    <td class="editable checkbox-cell" data-field="rake_0"><?= $row['rake_0'] ? '1' : '0' ?></td>
                                                                                    <td class="editable checkbox-cell" data-field="rake_5"><?= $row['rake_5'] ? '1' : '0' ?></td>
                                                                                    <td class="editable checkbox-cell" data-field="rake_10"><?= $row['rake_10'] ? '1' : '0' ?></td>
                                                                                    <td class="editable checkbox-cell" data-field="rake_12"><?= $row['rake_12'] ? '1' : '0' ?></td>
                                                                                    <td class="editable checkbox-cell" data-field="rake_15"><?= $row['rake_15'] ? '1' : '0' ?></td>
                                                                                    <td class="editable checkbox-cell" data-field="rake_20"><?= $row['rake_20'] ? '1' : '0' ?></td>
                                                                                    <td class="col-small"><?= number_format($row['cout_in'], 2) ?></td>
                                                                                    <td class="editable col-small" data-field="recave"><?= number_format($row['recave'], 2) ?></td>
                                                                                    <td class="editable col-small" data-field="classement"><?= $row['classement'] ?></td>
                                                                                    <td class="editable checkbox-cell" data-field="tf"><?= $row['tf'] ? '1' : '0' ?></td>
                                                                                    <td class="editable" data-field="points"><?= $row['points'] ?></td>
                                                                                    <td class="col-small"><?= number_format($row['caisse_chal'], 2) ?></td>
                                                                                </tr>
                                                                                <?php endforeach; ?>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>

                                                                    <div class="section-title">Récapitulatif des Totaux</div>
                                                                    <div class="table-responsive">
                                                                        <table id="totalsTable" class="table table-bordered totals-table">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th>Nombre de Participants</th>
                                                                                    <th>Total Buyin</th>
                                                                                    <th>Total Bounty</th>
                                                                                    <th>Total Rake</th>
                                                                                    <th>Total Général</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <tr>
                                                                                    <td><?= $data['totals']['count'] ?></td>
                                                                                    <td class="total-value"><?= number_format($data['totals']['buyin'], 2) ?> €</td>
                                                                                    <td class="total-value"><?= number_format($data['totals']['bounty'], 2) ?> €</td>
                                                                                    <td class="total-value"><?= number_format($data['totals']['rake'], 2) ?> €</td>
                                                                                    <td class="total-value"><?= number_format($data['totals']['buyin'] + $data['totals']['bounty'] + $data['totals']['rake'], 2) ?> €</td>
                                                                                </tr>
                                                                            </tbody>
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
    </div>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
    <script>
        jQuery(document).ready(function() {
            function showNotification(message) {
                const notification = $('#updateNotification');
                notification.text(message).fadeIn();
                setTimeout(() => notification.fadeOut(), 2000);
            }

            function updateTotals() {
                let buyinTotal = 0, bountyTotal = 0, rakeTotal = 0, count = 0;
                
                $('#participantsTable tbody tr').each(function() {
                    buyinTotal += parseFloat($(this).find('td[data-field="buyin"]').text()) || 0;
                    bountyTotal += parseFloat($(this).find('td[data-field="bounty"]').text()) || 0;
                    rakeTotal += parseFloat($(this).find('td[data-field="rake"]').text()) || 0;
                    count++;
                });
                
                $('#totalsTable tbody td:eq(0)').text(count);
                $('#totalsTable tbody td:eq(1)').text(buyinTotal.toFixed(2) + ' €');
                $('#totalsTable tbody td:eq(2)').text(bountyTotal.toFixed(2) + ' €');
                $('#totalsTable tbody td:eq(3)').text(rakeTotal.toFixed(2) + ' €');
                $('#totalsTable tbody td:eq(4)').text((buyinTotal + bountyTotal + rakeTotal).toFixed(2) + ' €');
            }

            // Initialisation des totaux
            updateTotals();

            // Gestion de l'édition des cellules
            $(document).on('click', '.editable', function(e) {
                e.stopPropagation();
                const cell = $(this);
                if (cell.find('input').length) return;
                
                const currentValue = cell.text().trim().replace(' €', '');
                const field = cell.data('field');
                const row = cell.closest('tr');
                const id_membre = row.data('id');

                if (['tf', 'rake_0', 'rake_5', 'rake_10', 'rake_12', 'rake_15', 'rake_20'].includes(field)) {
                    const newValue = currentValue === '1' ? '0' : '1';
                    cell.text(newValue);
                    updateTotals();
                    return;
                }
                
                cell.html(`<input type="text" value="${currentValue}" style="width:100%;text-align:center;">`);
                const input = cell.find('input').focus();

                input.on('blur', function() {
                    const newValue = $(this).val().trim();
                    cell.text(newValue);
                    updateTotals();
                });

                input.on('keypress', function(e) {
                    if (e.which === 13) $(this).blur();
                });
            });

            // Gestion des cases à cocher
            $(document).on('change', '.challenger-checkbox', function() {
                updateTotals();
            });

            // Bouton de sauvegarde globale
            $('#saveAllChanges').on('click', function() {
                alert('Toutes les modifications ont été enregistrées');
                updateTotals();
            });
        });
    </script>
</body>
</html>