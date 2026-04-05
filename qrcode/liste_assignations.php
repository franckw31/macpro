<?php
session_start();
error_reporting(0);

// Inclure le fichier de configuration du panel pour la connexion et la session
include(dirname(__DIR__) . '/panel/include/config.php');

if (strlen($_SESSION['id']) == 0) {
    header('location:../panel/logout.php');
} else {
    // Traitement du changement de propriétaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $id_col = isset($_POST['id_col']) ? intval($_POST['id_col']) : 0;
        if ($id_col === 0) {
            $_SESSION['msg'] = "Aucune carte sélectionnée pour l'action.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        if ($_POST['action'] === 'assign') {
            $id_indiv = intval($_POST['id_membre']);
            if ($id_indiv === 0) {
                $_SESSION['msg'] = "Choisissez un membre valide pour assigner.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
            // Vérifier que le collection existe
            $check_col = mysqli_query($con, "SELECT id_collection FROM collections WHERE id_collection = $id_col");
            if (mysqli_num_rows($check_col) === 0) {
                $_SESSION['msg'] = "La carte QR n'existe pas.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
            
            $check = mysqli_query($con, "SELECT * FROM `collections-individu` WHERE id_col = $id_col");
            if (mysqli_num_rows($check) > 0) {
                $result_update = mysqli_query($con, "UPDATE `collections-individu` SET `id-indiv` = $id_indiv WHERE id_col = $id_col");
                if (!$result_update) {
                    $_SESSION['msg'] = "Erreur lors de la mise à jour : " . mysqli_error($con);
                } else {
                    $_SESSION['msg'] = "Propriétaire mis à jour !";
                }
            } else {
                $q_val = mysqli_query($con, "SELECT valeur FROM collections WHERE id_collection = $id_col");
                $r_val = mysqli_fetch_assoc($q_val);
                $valeur = intval($r_val['valeur']);
                $result_insert = mysqli_query($con, "INSERT INTO `collections-individu` (id_col, `id-indiv`, co, valeur) VALUES ($id_col, $id_indiv, 'Manual', $valeur)");
                if (!$result_insert) {
                    $_SESSION['msg'] = "Erreur lors de l'assignation : " . mysqli_error($con);
                } else {
                    $_SESSION['msg'] = "Propriétaire assigné !";
                }
            }
        } elseif ($_POST['action'] === 'unassign') {
            $ok = mysqli_query($con, "DELETE FROM `collections-individu` WHERE id_col = $id_col");
            if ($ok) {
                if (mysqli_affected_rows($con) > 0) {
                    $_SESSION['msg'] = "Assignation supprimée !";
                } else {
                    $_SESSION['msg'] = "Aucune assignation trouvée pour cette carte.";
                }
            } else {
                $_SESSION['msg'] = "Erreur lors de la suppression : " . mysqli_error($con);
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Récupération de la liste des membres pour le modal
    $members_list = [];
    $q_m = mysqli_query($con, "SELECT `id-membre`, pseudo FROM membres WHERE pseudo != '' ORDER BY pseudo ASC");
    while($rm = mysqli_fetch_assoc($q_m)) {
        $members_list[] = $rm;
    }

    // Requête pour lister les QR codes et leurs propriétaires
    $sql = "SELECT c.id_collection, c.nom, c.valeur, m.`id-membre`, m.pseudo, m.fname, m.lname, ci.`date` 
            FROM collections c
            LEFT JOIN `collections-individu` ci ON c.id_collection = ci.id_col
            LEFT JOIN membres m ON ci.`id-indiv` = m.`id-membre`
            ORDER BY c.id_collection DESC";
    // On utilise $con qui est défini dans panel/include/config.php
    $result = mysqli_query($con, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin | Liste des assignations</title>
    <link href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="../panel/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../panel/vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../panel/vendor/themify-icons/themify-icons.min.css">
    <link href="../panel/vendor/animate.css/animate.min.css" rel="stylesheet" media="screen">
    <link href="../panel/vendor/perfect-scrollbar/perfect-scrollbar.min.css" rel="stylesheet" media="screen">
    <link href="../panel/vendor/switchery/switchery.min.css" rel="stylesheet" media="screen">
    <link href="../panel/vendor/bootstrap-touchspin/jquery.bootstrap-touchspin.min.css" rel="stylesheet" media="screen">
    <link href="../panel/vendor/select2/select2.min.css" rel="stylesheet" media="screen">
    <link href="../panel/vendor/bootstrap-datepicker/bootstrap-datepicker3.standalone.min.css" rel="stylesheet" media="screen">
    <link href="../panel/vendor/bootstrap-timepicker/bootstrap-timepicker.min.css" rel="stylesheet" media="screen">
    <link rel="stylesheet" href="../panel/assets/css/styles.css">
    <link rel="stylesheet" href="../panel/assets/css/plugins.css">
    <link rel="stylesheet" href="../panel/assets/css/themes/theme-1.css" id="skin_color" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" />
    <style>
        .valeur-tag { padding: 6px 12px; border-radius: 4px; font-size: 18px; font-weight: bold; }
        .val-1 { background: #e8f4fd; color: #3498db; border: 1px solid #3498db; }
        .val-2 { background: #eafaf1; color: #27ae60; border: 1px solid #27ae60; }
        .owner-none { color: #999; font-style: italic; font-size: 20px; }
        .owner-found { color: #2c3e50; font-weight: bold; font-size: 20px; }
        #assignationTable { font-size: 20px; }
        #assignationTable thead th { font-size: 18px; }
        code { font-size: 22px !important; }
        .app-content { overflow-x: hidden; }
        .main-content { overflow-x: hidden; }
        #assignationTable { 
            table-layout: fixed; 
            width: 100% !important;
        }
        #assignationTable td { 
            word-wrap: break-word; 
            overflow-wrap: break-word;
            white-space: normal;
        }
    </style>
</head>
<body>
    <div id="app">
        <?php 
        $id_orga = $_SESSION['id'];
        include(dirname(__DIR__) . '/panel/include/sidebar.php'); 
        ?>
        <div class="app-content">
            <?php include(dirname(__DIR__) . '/panel/include/header.php'); ?>
                    <div class="main-content">
                <div class="wrap-content container" id="container">
                    <section id="page-title">
                        <div class="row">
                            <div class="col-sm-8">
                                <h1 class="mainTitle">Liste des Assignations PokeCard</h1>
                            </div>
                        </div>
                    </section>
                            <?php if(!empty($_SESSION['msg'])): ?>
                                <div class="alert alert-info"><?php echo htmlspecialchars($_SESSION['msg']); unset($_SESSION['msg']); ?></div>
                            <?php endif; ?>
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
                                                            <ol class="breadcrumb mb-4">
                                                                <li class="breadcrumb-item"><a href="../panel/dashboard.php">Dashboard</a></li>
                                                                <li class="breadcrumb-item"><a href="../panel/qrcodes.php">Gestion QR Codes</a></li>
                                                                <li class="breadcrumb-item active">Liste des assignations</li>
                                                            </ol>
                                                            <div class="card mb-4">
                                                                <div class="card-body p-2">
                                                                    <div>
                                                                        <table id="assignationTable" class="table table-hover w-100">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th style="width: 30%;">QRcode</th>
                                                                                    <th style="width: 12%;">Valeur</th>
                                                                                    <th style="width: 23%;">Propriétaire</th>
                                                                                    <th style="width: 15%;">Date</th>
                                                                                    <th style="width: 20%;">Actions</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <?php while($row = mysqli_fetch_assoc($result)): ?>
                                                                                <tr class="clickable-row" onclick="<?php if ($row['id-membre']): ?>if(!event.target.closest('button')) window.location='../panel/voir-membre.php?id=<?php echo $row['id-membre']; ?>'<?php endif; ?>" style="cursor:<?php echo $row['id-membre'] ? 'pointer' : 'default'; ?>;">
                                                                                    <td><code><?php echo htmlspecialchars($row['nom']); ?></code></td>
                                                                                    <td data-order="<?php echo $row['valeur']; ?>">
                                                                                        <span class="valeur-tag <?php echo ($row['valeur'] == 2) ? 'val-2' : 'val-1'; ?>">
                                                                                            <?php echo ($row['valeur'] == 2) ? '2 Points' : '1 Point'; ?>
                                                                                        </span>
                                                                                    </td>
                                                                                    <td>
                                                                                        <?php if ($row['pseudo']): ?>
                                                                                            <span class="owner-found"><?php echo htmlspecialchars($row['pseudo']); ?></span>
                                                                                        <?php else: ?>
                                                                                            <span class="owner-none">Non assigné</span>
                                                                                        <?php endif; ?>
                                                                                    </td>
                                                                                    <td>
                                                                                        <?php if ($row['date']): ?>
                                                                                            <?php echo htmlspecialchars(date('d/m/Y', strtotime($row['date']))); ?>
                                                                                        <?php else: ?>
                                                                                            <span class="owner-none">-</span>
                                                                                        <?php endif; ?>
                                                                                    </td>
                                                                                    <td>
                                                                                        <button type="button" class="btn btn-primary btn-sm" 
                                                                                                onclick="event.stopPropagation(); openEditModal(<?php echo $row['id_collection']; ?>, '<?php echo addslashes($row['nom']); ?>', '<?php echo $row['id-membre']; ?>')"
                                                                                                style="font-size: 16px;">
                                                                                            <i class="fa fa-edit"></i> Changer
                                                                                        </button>
                                                                                    </td>
                                                                                </tr>
                                                                                <?php endwhile; ?>
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
        <?php include(dirname(__DIR__) . '/panel/include/footer.php'); ?>
        <?php include(dirname(__DIR__) . '/panel/include/setting.php'); ?>
    </div>

    <!-- Modal pour changer le propriétaire -->
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content" style="font-size: 20px;">
                <form method="POST" id="assignForm">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title" id="editModalLabel">Changer le propriétaire</h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id_col" id="modal_id_col">
                        <p><strong>QR Code :</strong> <span id="modal_nom_qr"></span></p>
                        
                        <div class="form-group mb-3">
                            <label for="id_membre">Nouveau Propriétaire :</label>
                            <select name="id_membre" id="id_membre" class="form-control" style="height: 50px; font-size: 18px;">
                                <option value="">-- Choisir un membre (pour assigner) --</option>
                                <?php foreach($members_list as $member): ?>
                                    <option value="<?php echo $member['id-membre']; ?>">
                                        <?php echo htmlspecialchars($member['pseudo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="action" id="modal_action" value="">
                        <button type="button" id="btn-unassign" class="btn btn-danger pull-left" style="margin-right: auto;" onclick="if(confirm('Supprimer l\'assignation ?')) { document.getElementById('modal_action').value='unassign'; document.getElementById('assignForm').submit(); }">Désassigner</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                        <button type="button" id="btn-assign" class="btn btn-success" onclick="validateAndSubmit();">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../panel/vendor/jquery/jquery.min.js"></script>
    <script src="../panel/vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="../panel/vendor/modernizr/modernizr.js"></script>
    <script src="../panel/vendor/jquery-cookie/jquery.cookie.js"></script>
    <script src="../panel/vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../panel/vendor/switchery/switchery.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="../panel/assets/js/main.js"></script>
    <script>
        jQuery(document).ready(function () {
            Main.init();
            $('#assignationTable').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json' },
                dom: '<"row"<"col"B><"col"f>>rt<"row"<"col"i><"col"p>>',
                pageLength: 25,
                order: [[3, 'desc']],
                responsive: true,
                autoWidth: true
            });
        });

        function openEditModal(id_col, nom, id_membre) {
            $('#modal_id_col').val(id_col);
            $('#modal_nom_qr').text(nom);
            $('#id_membre').val(id_membre || '');
            $('#modal_action').val(''); // Reset action
            $('#editModal').modal('show');
        }
        
        function validateAndSubmit() {
            var id_membre = $('#id_membre').val();
            if (!id_membre) {
                alert('Veuillez choisir un membre pour assigner.');
                return false;
            }
            document.getElementById('modal_action').value='assign';
            document.getElementById('assignForm').submit();
        }
    </script>
</body>
</html>
<?php } ?>
