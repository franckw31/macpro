<?php
session_start();
error_reporting(0);
include('include/config.php');

if (strlen($_SESSION['id']) == 0) {
    header('location:logout.php');
    exit;
}

// Check if user is admin
$is_admin = (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin')) || (isset($_SESSION['id']) && intval($_SESSION['id']) === 265);

if (!$is_admin) {
    header('location:../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Gestion des Tombolas</title>
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
    <style>
        .stat-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #2e6da4, #1e4a7a);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #444;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .stat-card strong {
            display: block;
            font-size: 0.9em;
            color: #aaa;
            margin-bottom: 10px;
        }
        .stat-card .value {
            display: block;
            font-size: 1.8em;
            font-weight: bold;
            color: #ffc107;
        }
        .stat-card.success { background: linear-gradient(135deg, #4CAF50, #45a049); }
        .stat-card.warning { background: linear-gradient(135deg, #ff9800, #ff8a65); }
        .stat-card.danger { background: linear-gradient(135deg, #f44336, #e53935); }
        .stat-card.success .value, .stat-card.warning .value, .stat-card.danger .value {
            color: #fff;
        }
        .tirage-section {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        .tirage-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            gap: 20px;
        }
        .tirage-header h3 {
            color: #8B4513;
            margin: 0;
        }
        .btn-tirage {
            background: linear-gradient(135deg, #ff6b35, #ff4500);
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 1em;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-tirage:hover {
            background: linear-gradient(135deg, #ff4500, #ff0000);
            transform: scale(1.05);
        }
        .winning-ticket {
            background-color: rgba(255,255,255,0.95);
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .winning-ticket h4 {
            color: #8B4513;
            text-align: center;
            font-size: 1.8em;
            margin-bottom: 15px;
        }
        .ticket-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        .ticket-item {
            padding: 10px;
            border-left: 3px solid #FFD700;
            background: #f9f9f9;
        }
        .ticket-label {
            color: #666;
            font-size: 0.85em;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .ticket-value {
            color: #000;
            font-size: 1.1em;
            font-weight: bold;
        }
        .ticket-montant {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
        }
        .ticket-montant .label {
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        .ticket-montant .value {
            font-size: 1.6em;
            font-weight: bold;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .table {
            margin-bottom: 0;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-success {
            background-color: #4CAF50;
            color: white;
        }
        .badge-danger {
            background-color: #F44336;
            color: white;
        }
        .table tbody tr.table-success {
            background-color: rgba(76, 175, 80, 0.1);
            border-left: 4px solid #4CAF50;
        }
        .table tbody tr.table-warning {
            background-color: rgba(255, 152, 0, 0.1);
            border-left: 4px solid #FF9800;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet" />
</head>
<body>
    <div id="app">
        <?php include('include/sidebar.php'); ?>
        <div class="app-content">
            <?php include('include/header.php'); ?>
            <div class="main-content">
                <div class="wrap-content container" id="container">
                    <section id="page-title"></section>
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
                                                            <h1 class="mt-4">Gestion des Tombolas</h1>
                                                            <ol class="breadcrumb mb-4">
                                                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                                                <li class="breadcrumb-item active">Tombolas</li>
                                                            </ol>
                                                            
                                                            <?php
                                                            echo htmlentities($_SESSION['msg'] = "");
                                                            
                                                            // Gestion du tri
                                                            $sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'pseudo';
                                                            $sort_order = isset($_GET['order']) && $_GET['order'] === 'DESC' ? 'DESC' : 'ASC';
                                                            $next_order = ($sort_order === 'ASC') ? 'DESC' : 'ASC';
                                                            
                                                            // Fonction pour générer les liens de tri
                                                            function getSortLink($column, $label, $current_sort, $current_order) {
                                                                $next_order = ($current_sort === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
                                                                $arrow = '';
                                                                if ($current_sort === $column) {
                                                                    $arrow = ($current_order === 'ASC') ? ' ▲' : ' ▼';
                                                                }
                                                                return '<a href="?sort=' . $column . '&order=' . $next_order . '">' . $label . $arrow . '</a>';
                                                            }
                                                            
                                                            // Traitement du tirage au sort
                                                            if (isset($_POST['tirage_au_sort'])) {
                                                                $tirage_query = mysqli_query($con, "SELECT 
                                                                    ci.`id`,
                                                                    ci.`id-indiv` as id_indiv,
                                                                    ci.`id_col`,
                                                                    ci.`date`,
                                                                    c.`nom` as qrcode,
                                                                    c.`valeur`,
                                                                    m.`pseudo`,
                                                                    m.`fname`,
                                                                    m.`lname`
                                                                    FROM `collections-individu` ci
                                                                    JOIN `collections` c ON ci.`id_col` = c.`id_collection`
                                                                    JOIN `membres` m ON ci.`id-indiv` = m.`id-membre`
                                                                    WHERE ci.`aff_rake` = 0 OR ci.`aff_rake` IS NULL
                                                                    ORDER BY RAND() LIMIT 1");
                                                                
                                                                if ($tirage_query && mysqli_num_rows($tirage_query) > 0) {
                                                                    $ticket_gagne = mysqli_fetch_array($tirage_query);
                                                                    $pseudo_gagne = $ticket_gagne['pseudo'];
                                                                    $qrcode_gagne = $ticket_gagne['qrcode'];
                                                                    $valeur_ticket = $ticket_gagne['valeur'];
                                                                    $date_gagnee = $ticket_gagne['date'];
                                                                    
                                                                    $date_simple = date('Y-m-d', strtotime($date_gagnee));
                                                                    $activite_query_tirage = mysqli_query($con, "SELECT `titre-activite` FROM `activite` WHERE DATE(`date_depart`) = '$date_simple'");
                                                                    $activite_row_tirage = mysqli_fetch_array($activite_query_tirage);
                                                                    $titre_gagnee = $activite_row_tirage ? $activite_row_tirage['titre-activite'] : '-';
                                                                    
                                                                    $montant_total_query = mysqli_query($con, "SELECT SUM(c.`valeur`) as total FROM `collections-individu` ci JOIN `collections` c ON ci.`id_col` = c.`id_collection` WHERE ci.`aff_rake` = 0 OR ci.`aff_rake` IS NULL");
                                                                    $montant_row = mysqli_fetch_array($montant_total_query);
                                                                    $valeur_gagnee = $montant_row['total'] ?: 0;
                                                                }
                                                            }
                                                            ?>
                                                            
                                                            <!-- TIRAGE AU SORT -->
                                                            <div class="tirage-section">
                                                                <div class="tirage-header">
                                                                    <h3>🎲 Tirage au Sort</h3>
                                                                    <form method="post" style="margin: 0;">
                                                                        <button type="submit" name="tirage_au_sort" class="btn-tirage">🎲 Tirer au Sort</button>
                                                                    </form>
                                                                </div>
                                                                <?php if (isset($ticket_gagne)): ?>
                                                                <div class="winning-ticket">
                                                                    <h4>🎉 TICKET GAGNANT! 🎉</h4>
                                                                    <div class="ticket-grid">
                                                                        <div class="ticket-item">
                                                                            <div class="ticket-label">👤 MEMBRE</div>
                                                                            <div class="ticket-value"><?php echo htmlspecialchars($pseudo_gagne); ?></div>
                                                                        </div>
                                                                        <div class="ticket-item">
                                                                            <div class="ticket-label">📱 QRCODE</div>
                                                                            <div class="ticket-value"><?php echo htmlspecialchars($qrcode_gagne); ?></div>
                                                                        </div>
                                                                        <div class="ticket-item">
                                                                            <div class="ticket-label">📅 DATE</div>
                                                                            <div class="ticket-value"><?php echo date('d/m/Y', strtotime($date_gagnee)); ?></div>
                                                                        </div>
                                                                        <div class="ticket-item">
                                                                            <div class="ticket-label">🎮 ACTIVITÉ</div>
                                                                            <div class="ticket-value"><?php echo htmlspecialchars($titre_gagnee); ?></div>
                                                                        </div>
                                                                        <div class="ticket-montant">
                                                                            <div class="label">💰 MONTANT GAGNANT</div>
                                                                            <div class="value"><?php echo number_format($valeur_gagnee, 2, ',', ' '); ?> €</div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                            <!-- RÉSUMÉ STATISTIQUE -->
                                                            <div>
                                                                <h4 class="mb-3">📊 Résumé Statistique</h4>
                                                                <div class="stat-row">
                                                                    <?php
                                                                    try {
                                                                        $total_query = mysqli_query($con, "SELECT COUNT(*) as total FROM `collections-individu`");
                                                                        $total_row = mysqli_fetch_array($total_query);
                                                                        $total_tickets = $total_row['total'];
                                                                        
                                                                        $rake_query = mysqli_query($con, "SELECT COUNT(*) as total FROM `collections-individu` WHERE `aff_rake` = 1");
                                                                        $rake_row = mysqli_fetch_array($rake_query);
                                                                        $rake_tickets = $rake_row['total'];
                                                                        
                                                                        $not_rake_tickets = $total_tickets - $rake_tickets;
                                                                        
                                                                        $montant_total_query = mysqli_query($con, "SELECT SUM(c.`valeur`) as total FROM `collections-individu` ci JOIN `collections` c ON ci.`id_col` = c.`id_collection`");
                                                                        $montant_row = mysqli_fetch_array($montant_total_query);
                                                                        $montant_total = $montant_row['total'] ?: 0;
                                                                        
                                                                        $montant_rake_query = mysqli_query($con, "SELECT SUM(c.`valeur`) as total FROM `collections-individu` ci JOIN `collections` c ON ci.`id_col` = c.`id_collection` WHERE ci.`aff_rake` = 1");
                                                                        $montant_rake_row = mysqli_fetch_array($montant_rake_query);
                                                                        $montant_rake = $montant_rake_row['total'] ?: 0;
                                                                        
                                                                        $montant_not_rake = $montant_total - $montant_rake;
                                                                        
                                                                        echo '<div class="stat-card success">';
                                                                        echo '<strong>📦 Total Tickets</strong>';
                                                                        echo '<span class="value">' . $total_tickets . '</span>';
                                                                        echo '</div>';
                                                                        
                                                                        echo '<div class="stat-card success">';
                                                                        echo '<strong>✅ Affectés au Rake</strong>';
                                                                        echo '<span class="value">' . $rake_tickets . '</span>';
                                                                        echo '</div>';
                                                                        
                                                                        echo '<div class="stat-card warning">';
                                                                        echo '<strong>❌ Non Affectés</strong>';
                                                                        echo '<span class="value">' . $not_rake_tickets . '</span>';
                                                                        echo '</div>';
                                                                    } catch (Exception $e) {
                                                                        echo '<div class="stat-card danger"><strong>Erreur</strong><span class="value">!</span></div>';
                                                                    }
                                                                    ?>
                                                                </div>
                                                                <div class="stat-row">
                                                                    <?php
                                                                    try {
                                                                        echo '<div class="stat-card success">';
                                                                        echo '<strong>💰 Montant Total</strong>';
                                                                        echo '<span class="value">' . number_format($montant_total, 2, ',', ' ') . ' €</span>';
                                                                        echo '</div>';
                                                                        
                                                                        echo '<div class="stat-card success">';
                                                                        echo '<strong>✅ Montant Affecté</strong>';
                                                                        echo '<span class="value">' . number_format($montant_rake, 2, ',', ' ') . ' €</span>';
                                                                        echo '</div>';
                                                                        
                                                                        echo '<div class="stat-card warning">';
                                                                        echo '<strong>❌ Montant Non Affecté</strong>';
                                                                        echo '<span class="value">' . number_format($montant_not_rake, 2, ',', ' ') . ' €</span>';
                                                                        echo '</div>';
                                                                    } catch (Exception $e) {
                                                                        echo '<div class="stat-card danger"><strong>Erreur</strong><span class="value">!</span></div>';
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- TABLEAU COMPLET -->
                                                            <div class="card mb-4 mt-4">
                                                                <div class="card-header">
                                                                    <h4 class="mb-0">📋 Tous les Tickets</h4>
                                                                </div>
                                                                <div class="card-body p-0">
                                                                    <div class="table-responsive">
                                                                        <table class="table">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th><?php echo getSortLink('pseudo', 'Membre', $sort_by, $sort_order); ?></th>
                                                                                    <th><?php echo getSortLink('qrcode', 'QRcode', $sort_by, $sort_order); ?></th>
                                                                                    <th><?php echo getSortLink('valeur', 'Valeur', $sort_by, $sort_order); ?></th>
                                                                                    <th><?php echo getSortLink('date', 'Date', $sort_by, $sort_order); ?></th>
                                                                                    <th>Titre Activité</th>
                                                                                    <th><?php echo getSortLink('aff_rake', 'Aff. Rake', $sort_by, $sort_order); ?></th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <?php
                                                                                try {
                                                                                    $query = "SELECT 
                                                                        ci.`id`,
                                                                        ci.`id-indiv` as id_indiv,
                                                                        ci.`id_col`,
                                                                        ci.`aff_rake`,
                                                                        ci.`date`,
                                                                        c.`nom` as qrcode,
                                                                        c.`valeur`,
                                                                        m.`pseudo`,
                                                                        m.`fname`,
                                                                        m.`lname`
                                                                        FROM `collections-individu` ci
                                                                        JOIN `collections` c ON ci.`id_col` = c.`id_collection`
                                                                        JOIN `membres` m ON ci.`id-indiv` = m.`id-membre`
                                                                        ORDER BY " . ($sort_by === 'valeur' ? 'c.valeur' : ($sort_by === 'qrcode' ? 'c.nom' : ($sort_by === 'aff_rake' ? 'ci.aff_rake' : 'm.' . $sort_by))) . " $sort_order";
                                                                    
                                                                    $result = mysqli_query($con, $query);
                                                                    
                                                                    if (!$result) {
                                                                        throw new Exception("Erreur requête: " . mysqli_error($con));
                                                                    }
                                                                    
                                                                    $has_data = false;
                                                                    
                                                                    while ($row = mysqli_fetch_array($result)) {
                                                                        $has_data = true;
                                                                        $id_indiv = $row['id_indiv'];
                                                                        $id_col = $row['id_col'];
                                                                        $qrcode = $row['qrcode'];
                                                                        $valeur = $row['valeur'];
                                                                        $date = $row['date'];
                                                                        $pseudo = $row['pseudo'];
                                                                        $aff_rake = $row['aff_rake'];
                                                                        
                                                                        $date_simple = date('Y-m-d', strtotime($date));
                                                                        $activite_query = mysqli_query($con, "SELECT `titre-activite` FROM `activite` WHERE DATE(`date_depart`) = '$date_simple'");
                                                                        $activite_row = mysqli_fetch_array($activite_query);
                                                                        $titre_activite = $activite_row ? $activite_row['titre-activite'] : '-';
                                                                        
                                                                        $row_class = (intval($aff_rake) === 1) ? 'table-success' : 'table-warning';
                                                                        $rake_label = (intval($aff_rake) === 1) ? '<span class="badge badge-success">OUI</span>' : '<span class="badge badge-danger">NON</span>';
                                                                        
                                                                        echo "<tr class='$row_class'>";
                                                                        echo "<td><strong><a href='voir-membre.php?id=$id_indiv'>" . htmlspecialchars($pseudo) . "</a></strong></td>";
                                                                        echo "<td>" . htmlspecialchars($qrcode) . "</td>";
                                                                        echo "<td>" . number_format($valeur, 2, ',', ' ') . " €</td>";
                                                                        echo "<td>" . date('d/m/Y', strtotime($date)) . "</td>";
                                                                        echo "<td>" . htmlspecialchars($titre_activite) . "</td>";
                                                                        echo "<td>" . $rake_label . "</td>";
                                                                        echo "</tr>";
                                                                    }
                                                                    
                                                                    if (!$has_data) {
                                                                        echo "<tr><td colspan='6' style='text-align: center; padding: 20px; color: #ccc;'>Aucun ticket trouvé</td></tr>";
                                                                    }
                                                                    
                                                                } catch (Exception $e) {
                                                                    echo "<tr><td colspan='6' style='text-align: center; color: #f00;'>Erreur: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                                                                }
                                                                ?>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Tableau des tickets non affectés au Rake -->
                                                            <div class="card mb-4 mt-4">
                                                                <div class="card-header">
                                                                    <h4 class="mb-0">📋 Tickets Non Affectés au Rake</h4>
                                                                </div>
                                                                <div class="card-body p-0">
                                                                    <div class="table-responsive">
                                                                        <table class="table">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th><?php echo getSortLink('pseudo', 'Membre', $sort_by, $sort_order); ?></th>
                                                                                    <th><?php echo getSortLink('qrcode', 'QRcode', $sort_by, $sort_order); ?></th>
                                                                                    <th><?php echo getSortLink('valeur', 'Valeur', $sort_by, $sort_order); ?></th>
                                                                                    <th><?php echo getSortLink('date', 'Date', $sort_by, $sort_order); ?></th>
                                                                                    <th>Titre Activité</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <?php
                                                                                try {
                                                                                    $query_not_rake = "SELECT 
                                                                            ci.`id`,
                                                                            ci.`id-indiv` as id_indiv,
                                                                            ci.`id_col`,
                                                                            ci.`date`,
                                                                            c.`nom` as qrcode,
                                                                            c.`valeur`,
                                                                            m.`pseudo`
                                                                            FROM `collections-individu` ci
                                                                            JOIN `collections` c ON ci.`id_col` = c.`id_collection`
                                                                            JOIN `membres` m ON ci.`id-indiv` = m.`id-membre`
                                                                            WHERE ci.`aff_rake` = 0 OR ci.`aff_rake` IS NULL
                                                                            ORDER BY " . ($sort_by === 'valeur' ? 'c.valeur' : ($sort_by === 'qrcode' ? 'c.nom' : 'm.' . $sort_by)) . " $sort_order";
                                                                    
                                                                    $result_not_rake = mysqli_query($con, $query_not_rake);
                                                                    
                                                                    if (!$result_not_rake) {
                                                                        throw new Exception("Erreur requête: " . mysqli_error($con));
                                                                    }
                                                                    
                                                                    $has_data_not_rake = false;
                                                                    
                                                                    while ($row_not = mysqli_fetch_array($result_not_rake)) {
                                                                        $has_data_not_rake = true;
                                                                        $id_indiv = $row_not['id_indiv'];
                                                                        $qrcode = $row_not['qrcode'];
                                                                        $valeur = $row_not['valeur'];
                                                                        $date = $row_not['date'];
                                                                        $pseudo = $row_not['pseudo'];
                                                                        
                                                                        $date_simple = date('Y-m-d', strtotime($date));
                                                                        $activite_query = mysqli_query($con, "SELECT `titre-activite` FROM `activite` WHERE DATE(`date_depart`) = '$date_simple'");
                                                                        $activite_row = mysqli_fetch_array($activite_query);
                                                                        $titre_activite = $activite_row ? $activite_row['titre-activite'] : '-';
                                                                        
                                                                        echo "<tr>";
                                                                        echo "<td><strong><a href='voir-membre.php?id=$id_indiv'>" . htmlspecialchars($pseudo) . "</a></strong></td>";
                                                                        echo "<td>" . htmlspecialchars($qrcode) . "</td>";
                                                                        echo "<td>" . number_format($valeur, 2, ',', ' ') . " €</td>";
                                                                        echo "<td>" . date('d/m/Y', strtotime($date)) . "</td>";
                                                                        echo "<td>" . htmlspecialchars($titre_activite) . "</td>";
                                                                        echo "</tr>";
                                                                    }
                                                                    
                                                                    if (!$has_data_not_rake) {
                                                                        echo "<tr><td colspan='5' style='text-align: center; padding: 20px; color: #ccc;'>Tous les tickets sont affectés au Rake</td></tr>";
                                                                    }
                                                                    
                                                                } catch (Exception $e) {
                                                                    echo "<tr><td colspan='5' style='text-align: center; color: #f00;'>Erreur: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                                                                }
                                                                ?>
                                                                            </tbody>
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
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
</body>
</html>
