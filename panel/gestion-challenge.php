<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');

if(strlen($_SESSION['id']) == 0) {
    header('location:logout.php');
} else {
    // Récupérer tous les challenges triés par date de fin
    $sql = "SELECT * FROM challenge ORDER BY id_challenge DESC";
    $result = mysqli_query($con, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin | Gestion Challenge</title>
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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" />
    <style>
        .challenge-tag { padding: 6px 12px; border-radius: 4px; font-size: 14px; font-weight: bold; }
        .challenge-active { background: #eafaf1; color: #27ae60; border: 1px solid #27ae60; }
        .challenge-inactive { background: #f5f5f5; color: #999; border: 1px solid #ddd; }
        #challengeTable { font-size: 16px; }
        #challengeTable thead th { font-size: 16px; }
        .app-content { overflow-x: hidden; }
        .main-content { overflow-x: hidden; }
        #challengeTable { 
            table-layout: fixed; 
            width: 100% !important;
        }
        #challengeTable td { 
            word-wrap: break-word; 
            overflow-wrap: break-word;
            white-space: normal;
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
                    <section id="page-title">
                        <div class="row">
                            <div class="col-sm-8">
                                <h1 class="mainTitle">Gestion des Challenges</h1>
                            </div>
                        </div>
                    </section>
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
                                                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                                                <li class="breadcrumb-item active">Gestion des Challenges</li>
                                                            </ol>
                                                            <div class="card mb-4">
                                                                <div class="card-body p-2">
                                                                    <a href="edit-challenge.php" class="btn btn-success mb-3">
                                                                        <i class="fa fa-plus"></i> Ajouter Challenge
                                                                    </a>
                                                                    <div>
                                                                        <table id="challengeTable" class="table table-hover w-100">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th style="width: 20%;">Titre</th>
                                                                                    <th style="width: 30%;">Commentaire</th>
                                                                                    <th style="width: 15%;">Début</th>
                                                                                    <th style="width: 15%;">Fin</th>
                                                                                    <th style="width: 20%;">Actions</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <?php while($row = mysqli_fetch_array($result)): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($row['titre_challenge']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($row['chal_com']); ?></td>
                                                                                    <td><?php echo $row['chal_deb']; ?></td>
                                                                                    <td><?php echo $row['chal_fin']; ?></td>
                                                                                    <td>
                                                                                        <a href="voir-challenge.php?id=<?php echo $row['id_challenge']; ?>" class="btn btn-info btn-sm">
                                                                                            <i class="fa fa-eye"></i> Voir
                                                                                        </a>
                                                                                        <a href="edit-challenge.php?id=<?php echo $row['id_challenge']; ?>" class="btn btn-primary btn-sm">
                                                                                            <i class="fa fa-edit"></i> Éditer
                                                                                        </a>
                                                                                        <a href="delete-challenge.php?id=<?php echo $row['id_challenge']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce challenge ?');">
                                                                                            <i class="fa fa-trash"></i> Supprimer
                                                                                        </a>
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
        <?php include('include/footer.php'); ?>
        <?php include('include/setting.php'); ?>
    </div>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="vendor/modernizr/modernizr.js"></script>
    <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
    <script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="vendor/switchery/switchery.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        jQuery(document).ready(function () {
            Main.init();
            $('#challengeTable').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json' },
                dom: '<"row"<"col"B><"col"f>>rt<"row"<"col"i><"col"p>>',
                pageLength: 10,
                order: [[2, 'desc']],
                responsive: true,
                autoWidth: false
            });
        });
    </script>
</body>
</html>
<?php } ?>
